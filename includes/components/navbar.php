<?php
// Verificar se as variáveis necessárias estão definidas
if (!isset($usuario) || !isset($current_page)) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
    header('Location: ' . $basePath);
    exit;
}

// Determinar o caminho correto para a imagem baseado na localização atual
$image_path = base_url('assets/img/LOGO AUTOPEL VETOR-01.png');

// Verificar se o usuário tem permissão para ver notificações
$perfil_usuario = strtolower(trim($usuario['perfil']));
$show_notifications = in_array($perfil_usuario, ['admin', 'vendedor', 'supervisor', 'diretor', 'representante']) && $perfil_usuario !== 'ecommerce';

// Debug: Log das informações
error_log("DEBUG NAVBAR - Usuario: " . ($usuario['nome'] ?? 'N/A'));
error_log("DEBUG NAVBAR - Perfil: " . $perfil_usuario);
error_log("DEBUG NAVBAR - Show notifications: " . ($show_notifications ? 'SIM' : 'NÃO'));

// Obter cor personalizada da navbar do usuário
$navbar_color = isset($usuario['sidebar_color']) ? $usuario['sidebar_color'] : '#1a237e';
?>
<!-- Navbar Superior -->
<nav class="top-navbar" style="background: linear-gradient(135deg, <?php echo $navbar_color; ?> 0%, <?php echo $navbar_color; ?>dd 100%);">
    <div class="navbar-container">
        <!-- Hambúrguer, Home e Logo -->
        <div class="navbar-brand">
            <?php if (!in_array(strtolower(trim($usuario['perfil'])), ['licitação', 'ecommerce'])): ?>
            <button class="navbar-hamburger" id="navbarHamburger" aria-label="Abrir menu">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            <?php endif; ?>
            <?php if (strtolower(trim($usuario['perfil'])) !== 'ecommerce'): ?>
            <?php
                $perfilLower = strtolower(trim($usuario['perfil']));
                if ($perfilLower === 'licitação') {
                    $homeTarget = base_url('contratos');
                } elseif ($perfilLower === 'ecommerce') {
                    $homeTarget = base_url('gestao-ecommerce');
                } else {
                    $homeTarget = base_url('home-comercial');
                }
            ?>
            <a href="<?php echo $homeTarget; ?>" class="navbar-home-btn" title="Ir para Home">
                <i class="fas fa-home"></i>
            </a>
            <?php endif; ?>
            <div class="navbar-logo">
                <img src="<?php echo $image_path; ?>" alt="Logo Autopel" class="logo-img">
            </div>
        </div>


        <!-- Área do usuário e notificações -->
        <div class="navbar-actions">
            
            <?php if (in_array(strtolower(trim($usuario['perfil'])), ['licitação', 'ecommerce'])): ?>
            <!-- Botão de Logout para usuários de licitação e ecommerce -->
            <div class="logout-container">
                <a href="<?php echo base_url('logout'); ?>" class="logout-btn" title="Sair do Sistema" onclick="return confirm('Tem certeza que deseja sair?')">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($show_notifications): ?>
            <!-- Notificações -->
            <div class="notifications-container">
                <button id="notifBell" class="notif-bell" title="Notificações">
                    <i class="fas fa-bell"></i>
                    <span id="notifBadge" class="notif-badge" style="display:none;">0</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Informações do usuário -->
            <div class="user-info-navbar">
                <a href="<?php echo base_url('perfil'); ?>" class="user-avatar-link" title="Configurações do Perfil">
                    <div class="user-avatar-navbar">
                        <?php
                            $fotoPerfil = isset($usuario['foto_perfil']) ? trim((string)$usuario['foto_perfil']) : '';
                            $fotoSrc = '';
                            if ($fotoPerfil !== '') {
                                if (preg_match('~^https?://~i', $fotoPerfil)) {
                                    $fotoSrc = $fotoPerfil; // URL absoluta
                                } elseif (preg_match('~^[a-zA-Z]:\\\\|^\\\\~', $fotoPerfil)) {
                                    // Caminho absoluto Windows -> mapear para URL relativa ao webroot
                                    $docRoot = rtrim(str_replace('/', '\\', $_SERVER['DOCUMENT_ROOT']), '\\');
                                    $fsPathNorm = str_replace('/', '\\', $fotoPerfil);
                                    if (stripos($fsPathNorm, $docRoot) === 0) {
                                        $rel = substr($fsPathNorm, strlen($docRoot));
                                        $rel = str_replace('\\', '/', $rel);
                                        $fotoSrc = ($rel[0] === '/') ? $rel : ('/' . $rel);
                                    }
                                } else {
                                    // Caminho web relativo ou absoluto
                                    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/Site/';
                                    $publicPath = $fotoPerfil[0] === '/' ? $fotoPerfil : (rtrim($basePath, '/') . '/' . ltrim($fotoPerfil, '/'));
                                    $fsPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $publicPath;
                                    if (file_exists($fsPath)) {
                                        $fotoSrc = $publicPath;
                                    }
                                }
                            }
                        ?>
                        <?php if ($fotoSrc !== ''): ?>
                            <img src="<?php echo htmlspecialchars($fotoSrc); ?>" alt="Foto de Perfil" class="user-photo-navbar">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="user-details">
                    <span class="user-name-navbar"><?php echo htmlspecialchars($usuario['nome']); ?></span>
                    <span class="user-role-navbar"><?php echo ucfirst($usuario['perfil']); ?></span>
                </div>
            </div>
        </div>
    </div>
</nav>

<?php if ($show_notifications): ?>
<!-- Dropdown de notificações -->
<div id="notifDropdown" class="notif-dropdown hidden">
    <div class="notif-header">
        <span>Notificações</span>
        <div class="notif-actions">
            <button id="notifMarkAll" class="notif-action-btn" title="Marcar todas como lidas">
                <i class="fas fa-check-double"></i>
            </button>
            <?php if ($perfil_usuario === 'admin'): ?>
            <button id="notifCreateTest" class="notif-action-btn" title="Criar notificação de teste">
                <i class="fas fa-plus"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div id="notifList" class="notif-list">
        <div class="notif-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Carregando...</span>
        </div>
    </div>
    <div class="notif-footer">
        <a href="#" class="notif-view-all" id="viewAllNotifications">Ver todas as notificações</a>
    </div>
</div>

<!-- Modal para ver todas as notificações -->
<div id="allNotificationsModal" class="notifications-modal hidden">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Todas as Notificações</h3>
            <button class="modal-close" id="closeAllNotifications">&times;</button>
        </div>
        <div class="modal-body">
            <div id="allNotificationsList" class="all-notifications-list">
                <div class="loading-notifications">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Carregando notificações...</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="markAllReadModal">Marcar todas como lidas</button>
            <button class="btn btn-primary" id="closeModalBtn">Fechar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* ===== NAVBAR SUPERIOR ===== */
.top-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    height: 55px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
    transition: var(--transition);
}

.navbar-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 100%;
    padding: 0 1.5rem;
    max-width: 100%;
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.navbar-hamburger {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 6px;
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 36px;
    height: 36px;
}

.navbar-hamburger:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.05);
}

.navbar-hamburger .hamburger-line {
    display: block;
    width: 18px;
    height: 2px;
    background: white;
    margin: 2px 0;
    transition: all 0.3s ease;
    border-radius: 1px;
}

.navbar-hamburger.active .hamburger-line:nth-child(1) {
    transform: rotate(45deg) translate(4px, 4px);
}

.navbar-hamburger.active .hamburger-line:nth-child(2) {
    opacity: 0;
}

.navbar-hamburger.active .hamburger-line:nth-child(3) {
    transform: rotate(-45deg) translate(5px, -5px);
}

.navbar-home-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 6px;
    padding: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    color: white;
    text-decoration: none;
    font-size: 1rem;
}

.navbar-home-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.05);
    color: white;
    text-decoration: none;
}

.navbar-logo {
    display: flex;
    align-items: center;
    margin-left: 0.5rem;
}

.logo-img {
    height: 32px;
    width: auto;
    max-width: 120px;
    object-fit: contain;
    filter: brightness(0) invert(1); /* Torna o logo branco */
    transition: all 0.3s ease;
}

.logo-img:hover {
    filter: brightness(0) invert(1) drop-shadow(0 0 8px rgba(255, 255, 255, 0.5));
    transform: scale(1.05);
}

.navbar-actions {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-shrink: 0;
}

/* (removido: estilos do toggle de tema) */

/* ===== LOGOUT ===== */
.logout-container {
    position: relative;
}

.logout-btn {
    background: rgba(220, 53, 69, 0.8);
    border: none;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    z-index: 10;
    pointer-events: auto;
    text-decoration: none;
}

.logout-btn:hover {
    background: rgba(220, 53, 69, 1);
    transform: scale(1.05);
    color: white;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

/* ===== NOTIFICAÇÕES ===== */
.notifications-container {
    position: relative;
}

.notif-bell {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    z-index: 10;
    pointer-events: auto;
}

.notif-bell:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.05);
}

.notif-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger-color);
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.notif-dropdown {
    position: fixed;
    top: 55px;
    right: 20px;
    width: 350px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    border: 1px solid #dee2e6;
    z-index: 9999;
    display: none;
}

.notif-dropdown.show {
    display: block !important;
}

.notif-dropdown.hidden {
    display: none !important;
}

.notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--light-color);
    border-radius: 12px 12px 0 0;
}

.notif-header span {
    font-weight: 600;
    color: var(--dark-color);
    font-size: 1rem;
}

.notif-actions {
    display: flex;
    gap: 0.5rem;
}

.notif-action-btn {
    background: none;
    border: none;
    color: var(--gray);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.notif-action-btn:hover {
    background: var(--primary-color);
    color: white;
}

.notif-list {
    max-height: 400px;
    overflow-y: auto;
}

.notif-item {
    display: flex;
    align-items: flex-start;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.3s ease;
    cursor: pointer;
}

.notif-item:hover {
    background: var(--light-color);
}

.notif-item:last-child {
    border-bottom: none;
}

.notif-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.notif-content {
    flex: 1;
}

.notif-title {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}

.notif-message {
    color: var(--gray);
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 0.5rem;
}

.notif-time {
    color: var(--gray);
    font-size: 0.75rem;
    opacity: 0.8;
}

.notif-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: var(--gray);
    gap: 0.5rem;
}

.notif-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background: var(--light-color);
    border-radius: 0 0 12px 12px;
}

.notif-view-all {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.notif-view-all:hover {
    color: var(--primary-color-light);
    text-decoration: underline;
}

/* ===== INFORMAÇÕES DO USUÁRIO ===== */
.user-info-navbar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
}

.user-avatar-link {
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
}

.user-avatar-link:hover {
    text-decoration: none;
    color: inherit;
}

.user-avatar-navbar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.user-photo-navbar {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.user-avatar-link:hover .user-avatar-navbar {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.05);
}

.user-details {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.user-name-navbar {
    font-weight: 600;
    font-size: 0.9rem;
    line-height: 1.2;
}

.user-role-navbar {
    font-size: 0.75rem;
    opacity: 0.9;
    text-transform: capitalize;
}

/* ===== RESPONSIVIDADE ===== */
@media (max-width: 768px) {
    .navbar-container {
        padding: 0 1rem;
    }
    
    .navbar-hamburger {
        display: none; /* Esconder hambúrguer da navbar em mobile - usar mobile-nav-toggle */
    }
    
    .navbar-brand {
        gap: 0.5rem;
    }
    
    .navbar-home-btn {
        width: 32px;
        height: 32px;
        font-size: 0.9rem;
    }
    
    .logo-img {
        height: 28px;
        max-width: 100px;
    }
    
    .notif-dropdown {
        width: 300px;
        right: 10px;
        top: 55px;
    }
    
    .user-details {
        display: none;
    }
    
    .user-avatar-navbar {
        width: 32px;
        height: 32px;
        font-size: 1.1rem;
    }
    
    .notif-bell {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }
    
    /* (removido: responsividade do toggle de tema) */
    
    .navbar-actions {
        gap: 0.75rem;
    }
    
    .logout-btn {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }
}

@media (max-width: 480px) {
    .navbar-container {
        padding: 0 0.5rem;
    }
    
    .navbar-home-btn {
        width: 30px;
        height: 30px;
        font-size: 0.85rem;
    }
    
    .logo-img {
        height: 24px;
        max-width: 80px;
    }
    
    .notif-dropdown {
        width: 280px;
        right: 5px;
        top: 55px;
    }
    
    .notif-item {
        padding: 0.75rem 1rem;
    }
    
    .notif-icon {
        width: 35px;
        height: 35px;
        margin-right: 0.75rem;
    }
    
    .user-avatar-navbar {
        width: 30px;
        height: 30px;
        font-size: 1rem;
    }
    
    .notif-bell {
        width: 32px;
        height: 32px;
        font-size: 0.85rem;
    }
    
    
    .logout-btn {
        width: 32px;
        height: 32px;
        font-size: 0.85rem;
    }
}

/* ===== UTILITÁRIOS ===== */
.hidden {
    display: none !important;
}

/* CSS específico para notificações */
.notifications-container .notif-dropdown {
    position: fixed !important;
    top: 55px !important;
    right: 20px !important;
    z-index: 9999 !important;
    background: white !important;
    border: 1px solid #ccc !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    width: 350px !important;
}

/* ===== MODAL DE NOTIFICAÇÕES ===== */
.notifications-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notifications-modal.hidden {
    display: none !important;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h3 {
    margin: 0;
    color: #333;
    font-size: 1.25rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s ease;
}

.modal-close:hover {
    background: #f8f9fa;
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.all-notifications-list {
    max-height: 400px;
    overflow-y: auto;
}

.loading-notifications {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: #666;
    gap: 0.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

/* Estilos para itens do modal */
.date-group {
    margin-bottom: 1.5rem;
}

.date-header {
    font-weight: 600;
    color: #333;
    padding: 0.5rem 1rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    text-transform: capitalize;
}

.date-notifications {
    padding: 0;
}

.notif-item-modal {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.3s ease;
}

.notif-item-modal:hover {
    background: #f8f9fa;
}

.notif-icon-modal {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #e3f2fd;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
    color: #1976d2;
}

.notif-content-modal {
    flex: 1;
}

.notif-title-modal {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
}

.notif-message-modal {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 0.5rem;
}

.notif-time-modal {
    color: #999;
    font-size: 0.8rem;
}

.no-notifications,
.error-notifications {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #666;
    text-align: center;
}

.no-notifications i,
.error-notifications i {
    font-size: 2rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Ajustar margem superior do conteúdo principal quando navbar está fixa */
.dashboard-main {
    margin-top: 55px;
    padding-top: 2rem;
}

/* Ajustar sidebar quando navbar está fixa */
.sidebar-modern {
    top: 55px;
    height: calc(100vh - 55px);
}

/* Ajustar layout geral */
.dashboard-container {
    padding-top: 0;
}

.dashboard-layout {
    padding-top: 0;
}

/* Garantir que o body tenha espaçamento adequado */
body {
    padding-top: 0 !important;
}

/* Ajustar seções específicas que podem estar sendo cortadas */
.welcome-section {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

.page-header-modern {
    margin-top: 0 !important;
}

/* Sobrescrever estilos conflitantes do estilo.css */
.dashboard-layout .dashboard-main {
    margin-top: 55px !important;
    padding-top: 2rem !important;
}

.dashboard-layout:not(.sidebar-visible) .dashboard-main {
    margin-top: 55px !important;
    padding-top: 2rem !important;
}

/* Garantir que o conteúdo principal tenha espaçamento adequado */
.dashboard-container .dashboard-main {
    margin-top: 55px !important;
    padding-top: 2rem !important;
}

/* CSS específico para corrigir corte de conteúdo */
.top-navbar + .dashboard-container .dashboard-main {
    margin-top: 55px !important;
    padding-top: 2rem !important;
}

/* Garantir espaçamento para todas as seções principais */
.dashboard-main > *:first-child {
    margin-top: 0 !important;
    padding-top: 0 !important;
}

/* Ajuste específico para seções de boas-vindas */
.dashboard-main .welcome-section:first-child {
    margin-top: 0 !important;
    padding-top: 0 !important;
}
</style>

<script>
// Controlar hambúrguer da navbar
document.addEventListener('DOMContentLoaded', function() {
    const navbarHamburger = document.getElementById('navbarHamburger');
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    
    if (navbarHamburger && mobileNavToggle) {
        navbarHamburger.addEventListener('click', function() {
            // Simular clique no hambúrguer mobile
            mobileNavToggle.click();
            
            // Alternar classe active
            navbarHamburger.classList.toggle('active');
        });
        
        // Sincronizar estado quando mobile nav toggle muda
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const isActive = mobileNavToggle.classList.contains('active');
                    if (isActive) {
                        navbarHamburger.classList.add('active');
                    } else {
                        navbarHamburger.classList.remove('active');
                    }
                }
            });
        });
        
        observer.observe(mobileNavToggle, {
            attributes: true,
            attributeFilter: ['class']
        });
    }
});

<?php if ($show_notifications): ?>
console.log('🔔 NAVBAR: Notificações habilitadas para perfil: <?php echo $perfil_usuario; ?>');

// Configurar endpoint das notificações
window.NOTIF_ENDPOINT = '<?php echo base_url("includes/management/gerenciar_notificacoes.php"); ?>';

console.log('🔔 NAVBAR: Endpoint configurado:', window.NOTIF_ENDPOINT);
<?php else: ?>
console.log('❌ NAVBAR: Notificações desabilitadas para perfil: <?php echo $perfil_usuario; ?>');
<?php endif; ?>

<?php if ($show_notifications): ?>

// JavaScript das notificações diretamente aqui
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔔 DOM carregado, iniciando notificações...');
    
    const bell = document.getElementById('notifBell');
    const dropdown = document.getElementById('notifDropdown');
    
    console.log('🔔 Sino encontrado:', bell);
    console.log('🔔 Dropdown encontrado:', dropdown);
    
    if (!bell || !dropdown) {
        console.log('❌ Elementos não encontrados');
        return;
    }
    
    // Event listener para o sino
    bell.addEventListener('click', function(e) {
        console.log('🔔 SINO CLICADO!');
        e.preventDefault();
        
        // Alternar visibilidade simples
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
            dropdown.classList.add('hidden');
            dropdown.classList.remove('show');
            console.log('🔔 Dropdown escondido');
        } else {
            dropdown.style.display = 'block';
            dropdown.classList.remove('hidden');
            dropdown.classList.add('show');
            console.log('🔔 Dropdown mostrado');
        }
        
        // Carregar notificações reais
        fetchNotifications();
    });
    
    // Event listener para botão de teste (apenas admins)
    const createTestBtn = document.getElementById('notifCreateTest');
    if (createTestBtn) {
        createTestBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔔 Criando notificação de teste...');
            createTestNotification();
        });
    }
    
    // Event listener para marcar todas como lidas
    const markAllBtn = document.getElementById('notifMarkAll');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔔 Marcando todas como lidas...');
            markAllAsRead();
        });
    }
    
    // Event listener para "Ver todas as notificações"
    const viewAllBtn = document.getElementById('viewAllNotifications');
    if (viewAllBtn) {
        viewAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔔 Abrindo modal de todas as notificações...');
            openAllNotificationsModal();
        });
    }
    
    // Event listeners para o modal
    const modal = document.getElementById('allNotificationsModal');
    const closeModalBtn = document.getElementById('closeAllNotifications');
    const closeModalBtn2 = document.getElementById('closeModalBtn');
    const markAllReadModalBtn = document.getElementById('markAllReadModal');
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeAllNotificationsModal);
    }
    
    if (closeModalBtn2) {
        closeModalBtn2.addEventListener('click', closeAllNotificationsModal);
    }
    
    if (markAllReadModalBtn) {
        markAllReadModalBtn.addEventListener('click', function() {
            markAllAsRead();
            closeAllNotificationsModal();
        });
    }
    
    // Fechar modal clicando no overlay
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal || e.target.classList.contains('modal-overlay')) {
                closeAllNotificationsModal();
            }
        });
    }
    
    // Carregar notificações
    fetchNotifications();
});

async function fetchNotifications() {
    try {
        console.log('🔔 Buscando notificações...');
        const response = await fetch(window.NOTIF_ENDPOINT + '?acao=listar', {
            credentials: 'same-origin'
        });
        const data = await response.json();
        console.log('🔔 Dados recebidos:', data);
        
        if (data.success) {
            const badge = document.getElementById('notifBadge');
            const listContainer = document.getElementById('notifList');
            
            if (badge) {
                const count = data.count || 0;
                badge.textContent = count > 9 ? '9+' : String(count);
                badge.style.display = count > 0 ? 'inline-flex' : 'none';
                console.log('🔔 Badge atualizado:', count);
            }
            
            if (listContainer) {
                if (data.items && data.items.length > 0) {
                    listContainer.innerHTML = data.items.map(item => `
                        <div class="notif-item" data-key="${item.key || ''}">
                            <div class="notif-icon"><i class="fas fa-bell"></i></div>
                            <div class="notif-content">
                                <div class="notif-title">${item.title || 'Notificação'}</div>
                                <div class="notif-message">${item.message || ''}</div>
                                <div class="notif-time">${new Date(item.time).toLocaleString('pt-BR')}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    listContainer.innerHTML = '<div class="notif-empty">Sem notificações</div>';
                }
                console.log('🔔 Lista atualizada');
            }
        }
    } catch (error) {
        console.error('❌ Erro ao buscar notificações:', error);
    }
}

async function createTestNotification() {
    try {
        const formData = new FormData();
        formData.append('acao', 'criar_teste');
        formData.append('titulo', 'Teste de Notificação');
        formData.append('mensagem', 'Esta é uma notificação de teste criada em ' + new Date().toLocaleString('pt-BR'));
        
        const response = await fetch(window.NOTIF_ENDPOINT, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        console.log('🔔 Resultado do teste:', result);
        
        if (result.success) {
            // Recarregar notificações
            await fetchNotifications();
            console.log('🔔 Notificação de teste criada com sucesso!');
        } else {
            console.error('❌ Erro ao criar teste:', result.message);
        }
    } catch (error) {
        console.error('❌ Erro ao criar notificação de teste:', error);
    }
}

async function markAllAsRead() {
    try {
        const formData = new FormData();
        formData.append('acao', 'marcar_todas');
        
        // Adicionar todas as chaves das notificações atuais
        const notifItems = document.querySelectorAll('.notif-item[data-key]');
        notifItems.forEach(item => {
            const key = item.getAttribute('data-key');
            if (key) {
                formData.append('keys[]', key);
            }
        });
        
        const response = await fetch(window.NOTIF_ENDPOINT, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        console.log('🔔 Resultado marcar como lidas:', result);
        
        if (result.success) {
            // Recarregar notificações
            await fetchNotifications();
            console.log('🔔 Todas as notificações marcadas como lidas!');
        }
    } catch (error) {
        console.error('❌ Erro ao marcar como lidas:', error);
    }
}

function openAllNotificationsModal() {
    const modal = document.getElementById('allNotificationsModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('show');
        loadAllNotifications();
    }
}

function closeAllNotificationsModal() {
    const modal = document.getElementById('allNotificationsModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('show');
    }
}

async function loadAllNotifications() {
    const listContainer = document.getElementById('allNotificationsList');
    if (!listContainer) {
        console.error('❌ Modal: Container allNotificationsList não encontrado');
        return;
    }
    
    console.log('🔔 Modal: Iniciando carregamento de notificações...');
    console.log('🔔 Modal: Endpoint:', window.NOTIF_ENDPOINT);
    
    // Mostrar loading
    listContainer.innerHTML = '<div class="loading-notifications"><i class="fas fa-spinner fa-spin"></i><span>Carregando notificações...</span></div>';
    
    // Timeout de 10 segundos
    const timeoutId = setTimeout(() => {
        console.error('❌ Modal: Timeout - requisição demorou mais de 10 segundos');
        listContainer.innerHTML = '<div class="error-notifications"><i class="fas fa-exclamation-triangle"></i><span>Timeout: Servidor não respondeu</span></div>';
    }, 10000);
    
    try {
        const url = window.NOTIF_ENDPOINT + '?acao=listar&dias=7';
        console.log('🔔 Modal: Fazendo requisição para:', url);
        
        // Buscar notificações dos últimos 7 dias
        const response = await fetch(url, {
            credentials: 'same-origin',
            timeout: 8000 // 8 segundos de timeout
        });
        
        clearTimeout(timeoutId); // Limpar timeout se chegou aqui
        
        console.log('🔔 Modal: Resposta recebida:', response.status, response.statusText);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('🔔 Modal: Dados recebidos:', data);
        
        if (data.success && data.items && data.items.length > 0) {
            // Agrupar por data
            const groupedByDate = groupNotificationsByDate(data.items);
            
            let html = '';
            for (const [date, notifications] of Object.entries(groupedByDate)) {
                html += `<div class="date-group">
                    <div class="date-header">${formatDateHeader(date)}</div>
                    <div class="date-notifications">`;
                
                notifications.forEach(item => {
                    html += `<div class="notif-item-modal" data-key="${item.key || ''}">
                        <div class="notif-icon-modal"><i class="fas fa-bell"></i></div>
                        <div class="notif-content-modal">
                            <div class="notif-title-modal">${item.title || 'Notificação'}</div>
                            <div class="notif-message-modal">${item.message || ''}</div>
                            <div class="notif-time-modal">${new Date(item.time).toLocaleTimeString('pt-BR')}</div>
                        </div>
                    </div>`;
                });
                
                html += `</div></div>`;
            }
            
            listContainer.innerHTML = html;
        } else {
            listContainer.innerHTML = '<div class="no-notifications"><i class="fas fa-bell-slash"></i><span>Nenhuma notificação encontrada</span></div>';
        }
    } catch (error) {
        clearTimeout(timeoutId); // Limpar timeout em caso de erro
        console.error('❌ Erro ao carregar todas as notificações:', error);
        console.error('❌ Stack trace:', error.stack);
        listContainer.innerHTML = '<div class="error-notifications"><i class="fas fa-exclamation-triangle"></i><span>Erro ao carregar notificações: ' + error.message + '</span></div>';
    }
}

function groupNotificationsByDate(notifications) {
    const grouped = {};
    
    notifications.forEach(notification => {
        const date = new Date(notification.time).toDateString();
        if (!grouped[date]) {
            grouped[date] = [];
        }
        grouped[date].push(notification);
    });
    
    return grouped;
}

function formatDateHeader(dateString) {
    const date = new Date(dateString);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    if (date.toDateString() === today.toDateString()) {
        return 'Hoje';
    } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Ontem';
    } else {
        return date.toLocaleDateString('pt-BR', { 
            weekday: 'long', 
            day: 'numeric', 
            month: 'long' 
        });
    }
}
<?php endif; ?>

// (removido: carregamento do script de modo escuro)

// Carregar script de navegação mobile (caminho absoluto)
if (!document.querySelector('script[src*="assets/js/mobile-nav.js"]')) {
    const mobileNavScript = document.createElement('script');
    mobileNavScript.src = '<?php echo base_url("assets/js/mobile-nav.js"); ?>?v=' + Date.now();
    mobileNavScript.async = true;
    document.head.appendChild(mobileNavScript);
    console.log('📱 Script mobile-nav.js carregado de:', mobileNavScript.src);
}
</script>

