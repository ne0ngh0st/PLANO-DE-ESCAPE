<?php
// IMPORTANTE: Carregar config.php primeiro para garantir que a sessão seja iniciada
require_once __DIR__ . '/../../../includes/config/config.php';

// Verificar se a sessão foi iniciada corretamente
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    // Em produção, garantir que redirecione para a raiz corretamente
    if (empty($basePath) || $basePath === '/') {
        header('Location: /');
    } else {
        header('Location: ' . rtrim($basePath, '/'));
    }
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once __DIR__ . '/../../../includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Orçamentos';

// Buscar dados de orçamentos
$orcamentos = [];
$erro_mensagem = '';
$total_registros = 0;

// Definir perfil do usuário para uso em toda a página
$perfil_usuario = strtolower(trim($usuario['perfil'] ?? ''));

try {
    // Verificar se a tabela ORCAMENTOS existe
    $sql_check = "SHOW TABLES LIKE 'ORCAMENTOS'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute();
    $table_exists = $stmt_check->fetch();
    
    if (!$table_exists) {
        // Criar tabela ORCAMENTOS se não existir
        $sql_create = "CREATE TABLE IF NOT EXISTS ORCAMENTOS (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_nome VARCHAR(255) NOT NULL,
            cliente_cnpj VARCHAR(18),
            cliente_email VARCHAR(255),
            cliente_telefone VARCHAR(20),
            tipo_produto_servico ENUM('produto', 'servico') DEFAULT 'produto',
            produto_servico TEXT NOT NULL,
            descricao TEXT,
            valor_total DECIMAL(10,2) NOT NULL,
            status ENUM('pendente', 'aprovado', 'rejeitado', 'cancelado') DEFAULT 'pendente',
            forma_pagamento ENUM('a_vista', '28_ddl') DEFAULT 'a_vista',
            tipo_faturamento VARCHAR(255),
            itens_orcamento JSON,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_validade DATE,
            codigo_vendedor VARCHAR(50) NOT NULL,
            observacoes TEXT,
            usuario_criador VARCHAR(100),
            token_aprovacao VARCHAR(255) UNIQUE,
            data_aprovacao_cliente TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql_create);
        
        // Adicionar coluna cliente_cnpj se não existir (para tabelas já criadas)
        try {
            $sql_check_cnpj = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'ORCAMENTOS' 
                                AND COLUMN_NAME = 'cliente_cnpj'";
            $stmt_check_cnpj = $pdo->prepare($sql_check_cnpj);
            $stmt_check_cnpj->execute();
            $col_exists = $stmt_check_cnpj->fetch()['count'];
            
            if ($col_exists == 0) {
                $sql_add_cnpj = "ALTER TABLE ORCAMENTOS ADD COLUMN cliente_cnpj VARCHAR(18) AFTER cliente_nome";
                $pdo->exec($sql_add_cnpj);
            }
        } catch (PDOException $e) {
            error_log("Erro ao adicionar coluna cliente_cnpj: " . $e->getMessage());
        }
        
        // Adicionar coluna tipo_faturamento se não existir (para tabelas já criadas)
        try {
            $sql_check_tipo_faturamento = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'ORCAMENTOS' 
                                AND COLUMN_NAME = 'tipo_faturamento'";
            $stmt_check_tipo_faturamento = $pdo->prepare($sql_check_tipo_faturamento);
            $stmt_check_tipo_faturamento->execute();
            $col_exists = $stmt_check_tipo_faturamento->fetch()['count'];
            
            if ($col_exists == 0) {
                $sql_add_tipo_faturamento = "ALTER TABLE ORCAMENTOS ADD COLUMN tipo_faturamento VARCHAR(255) AFTER forma_pagamento";
                $pdo->exec($sql_add_tipo_faturamento);
            }
        } catch (PDOException $e) {
            error_log("Erro ao adicionar coluna tipo_faturamento: " . $e->getMessage());
        }
        
        // Adicionar coluna motivo_recusa se não existir (para tabelas já criadas)
        try {
            $sql_check_motivo_recusa = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'ORCAMENTOS' 
                                AND COLUMN_NAME = 'motivo_recusa'";
            $stmt_check_motivo_recusa = $pdo->prepare($sql_check_motivo_recusa);
            $stmt_check_motivo_recusa->execute();
            $col_exists = $stmt_check_motivo_recusa->fetch()['count'];
            
            if ($col_exists == 0) {
                $sql_add_motivo_recusa = "ALTER TABLE ORCAMENTOS ADD COLUMN motivo_recusa TEXT AFTER data_aprovacao_cliente";
                $pdo->exec($sql_add_motivo_recusa);
            }
        } catch (PDOException $e) {
            error_log("Erro ao adicionar coluna motivo_recusa: " . $e->getMessage());
        }
        
        // Adicionar colunas para aprovação do gestor se não existirem
        try {
            $sql_check_aprovacao_gestor = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'ORCAMENTOS' 
                                AND COLUMN_NAME = 'data_aprovacao_gestor'";
            $stmt_check_aprovacao_gestor = $pdo->prepare($sql_check_aprovacao_gestor);
            $stmt_check_aprovacao_gestor->execute();
            $col_exists = $stmt_check_aprovacao_gestor->fetch()['count'];
            
            if ($col_exists == 0) {
                $sql_add_aprovacao_gestor = "ALTER TABLE ORCAMENTOS ADD COLUMN data_aprovacao_gestor TIMESTAMP NULL AFTER data_aprovacao_cliente";
                $pdo->exec($sql_add_aprovacao_gestor);
            }
        } catch (PDOException $e) {
            error_log("Erro ao adicionar coluna data_aprovacao_gestor: " . $e->getMessage());
        }
        
        try {
            $sql_check_status_gestor = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'ORCAMENTOS' 
                                AND COLUMN_NAME = 'status_gestor'";
            $stmt_check_status_gestor = $pdo->prepare($sql_check_status_gestor);
            $stmt_check_status_gestor->execute();
            $col_exists = $stmt_check_status_gestor->fetch()['count'];
            
            if ($col_exists == 0) {
                $sql_add_status_gestor = "ALTER TABLE ORCAMENTOS ADD COLUMN status_gestor ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente' AFTER data_aprovacao_gestor";
                $pdo->exec($sql_add_status_gestor);
            }
        } catch (PDOException $e) {
            error_log("Erro ao adicionar coluna status_gestor: " . $e->getMessage());
        }
        
        try {
            $sql_check_status_cliente = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'ORCAMENTOS' 
                                AND COLUMN_NAME = 'status_cliente'";
            $stmt_check_status_cliente = $pdo->prepare($sql_check_status_cliente);
            $stmt_check_status_cliente->execute();
            $col_exists = $stmt_check_status_cliente->fetch()['count'];
            
            if ($col_exists == 0) {
                $sql_add_status_cliente = "ALTER TABLE ORCAMENTOS ADD COLUMN status_cliente ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente' AFTER status_gestor";
                $pdo->exec($sql_add_status_cliente);
            }
        } catch (PDOException $e) {
            error_log("Erro ao adicionar coluna status_cliente: " . $e->getMessage());
        }
    }
    
    // Implementar RLS (Row Level Security) baseado no perfil do usuário
    $where_clause = "";
    $params = [];
    
    if (in_array($perfil_usuario, ['admin', 'diretor'])) {
        // Admin e Diretor veem todos os orçamentos
        $where_clause = "";
    } elseif ($perfil_usuario === 'supervisor') {
        // Supervisor vê seus próprios orçamentos E os orçamentos da sua equipe
        $where_clause = "WHERE codigo_vendedor = :cod_vendedor 
                         OR codigo_vendedor IN (
                             SELECT COD_VENDEDOR FROM USUARIOS 
                             WHERE COD_SUPER = :cod_vendedor_super AND ATIVO = 1
                         )";
        $params[':cod_vendedor'] = $usuario['COD_VENDEDOR'];
        $params[':cod_vendedor_super'] = $usuario['COD_VENDEDOR'];
    } elseif (in_array($perfil_usuario, ['vendedor', 'representante', 'assistente'])) {
        // Vendedor, Representante e Assistente veem apenas seus próprios orçamentos
        $where_clause = "WHERE codigo_vendedor = :cod_vendedor";
        $params[':cod_vendedor'] = $usuario['COD_VENDEDOR'];
    } else {
        // Perfil não reconhecido - não mostra nenhum orçamento
        $where_clause = "WHERE 1 = 0";
    }
    
    // Contar total de registros com RLS
    $sql_count = "SELECT COUNT(*) as total FROM ORCAMENTOS " . $where_clause;
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $result_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_registros = $result_count['total'];
    
    // Buscar orçamentos com RLS
    $sql = "SELECT 
                id,
                cliente_nome,
                cliente_cnpj,
                cliente_email,
                cliente_telefone,
                tipo_produto_servico,
                produto_servico,
                descricao,
                valor_total,
                status,
                forma_pagamento,
                tipo_faturamento,
                itens_orcamento,
                data_criacao,
                data_validade,
                codigo_vendedor,
                observacoes,
                usuario_criador,
                token_aprovacao,
                data_aprovacao_cliente,
                data_aprovacao_gestor,
                status_gestor,
                status_cliente,
                motivo_recusa
            FROM ORCAMENTOS 
            " . $where_clause . "
            ORDER BY data_criacao DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $erro_mensagem = "Erro ao buscar orçamentos: " . $e->getMessage();
    error_log("Erro na página de orçamentos: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="secondary-color" content="<?php echo htmlspecialchars($usuario['secondary_color'] ?? '#ff8f00'); ?>">
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/orcamentos.css'); ?>?v=<?php echo time(); ?>">
    <!-- Cache bust: <?php echo date('Y-m-d H:i:s'); ?> -->
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include __DIR__ . '/../../../includes/components/navbar.php'; ?>
        
        <div class="dashboard-container">
            <!-- Incluir sidebar -->
            <?php include __DIR__ . '/../../../includes/components/sidebar.php'; ?>
            
            <main class="dashboard-main">
    <!-- Estatísticas Rápidas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $total_registros; ?></div>
                <div class="stat-label">Total de Orçamentos</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">
                    <?php 
                    $pendentes = array_filter($orcamentos, function($o) { return $o['status'] === 'pendente'; });
                    echo count($pendentes);
                    ?>
                </div>
                <div class="stat-label">Pendentes</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">
                    <?php 
                    $aprovados = array_filter($orcamentos, function($o) { return $o['status'] === 'aprovado'; });
                    echo count($aprovados);
                    ?>
                </div>
                <div class="stat-label">Aprovados</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">
                    R$ <?php 
                    $valor_total = array_sum(array_column($orcamentos, 'valor_total'));
                    echo number_format($valor_total, 2, ',', '.');
                    ?>
                </div>
                <div class="stat-label">Valor Total</div>
            </div>
        </div>
    </div>

    <!-- Filtros e Busca -->
    <div class="filters-section">
        <div class="filters-row">
            <div class="filter-group">
                <label for="filtro-status">Status:</label>
                <select id="filtro-status" class="filter-select">
                    <option value="">Todos</option>
                    <option value="pendente">Pendente</option>
                    <option value="aprovado">Aprovado</option>
                    <option value="rejeitado">Rejeitado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="busca-orcamento">Buscar:</label>
                <input type="text" id="busca-orcamento" class="filter-input" placeholder="Cliente, produto ou descrição...">
            </div>
            
            <div class="filter-actions">
                <button class="btn btn-success" onclick="abrirModalNovoOrcamento()">
                    <i class="fas fa-plus"></i>
                    Novo Orçamento
                </button>
                <button class="btn btn-secondary" onclick="limparFiltros()">
                    <i class="fas fa-times"></i>
                    Limpar
                </button>
                <button class="btn btn-primary" onclick="aplicarFiltros()">
                    <i class="fas fa-search"></i>
                    Filtrar
                </button>
            </div>
        </div>
    </div>

    <!-- Tabela de Orçamentos -->
    <div class="table-container">
        <?php if (!empty($erro_mensagem)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($erro_mensagem); ?>
            </div>
        <?php elseif (empty($orcamentos)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <h3>Nenhum orçamento encontrado</h3>
                <p>Comece criando seu primeiro orçamento</p>
                <button class="btn btn-primary" onclick="abrirModalNovoOrcamento()">
                    <i class="fas fa-plus"></i>
                    Criar Orçamento
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="tabela-orcamentos">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data Criação</th>
                            <th>Cliente</th>
                            <th>Produto/Serviço</th>
                            <th>Valor</th>
                            <th>Pagamento</th>
                            <th>Validade</th>
                            <th>Vendedor / Criado por</th>
                            <th>Aprovação Cliente</th>
                            <th>Aprovação Gestor</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orcamentos as $orcamento): ?>
                            <tr data-id="<?php echo $orcamento['id']; ?>">
                                <td><?php echo $orcamento['id']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($orcamento['data_criacao'])); ?></td>
                                <td>
                                    <div class="cliente-info">
                                        <div class="cliente-nome"><?php echo htmlspecialchars($orcamento['cliente_nome']); ?></div>
                                        <?php if ($orcamento['cliente_email']): ?>
                                            <div class="cliente-email"><?php echo htmlspecialchars($orcamento['cliente_email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="produto-info">
                                        <div class="produto-nome"><?php echo htmlspecialchars(substr($orcamento['produto_servico'], 0, 50)) . (strlen($orcamento['produto_servico']) > 50 ? '...' : ''); ?></div>
                                    </div>
                                </td>
                                <td class="valor-cell">
                                    R$ <?php echo number_format($orcamento['valor_total'], 2, ',', '.'); ?>
                                </td>
                                <td>
                                    <span class="pagamento-badge">
                                        <?php 
                                        // Mostrar forma de pagamento diretamente
                                        $forma_pag = $orcamento['forma_pagamento'] ?? 'Não informado';
                                        
                                        // Se for um valor antigo (a_vista, 28_ddl), converter para legível
                                        if ($forma_pag === 'a_vista') {
                                            echo 'À Vista';
                                        } elseif ($forma_pag === '28_ddl') {
                                            echo '28 DDL';
                                        } else {
                                            // Mostrar valor direto (pode ser personalizado ou da TABLE 74)
                                            echo htmlspecialchars($forma_pag);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($orcamento['data_validade']): ?>
                                        <?php 
                                        $data_validade = new DateTime($orcamento['data_validade']);
                                        $hoje = new DateTime();
                                        $dias_restantes = $hoje->diff($data_validade)->days;
                                        
                                        if ($data_validade < $hoje) {
                                            echo '<span class="text-danger">Vencido</span>';
                                        } elseif ($dias_restantes <= 7) {
                                            echo '<span class="text-warning">' . $dias_restantes . ' dias</span>';
                                        } else {
                                            echo date('d/m/Y', strtotime($orcamento['data_validade']));
                                        }
                                        ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="vendedor-criador-info">
                                        <div class="codigo-vendedor"><?php echo htmlspecialchars($orcamento['codigo_vendedor'] ?? 'N/A'); ?></div>
                                        <div class="nome-usuario-criador"><?php echo htmlspecialchars($orcamento['usuario_criador'] ?? 'N/A'); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $status_cliente = $orcamento['status_cliente'] ?? 'pendente';
                                    $data_aprovacao_cliente = $orcamento['data_aprovacao_cliente'];
                                    
                                    if ($status_cliente === 'aprovado' && $data_aprovacao_cliente): ?>
                                        <span class="aprovacao-badge aprovacao-aprovado" title="Aprovado pelo cliente em <?php echo date('d/m/Y H:i', strtotime($data_aprovacao_cliente)); ?>">
                                            <i class="fas fa-check-circle"></i> Aprovado
                                        </span>
                                    <?php elseif ($status_cliente === 'rejeitado' && $data_aprovacao_cliente): ?>
                                        <span class="aprovacao-badge aprovacao-rejeitado" title="Rejeitado pelo cliente em <?php echo date('d/m/Y H:i', strtotime($data_aprovacao_cliente)); ?><?php if ($orcamento['motivo_recusa']): ?><?php echo ' - Motivo: ' . htmlspecialchars($orcamento['motivo_recusa']); ?><?php endif; ?>">
                                            <i class="fas fa-times-circle"></i> Rejeitado
                                        </span>
                                    <?php elseif ($orcamento['token_aprovacao']): ?>
                                        <span class="aprovacao-badge aprovacao-pendente" title="Aguardando aprovação do cliente">
                                            <i class="fas fa-clock"></i> Pendente
                                        </span>
                                    <?php else: ?>
                                        <span class="aprovacao-badge aprovacao-nao-enviado" title="Link não gerado">
                                            <i class="fas fa-times-circle"></i> Não enviado
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="approval-actions-cell">
                                    <?php 
                                    $status_gestor = $orcamento['status_gestor'] ?? 'pendente';
                                    $data_aprovacao_gestor = $orcamento['data_aprovacao_gestor'];
                                    $is_gestor = in_array($perfil_usuario, ['admin', 'diretor', 'supervisor']);
                                    
                                    if ($status_gestor === 'pendente'): 
                                        if ($is_gestor): ?>
                                            <!-- Gestores veem botões de ação -->
                                            <div class="approval-buttons">
                                                <button class="btn-approve-main" onclick="aprovarOrcamento(<?php echo $orcamento['id']; ?>)" title="Aprovar Orçamento">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn-reject-main" onclick="rejeitarOrcamento(<?php echo $orcamento['id']; ?>)" title="Rejeitar Orçamento">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <!-- Vendedores/Representantes veem apenas status pendente -->
                                            <span class="status-badge status-pendente" title="Aguardando aprovação do gestor">
                                                <i class="fas fa-clock"></i> Aguardando aprovação do gestor
                                            </span>
                                        <?php endif;
                                    elseif ($status_gestor === 'aprovado' && $data_aprovacao_gestor): ?>
                                        <span class="status-badge status-aprovado" title="Aprovado pelo gestor em <?php echo date('d/m/Y H:i', strtotime($data_aprovacao_gestor)); ?>">
                                            <i class="fas fa-check-circle"></i> Aprovado
                                        </span>
                                    <?php elseif ($status_gestor === 'rejeitado' && $data_aprovacao_gestor): ?>
                                        <span class="status-badge status-rejeitado" title="Rejeitado pelo gestor em <?php echo date('d/m/Y H:i', strtotime($data_aprovacao_gestor)); ?>">
                                            <i class="fas fa-times-circle"></i> Rejeitado
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pendente">
                                            <i class="fas fa-clock"></i> Pendente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <button class="btn-action btn-view" onclick="visualizarOrcamento(<?php echo $orcamento['id']; ?>)" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-action btn-pdf" onclick="gerarPDFOrcamento(<?php echo $orcamento['id']; ?>)" title="Gerar PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </button>
                                        <button class="btn-action btn-edit" onclick="editarOrcamento(<?php echo $orcamento['id']; ?>)" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-link" onclick="gerarLinkAprovacao(<?php echo $orcamento['id']; ?>)" title="Link de Aprovação">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <?php if (in_array($perfil_usuario, ['admin', 'diretor'])): ?>
                                            <button class="btn-action btn-delete" onclick="excluirOrcamento(<?php echo $orcamento['id']; ?>)" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Novo/Editar Orçamento -->
<div id="modal-orcamento" class="modal">
    <div class="modal-content modal-extra-large">
        <div class="modal-header">
            <h2 id="modal-titulo">Novo Orçamento</h2>
            <button class="modal-close" onclick="fecharModalOrcamento()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-orcamento" class="modal-body">
            <input type="hidden" id="orcamento-id" name="id">
            
            <!-- Seção: Informações do Cliente -->
            <div class="form-section">
                <h4><i class="fas fa-user"></i> Informações do Cliente</h4>
                
                <!-- Busca rápida de cliente -->
                <div class="form-group">
                    <label for="busca-cliente"><i class="fas fa-search"></i> Buscar Cliente (CNPJ ou Nome)</label>
                    <div class="busca-cliente-wrapper">
                        <input type="text" id="busca-cliente" placeholder="Digite CNPJ ou nome do cliente...">
                        <button type="button" class="btn-buscar-cliente" onclick="buscarCliente()">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                    <small class="form-help">Digite o CNPJ ou nome do cliente para preencher automaticamente</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="cliente-nome">Nome do Cliente *</label>
                        <input type="text" id="cliente-nome" name="cliente_nome" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cliente-cnpj">CNPJ</label>
                        <input type="text" id="cliente-cnpj" name="cliente_cnpj" placeholder="00.000.000/0000-00">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="cliente-email">E-mail</label>
                        <input type="email" id="cliente-email" name="cliente_email">
                    </div>
                    
                    <div class="form-group">
                        <label for="cliente-telefone">Telefone</label>
                        <input type="text" id="cliente-telefone" name="cliente_telefone" placeholder="(11) 99999-9999">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="forma-pagamento">Forma de Pagamento *</label>
                        <select id="forma-pagamento" name="forma_pagamento" required onchange="toggleFormaPagamentoPersonalizada()">
                            <option value="">Selecione a forma de pagamento</option>
                            <option value="A VISTA">A VISTA</option>
                            <option value="7DDL">7DDL</option>
                            <option value="14DDL">14DDL</option>
                            <option value="21 DDL">21 DDL</option>
                            <option value="28 DDL">28 DDL</option>
                            <option value="28/35/42DDL">28/35/42DDL</option>
                            <option value="28/42/56DDL">28/42/56DDL</option>
                            <option value="30/45/60 DDL">30/45/60 DDL</option>
                            <option value="OUTROS">OUTROS (DISCERTATIVO)</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="forma-pagamento-personalizada-group" style="display: none;">
                        <label for="forma-pagamento-personalizada">Descrição Discertativa *</label>
                        <input type="text" id="forma-pagamento-personalizada" name="forma_pagamento_personalizada" placeholder="Ex: 15/30/45 dias, Parcelado em 3x, etc.">
                    </div>
                </div>
            </div>
            
            <!-- Seção: Configurações do Orçamento -->
            <div class="form-section">
                <h4><i class="fas fa-cog"></i> Configurações do Orçamento</h4>
                
                <div class="form-group">
                    <label for="tipo-produto-servico-faturamento">Tipo de Faturamento *</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="radio" id="tipo-servico" name="tipo_faturamento" value="servico" onchange="toggleCalculoIPI()">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-text">
                                <i class="fas fa-tools"></i>
                                <strong>SERVIÇO</strong>
                                <small>Sem IPI</small>
                            </span>
                        </label>
                        
                        <label class="checkbox-label">
                            <input type="radio" id="tipo-produto" name="tipo_faturamento" value="produto" onchange="toggleCalculoIPI()">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-text">
                                <i class="fas fa-box"></i>
                                <strong>PRODUTO</strong>
                                <small>Com IPI 3,25%</small>
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- Informação sobre validade -->
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <span>Validade automática de <strong>5 dias</strong> a partir da data de emissão</span>
                </div>
            </div>
            
            <!-- Campos hidden -->
            <input type="hidden" id="data-validade" name="data_validade">
            <input type="hidden" id="tipo-produto-servico" name="tipo_produto_servico" value="produto">
            <input type="hidden" id="produto-servico" name="produto_servico" value="Produto">
            <input type="hidden" id="valor-total" name="valor_total" value="0">
            <input type="hidden" id="status" name="status" value="pendente">
            <input type="hidden" id="codigo-vendedor" name="codigo_vendedor" value="">
            
            <!-- Tabela de Itens do Orçamento -->
            <div class="form-section">
                <h4><i class="fas fa-list"></i> Itens do Orçamento</h4>
                
                <div class="tabela-itens-container">
                    <table class="tabela-itens" id="tabela-itens">
                        <thead>
                            <tr>
                                <th>ITEM</th>
                                <th>DESCRIÇÃO</th>
                                <th>QTD</th>
                                <th>VLR UNITÁRIO</th>
                                <th>VLR TOTAL</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="itens-tbody">
                            <!-- Itens serão adicionados dinamicamente -->
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4" class="total-label">TOTAL GERAL:</td>
                                <td class="total-value" id="total-geral">R$ 0,00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="botoes-itens">
                        <button type="button" class="btn btn-secondary" onclick="adicionarItem()">
                            <i class="fas fa-plus"></i> Adicionar Item
                        </button>
                    </div>
                </div>
                
                <!-- Campo para mostrar cálculo de IPI quando produto -->
                <div id="ipi-calculo-group" style="display: none;">
                    <div class="ipi-info-box">
                        <div class="ipi-header">
                            <i class="fas fa-calculator"></i>
                            <span>Cálculo de IPI (3,25%)</span>
                        </div>
                        <div class="ipi-details">
                            <div class="ipi-row">
                                <span>Valor Base:</span>
                                <span id="ipi-valor-base">R$ 0,00</span>
                            </div>
                            <div class="ipi-row">
                                <span>IPI (3,25%):</span>
                                <span id="ipi-valor-imposto">R$ 0,00</span>
                            </div>
                            <div class="ipi-row total">
                                <span>Valor Total:</span>
                                <span id="ipi-valor-total">R$ 0,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seção: Outras Informações (para o PDF) -->
            <div class="form-section">
                <h4><i class="fas fa-info-circle"></i> Outras Informações (para o PDF)</h4>
                
                <div class="form-group">
                    <label for="variacao-produtos">Variação nos Produtos Personalizados</label>
                    <input type="text" id="variacao-produtos" name="variacao_produtos" placeholder="Ex: (+ ou - 10% da quantidade produzida)" value="(+ ou - 10% da quantidade produzida)">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="prazo-producao">Prazo de Produção</label>
                        <input type="text" id="prazo-producao" name="prazo_producao" placeholder="Ex: 10 dias após aprovação do LAYOUT" value="10 dias após a aprovação do LAYOUT">
                    </div>
                    
                    <div class="form-group">
                        <label for="garantia-imagem">Garantia de Imagem</label>
                        <input type="text" id="garantia-imagem" name="garantia_imagem" placeholder="Ex: 5 anos" value="5 anos">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="texto-importante">Texto Importante (Validade e Condições)</label>
                    <textarea id="texto-importante" name="texto_importante" rows="3" placeholder="Texto sobre validade de preços e condições...">Os preços são válidos para 05 dias, podendo ser realinhados até o fechamento do mesmo em detrimento de forte desequilíbrio econômico, e/ou de aumentos acima das expectativas habituais de insumos e matéria prima.</textarea>
                </div>
            </div>
            
            <!-- Seção: Observações -->
            <div class="form-section">
                <h4><i class="fas fa-sticky-note"></i> Observações</h4>
                <div class="form-group">
                    <label for="observacoes">Observações Adicionais</label>
                    <textarea id="observacoes" name="observacoes" rows="3" placeholder="Observações adicionais sobre o orçamento..."></textarea>
                </div>
            </div>
        </form>
        
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="fecharModalOrcamento()">
                Cancelar
            </button>
            <button type="button" class="btn-save" onclick="salvarOrcamento()">
                <i class="fas fa-save"></i>
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal Visualizar Orçamento -->
<div id="modal-visualizar" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2>Detalhes do Orçamento</h2>
            <button class="modal-close" onclick="fecharModalVisualizar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body" id="conteudo-visualizar">
            <!-- Conteúdo será carregado via AJAX -->
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="fecharModalVisualizar()">
                Fechar
            </button>
            <button type="button" class="btn-save" onclick="imprimirOrcamento()">
                <i class="fas fa-print"></i>
                Imprimir
            </button>
            <button type="button" class="btn-secondary" id="btn-pdf-modal">
                <i class="fas fa-file-pdf"></i>
                Gerar PDF
            </button>
        </div>
            </main>
        </div>
    </div>
    
    <script>
        // Dados do usuário para preenchimento automático
        window.dadosUsuario = {
            nome: '<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>',
            email: '<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>',
            cod_vendedor: '<?php echo htmlspecialchars($usuario['COD_VENDEDOR'] ?? ''); ?>',
            cod_super: '<?php echo htmlspecialchars($usuario['COD_SUPER'] ?? ''); ?>',
            perfil: '<?php echo htmlspecialchars($usuario['perfil'] ?? ''); ?>'
        };
    </script>
    
    <script src="<?php echo base_url('assets/js/orcamentos.js'); ?>?v=<?php echo time(); ?>"></script>
    
    <?php include __DIR__ . '/../../../includes/components/nav_hamburguer.php'; ?>
</body>
</html>
