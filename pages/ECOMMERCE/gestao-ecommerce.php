<?php
session_start();
require_once '../../includes/config/conexao.php';

// Verificar se é admin, diretor ou ecommerce
if (!isset($_SESSION['usuario']) || !in_array(strtolower($_SESSION['usuario']['perfil']), ['admin', 'diretor', 'ecommerce'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'home');
    exit;
}

// Se é perfil ecommerce tentando acessar qualquer outra página, redirecionar para ecommerce
if (strtolower($_SESSION['usuario']['perfil']) === 'ecommerce') {
    // Esta é a única página que eles podem acessar
}

$usuario = $_SESSION['usuario'];
$current_page = 'gestao-ecommerce';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão E-commerce - Autopel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/gestao-ecommerce.css'); ?>">
</head>
<body class="ecommerce-page">
    <div class="dashboard-layout">
        <?php include dirname(__DIR__, 2) . '/includes/components/navbar.php'; ?>
        <?php include dirname(__DIR__, 2) . '/includes/components/nav_hamburguer.php'; ?>
        
        <div class="dashboard-container">
            <main class="dashboard-main">
        <div class="ecommerce-container">
            <!-- Cabeçalho -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-shopping-cart"></i> Gestão E-commerce</h1>
                    <p class="page-subtitle">Análise de fretes, faturamento por produto e despesas</p>
                </div>
                <div class="header-actions">
                    <button class="btn-refresh" id="btnRefresh" title="Atualizar dados">
                        <i class="fas fa-sync-alt"></i> Atualizar
                    </button>
                    <button class="btn-export" id="btnExport" title="Exportar relatório">
                        <i class="fas fa-file-excel"></i> Exportar
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-section">
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> Data Inicial:</label>
                    <input type="date" id="dataInicial" class="filter-input">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> Data Final:</label>
                    <input type="date" id="dataFinal" class="filter-input">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-box"></i> Produto:</label>
                    <input type="text" id="filtroProdu" class="filter-input" placeholder="Buscar produto...">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-list-ol"></i> Limite:</label>
                    <select id="limite" class="filter-input">
                        <option value="20">20 pedidos (rápido)</option>
                        <option value="50" selected>50 pedidos (padrão)</option>
                        <option value="100">100 pedidos</option>
                        <option value="200">200 pedidos</option>
                        <option value="500">500 pedidos (lento)</option>
                    </select>
                </div>
                <button class="btn-filter" id="btnFiltrar">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <button class="btn-clear" id="btnLimpar">
                    <i class="fas fa-times"></i> Limpar
                </button>
            </div>
            
            <!-- Alerta de carregamento -->
            <div id="alertaCarregamento" style="display: none; background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-spinner fa-spin"></i> 
                <strong>Buscando dados do Bling...</strong> 
                <span id="mensagemProgresso">Isso pode levar alguns segundos dependendo da quantidade de pedidos.</span>
            </div>

            <!-- Cards de Resumo -->
            <div class="summary-cards">
                <div class="summary-card card-blue">
                    <div class="card-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total de Vendas</h3>
                        <p class="card-value" id="totalVendas">R$ 0,00</p>
                        <span class="card-subtitle" id="quantidadeVendas">0 vendas</span>
                    </div>
                </div>

                <div class="summary-card card-green">
                    <div class="card-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div class="card-content">
                        <h3>Valor Produtos</h3>
                        <p class="card-value" id="valorProdutos">R$ 0,00</p>
                        <span class="card-subtitle">Sem frete e despesas</span>
                    </div>
                </div>

                <div class="summary-card card-orange">
                    <div class="card-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Fretes</h3>
                        <p class="card-value" id="totalFretes">R$ 0,00</p>
                        <span class="card-subtitle" id="percentualFrete">0% do total</span>
                    </div>
                </div>

                <div class="summary-card card-red">
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="card-content">
                        <h3>Outras Despesas</h3>
                        <p class="card-value" id="totalDespesas">R$ 0,00</p>
                        <span class="card-subtitle" id="percentualDespesas">0% do total</span>
                    </div>
                </div>
            </div>

            <!-- Análise de Fretes - Seção Horizontal -->
            <div class="section-box fretes-section">
                <div class="section-title">
                    <h2><i class="fas fa-truck-loading"></i> Análise de Fretes</h2>
                </div>
                <div class="fretes-grid">
                    <div class="analysis-card">
                        <h4>Pedidos com Frete</h4>
                        <p class="analysis-value" id="pedidosComFrete">0</p>
                        <span class="analysis-label" id="percentualPedidosComFrete">0% do total</span>
                    </div>
                    <div class="analysis-card">
                        <h4>Pedidos sem Frete</h4>
                        <p class="analysis-value" id="pedidosSemFrete">0</p>
                        <span class="analysis-label" id="percentualPedidosSemFrete">0% do total</span>
                    </div>
                    <div class="analysis-card">
                        <h4>Ticket Médio Frete</h4>
                        <p class="analysis-value" id="ticketMedioFrete">R$ 0,00</p>
                        <span class="analysis-label">Por pedido com frete</span>
                    </div>
                    <div class="analysis-card">
                        <h4>Maior Frete</h4>
                        <p class="analysis-value" id="maiorFrete">R$ 0,00</p>
                        <span class="analysis-label" id="produtoMaiorFrete">-</span>
                    </div>
                </div>
            </div>

            <!-- Seção de Gráficos - 3 Colunas -->
            <div class="charts-section">
                <div class="section-box chart-item">
                    <div class="section-title">
                        <h2><i class="fas fa-chart-line"></i> Faturamento por Período</h2>
                        <button class="btn-expand-chart" onclick="expandirGrafico('faturamento')" title="Expandir gráfico">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartFaturamento"></canvas>
                    </div>
                </div>

                <div class="section-box chart-item">
                    <div class="section-title">
                        <h2><i class="fas fa-chart-bar"></i> Top 10 Produtos</h2>
                        <button class="btn-expand-chart" onclick="expandirGrafico('produtos')" title="Expandir gráfico">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartProdutos"></canvas>
                    </div>
                </div>

                <div class="section-box chart-item">
                    <div class="section-title">
                        <h2><i class="fas fa-chart-pie"></i> Composição de Custos</h2>
                        <button class="btn-expand-chart" onclick="expandirGrafico('custos')" title="Expandir gráfico">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartCustos"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabela de Produtos -->
            <div class="table-section">
                <div class="section-header">
                    <h2><i class="fas fa-table"></i> Detalhamento por Produto</h2>
                    <div class="table-controls">
                        <input type="text" id="searchTable" class="search-input" placeholder="Buscar na tabela...">
                        <select id="ordenarPor" class="select-input">
                            <option value="valor_total_desc">Maior Faturamento</option>
                            <option value="valor_total_asc">Menor Faturamento</option>
                            <option value="quantidade_desc">Mais Vendidos</option>
                            <option value="frete_desc">Maior Frete</option>
                            <option value="descricao_asc">Nome A-Z</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="tabelaProdutos">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Qtd. Vendas</th>
                                <th>Valor Produtos</th>
                                <th>Frete Total</th>
                                <th>Outras Despesas</th>
                                <th>Valor Total</th>
                                <th>% do Faturamento</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaProdutosBody">
                            <tr>
                                <td colspan="8" class="loading-cell">
                                    <i class="fas fa-spinner fa-spin"></i> Carregando dados...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" id="paginacao">
                    <!-- Paginação será inserida via JS -->
                </div>
            </div>

            <!-- Tabela de Vendas Detalhadas -->
            <div class="table-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Vendas Detalhadas</h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="tabelaVendas">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Produto</th>
                                <th>Valor Produto</th>
                                <th>Frete</th>
                                <th>Outras Despesas</th>
                                <th>Valor Total</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaVendasBody">
                            <tr>
                                <td colspan="6" class="loading-cell">
                                    <i class="fas fa-spinner fa-spin"></i> Carregando dados...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" id="paginacaoVendas">
                    <!-- Paginação será inserida via JS -->
                </div>
            </div>

        </div>
            </main>
        </div>
    </div>

    <!-- Modal de Gráfico Expandido -->
    <div id="modalChart" class="modal-chart-overlay">
        <div class="modal-chart-container">
            <div class="modal-chart-header">
                <h2 id="modalChartTitulo"><i class="fas fa-chart-line"></i> Gráfico</h2>
                <button class="modal-chart-close" onclick="fecharModalGrafico()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-chart-body">
                <canvas id="chartExpandido"></canvas>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes do Produto -->
    <div id="modalDetalhes" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2><i class="fas fa-info-circle"></i> Detalhes do Produto</h2>
                <button class="modal-close" onclick="fecharModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="produto-info">
                    <h3 id="modalProdutoNome">-</h3>
                    <div class="produto-stats">
                        <div class="stat-item">
                            <i class="fas fa-shopping-cart"></i>
                            <span id="modalTotalVendas">0</span>
                            <small>Total de Vendas</small>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-dollar-sign"></i>
                            <span id="modalValorTotal">R$ 0,00</span>
                            <small>Valor Total</small>
                        </div>
                    </div>
                </div>
                <div class="vendas-historico">
                    <h4><i class="fas fa-history"></i> Histórico de Vendas</h4>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Valor Produto</th>
                                    <th>Frete</th>
                                    <th>Outras Despesas</th>
                                    <th>Valor Total</th>
                                </tr>
                            </thead>
                            <tbody id="modalVendasBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-fechar" onclick="fecharModal()">
                    <i class="fas fa-times"></i> Fechar
                </button>
                <button class="btn-modal-exportar" onclick="exportarDetalhesProduto()">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="../../assets/js/gestao-ecommerce.js"></script>
</body>
</html>


