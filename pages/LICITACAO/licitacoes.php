<?php
require_once __DIR__ . '/../../includes/config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$perfilUsuario = strtolower($usuario['perfil'] ?? '');

// Verificar se o usuário tem permissão para acessar esta página
// Apenas admins, diretores e usuários com perfil "licitação" podem acessar
$perfis_permitidos = ['admin', 'diretor', 'licitação'];
if (!in_array($perfilUsuario, $perfis_permitidos)) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'home');
    exit;
}

$current_page = 'licitacoes.php';

// Processar ações AJAX (após verificação de permissões)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    include dirname(__DIR__, 2) . '/includes/gerenciar_licitacoes.php';
    exit;
}

// Incluir navbar e sidebar
include dirname(__DIR__, 2) . '/includes/components/navbar.php';
include dirname(__DIR__, 2) . '/includes/components/sidebar.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licitações e Contratos - Autopel BI</title>
    
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/licitacoes.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header-modern {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            text-align: center;
            margin-bottom: 0;
        }

        /* Cards de Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #4facfe, #00f2fe);
            background-size: 300% 100%;
            animation: gradientShift 3s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 12px 25px rgba(102, 126, 234, 0.4);
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #000;
            margin: 0;
            text-shadow: none;
        }

        .stat-label {
            color: #7f8c8d;
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filtros */
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .filters-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .search-box {
            position: relative;
            max-width: 500px;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1a237e;
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-select {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            min-width: 150px;
            background: white;
        }

        .filter-select:focus {
            outline: none;
            border-color: #1a237e;
        }

        .loading-row {
            text-align: center;
            color: #666;
            font-style: italic;
        }

        /* Estilos específicos para valores e percentuais */
        .valor-contratado {
            font-weight: 700;
            color: #2e7d32;
            font-size: 1rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .valor-consumido {
            font-weight: 700;
            color: #c62828;
            font-size: 1rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .percentual-consumido {
            font-weight: 800;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .percentual-consumido::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .percentual-consumido:hover::before {
            left: 100%;
        }

        .percentual-baixo {
            background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .percentual-baixo:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .percentual-medio {
            background: linear-gradient(135deg, #ff9800 0%, #ffc107 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
        }

        .percentual-medio:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 152, 0, 0.4);
        }

        .percentual-alto {
            background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(244, 67, 54, 0.3);
        }

        .percentual-alto:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 67, 54, 0.4);
        }

        .gerenciador-info {
            font-weight: 500;
            color: #495057;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .placeholder-card {
                padding: 2rem 1.5rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <div class="dashboard-container">
            <div class="dashboard-main">
                <!-- Header minimalista -->
                <div style="padding: 16px 24px;">
                    <h2 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #1f2937;">Licitações e Contratos</h2>
                </div>

                <div class="dashboard-container">
                    <!-- Cards de Estatísticas (minimalistas) -->
                    <div class="stats-grid" id="statsGrid">
                        <div class="stat-card">
                            <div class="stat-icon-min"><i class="fas fa-list"></i></div>
                            <h3 class="stat-number" id="totalLicitacoes">-</h3>
                            <p class="stat-label">Total de Licitações</p>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-min"><i class="fas fa-check"></i></div>
                            <h3 class="stat-number" id="totalAtivas">-</h3>
                            <p class="stat-label">Contratos Ativos</p>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-min"><i class="fas fa-dollar-sign"></i></div>
                            <h3 class="stat-number" id="valorTotal">-</h3>
                            <p class="stat-label">Valor Total (R$)</p>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon-min"><i class="fas fa-building"></i></div>
                            <h3 class="stat-number" id="totalOrgaos">-</h3>
                            <p class="stat-label">Órgãos Atendidos</p>
                        </div>
                    </div>

                    <!-- Filtros e Busca -->
                    <div class="filters-section">
                        <div class="filters-container">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="buscaInput" placeholder="Buscar por órgão, sigla, CNPJ ou produto...">
                            </div>
                            
                            <div class="filters-row">
                                <select id="filtroOrgao" class="filter-select">
                                    <option value="">Todos os Órgãos</option>
                                </select>
                                
                                <select id="filtroStatus" class="filter-select">
                                    <option value="">Todos os Status</option>
                                </select>
                                
                                <select id="filtroTipo" class="filter-select">
                                    <option value="">Todos os Tipos</option>
                                </select>
                                
                                <button id="limparFiltros" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Limpar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Ações da Tabela -->
                    <div class="table-actions-header" style="margin-bottom: 1rem; display: flex; gap: 1rem; justify-content: flex-end; flex-wrap: wrap;">
                        <button id="expandirTodosBtn" class="btn btn-secondary">
                            <i class="fas fa-expand-arrows-alt"></i>
                            Expandir Todos
                        </button>
                        <button id="retrairTodosBtn" class="btn btn-secondary">
                            <i class="fas fa-compress-arrows-alt"></i>
                            Retrair Todos
                        </button>
                        <button id="exportarBtn" class="btn btn-primary">
                            <i class="fas fa-download"></i>
                            Exportar
                        </button>
                    </div>

                    <!-- Tabela de Licitações -->
                    <div class="licitacoes-container">
                        <div class="table-responsive">
                            <table class="licitacoes-table" id="licitacoesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;"></th>
                                        <th>Órgão</th>
                                        <th>Sigla</th>
                                        <th>CNPJ</th>
                                        <th>Gerenciador</th>
                                        <th>Tipo</th>
                                        <th>Produto</th>
                                        <th>Status</th>
                                        <th>Valor Contratado</th>
                                        <th>Valor Consumido</th>
                                        <th>% Consumido</th>
                                        <th>Dias Restantes</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="licitacoesTableBody">
                                    <tr>
                                        <td colspan="13" class="loading-row">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Carregando licitações...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="pagination-container" id="paginationContainer">
                            <!-- Paginação será inserida aqui via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo base_url('assets/js/estilo.js'); ?>"></script>
    <script src="<?php echo base_url('assets/js/licitacoes.js'); ?>"></script>
</body>
</html>
