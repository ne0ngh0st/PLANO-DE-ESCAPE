<?php
// IMPORTANTE: Carregar config.php primeiro para garantir que a sessão seja iniciada
require_once __DIR__ . '/../../includes/config/config.php';

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
require_once __DIR__ . '/../../includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Pedidos em Aberto';

// Verificar permissões - apenas vendedores, supervisores, diretores, admins e assistentes
$perfil_usuario = strtolower(trim($usuario['perfil']));
if (!in_array($perfil_usuario, ['vendedor', 'representante', 'supervisor', 'diretor', 'admin', 'assistente'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . rtrim($basePath, '/') . '/home');
    exit;
}


// Parâmetros de filtro
$filtro_atrasados = $_GET['filtro_atrasados'] ?? '';
$filtro_status = $_GET['filtro_status'] ?? '';
$filtro_vendedor = $_GET['filtro_vendedor'] ?? '';
$busca = $_GET['busca'] ?? '';

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
    <link rel="stylesheet" href="<?php echo base_url('assets/css/pedidos-abertos.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/pedidos-mobile-responsive.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/pedidos-mobile-cards.css'); ?>">
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include __DIR__ . '/../../includes/components/navbar.php'; ?>
        
        <div class="dashboard-container">
            <main class="dashboard-main">

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
                        <?php if (in_array($perfil_usuario, ['supervisor', 'diretor', 'admin'])): ?>
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
                                    $is_selected = ((string)($_GET['visao_supervisor'] ?? '') === $cod) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($cod); ?>" <?php echo $is_selected; ?>>
                                        <?php echo htmlspecialchars($nome); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array($perfil_usuario, ['supervisor', 'diretor', 'admin'])): ?>
                        <div class="filtro-grupo">
                            <label for="filtro_vendedor">Vendedor:</label>
                            <select name="filtro_vendedor" id="filtro_vendedor">
                                <option value="">Todos</option>
                                <?php
                                try {
                                    if ($perfil_usuario === 'supervisor') {
                                        // Para supervisores, mostrar apenas vendedores da sua equipe da tabela USUARIOS
                                        $sql_vendedores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO as NOME_VENDEDOR 
                                                         FROM USUARIOS u 
                                                         WHERE u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                                                         ORDER BY u.NOME_COMPLETO";
                                        $stmt_vendedores = $pdo->prepare($sql_vendedores);
                                        $stmt_vendedores->execute([$usuario['cod_vendedor']]);
                                    } else {
                                        // Para diretores e admins, mostrar todos os vendedores da tabela USUARIOS
                                        $sql_vendedores = "SELECT DISTINCT COD_VENDEDOR, NOME_COMPLETO as NOME_VENDEDOR 
                                                         FROM USUARIOS 
                                                         WHERE ATIVO = 1 AND PERFIL IN ('vendedor', 'representante') 
                                                         ORDER BY NOME_COMPLETO";
                                        $stmt_vendedores = $pdo->query($sql_vendedores);
                                    }
                                    
                                    $vendedores = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($vendedores as $vendedor):
                                        $is_selected = (($_GET['filtro_vendedor'] ?? '') === $vendedor['COD_VENDEDOR']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($vendedor['COD_VENDEDOR']); ?>" <?php echo $is_selected; ?>>
                                        <?php echo htmlspecialchars($vendedor['NOME_VENDEDOR']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php } catch (PDOException $e) { 
                                    error_log("Erro ao buscar vendedores: " . $e->getMessage());
                                } ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="filtro-grupo">
                            <label for="filtro_segmento">Segmento:</label>
                            <select name="filtro_segmento" id="filtro_segmento">
                                <option value="">Todos</option>
                                <?php
                                try {
                                    $sql_segmentos = "SELECT DISTINCT SEGMENTO FROM PEDIDOS_EM_ABERTO WHERE SEGMENTO IS NOT NULL AND SEGMENTO != '' ORDER BY SEGMENTO LIMIT 50";
                                    $stmt_segmentos = $pdo->query($sql_segmentos);
                                    $segmentos = $stmt_segmentos->fetchAll(PDO::FETCH_COLUMN);
                                    
                                    foreach ($segmentos as $segmento):
                                        $is_selected = (($_GET['filtro_segmento'] ?? '') === $segmento) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo htmlspecialchars($segmento); ?>" <?php echo $is_selected; ?>>
                                        <?php echo htmlspecialchars($segmento); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php } catch (PDOException $e) { 
                                    error_log("Erro ao buscar segmentos: " . $e->getMessage());
                                } ?>
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
                                    $selected = (($_GET['filtro_ano'] ?? '') === $ano) ? 'selected' : '';
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
                                    $selected = ((int)($_GET['filtro_mes'] ?? 0) === $m) ? 'selected' : '';
                                    echo "<option value='{$m}' {$selected}>{$meses_pt[$m]}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="filtro_status">Status:</label>
                            <select name="filtro_status" id="filtro_status">
                                <option value="">Todos</option>
                                <option value="separacao" <?php echo (($_GET['filtro_status'] ?? '') === 'separacao') ? 'selected' : ''; ?>>Separação</option>
                                <option value="bloqueio" <?php echo (($_GET['filtro_status'] ?? '') === 'bloqueio') ? 'selected' : ''; ?>>Bloqueio</option>
                                <option value="wms" <?php echo (($_GET['filtro_status'] ?? '') === 'wms') ? 'selected' : ''; ?>>WMS</option>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="filtro_atraso">Situação:</label>
                            <select name="filtro_atraso" id="filtro_atraso">
                                <option value="">Todos</option>
                                <option value="atraso_faturamento" <?php echo (($_GET['filtro_atraso'] ?? '') === 'atraso_faturamento') ? 'selected' : ''; ?>>Atraso de Faturamento</option>
                                <option value="atraso_entrega" <?php echo (($_GET['filtro_atraso'] ?? '') === 'atraso_entrega') ? 'selected' : ''; ?>>Atraso de Entrega</option>
                                <option value="atrasados" <?php echo (($_GET['filtro_atraso'] ?? '') === 'atrasados') ? 'selected' : ''; ?>>Atrasados (Geral)</option>
                                <option value="no_prazo" <?php echo (($_GET['filtro_atraso'] ?? '') === 'no_prazo') ? 'selected' : ''; ?>>No Prazo</option>
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

                <!-- Estatísticas rápidas -->
                <div class="stats-grid-modern" id="statsContainer">
                    <div class="stat-card-modern total">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="totalPedidos">-</div>
                            <div class="stat-label">Total de Pedidos</div>
                        </div>
                    </div>
                    
                    <div class="stat-card-modern atraso-faturamento">
                        <div class="stat-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="atrasoFaturamento">-</div>
                            <div class="stat-label">Atraso de Faturamento</div>
                        </div>
                    </div>
                    
                    <div class="stat-card-modern atraso-entrega">
                        <div class="stat-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="atrasoEntrega">-</div>
                            <div class="stat-label">Atraso de Entrega</div>
                        </div>
                    </div>
                    
                    <div class="stat-card-modern valor">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="valorTotal">-</div>
                            <div class="stat-label">Valor Total</div>
                        </div>
                    </div>
                    
                    <div class="stat-card-modern vendedores">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" id="totalVendedores">-</div>
                            <div class="stat-label">Vendedores</div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de pedidos -->
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-table"></i> Lista de Pedidos</h3>
                        <div class="table-actions">
                            <button class="btn-export" id="btnExport" title="Exportar dados">
                                <i class="fas fa-download"></i>
                                Exportar
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="pedidos-table" id="pedidosTable">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Produto</th>
                                    <th>Qtd</th>
                                    <th>Valor</th>
                                    <th>Data Pedido</th>
                                    <th>Previsão Faturamento</th>
                                    <th>Status</th>
                                    <th>Status Entrega</th>
                                    <th>Histórico</th>
                                </tr>
                            </thead>
                            <tbody id="pedidosTableBody">
                                <tr>
                                    <td colspan="11" class="loading-row">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        Carregando pedidos...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Layout Mobile - Cards -->
                    <div class="mobile-layout-indicator">
                        <i class="fas fa-mobile-alt"></i>
                        Visualização otimizada para dispositivos móveis
                    </div>
                    <div id="mobileCardsContainer">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                    
                    <!-- Paginação -->
                    <div class="paginacao-container" id="paginationContainer">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                </div>
            </main>
            <?php include __DIR__ . '/../../includes/components/footer.php'; ?>
        </div>
    </div>
    
    <!-- Modal de detalhes do pedido -->
    <div id="pedidoModal" class="modal hidden">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Detalhes do Pedido</h3>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="modalCloseBtn">Fechar</button>
            </div>
        </div>
    </div>
    
    <script src="<?php echo base_url('assets/js/pedidos-abertos.js'); ?>?v=<?php echo time(); ?>"></script>
    <?php include __DIR__ . '/../../includes/components/nav_hamburguer.php'; ?>
    
    <script>
    // Função para criar cards mobile dos pedidos
    function criarCardsMobile(pedidos) {
        const container = document.getElementById('mobileCardsContainer');
        
        if (!container) {
            return;
        }
        
        if (!pedidos || pedidos.length === 0) {
            container.innerHTML = '<div class="alert alert-info text-center"><i class="fas fa-info-circle"></i> Nenhum pedido encontrado</div>';
            return;
        }
        
        let cardsHTML = '';
        
        pedidos.forEach(pedido => {
            
            // Determinar status do pedido
            let statusClass = 'status-aberto';
            let statusText = 'Aberto';
            let isAtrasado = false;
            
            // Verificar se está atrasado baseado na data de previsão de faturamento
            const dataPrevisao = pedido.data_previsao_faturamento || pedido.previsao_faturamento || pedido.data_previsao;
            if (dataPrevisao && dataPrevisao !== 'N/A' && dataPrevisao !== '') {
                try {
                    const previsaoStr = dataPrevisao.toString().trim();
                    let dataPrevisaoObj;
                    
                    // Formato DD/MM/YYYY
                    if (previsaoStr.includes('/')) {
                        const parts = previsaoStr.split('/');
                        if (parts.length === 3) {
                            dataPrevisaoObj = new Date(parts[2], parts[1] - 1, parts[0]);
                        }
                    } 
                    // Formato YYYY-MM-DD
                    else if (previsaoStr.includes('-')) {
                        dataPrevisaoObj = new Date(previsaoStr);
                    }
                    // Outros formatos
                    else {
                        dataPrevisaoObj = new Date(previsaoStr);
                    }
                    
                    const hoje = new Date();
                    hoje.setHours(0, 0, 0, 0);
                    dataPrevisaoObj.setHours(0, 0, 0, 0);
                    
                    // Só marcar como atrasado se a data for válida e realmente passou
                    if (!isNaN(dataPrevisaoObj.getTime()) && dataPrevisaoObj < hoje) {
                        isAtrasado = true;
                        statusClass = 'status-atrasado';
                        statusText = 'Atrasado';
                    }
                } catch (e) {
                    console.log('Erro ao processar data:', dataPrevisao, e);
                }
            }
            
            // Formatar valor - tentar diferentes campos
            let valor = 'R$ 0,00';
            const valorField = pedido.vlr_total || pedido.valor || pedido.valor_total || pedido.total;
            if (valorField) {
                const valorNum = parseFloat(valorField.toString().replace(',', '.'));
                if (!isNaN(valorNum)) {
                    valor = valorNum.toLocaleString('pt-BR', {
                        style: 'currency',
                        currency: 'BRL'
                    });
                }
            }
            
            // Escapar caracteres especiais para evitar problemas no HTML
            const escapeHtml = (text) => {
                if (!text) return 'N/A';
                return text.toString()
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };
            
            // Formatar data
            const formatDate = (dateStr) => {
                if (!dateStr) return 'N/A';
                try {
                    const [day, month, year] = dateStr.split('/');
                    if (day && month && year) {
                        return `${day}/${month}/${year}`;
                    }
                    return dateStr;
                } catch (e) {
                    return dateStr;
                }
            };
            
            // Mapear campos com fallbacks
            const numeroPedido = pedido.numero_pedido || pedido.pedido || pedido.numero || 'N/A';
            const cliente = pedido.cliente || pedido.nome_cliente || 'N/A';
            const vendedor = pedido.nome_vendedor || pedido.vendedor || pedido.vendedor_nome || 'N/A';
            const codProduto = pedido.cod_produto || pedido.produto || pedido.codigo_produto || 'N/A';
            const descricaoProduto = pedido.descricao_produto || pedido.produto_descricao || pedido.descricao || '';
            const quantidade = pedido.quantidade_venda || pedido.quantidade || pedido.qtd || 'N/A';
            const dataPedido = pedido.data_pedido || pedido.data || 'N/A';
            
            cardsHTML += `
                <div class="pedido-card ${isAtrasado ? 'pedido-atrasado' : ''}">
                    <!-- Header do Card -->
                    <div class="pedido-card-header">
                        <div class="pedido-card-numero">
                            <h4>Pedido #${escapeHtml(numeroPedido)}</h4>
                            <div class="pedido-card-valor">${valor}</div>
                        </div>
                        <div class="pedido-card-cliente">${escapeHtml(cliente)}</div>
                        <div class="pedido-card-vendedor">Vendedor: ${escapeHtml(vendedor)}</div>
                        <div class="pedido-card-status">
                            <span class="status-badge ${statusClass}">${statusText}</span>
                            ${isAtrasado ? '<span class="status-badge status-atrasado"><i class="fas fa-exclamation-triangle"></i> Atrasado</span>' : ''}
                        </div>
                    </div>
                    
                    <!-- Informações do Pedido -->
                    <div class="pedido-card-info">
                        <div class="pedido-info-grid">
                            <div class="pedido-info-item">
                                <span class="pedido-info-label">Produto</span>
                                <span class="pedido-info-value produto">${escapeHtml(codProduto)}${descricaoProduto ? ' - ' + escapeHtml(descricaoProduto) : ''}</span>
                            </div>
                            
                            <div class="pedido-info-item">
                                <span class="pedido-info-label">Quantidade</span>
                                <span class="pedido-info-value quantidade">${escapeHtml(quantidade)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Datas -->
                    <div class="pedido-datas">
                        <div class="pedido-datas-header">Datas Importantes</div>
                        <div class="pedido-datas-grid">
                            <div class="pedido-data-item">
                                <span class="pedido-data-label">Data Pedido</span>
                                <span class="pedido-data-value">${formatDate(dataPedido)}</span>
                            </div>
                            <div class="pedido-data-item">
                                <span class="pedido-data-label">Previsão</span>
                                <span class="pedido-data-value ${isAtrasado ? 'data-atrasada' : ''}">${formatDate(dataPrevisao)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ações do Card -->
                    <div class="pedido-card-actions">
                        <div class="pedido-actions-single-row">
                            <button class="btn btn-primary-action" title="Ver detalhes" 
                                    onclick="showPedidoDetails('${escapeHtml(numeroPedido)}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <button class="btn btn-success-action" title="Histórico" 
                                    onclick="verHistoricoPedido('${escapeHtml(numeroPedido)}')">
                                <i class="fas fa-history"></i>
                            </button>
                            
                            <button class="btn btn-warning-action" title="Atualizar status" 
                                    onclick="atualizarStatusPedido('${escapeHtml(numeroPedido)}')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = cardsHTML;
    }
    
    // Função para abrir detalhes do pedido
    function abrirDetalhesPedido(pedidoId) {
        // Implementar lógica para abrir modal de detalhes
        console.log('Abrir detalhes do pedido:', pedidoId);
    }
    
    // Função para ver histórico do pedido
    function verHistoricoPedido(pedidoId) {
        // Implementar lógica para ver histórico
        console.log('Ver histórico do pedido:', pedidoId);
    }
    
    // Função para atualizar status do pedido
    function atualizarStatusPedido(pedidoId) {
        // Implementar lógica para atualizar status
        console.log('Atualizar status do pedido:', pedidoId);
    }
    
    // Função para integrar com o sistema existente
    function integrarComSistemaExistente() {
        // Interceptar a função updateTable do JavaScript existente
        const originalUpdateTable = window.updateTable;
        if (originalUpdateTable) {
            window.updateTable = function(pedidos) {
                // Chamar a função original
                originalUpdateTable(pedidos);
                // Criar cards mobile
                setTimeout(() => {
                    criarCardsMobile(pedidos);
                }, 100);
            };
        }
        
        // Interceptar chamadas AJAX existentes
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url, ...args) {
            this._url = url;
            return originalOpen.apply(this, [method, url, ...args]);
        };
        
        XMLHttpRequest.prototype.send = function(...args) {
            const xhr = this;
            const originalOnReadyStateChange = xhr.onreadystatechange;
            
            xhr.onreadystatechange = function() {
                if (originalOnReadyStateChange) {
                    originalOnReadyStateChange.apply(this, arguments);
                }
                
                if (xhr.readyState === 4 && xhr.status === 200) {
                    if (xhr._url && xhr._url.includes('pedidos_abertos_ajax.php')) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success && response.pedidos) {
                                setTimeout(() => {
                                    criarCardsMobile(response.pedidos);
                                }, 100);
                            }
                        } catch (e) {
                            console.log('Erro ao processar resposta AJAX:', e);
                        }
                    }
                }
            };
            
            return originalSend.apply(this, args);
        };
    }
    
    // Função para extrair dados da tabela HTML
    function extrairDadosDaTabela() {
        const tableBody = document.getElementById('pedidosTableBody');
        if (!tableBody) return [];
        
        const rows = tableBody.querySelectorAll('tr:not(.loading-row)');
        const pedidos = [];
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 11) {
                const pedido = {
                    pedido: cells[0]?.textContent?.trim() || '',
                    cliente: cells[1]?.textContent?.trim() || '',
                    vendedor: cells[2]?.textContent?.trim() || '',
                    produto: cells[3]?.textContent?.trim() || '',
                    quantidade: cells[4]?.textContent?.trim() || '',
                    valor: cells[5]?.textContent?.trim() || '',
                    data_pedido: cells[6]?.textContent?.trim() || '',
                    previsao_faturamento: cells[7]?.textContent?.trim() || '',
                    status: cells[8]?.textContent?.trim() || '',
                    status_entrega: cells[9]?.textContent?.trim() || ''
                };
                pedidos.push(pedido);
            }
        });
        
        return pedidos;
    }
    
    // Função global para ser chamada pelo JavaScript existente
    window.criarCardsMobile = criarCardsMobile;
    
    
    // Inicializar quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        integrarComSistemaExistente();
        
        // Tentar criar cards mobile após um delay inicial
        setTimeout(() => {
            const pedidos = extrairDadosDaTabela();
            if (pedidos.length > 0) {
                criarCardsMobile(pedidos);
            }
        }, 1000);
    });
    </script>
</body>
</html>
