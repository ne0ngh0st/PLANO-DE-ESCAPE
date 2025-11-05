<?php
require_once __DIR__ . '/../../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar se o usuário tem permissão para acessar gestão de ligações
$perfil_permitido = in_array($usuario['perfil'], ['diretor', 'supervisor', 'admin']);
if (!$perfil_permitido) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'home');
    exit;
}

// Processar filtros
$filtro_status = $_GET['filtro_status'] ?? '';
$filtro_data_inicio = $_GET['filtro_data_inicio'] ?? '';
$filtro_data_fim = $_GET['filtro_data_fim'] ?? '';
$filtro_usuario = $_GET['filtro_usuario'] ?? '';
$filtro_cliente = $_GET['filtro_cliente'] ?? '';
$supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
$vendedor_selecionado = $_GET['visao_vendedor'] ?? '';

// Configurações de paginação
$itens_por_pagina = 20;
$pagina_atual = max(1, intval($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Construir condições WHERE baseadas no perfil do usuário e filtros
$where_conditions = ["1=1"];
$params = [];

// Aplicar filtros baseados no perfil do usuário
if (strtolower(trim($usuario['perfil'])) === 'vendedor' && !empty($usuario['cod_vendedor'])) {
    $where_conditions[] = "l.usuario_id = ?";
    $params[] = $usuario['id'];
}
// Se for representante, filtrar apenas suas ligações
elseif (strtolower(trim($usuario['perfil'])) === 'representante' && !empty($usuario['cod_vendedor'])) {
    $where_conditions[] = "l.usuario_id = ?";
    $params[] = $usuario['id'];
}
// Se for supervisor, filtrar ligações dos vendedores sob sua supervisão
elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
    if (!empty($vendedor_selecionado)) {
        // Se um vendedor específico foi selecionado, filtrar apenas suas ligações
        // Primeiro buscar o ID do usuário pelo COD_VENDEDOR
        $sql_buscar_id = "SELECT ID FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1";
        $stmt_buscar_id = $pdo->prepare($sql_buscar_id);
        $stmt_buscar_id->execute([$vendedor_selecionado]);
        $usuario_encontrado = $stmt_buscar_id->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario_encontrado) {
            $where_conditions[] = "l.usuario_id = ?";
            $params[] = $usuario_encontrado['ID'];
        }
    } else {
        // Filtrar ligações de todos os vendedores da equipe
        $where_conditions[] = "u.COD_SUPER = ?";
        $params[] = $usuario['cod_vendedor'];
    }
}
// Se for diretor ou admin, verificar se há filtro de visão específica
elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
    if (!empty($vendedor_selecionado)) {
        // Se um vendedor específico foi selecionado, filtrar apenas suas ligações
        // Primeiro buscar o ID do usuário pelo COD_VENDEDOR
        $sql_buscar_id = "SELECT ID FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1";
        $stmt_buscar_id = $pdo->prepare($sql_buscar_id);
        $stmt_buscar_id->execute([$vendedor_selecionado]);
        $usuario_encontrado = $stmt_buscar_id->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario_encontrado) {
            $where_conditions[] = "l.usuario_id = ?";
            $params[] = $usuario_encontrado['ID'];
        }
    } elseif (!empty($supervisor_selecionado)) {
        // Se um supervisor foi selecionado, filtrar ligações dos vendedores sob sua supervisão
        $where_conditions[] = "u.COD_SUPER = ?";
        $params[] = $supervisor_selecionado;
    }
    // Se nenhum filtro for aplicado, diretores veem todas as ligações
}

// Não mostrar ligações excluídas (exclusão lógica) - sempre aplicar primeiro
$where_conditions[] = "l.status != 'excluida'";

// Aplicar filtros adicionais
if (!empty($filtro_status)) {
    // Se há filtro de status específico, aplicar apenas para status válidos (não excluídas)
    if ($filtro_status === 'finalizada' || $filtro_status === 'cancelada') {
        $where_conditions[] = "l.status = ?";
        $params[] = $filtro_status;
    }
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "DATE(l.data_ligacao) >= ?";
    $params[] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = "DATE(l.data_ligacao) <= ?";
    $params[] = $filtro_data_fim;
}

if (!empty($filtro_usuario)) {
    $where_conditions[] = "u.NOME_COMPLETO LIKE ?";
    $params[] = "%$filtro_usuario%";
}

if (!empty($filtro_cliente)) {
    $where_conditions[] = "(uf.CLIENTE LIKE ? OR uf.CNPJ LIKE ? OR l.cliente_id LIKE ?)";
    $params[] = "%$filtro_cliente%";
    $params[] = "%$filtro_cliente%";
    $params[] = "%$filtro_cliente%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Consulta principal para buscar ligações
$sql = "
    SELECT l.*, 
           u.NOME_COMPLETO as usuario_nome,
           COALESCE(uf.CLIENTE, l.cliente_id) as cliente_nome,
           COALESCE(uf.CNPJ, l.cliente_id) as cliente_identificador,
           (SELECT COUNT(DISTINCT r2.pergunta_id) 
            FROM RESPOSTAS_LIGACAO r2 
            INNER JOIN PERGUNTAS_LIGACAO p2 ON r2.pergunta_id = p2.id 
            WHERE r2.ligacao_id = l.id AND p2.obrigatoria = TRUE) as total_respostas,
           (SELECT COUNT(*) FROM PERGUNTAS_LIGACAO WHERE obrigatoria = TRUE) as total_obrigatorias
    FROM LIGACOES l
    LEFT JOIN USUARIOS u ON l.usuario_id = u.ID
    LEFT JOIN (
        SELECT DISTINCT CNPJ, CLIENTE
        FROM ultimo_faturamento
        WHERE CNPJ IS NOT NULL AND CNPJ != ''
    ) uf ON l.cliente_id = uf.CNPJ
    $where_clause
    ORDER BY l.data_ligacao DESC
    LIMIT $itens_por_pagina OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ligacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar total de registros para paginação
$sql_count = "
    SELECT COUNT(DISTINCT l.id) as total
    FROM LIGACOES l
    LEFT JOIN USUARIOS u ON l.usuario_id = u.ID
    LEFT JOIN (
        SELECT DISTINCT CNPJ, CLIENTE
        FROM ultimo_faturamento
        WHERE CNPJ IS NOT NULL AND CNPJ != ''
    ) uf ON l.cliente_id = uf.CNPJ
    $where_clause
";

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_registros = $stmt_count->fetch(PDO::FETCH_COLUMN);

$total_paginas = ceil($total_registros / $itens_por_pagina);

// Buscar estatísticas aplicando os mesmos filtros da consulta principal
$sql_stats = "
    SELECT 
        COUNT(DISTINCT l.id) as total_ligacoes,
        COUNT(DISTINCT CASE WHEN l.status = 'finalizada' THEN l.id END) as ligacoes_finalizadas,
        COUNT(DISTINCT CASE WHEN l.status != 'finalizada' THEN l.id END) as ligacoes_canceladas,
        AVG(CASE WHEN l.status = 'finalizada' THEN 
            (SELECT COUNT(DISTINCT r2.pergunta_id) 
             FROM RESPOSTAS_LIGACAO r2 
             INNER JOIN PERGUNTAS_LIGACAO p2 ON r2.pergunta_id = p2.id 
             WHERE r2.ligacao_id = l.id AND p2.obrigatoria = TRUE)
        END) as media_respostas
    FROM LIGACOES l
    LEFT JOIN USUARIOS u ON l.usuario_id = u.ID
    LEFT JOIN (
        SELECT DISTINCT CNPJ, CLIENTE
        FROM ultimo_faturamento
        WHERE CNPJ IS NOT NULL AND CNPJ != ''
    ) uf ON l.cliente_id = uf.CNPJ
    $where_clause
";

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute($params);
$estatisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Buscar usuários para filtro baseado no perfil
$sql_usuarios = "";
$stmt_usuarios = null;

if (strtolower(trim($usuario['perfil'])) === 'vendedor' || strtolower(trim($usuario['perfil'])) === 'representante') {
    // Vendedor/representante vê apenas a si mesmo
    $sql_usuarios = "SELECT ID, NOME_COMPLETO as nome FROM USUARIOS WHERE ID = ? ORDER BY NOME_COMPLETO";
    $stmt_usuarios = $pdo->prepare($sql_usuarios);
    $stmt_usuarios->execute([$usuario['id']]);
} elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
    // Supervisor vê vendedores sob sua supervisão
    if (!empty($vendedor_selecionado)) {
        // Supervisor com visão específica de vendedor - mostrar apenas o vendedor selecionado
        $sql_usuarios = "SELECT ID, NOME_COMPLETO as nome FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1 ORDER BY NOME_COMPLETO";
        $stmt_usuarios = $pdo->prepare($sql_usuarios);
        $stmt_usuarios->execute([$vendedor_selecionado]);
    } else {
        // Supervisor vê todos os vendedores da sua equipe
        $sql_usuarios = "SELECT ID, NOME_COMPLETO as nome FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante') ORDER BY NOME_COMPLETO";
        $stmt_usuarios = $pdo->prepare($sql_usuarios);
        $stmt_usuarios->execute([$usuario['cod_vendedor']]);
    }
} elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
    if (!empty($vendedor_selecionado)) {
        // Diretor com visão específica de vendedor - mostrar apenas o vendedor selecionado
        $sql_usuarios = "SELECT ID, NOME_COMPLETO as nome FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1 ORDER BY NOME_COMPLETO";
        $stmt_usuarios = $pdo->prepare($sql_usuarios);
        $stmt_usuarios->execute([$vendedor_selecionado]);
    } elseif (!empty($supervisor_selecionado)) {
        // Diretor com visão específica de supervisor
        $sql_usuarios = "SELECT ID, NOME_COMPLETO as nome FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante') ORDER BY NOME_COMPLETO";
        $stmt_usuarios = $pdo->prepare($sql_usuarios);
        $stmt_usuarios->execute([$supervisor_selecionado]);
    } else {
        // Diretor vê todos os usuários
        $sql_usuarios = "SELECT ID, NOME_COMPLETO as nome FROM USUARIOS ORDER BY NOME_COMPLETO";
        $stmt_usuarios = $pdo->query($sql_usuarios);
    }
} else {
    // Admin vê todos os usuários
    $sql_usuarios = "SELECT ID, NOME_COMPLETO as nome FROM USUARIOS ORDER BY NOME_COMPLETO";
    $stmt_usuarios = $pdo->query($sql_usuarios);
}

$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Ligações - Sistema BI</title>
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/gestao-ligacoes.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include __DIR__ . '/../../includes/navbar.php'; ?>
        
        <?php include __DIR__ . '/../../includes/nav_mobile.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                <section class="welcome-section">
                    <h2><i class="fas fa-phone"></i> Gestão de Ligações</h2>
                    <p>Visualize e gerencie todas as ligações realizadas com os questionários</p>
                </section>

                <!-- Seletor de Visão para Diretores e Supervisores -->
                <?php if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin'])): ?>
                <div class="visao-selector-container" style="margin-bottom: 2rem; padding: 1rem; background: var(--white); border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div class="visao-selector" style="display: flex; align-items: center; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <?php if (strtolower(trim($usuario['perfil'])) === 'diretor' || strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label for="visao_supervisor" style="font-weight: 600; color: var(--dark-color); margin: 0;">Supervisor:</label>
                            <select id="visao_supervisor" name="visao_supervisor" onchange="mudarVisao()" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; background: var(--white); color: var(--text-color); min-width: 200px;">
                                <option value="">Todas as Equipes</option>
                                <?php
                                // Buscar supervisores disponíveis
                                if (isset($pdo)) {
                                    try {
                                        $sql_supervisores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO 
                                                           FROM USUARIOS u 
                                                           INNER JOIN USUARIOS v ON v.COD_SUPER = u.COD_VENDEDOR 
                                                           WHERE u.ATIVO = 1 AND u.PERFIL = 'supervisor' 
                                                           AND v.ATIVO = 1 AND v.PERFIL IN ('vendedor', 'representante')
                                                           ORDER BY u.NOME_COMPLETO";
                                        $stmt_supervisores = $pdo->prepare($sql_supervisores);
                                        $stmt_supervisores->execute();
                                        $supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (PDOException $e) {
                                        $supervisores = [];
                                    }
                                } else {
                                    $supervisores = [];
                                }
                                
                                foreach ($supervisores as $supervisor):
                                    // Garantir que ambos sejam strings para comparação correta
                                    $supervisor_selecionado_str = (string)$supervisor_selecionado;
                                    $cod_vendedor_str = (string)$supervisor['COD_VENDEDOR'];
                                    $is_selected = $supervisor_selecionado_str === $cod_vendedor_str;
                                ?>
                                <option value="<?php echo htmlspecialchars($supervisor['COD_VENDEDOR']); ?>" 
                                        <?php echo $is_selected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supervisor['NOME_COMPLETO']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <label for="visao_vendedor" style="font-weight: 600; color: var(--dark-color); margin: 0;">Vendedor:</label>
                            <select id="visao_vendedor" name="visao_vendedor" onchange="mudarVisao()" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; background: var(--white); color: var(--text-color); min-width: 200px;">
                                <option value="">Todos os Vendedores</option>
                                <?php
                                // Buscar vendedores disponíveis baseado no supervisor selecionado
                                if (isset($pdo)) {
                                    try {
                                        if (strtolower(trim($usuario['perfil'])) === 'supervisor') {
                                            // Para supervisores, mostrar apenas vendedores da sua equipe
                                            $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO 
                                                             FROM USUARIOS 
                                                             WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
                                                             ORDER BY NOME_COMPLETO";
                                            $stmt_vendedores = $pdo->prepare($sql_vendedores);
                                            $stmt_vendedores->execute([$usuario['cod_vendedor']]);
                                        } elseif (!empty($supervisor_selecionado)) {
                                            // Se um supervisor foi selecionado, mostrar apenas vendedores dele
                                            $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO 
                                                             FROM USUARIOS 
                                                             WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
                                                             ORDER BY NOME_COMPLETO";
                                            $stmt_vendedores = $pdo->prepare($sql_vendedores);
                                            $stmt_vendedores->execute([$supervisor_selecionado]);
                                        } else {
                                            // Se nenhum supervisor foi selecionado, mostrar todos os vendedores
                                            $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO 
                                                             FROM USUARIOS 
                                                             WHERE ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
                                                             ORDER BY NOME_COMPLETO";
                                            $stmt_vendedores = $pdo->prepare($sql_vendedores);
                                            $stmt_vendedores->execute();
                                        }
                                        $vendedores = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (PDOException $e) {
                                        $vendedores = [];
                                    }
                                } else {
                                    $vendedores = [];
                                }
                                
                                foreach ($vendedores as $vendedor):
                                    $vendedor_selecionado_str = (string)$vendedor_selecionado;
                                    $cod_vendedor_str = (string)$vendedor['COD_VENDEDOR'];
                                    $is_selected = $vendedor_selecionado_str === $cod_vendedor_str;
                                ?>
                                <option value="<?php echo htmlspecialchars($vendedor['COD_VENDEDOR']); ?>" 
                                        <?php echo $is_selected ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vendedor['NOME_COMPLETO']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                                 <!-- Estatísticas -->
                 <div class="stats-grid">
                     <div class="stat-card">
                         <h3><?php echo number_format($estatisticas['total_ligacoes']); ?></h3>
                         <p><i class="fas fa-phone"></i> Total de Ligações</p>
                     </div>
                     <div class="stat-card finalizadas">
                         <h3><?php echo number_format($estatisticas['ligacoes_finalizadas']); ?></h3>
                         <p><i class="fas fa-check-circle"></i> Finalizadas</p>
                     </div>
                     <div class="stat-card canceladas">
                         <h3><?php echo number_format($estatisticas['ligacoes_canceladas']); ?></h3>
                         <p><i class="fas fa-times-circle"></i> Canceladas</p>
                     </div>
                     <div class="stat-card">
                         <h3><?php echo number_format($estatisticas['media_respostas'], 1); ?></h3>
                         <p><i class="fas fa-chart-line"></i> Média de Respostas</p>
                     </div>
                 </div>
                
                <!-- Filtros -->
                <div class="filtros-container">
                    <form method="GET" class="filtros-form">
                        <div class="filtros-grid">
                                                         <div class="filtro-grupo">
                                 <label for="filtro_status">Status:</label>
                                 <select name="filtro_status" id="filtro_status">
                                     <option value="">Todos</option>
                                     <option value="finalizada" <?php echo $filtro_status === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                     <option value="cancelada" <?php echo $filtro_status === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                 </select>
                             </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_data_inicio">Data Início:</label>
                                <input type="date" name="filtro_data_inicio" id="filtro_data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_data_fim">Data Fim:</label>
                                <input type="date" name="filtro_data_fim" id="filtro_data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_usuario">Usuário:</label>
                                <input type="text" name="filtro_usuario" id="filtro_usuario" placeholder="Nome do usuário" value="<?php echo htmlspecialchars($filtro_usuario); ?>">
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_cliente">Cliente:</label>
                                <input type="text" name="filtro_cliente" id="filtro_cliente" placeholder="Nome ou CNPJ do cliente" value="<?php echo htmlspecialchars($filtro_cliente); ?>">
                            </div>
                            
                        </div>
                        
                        <div class="filtros-acoes">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="<?php echo base_url('gestao-ligacoes'); ?>" class="btn btn-limpar-filtros">
                                <i class="fas fa-times"></i> Limpar Filtros
                            </a>
                        </div>
                    </form>
                </div>
                
                <?php 
                // Mostrar informações dos filtros aplicados
                $filtros_ativos = [];
                if (!empty($filtro_status)) {
                    $status_text = '';
                    if ($filtro_status === 'finalizada') $status_text = 'Finalizadas';
                    elseif ($filtro_status === 'cancelada') $status_text = 'Canceladas';
                    $filtros_ativos[] = "Status: " . $status_text;
                }
                if (!empty($filtro_data_inicio)) $filtros_ativos[] = "Data início: " . date('d/m/Y', strtotime($filtro_data_inicio));
                if (!empty($filtro_data_fim)) $filtros_ativos[] = "Data fim: " . date('d/m/Y', strtotime($filtro_data_fim));
                if (!empty($filtro_usuario)) $filtros_ativos[] = "Usuário: " . $filtro_usuario;
                if (!empty($filtro_cliente)) $filtros_ativos[] = "Cliente: " . $filtro_cliente;

                if (!empty($filtros_ativos)): ?>
                    <div class="filtros-info">
                        <i class="fas fa-filter"></i>
                        <strong>Filtros aplicados:</strong> <?php echo implode(' | ', $filtros_ativos); ?>
                        <span class="filtros-count">(<?php echo number_format($total_registros); ?> ligações encontradas)</span>
                    </div>
                <?php endif; ?>
                
                <!-- Tabela de Ligações -->
                <div class="gestao-container">
                    <h3><i class="fas fa-list"></i> Lista de Ligações</h3>
                    
                    <?php if (empty($ligacoes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-phone-slash empty-icon"></i>
                            <p class="empty-text">Nenhuma ligação encontrada.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="ligacoes-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Telefone</th>
                                        <th>Usuário</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                        <th>Progresso</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ligacoes as $ligacao): ?>
                                        <tr>
                                            <td>#<?php echo $ligacao['id']; ?></td>
                                                                                         <td>
                                                 <strong><?php echo htmlspecialchars($ligacao['cliente_nome'] ?: 'Cliente não encontrado'); ?></strong>
                                                 <?php if ($ligacao['cliente_identificador']): ?>
                                                     <br><small><?php echo htmlspecialchars($ligacao['cliente_identificador']); ?></small>
                                                 <?php endif; ?>
                                             </td>
                                            <td><?php echo htmlspecialchars($ligacao['telefone']); ?></td>
                                            <td><?php echo htmlspecialchars($ligacao['usuario_nome'] ?: 'Usuário não encontrado'); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($ligacao['data_ligacao'])); ?></td>
                                                                                         <td>
                                                 <?php 
                                                 $status_class = '';
                                                 $status_text = '';
                                                 switch($ligacao['status']) {
                                                     case 'finalizada':
                                                         $status_class = 'finalizada';
                                                         $status_text = 'Finalizada';
                                                         break;
                                                     case 'cancelada':
                                                         $status_class = 'cancelada';
                                                         $status_text = 'Cancelada';
                                                         break;
                                                     default:
                                                         $status_class = 'cancelada';
                                                         $status_text = 'Cancelada';
                                                 }
                                                 ?>
                                                 <span class="status-badge status-<?php echo $status_class; ?>">
                                                     <?php echo $status_text; ?>
                                                 </span>
                                             </td>
                                            <td>
                                                <?php 
                                                $total_obrigatorias = $ligacao['total_obrigatorias'] ?: 1;
                                                $total_respostas = $ligacao['total_respostas'] ?: 0;
                                                $percentual = ($total_respostas / $total_obrigatorias) * 100;
                                                ?>
                                                <div class="progresso-bar">
                                                    <div class="progresso-fill" data-width="<?php echo min(100, $percentual); ?>"></div>
                                                </div>
                                                <small><?php echo $total_respostas; ?>/<?php echo $total_obrigatorias; ?> respostas</small>
                                            </td>
                                            <td>
                                                                        <div class="btn-acoes">
                            <button class="btn btn-info btn-detalhes" data-ligacao-id="<?php echo $ligacao['id']; ?>" title="Ver Detalhes">
                                <i class="fas fa-eye"></i> Detalhes
                            </button>
                            <?php if ($ligacao['status'] === 'finalizada'): ?>
                                <button class="btn btn-success btn-relatorio" data-ligacao-id="<?php echo $ligacao['id']; ?>" title="Relatório">
                                    <i class="fas fa-file-alt"></i> Relatório
                                </button>
                            <?php endif; ?>
                            <?php if (strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                <button class="btn btn-danger btn-excluir" data-ligacao-id="<?php echo $ligacao['id']; ?>" title="Excluir Ligação">
                                    <i class="fas fa-trash"></i> Excluir
                                </button>
                            <?php endif; ?>
                        </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                            <div class="paginacao-container">
                                <div class="paginacao-info">
                                    <span>Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $itens_por_pagina, $total_registros); ?> de <?php echo number_format($total_registros); ?> ligações</span>
                                </div>
                                
                                <div class="paginacao-controles">
                                    <?php if ($pagina_atual > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>" class="btn">
                                            <i class="fas fa-angle-double-left"></i> Primeira
                                        </a>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])); ?>" class="btn">
                                            <i class="fas fa-angle-left"></i> Anterior
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div class="paginacao-numeros">
                                        <?php
                                        $inicio = max(1, $pagina_atual - 2);
                                        $fim = min($total_paginas, $pagina_atual + 2);
                                        
                                        if ($inicio > 1): ?>
                                            <span class="paginacao-ellipsis">...</span>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                                               class="btn <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($fim < $total_paginas): ?>
                                            <span class="paginacao-ellipsis">...</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($pagina_atual < $total_paginas): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])); ?>" class="btn">
                                            Próxima <i class="fas fa-angle-right"></i>
                                        </a>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>" class="btn">
                                            Última <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
            <footer class="dashboard-footer">
                <p>© 2025 Autopel - Todos os direitos reservados</p>
                <p class="system-info">Sistema BI v1.0</p>
            </footer>
        </div>
    </div>
    
    <!-- Modal de Detalhes da Ligação -->
    <div id="modalDetalhesLigacao" class="modal-detalhes">
        <div class="modal-detalhes-content">
            <div class="modal-detalhes-header">
                <div class="modal-detalhes-title">
                    <i class="fas fa-phone"></i>
                    <h3>Detalhes da Ligação</h3>
                </div>
                <button class="modal-detalhes-close btn-fechar-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-detalhes-body" id="modalDetalhesBody">
                <div class="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Carregando detalhes...</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?php echo base_url('assets/js/gestao-ligacoes.js'); ?>"></script>
    
    <script>
    // Função para mudar a visão do diretor
    function mudarVisao() {
        console.log('Função mudarVisao() chamada em gestao_ligacoes.php');
        
        const supervisorSelect = document.getElementById('visao_supervisor');
        const vendedorSelect = document.getElementById('visao_vendedor');

        console.log('Supervisor select:', supervisorSelect);
        console.log('Vendedor select:', vendedorSelect);

        // Para supervisores, pode não ter o seletor de supervisor
        if (!vendedorSelect) {
            console.log('Seletor de vendedor não encontrado');
            return;
        }

        const supervisorSelecionado = supervisorSelect ? supervisorSelect.value : '';
        const vendedorSelecionado = vendedorSelect.value;

        console.log('Supervisor selecionado:', supervisorSelecionado);
        console.log('Vendedor selecionado:', vendedorSelecionado);

        // Construir URL com parâmetros
        const urlParams = new URLSearchParams();

        if (supervisorSelecionado) {
            urlParams.set('visao_supervisor', supervisorSelecionado);
        }

        if (vendedorSelecionado) {
            urlParams.set('visao_vendedor', vendedorSelecionado);
        }

        // Manter os filtros existentes
        const filtroStatus = document.getElementById('filtro_status').value;
        const filtroDataInicio = document.getElementById('filtro_data_inicio').value;
        const filtroDataFim = document.getElementById('filtro_data_fim').value;
        const filtroUsuario = document.getElementById('filtro_usuario').value;
        const filtroCliente = document.getElementById('filtro_cliente').value;

        if (filtroStatus) urlParams.set('filtro_status', filtroStatus);
        if (filtroDataInicio) urlParams.set('filtro_data_inicio', filtroDataInicio);
        if (filtroDataFim) urlParams.set('filtro_data_fim', filtroDataFim);
        if (filtroUsuario) urlParams.set('filtro_usuario', filtroUsuario);
        if (filtroCliente) urlParams.set('filtro_cliente', filtroCliente);

        const novaURL = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        
        console.log('Nova URL:', novaURL);

        // Recarregar a página com os novos parâmetros
        window.location.href = novaURL;
    }

    // Adicionar event listeners quando o DOM estiver carregado
    document.addEventListener('DOMContentLoaded', function() {
        try {
            console.log('Configurando event listeners para seletores de visão em gestao_ligacoes.php');
            
            const supervisorSelect = document.getElementById('visao_supervisor');
            const vendedorSelect = document.getElementById('visao_vendedor');

            if (supervisorSelect) {
                console.log('Adicionando event listener para supervisor selector');
                supervisorSelect.addEventListener('change', function() {
                    console.log('Supervisor selector mudou');
                    mudarVisao();
                });
            }

            if (vendedorSelect) {
                console.log('Adicionando event listener para vendedor selector');
                vendedorSelect.addEventListener('change', function() {
                    console.log('Vendedor selector mudou');
                    mudarVisao();
                });
            }
        } catch (error) {
            console.error('Erro ao configurar seletores de visão:', error);
        }
    });
    </script>
</body>
</html>
