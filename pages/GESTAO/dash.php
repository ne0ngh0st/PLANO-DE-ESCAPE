<?php
require_once __DIR__ . '/../../includes/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once __DIR__ . '/../../includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

// Verifica se o usuário tem acesso aos dashboards
// Vendedores, representantes e usuários de licitação não podem acessar o dashboard
if ($usuario['perfil'] === 'representante' || $usuario['perfil'] === 'vendedor' || $usuario['perfil'] === 'licitação') {
    header('Location: ' . ($usuario['perfil'] === 'licitação' ? 'pages/contratos.php' : 'home.php'));
    exit;
}

// Verifica se o usuário deve ter acesso limitado (apenas para casos especiais)
$acesso_limitado = false;
if (strtoupper($usuario['nome']) === 'AMERICO') {
    $acesso_limitado = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard BI - Autopel</title>
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
    <script src="<?php echo base_url('assets/js/dark-mode.js'); ?>"></script>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/dash.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/dash-mobile.css'); ?>?v=<?php echo time(); ?>">
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include __DIR__ . '/../../includes/components/navbar.php'; ?>
        <?php include __DIR__ . '/../../includes/components/nav_hamburguer.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                <section class="welcome-section dash-welcome">
                    <h2 class="dash-title">Bem-vindo ao seu Painel de Business Intelligence</h2>
                    <p class="dash-subtitle">Acesse os dashboards abaixo para visualizar os dados estratégicos da empresa</p>
                    <?php if ($acesso_limitado): ?>
                    <div class="acesso-limitado-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Acesso Limitado:</strong> Você tem acesso apenas ao Dashboard Comercial.
                    </div>
                    <?php endif; ?>
                </section>
<?php
// Defina as URLs das dashboards do Power BI e aplique os filtros conforme o perfil do usuário
$iframe_url1 = "https://app.powerbi.com/reportEmbed?reportId=9923c725-d737-47d6-8fd1-70e78bd82b8e&autoAuth=true&ctid=455c3f1c-0a92-4f6d-8943-26ee08301ad0";
$iframe_url2 = "https://app.powerbi.com/view?r=eyJrIjoiNGFiNGJhNDEtYjY4OC00YWE5LWFhODgtOGEwNWVkYmM1ZGEzIiwidCI6IjQ1NWMzZjFjLTBhOTItNGY2ZC04OTQzLTI2ZWUwODMwMWFkMCJ9";
$perfil = $usuario['perfil']; // 'diretor', 'supervisor', 'vendedor'

// Aplica filtros baseados no perfil do usuário
if ($perfil === 'supervisor' && !empty($usuario['cod_vendedor'])) {
    $iframe_url1 .= "&filter=DIM_SUPERVISAO/COD_SUPERVISAO eq " . urlencode($usuario['cod_vendedor']);
    $iframe_url2 .= "&filter=DIM_SUPERVISAO/COD_SUPERVISAO eq " . urlencode($usuario['cod_vendedor']);
} elseif ($perfil === 'vendedor' && !empty($usuario['cod_vendedor'])) {
    $iframe_url1 .= "&filter=DIM_VENDEDOR/COD_VENDEDOR eq " . urlencode($usuario['cod_vendedor']);
    $iframe_url2 .= "&filter=DIM_VENDEDOR/COD_VENDEDOR eq " . urlencode($usuario['cod_vendedor']);
}
?>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="dashboard-header">
                            <h3 class="dashboard-title">Dashboard Comercial</h3>
                            <button class="fullscreen-btn" onclick="openFullscreen('dashboard1', '<?php echo $iframe_url1; ?>')" title="Abrir em tela cheia">
                                <i class="fas fa-expand"></i>
                            </button>
                        </div>
                        <iframe id="dashboard1" class="dashboard-iframe" src="<?php echo $iframe_url1; ?>" frameborder="0" allowFullScreen="true"></iframe>
                        <!-- Informações mobile -->
                        <div class="dashboard-mobile-info">
                            <h4><i class="fas fa-chart-bar"></i> Dashboard Comercial</h4>
                            <p>Visualize métricas de vendas, performance comercial e análises estratégicas em tempo real.</p>
                            <button class="btn" onclick="openFullscreen('dashboard1', '<?php echo $iframe_url1; ?>')">
                                <i class="fas fa-external-link-alt"></i> Abrir Dashboard
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!$acesso_limitado): ?>
                    <div class="dashboard-card">
                        <div class="dashboard-header">
                            <h3 class="dashboard-title">Dashboard Mapas</h3>
                            <button class="fullscreen-btn" onclick="openFullscreen('dashboard2', '<?php echo $iframe_url2; ?>')" title="Abrir em tela cheia">
                                <i class="fas fa-expand"></i>
                            </button>
                        </div>
                        <iframe id="dashboard2" class="dashboard-iframe" src="<?php echo $iframe_url2; ?>" frameborder="0" allowFullScreen="true"></iframe>
                        <!-- Informações mobile -->
                        <div class="dashboard-mobile-info">
                            <h4><i class="fas fa-map-marked-alt"></i> Dashboard Mapas</h4>
                            <p>Explore visualizações geográficas, distribuição territorial e análises regionais de vendas.</p>
                            <button class="btn" onclick="openFullscreen('dashboard2', '<?php echo $iframe_url2; ?>')">
                                <i class="fas fa-external-link-alt"></i> Abrir Dashboard
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Modal Fullscreen -->
                <div id="fullscreenModal" class="fullscreen-modal">
                    <div class="fullscreen-header">
                        <h3 id="fullscreenTitle"></h3>
                        <button class="close-fullscreen" onclick="closeFullscreen()" title="Fechar tela cheia">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="fullscreen-content">
                        <iframe id="fullscreenIframe" src="" frameborder="0" allowFullScreen="true"></iframe>
                    </div>
                </div>
            </main>
            <footer class="dashboard-footer">
                <p>© 2025 Autopel - Todos os direitos reservados</p>
                <p class="system-info">Sistema BI v1.0</p>
            </footer>
        </div>
    </div>
    
    <script>
        function openFullscreen(dashboardId, url) {
            const modal = document.getElementById('fullscreenModal');
            const iframe = document.getElementById('fullscreenIframe');
            const title = document.getElementById('fullscreenTitle');
            
            // Define o título baseado no dashboard
            if (dashboardId === 'dashboard1') {
                title.textContent = 'Dashboard Comercial - Tela Cheia';
            } else if (dashboardId === 'dashboard2') {
                title.textContent = 'Dashboard Mapas - Tela Cheia';
            }
            
            // Define a URL do iframe
            iframe.src = url;
            
            // Mostra o modal com animação
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
            
            // Previne scroll do body
            document.body.style.overflow = 'hidden';
        }
        
        function closeFullscreen() {
            const modal = document.getElementById('fullscreenModal');
            const iframe = document.getElementById('fullscreenIframe');
            
            // Remove animação
            modal.classList.remove('show');
            
            // Esconde o modal após a animação
            setTimeout(() => {
                modal.style.display = 'none';
                iframe.src = ''; // Limpa o iframe para economizar recursos
            }, 300);
            
            // Restaura scroll do body
            document.body.style.overflow = 'auto';
        }
        
        // Fecha o modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('fullscreenModal');
                if (modal.style.display === 'flex') {
                    closeFullscreen();
                }
            }
        });
        
        // Fecha o modal clicando fora do iframe (apenas no header)
        document.getElementById('fullscreenModal').addEventListener('click', function(e) {
            if (e.target === this || e.target.classList.contains('fullscreen-header')) {
                closeFullscreen();
            }
        });
        
        // Controle de exibição mobile
        function toggleMobileElements() {
            const isMobile = window.innerWidth <= 768;
            const iframes = document.querySelectorAll('.dashboard-iframe');
            const mobileInfos = document.querySelectorAll('.dashboard-mobile-info');
            
            iframes.forEach(iframe => {
                if (isMobile) {
                    iframe.style.display = 'none';
                } else {
                    iframe.style.display = 'block';
                }
            });
            
            mobileInfos.forEach(info => {
                if (isMobile) {
                    info.style.display = 'block';
                } else {
                    info.style.display = 'none';
                }
            });
        }
        
        // Executar na carga inicial
        toggleMobileElements();
        
        // Executar no redimensionamento
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(toggleMobileElements, 250);
        });
        
        // Melhorar experiência mobile
        if (window.innerWidth <= 768) {
            // Adicionar indicador de carregamento para iframes
            const iframes = document.querySelectorAll('.dashboard-iframe');
            iframes.forEach(iframe => {
                iframe.addEventListener('load', function() {
                    this.style.opacity = '1';
                });
                
                iframe.addEventListener('error', function() {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'dashboard-loading';
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao carregar dashboard';
                    this.parentNode.insertBefore(errorDiv, this);
                });
            });
        }
    </script>
    
</body>
</html>