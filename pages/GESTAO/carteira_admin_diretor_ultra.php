<?php
// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../includes/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once __DIR__ . '/../../includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar se é admin ou diretor
$is_admin_diretor = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin']);
if (!$is_admin_diretor) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    if (function_exists('base_url')) {
        header('Location: ' . base_url('carteira-teste'));
    } else {
        header('Location: ' . rtrim($basePath, '/') . '/carteira-teste');
    }
    exit;
}

// Configurações ULTRA otimizadas
ini_set('max_execution_time', 300);
ini_set('memory_limit', '1024M'); // Aumentado para 1GB
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        // Otimizações adicionais
        $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET SESSION group_concat_max_len = 1000000");
    } catch (Throwable $e) {
        // Ignorar ajustes caso o driver não suporte
    }
}

// Processar filtros - persistência em sessão
if (isset($_GET['limpar_filtros'])) {
    unset($_SESSION['carteira_filtros_admin_ultra']);
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'pages/GESTAO/carteira_admin_diretor_ultra.php');
    exit;
}

// Salvar filtros na sessão
if (!empty($_GET)) {
    foreach (['filtro_estado', 'filtro_vendedor', 'filtro_segmento', 'filtro_ano', 'filtro_mes', 'visao_supervisor', 'busca_cliente'] as $filtro) {
        if (isset($_GET[$filtro])) {
            $_SESSION['carteira_filtros_admin_ultra'][$filtro] = $_GET[$filtro];
        }
    }
}

// Usar filtros da sessão
$filtro_estado = $_GET['filtro_estado'] ?? ($_SESSION['carteira_filtros_admin_ultra']['filtro_estado'] ?? '');
$filtro_vendedor = $_GET['filtro_vendedor'] ?? ($_SESSION['carteira_filtros_admin_ultra']['filtro_vendedor'] ?? '');
$filtro_segmento = $_GET['filtro_segmento'] ?? ($_SESSION['carteira_filtros_admin_ultra']['filtro_segmento'] ?? '');
$filtro_ano = $_GET['filtro_ano'] ?? ($_SESSION['carteira_filtros_admin_ultra']['filtro_ano'] ?? '');
$filtro_mes = $_GET['filtro_mes'] ?? ($_SESSION['carteira_filtros_admin_ultra']['filtro_mes'] ?? '');
$supervisor_selecionado = $_GET['visao_supervisor'] ?? ($_SESSION['carteira_filtros_admin_ultra']['visao_supervisor'] ?? '');
$busca_cliente = $_GET['busca_cliente'] ?? ($_SESSION['carteira_filtros_admin_ultra']['busca_cliente'] ?? '');

// Configurações de paginação ULTRA otimizadas
$itens_por_pagina = 30; // Reduzido para melhor performance
$pagina_atual = max(1, intval($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Buscar clientes da carteira - VERSÃO ULTRA OTIMIZADA
$clientes = [];
$total_clientes = 0;

try {
    // Construir condições WHERE simplificadas
    $where_conditions = [];
    $params = [];
    
    // Filtro de supervisão - usar tabela USUARIOS para garantir consistência
    if (!empty($supervisor_selecionado)) {
        $where_conditions[] = "uf.COD_VENDEDOR IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante'))";
        $params[] = str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT);
        if (!empty($filtro_vendedor)) {
            $where_conditions[] = "uf.COD_VENDEDOR = ?";
            $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
        }
    }
    
    // Filtros básicos
    if (!empty($filtro_estado)) {
        $where_conditions[] = "uf.ESTADO = ?";
        $params[] = $filtro_estado;
    }
    
    if (!empty($filtro_vendedor) && empty($supervisor_selecionado)) {
        $where_conditions[] = "uf.COD_VENDEDOR = ?";
        $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
    }
    
    if (!empty($filtro_segmento)) {
        $where_conditions[] = "uf.Descricao1 = ?";
        $params[] = $filtro_segmento;
    }
    
    // Filtro de ano/mês usando tabela FATURAMENTO
    if (!empty($filtro_ano)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)";
        $params[] = intval($filtro_ano);
    }
    if (!empty($filtro_mes)) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM FATURAMENTO f WHERE f.CNPJ = uf.CNPJ AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = ?)";
        $params[] = intval($filtro_mes);
    }
    
    // Busca discricionária por nome ou CNPJ
    if (!empty($busca_cliente)) {
        $busca_termo = trim($busca_cliente);
        if (strlen($busca_termo) >= 2) { // Mínimo 2 caracteres para busca
            $where_conditions[] = "(uf.CLIENTE LIKE ? OR uf.NOME_FANTASIA LIKE ? OR uf.CNPJ LIKE ?)";
            $busca_param = '%' . $busca_termo . '%';
            $params[] = $busca_param;
            $params[] = $busca_param;
            $params[] = $busca_param;
        }
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // CONSULTA ULTRA SIMPLIFICADA - SEM JOINs PESADOS
    $sql_clientes = "
        SELECT 
            SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,
            MAX(uf.CNPJ) AS cnpj_representativo,
            MAX(uf.CLIENTE) AS cliente,
            MAX(uf.NOME_FANTASIA) AS nome_fantasia,
            MAX(uf.ESTADO) AS estado,
            MAX(COALESCE(uf.Descricao1, '')) AS segmento_atuacao,
            COALESCE(MAX(uf.COD_VENDEDOR), '') AS cod_vendedor,
            MAX(uf.NOME_VENDEDOR) AS nome_vendedor,
            COALESCE(MAX(uf.COD_SUPER), '') AS cod_supervisor,
            MAX(uf.FANT_SUPER) AS nome_supervisor,
            COUNT(*) AS total_pedidos,
            SUM(uf.VLR_TOTAL) AS valor_total,
            DATE_FORMAT(MAX(STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')), '%d/%m/%Y') AS ultima_compra,
            MAX(STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) AS ultima_compra_data,
            COUNT(DISTINCT uf.CNPJ) AS total_cnpjs_diferentes,
            CASE 
                WHEN MAX(uf.DDD) IS NOT NULL AND MAX(uf.DDD) != '' AND MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' 
                    THEN CONCAT('(', MAX(uf.DDD), ') ', MAX(uf.TELEFONE))
                WHEN MAX(uf.TELEFONE) IS NOT NULL AND MAX(uf.TELEFONE) != '' THEN MAX(uf.TELEFONE)
                WHEN MAX(uf.TELEFONE2) IS NOT NULL AND MAX(uf.TELEFONE2) != '' THEN MAX(uf.TELEFONE2)
                ELSE 'N/A'
            END AS telefone,
            NULL AS ultima_ligacao,
            0 AS faturamento_mes
        FROM ultimo_faturamento uf
        $where_clause
        GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
        ORDER BY ultima_compra_data DESC, valor_total DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $itens_por_pagina;
    $params[] = $offset;
    
    $stmt_clientes = $pdo->prepare($sql_clientes);
    $stmt_clientes->execute($params);
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
    $stmt_clientes->closeCursor();

    // Contar total de registros - CONSULTA SIMPLIFICADA
    $sql_count = "
        SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total
        FROM ultimo_faturamento uf
        $where_clause
    ";
    
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute(array_slice($params, 0, -2)); // Remove LIMIT e OFFSET
    $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_registros = (int)($count_result['total'] ?? 0);
    $total_paginas = (int)ceil($total_registros / $itens_por_pagina);
    
    // Ajustar página atual se necessário
    if ($pagina_atual > $total_paginas && $total_paginas > 0) {
        $pagina_atual = $total_paginas;
    }
    
    $total_clientes = count($clientes);
    
    // Calcular faturamento do período apenas para os clientes da página atual (LEVE)
    if (!empty($clientes) && (!empty($filtro_mes) || !empty($filtro_ano))) {
        $raizes_pagina = array_column($clientes, 'raiz_cnpj');
        $placeholders_raiz = implode(',', array_fill(0, count($raizes_pagina), '?'));
        
        $sql_fat_periodo = "
            SELECT 
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) AS raiz_cnpj,
                SUM(f.VLR_TOTAL) AS fat_periodo
            FROM FATURAMENTO f
            WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(f.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) IN ($placeholders_raiz)
            " . (!empty($filtro_ano) ? "AND YEAR(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = " . intval($filtro_ano) : "") . "
            " . (!empty($filtro_mes) ? "AND MONTH(STR_TO_DATE(f.EMISSAO, '%d/%m/%Y')) = " . intval($filtro_mes) : "") . "
            GROUP BY raiz_cnpj
        ";
        
        $stmt_fat_periodo = $pdo->prepare($sql_fat_periodo);
        $stmt_fat_periodo->execute($raizes_pagina);
        $fat_periodo_rows = $stmt_fat_periodo->fetchAll(PDO::FETCH_ASSOC);
        $stmt_fat_periodo->closeCursor();
        
        // Mapear faturamento do período
        $map_fat_periodo = [];
        foreach ($fat_periodo_rows as $row) {
            $map_fat_periodo[$row['raiz_cnpj']] = (float)$row['fat_periodo'];
        }
        
        // Adicionar faturamento do período aos clientes
        foreach ($clientes as &$cliente) {
            $cliente['faturamento_periodo'] = $map_fat_periodo[$cliente['raiz_cnpj']] ?? 0;
        }
        unset($cliente);
    }
    
} catch (PDOException $e) {
    $error_message = "Erro ao buscar clientes: " . $e->getMessage();
}

// Buscar dados para filtros - CONSULTAS SIMPLIFICADAS
$estados = [];
$vendedores = [];
$segmentos = [];

try {
    // Estados - usar tabela USUARIOS para garantir consistência
    if (!empty($supervisor_selecionado)) {
        $sql_estados = "SELECT DISTINCT uf.ESTADO FROM ultimo_faturamento uf 
                       WHERE uf.ESTADO IS NOT NULL AND uf.ESTADO != '' 
                       AND uf.COD_VENDEDOR IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante'))
                       ORDER BY uf.ESTADO";
        $stmt_estados = $pdo->prepare($sql_estados);
        $stmt_estados->execute([str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT)]);
    } else {
        $sql_estados = "SELECT DISTINCT ESTADO FROM ultimo_faturamento WHERE ESTADO IS NOT NULL AND ESTADO != '' ORDER BY ESTADO LIMIT 50";
        $stmt_estados = $pdo->query($sql_estados);
    }
    $estados = $stmt_estados->fetchAll(PDO::FETCH_COLUMN);
    
    // Vendedores - buscar na tabela USUARIOS para garantir consistência
    if (!empty($supervisor_selecionado)) {
        $sql_vendedores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO as NOME_VENDEDOR 
                          FROM USUARIOS u 
                          WHERE u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                          ORDER BY u.NOME_COMPLETO";
        $stmt_vendedores = $pdo->prepare($sql_vendedores);
        $stmt_vendedores->execute([str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT)]);
    } else {
        $sql_vendedores = "SELECT DISTINCT COD_VENDEDOR, NOME_COMPLETO as NOME_VENDEDOR 
                          FROM USUARIOS 
                          WHERE ATIVO = 1 AND PERFIL IN ('vendedor', 'representante') 
                          ORDER BY NOME_COMPLETO";
        $stmt_vendedores = $pdo->query($sql_vendedores);
    }
    $vendedores = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
    
    // Segmentos - usar tabela USUARIOS para garantir consistência
    if (!empty($supervisor_selecionado)) {
        $sql_segmentos = "SELECT DISTINCT uf.Descricao1 FROM ultimo_faturamento uf 
                         WHERE uf.Descricao1 IS NOT NULL AND uf.Descricao1 != '' 
                         AND uf.COD_VENDEDOR IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante'))
                         ORDER BY uf.Descricao1 LIMIT 50";
        $stmt_segmentos = $pdo->prepare($sql_segmentos);
        $stmt_segmentos->execute([str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT)]);
    } else {
        $sql_segmentos = "SELECT DISTINCT Descricao1 FROM ultimo_faturamento WHERE Descricao1 IS NOT NULL AND Descricao1 != '' ORDER BY Descricao1 LIMIT 50";
        $stmt_segmentos = $pdo->query($sql_segmentos);
    }
    $segmentos = $stmt_segmentos->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    // Ignorar erros nos filtros
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Carteira de Clientes - Admin/Diretor (Ultra Otimizada)</title>
    <meta name="viewport" content="width=device-width, initial-scale=0.8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="usuario-id" content="<?php echo $usuario['id'] ?? ''; ?>">
    <meta name="secondary-color" content="<?php echo htmlspecialchars($usuario['secondary_color'] ?? '#ff8f00'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/carteira.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/modais.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/carteira-ultra.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/carteira-mobile-cards.css'); ?>">
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
</head>
<body>
    <div class="dashboard-layout" data-perfil="<?php echo strtolower(trim($usuario['perfil'])); ?>">
        <!-- Incluir navbar -->
		<?php include __DIR__ . '/../../includes/components/navbar.php'; ?>
		<?php include __DIR__ . '/../../includes/components/nav_hamburguer.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Título Principal -->
                <div class="titulo-principal">
                    <h2 class="titulo-carteira">
                        <i class="fas fa-rocket"></i>
                        Carteira Otimizada
                    </h2>
                </div>
                
                <!-- Container principal -->
                <div class="carteira-container">
                    <!-- Container Agrupado - Busca e Filtros -->
                    <div class="filtros-busca-container">
                        <!-- Filtros Ultra Simplificados -->
                        <form method="GET" class="filtros-form">
                        <div class="filtros-grid">
                            <!-- Buscador Discricionário -->
                            <div class="filtro-grupo">
                                <label for="busca_cliente">Pesquisar:</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="busca_cliente" 
                                       name="busca_cliente"
                                       placeholder="CNPJ ou nome do cliente..."
                                       value="<?php echo htmlspecialchars($_GET['busca_cliente'] ?? ''); ?>">
                            </div>
                            <div class="filtro-grupo">
                                <label for="visao_supervisor">Supervisão:</label>
                                <select id="visao_supervisor" name="visao_supervisor" onchange="mudarVisao()">
                                    <option value="">Todas as Equipes</option>
                                    <?php
                                    try {
                                        $sql_supervisores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO 
                                                           FROM USUARIOS u 
                                                           INNER JOIN USUARIOS v ON v.COD_SUPER = u.COD_VENDEDOR 
                                                           WHERE u.ATIVO = 1 AND u.PERFIL = 'supervisor' 
                                                           AND v.ATIVO = 1 AND v.PERFIL IN ('vendedor', 'representante')
                                                           ORDER BY u.NOME_COMPLETO LIMIT 20";
                                        $stmt_supervisores = $pdo->prepare($sql_supervisores);
                                        $stmt_supervisores->execute();
                                        $supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Throwable $e) { $supervisores = []; }

                                    foreach ($supervisores as $supervisor):
                                        $cod = (string)($supervisor['COD_VENDEDOR'] ?? '');
                                        $nome = (string)($supervisor['NOME_COMPLETO'] ?? '');
                                        $is_selected = ((string)$supervisor_selecionado === $cod) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($cod); ?>" <?php echo $is_selected; ?>>
                                            <?php echo htmlspecialchars($nome); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_estado">Estado:</label>
                                <select name="filtro_estado" id="filtro_estado">
                                    <option value="">Todos</option>
                                    <?php foreach ($estados as $estado): ?>
                                        <option value="<?php echo htmlspecialchars($estado); ?>" <?php echo $filtro_estado === $estado ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($estado); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_vendedor">Vendedor:</label>
                                <select name="filtro_vendedor" id="filtro_vendedor">
                                    <option value="">Todos</option>
                                    <?php foreach ($vendedores as $vendedor): ?>
                                        <option value="<?php echo htmlspecialchars($vendedor['COD_VENDEDOR']); ?>" <?php echo $filtro_vendedor === $vendedor['COD_VENDEDOR'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vendedor['NOME_VENDEDOR']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_segmento">Segmento:</label>
                                <select name="filtro_segmento" id="filtro_segmento">
                                    <option value="">Todos</option>
                                    <?php foreach ($segmentos as $segmento): ?>
                                        <option value="<?php echo htmlspecialchars($segmento); ?>" <?php echo $filtro_segmento === $segmento ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($segmento); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_ano">Ano:</label>
                                <select name="filtro_ano" id="filtro_ano">
                                    <option value="">Todos</option>
                                    <?php 
                                    $ano_atual = (int)date('Y');
                                    for ($i = 0; $i < 3; $i++) {
                                        $ano = (string)($ano_atual - $i);
                                        $selected = ($filtro_ano ?? '') === $ano ? 'selected' : '';
                                        echo "<option value='{$ano}' {$selected}>{$ano}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="filtro-grupo">
                                <label for="filtro_mes">Mês:</label>
                                <select name="filtro_mes" id="filtro_mes">
                                    <option value="">Todos</option>
                                    <?php 
                                    $meses_pt = [
                                        1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',
                                        7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'
                                    ];
                                    for ($m = 1; $m <= 12; $m++) {
                                        $selected = ((int)($filtro_mes ?? 0) === $m) ? 'selected' : '';
                                        echo "<option value='{$m}' {$selected}>{$meses_pt[$m]}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filtros-acoes">
                            <button type="button" onclick="limparFiltros()" class="btn btn-outline-danger btn-sm limpar-filtros-btn">
                                <i class="fas fa-eraser"></i> Limpar Filtros
                            </button>
                            <small class="text-muted align-self-center">
                                <i class="fas fa-info-circle"></i> Filtros aplicados automaticamente
                            </small>
                        </div>
                        </form>
                    </div>

                    <!-- Informações dos filtros -->
                    <?php 
                    $filtros_ativos = [];
                    if (!empty($filtro_estado)) $filtros_ativos[] = "Estado: " . $filtro_estado;
                    if (!empty($filtro_vendedor)) {
                        $nome_vendedor = '';
                        foreach ($vendedores as $v) {
                            if ($v['COD_VENDEDOR'] === $filtro_vendedor) {
                                $nome_vendedor = $v['NOME_VENDEDOR'];
                                break;
                            }
                        }
                        $filtros_ativos[] = "Vendedor: " . $nome_vendedor;
                    }
                    if (!empty($filtro_segmento)) $filtros_ativos[] = "Segmento: " . $filtro_segmento;
                    if (!empty($filtro_ano)) $filtros_ativos[] = "Ano: " . $filtro_ano;
                    if (!empty($filtro_mes)) {
                        $meses_pt = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
                        $filtros_ativos[] = "Mês: " . ($meses_pt[(int)$filtro_mes] ?? $filtro_mes);
                    }
                    if (!empty($busca_cliente)) {
                        $filtros_ativos[] = "Busca: \"" . htmlspecialchars($busca_cliente) . "\"";
                    }

                    if (!empty($supervisor_selecionado)) {
                        $sql_nome_supervisor = "SELECT NOME_COMPLETO FROM USUARIOS WHERE COD_VENDEDOR = ?";
                        $stmt_nome_supervisor = $pdo->prepare($sql_nome_supervisor);
                        $stmt_nome_supervisor->execute([$supervisor_selecionado]);
                        $nome_supervisor = $stmt_nome_supervisor->fetch(PDO::FETCH_COLUMN);
                        $filtros_ativos[] = "<span class='visao-filtro'>Visão: Equipe de " . htmlspecialchars($nome_supervisor) . "</span>";
                    } else {
                        $filtros_ativos[] = "<span class='visao-filtro'>Visão: Todas as Equipes</span>";
                    }

                    if (!empty($filtros_ativos)): ?>
                        <div class="alert alert-info filtros-info">
                            <i class="fas fa-filter"></i>
                            <strong>Filtros:</strong> <?php echo implode(' | ', $filtros_ativos); ?>
                            <span class="badge bg-primary ms-2"><?php echo number_format($total_registros); ?> clientes</span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Tabela Ultra Simplificada -->
                    <?php if (empty($clientes)): ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-users"></i>
                        <h4>Nenhum cliente encontrado</h4>
                        <p>Não há clientes disponíveis com os filtros aplicados.</p>
                    </div>
                    <?php else: ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped clientes-table">
                            <thead class="table-dark">
                                <tr>
                                    <th>Cliente</th>
                                    <th>CNPJ</th>
                                    <th>UF</th>
                                    <th>Segmento</th>
                                    <th>Vendedor</th>
                                    <th>Supervisor</th>
                                    <th>Status</th>
                                    <th>Últ. Compra</th>
                                    <th><?php echo (!empty($filtro_mes) || !empty($filtro_ano)) ? 'Fat. Período' : 'Valor Total'; ?></th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): 
                                    // Determinar status do cliente
                                    $is_ativo = false;
                                    $is_inativando = false;
                                    $is_inativo = false;
                                    if (!empty($cliente['ultima_compra_data'])) {
                                        $dias_inativo = (time() - strtotime($cliente['ultima_compra_data'])) / (60 * 60 * 24);
                                        $is_ativo = $dias_inativo <= 290;
                                        $is_inativando = $dias_inativo > 290 && $dias_inativo <= 365;
                                        $is_inativo = $dias_inativo > 365;
                                    } else {
                                        $is_inativo = true;
                                    }
                                    
                                    if ($is_ativo) {
                                        $status_class = 'status-ativo';
                                        $status_text = 'Ativo';
                                    } elseif ($is_inativando) {
                                        $status_class = 'status-inativando';
                                        $status_text = 'Ativos...';
                                    } else {
                                        $status_class = 'status-inativo';
                                        $status_text = 'Inativo';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="cliente-nome"><?php echo htmlspecialchars($cliente['cliente'] ?? ''); ?></div>
                                            <div class="cliente-fantasia"><?php echo htmlspecialchars($cliente['nome_fantasia'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? ''); ?></code>
                                            <?php if (($cliente['total_cnpjs_diferentes'] ?? 0) > 1): ?>
                                                <div class="cnpj-info">
                                                    <i class="fas fa-link"></i> <?php echo $cliente['total_cnpjs_diferentes']; ?> CNPJs
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($cliente['estado'] ?? ''); ?></span></td>
                                        <td><?php echo htmlspecialchars($cliente['segmento_atuacao'] ?? ''); ?></td>
                                        <td>
                                            <div class="vendedor-nome"><?php echo htmlspecialchars($cliente['nome_vendedor'] ?? ''); ?></div>
                                            <div class="vendedor-codigo"><?php echo htmlspecialchars($cliente['cod_vendedor']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($cliente['nome_supervisor'] ?? ''); ?></td>
                                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        <td><?php echo htmlspecialchars($cliente['ultima_compra'] ?? '-'); ?></td>
                                        <td><strong>R$ <?php echo number_format((!empty($filtro_mes) || !empty($filtro_ano)) ? ($cliente['faturamento_periodo'] ?? 0) : $cliente['valor_total'], 2, ',', '.'); ?></strong></td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="<?php echo base_url('includes/api/detalhes_cliente.php'); ?>?cnpj=<?php echo urlencode($cliente['cnpj_representativo']); ?>&from_optimized=1" 
                                                   class="btn btn-primary btn-sm" title="Ver detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button class="btn btn-danger btn-sm" title="Excluir Cliente" 
                                                        onclick="confirmarExclusaoCliente('<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Layout Mobile - Cards -->
                    <div class="mobile-layout-indicator">
                        <i class="fas fa-mobile-alt"></i>
                        Visualização otimizada para dispositivos móveis
                    </div>
                    <div class="mobile-cards-container">
                        <?php foreach ($clientes as $cliente): 
                            // Determinar status do cliente
                            $is_ativo = false;
                            $is_inativando = false;
                            $is_inativo = false;
                            if (!empty($cliente['ultima_compra_data'])) {
                                $dias_inativo = (time() - strtotime($cliente['ultima_compra_data'])) / (60 * 60 * 24);
                                $is_ativo = $dias_inativo <= 290;
                                $is_inativando = $dias_inativo > 290 && $dias_inativo <= 365;
                                $is_inativo = $dias_inativo > 365;
                            } else {
                                $is_inativo = true;
                            }
                            
                            if ($is_ativo) {
                                $status_class = 'status-ativo';
                                $status_text = 'Ativo';
                            } elseif ($is_inativando) {
                                $status_class = 'status-inativando';
                                $status_text = 'Ativos...';
                            } else {
                                $status_class = 'status-inativo';
                                $status_text = 'Inativo';
                            }
                        ?>
                            <div class="cliente-card">
                                <!-- Header do Card -->
                                <div class="cliente-card-header">
                                    <div class="cliente-card-nome">
                                        <h4><?php echo htmlspecialchars($cliente['cliente'] ?? ''); ?></h4>
                                    </div>
                                    <p class="cliente-card-fantasia"><?php echo htmlspecialchars($cliente['nome_fantasia'] ?? ''); ?></p>
                                    <div class="cliente-card-status">
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        <span class="uf-badge"><?php echo htmlspecialchars($cliente['estado'] ?? ''); ?></span>
                                        <?php if (!empty($cliente['segmento_atuacao'])): ?>
                                            <span class="segmento-badge"><?php echo htmlspecialchars($cliente['segmento_atuacao']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Informações do Cliente -->
                                <div class="cliente-card-info">
                                    <div class="cliente-info-grid">
                                        <div class="cliente-info-item">
                                            <span class="cliente-info-label">CNPJ</span>
                                            <span class="cliente-info-value cnpj"><?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? ''); ?></span>
                                        </div>
                                        
                                        <div class="cliente-info-item">
                                            <span class="cliente-info-label">Vendedor</span>
                                            <span class="cliente-info-value">
                                                <?php echo htmlspecialchars($cliente['nome_vendedor'] ?? ''); ?>
                                                <?php if (!empty($cliente['cod_vendedor'])): ?>
                                                    <br><small class="text-muted">Cód: <?php echo htmlspecialchars($cliente['cod_vendedor']); ?></small>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="cliente-info-item">
                                            <span class="cliente-info-label">Supervisor</span>
                                            <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['nome_supervisor'] ?? ''); ?></span>
                                        </div>
                                        
                                        <div class="cliente-info-item">
                                            <span class="cliente-info-label">Última Compra</span>
                                            <span class="cliente-info-value"><?php echo htmlspecialchars($cliente['ultima_compra'] ?? '-'); ?></span>
                                        </div>
                                        
                                        <div class="cliente-info-item">
                                            <span class="cliente-info-label"><?php echo (!empty($filtro_mes) || !empty($filtro_ano)) ? 'Fat. Período' : 'Valor Total'; ?></span>
                                            <span class="cliente-info-value valor">
                                                R$ <?php echo number_format((!empty($filtro_mes) || !empty($filtro_ano)) ? ($cliente['faturamento_periodo'] ?? 0) : $cliente['valor_total'], 2, ',', '.'); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (!empty($cliente['telefone']) && $cliente['telefone'] !== 'N/A'): ?>
                                            <div class="cliente-info-item">
                                                <span class="cliente-info-label">Telefone</span>
                                                <span class="cliente-info-value telefone"><?php echo htmlspecialchars($cliente['telefone']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Informações adicionais -->
                                <?php if (($cliente['total_cnpjs_diferentes'] ?? 0) > 1): ?>
                                    <div class="cliente-meta-info">
                                        <div class="cliente-cnpjs-info">
                                            <i class="fas fa-link"></i>
                                            <span><?php echo $cliente['total_cnpjs_diferentes']; ?> CNPJs vinculados</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Ações do Card -->
                                <div class="cliente-card-actions">
                                    <div class="cliente-actions-single-row">
                                        <a href="<?php echo base_url('includes/api/detalhes_cliente.php'); ?>?cnpj=<?php echo urlencode($cliente['cnpj_representativo']); ?>&from_optimized=1" 
                                           class="btn btn-primary-action" title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (!empty($cliente['telefone']) && $cliente['telefone'] !== 'N/A'): ?>
                                            <button class="btn btn-success-action" title="Ligar" 
                                                    onclick="window.open('tel:<?php echo preg_replace('/\D/', '', $cliente['telefone']); ?>', '_self')">
                                                <i class="fas fa-phone"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="btn btn-success-action disabled" title="Telefone não disponível">
                                                <i class="fas fa-phone"></i>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-warning-action" title="Observações" 
                                                onclick="abrirObservacoes('cliente', '<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                        
                                        <button class="btn btn-danger-action" title="Excluir Cliente" 
                                                onclick="confirmarExclusaoCliente('<?php echo htmlspecialchars($cliente['cnpj_representativo'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cliente['cliente'] ?? '', ENT_QUOTES); ?>')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginação Simplificada -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <small class="text-muted">
                                    Mostrando <?php echo (($pagina_atual - 1) * $itens_por_pagina + 1); ?> a <?php echo min($pagina_atual * $itens_por_pagina, $total_registros); ?> de <?php echo number_format($total_registros); ?> clientes
                                </small>
                            </div>
                            <div>
                                <?php if ($pagina_atual > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])); ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </a>
                                <?php endif; ?>
                                
                                <span class="mx-2">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?></span>
                                
                                <?php if ($pagina_atual < $total_paginas): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])); ?>" class="btn btn-sm btn-outline-secondary">
                                        Próxima <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php endif; ?>
                </div>
            </main>
            <footer class="dashboard-footer text-center py-3">
                <p class="mb-0">© 2025 Autopel - Sistema BI v1.0 - Versão Ultra Otimizada</p>
            </footer>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Filtros AJAX fluidos
    let filtroTimeout;
    let searchTimeout;
    
    function aplicarFiltros() {
        clearTimeout(filtroTimeout);
        filtroTimeout = setTimeout(function() {
            carregarClientes();
        }, 300); // Aguarda 300ms após parar de digitar/selecionar
    }
    
    function debounceSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            carregarClientes();
        }, 500); // Aguarda 500ms após parar de digitar para busca
    }
    
    
    function carregarClientes() {
        const form = document.querySelector('.filtros-form');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        
        // Mostrar loading
        const tbody = document.querySelector('.clientes-table tbody');
        tbody.innerHTML = '<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>';
        
        fetch(window.baseUrl('includes/ajax/carteira_ajax_ultra.php') + '?' + params.toString())
            .then(response => response.text())
            .then(data => {
                tbody.innerHTML = data;
            })
            .catch(error => {
                console.error('Erro:', error);
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">Erro ao carregar dados</td></tr>';
            });
    }
    
    function mudarVisao() {
        const supervisorSelecionado = document.getElementById('visao_supervisor').value;
        const selectVendedor = document.getElementById('filtro_vendedor');
        
        // Limpar seleção atual
        selectVendedor.value = '';
        
        // Carregar vendedores baseado no supervisor
        carregarVendedores(supervisorSelecionado);
        
        aplicarFiltros();
    }
    
    function carregarVendedores(supervisorId) {
        const selectVendedor = document.getElementById('filtro_vendedor');
        
        // Mostrar loading
        selectVendedor.innerHTML = '<option value="">Carregando...</option>';
        
        // Fazer requisição AJAX
        const url = window.baseUrl('includes/ajax/carregar_vendedores_ajax.php') + (supervisorId ? '?supervisor=' + encodeURIComponent(supervisorId) : '');
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    selectVendedor.innerHTML = data.html;
                    // Mostrar mensagem de sucesso no console (opcional)
                    if (data.count > 0) {
                        console.log(data.message);
                    }
                } else {
                    selectVendedor.innerHTML = '<option value="">Erro ao carregar</option>';
                    console.error('Erro:', data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                selectVendedor.innerHTML = '<option value="">Erro ao carregar</option>';
            });
    }
    
    function limparFiltros() {
        const filtros = document.querySelectorAll('.filtros-form select, .filtros-form input[type="text"]');
        filtros.forEach(filtro => {
            filtro.value = '';
        });
        
        // Recarregar vendedores (todos) quando limpar filtros
        carregarVendedores('');
        
        aplicarFiltros();
    }
    
    // Adicionar eventos aos filtros
    document.addEventListener('DOMContentLoaded', function() {
        const filtros = document.querySelectorAll('.filtros-form select, .filtros-form input[type="text"]');
        filtros.forEach(filtro => {
            if (filtro.type === 'text') {
                filtro.addEventListener('keyup', debounceSearch);
            } else {
                filtro.addEventListener('change', aplicarFiltros);
            }
        });
        
        // Carregar vendedores baseado no supervisor já selecionado (se houver)
        const supervisorSelecionado = document.getElementById('visao_supervisor').value;
        if (supervisorSelecionado) {
            carregarVendedores(supervisorSelecionado);
        }
    });
    
    // Função de exclusão de cliente
    function confirmarExclusaoCliente(cnpj, nome) {
        if (confirm(`Tem certeza que deseja excluir o cliente "${nome}"?\n\nEsta ação moverá o cliente para a lixeira.`)) {
            excluirCliente(cnpj);
        }
    }
    
    function excluirCliente(cnpj) {
        fetch(window.baseUrl('includes/crud/excluir_cliente.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'cnpj=' + encodeURIComponent(cnpj)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cliente excluído com sucesso!');
                carregarClientes(); // Recarregar a lista
            } else {
                alert('Erro ao excluir cliente: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir cliente. Tente novamente.');
        });
    }
    </script>
</body>
</html>

