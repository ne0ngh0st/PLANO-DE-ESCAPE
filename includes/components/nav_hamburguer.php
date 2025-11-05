<?php
// Verificar se as variáveis necessárias estão definidas
if (!isset($usuario) || !isset($current_page)) {
    return;
}

// Normalizar perfil do usuário
$perfil_usuario = strtolower(trim($usuario['perfil']));

// Cor personalizada da navegação lateral (usa a mesma preferência do usuário da sidebar)
$sidebar_color = isset($usuario['sidebar_color']) && $usuario['sidebar_color']
    ? trim($usuario['sidebar_color'])
    : '#1a237e';

// Caminho do logo (mesmo usado na navbar principal)
$image_path = '/Site/assets/img/LOGO AUTOPEL VETOR-01.png';

// Resolver foto de perfil com caminhos absolutos/relativos e URLs externas
$user_photo_url = '';
if (!empty($usuario['foto_perfil'])) {
    $foto = trim($usuario['foto_perfil']);
    if (preg_match('/^https?:\/\//i', $foto)) {
        $user_photo_url = $foto; // URL externa
    } else {
        // Tentar resolver caminho no filesystem a partir do DOCUMENT_ROOT
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        // Se já vier iniciado com /, considerar relativo ao root web
        if (strpos($foto, '/Site/') === 0) {
            $fsPath = $docRoot . $foto;
            if (file_exists($fsPath)) {
                $user_photo_url = $foto;
            }
        } else {
            // Tentar em /Site/ (upload comum do projeto)
            $candidateFs = $docRoot . '/Site/' . ltrim($foto, '/');
            if (file_exists($candidateFs)) {
                $user_photo_url = '/Site/' . ltrim($foto, '/');
            } elseif (file_exists($docRoot . '/' . ltrim($foto, '/'))) {
                // Caso esteja salvo direto no root
                $user_photo_url = '/' . ltrim($foto, '/');
            }
        }
    }
}
?>
<style>
/* Mobile Navigation Styles */
.mobile-nav-toggle {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 10000;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 8px;
    padding: 0.75rem;
    cursor: pointer;
    width: 44px;
    height: 44px;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.mobile-nav-toggle .hamburger-line {
    width: 24px;
    height: 3px;
    background: white;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.mobile-nav-toggle.active .hamburger-line:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
}

.mobile-nav-toggle.active .hamburger-line:nth-child(2) {
    opacity: 0;
}

.mobile-nav-toggle.active .hamburger-line:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -6px);
}

.mobile-nav-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9998;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mobile-nav-overlay.show {
    display: block;
    opacity: 1;
}

.mobile-nav {
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: -100%;
    width: 340px;
    max-width: 92vw;
    height: 100vh;
    background: #ffffff;
    z-index: 9999;
    transition: left 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    box-shadow: 2px 0 24px rgba(0, 0, 0, 0.18);
    border-right: 1px solid #e5e7eb;
}

.mobile-nav.show {
    left: 0;
}

.mobile-nav-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1rem;
    background: linear-gradient(135deg, <?php echo $sidebar_color; ?> 0%, <?php echo $sidebar_color; ?>dd 100%) !important;
    color: #ffffff;
    border-bottom: none;
}

.mobile-nav-logo {
    height: 32px;
    width: auto;
    filter: brightness(0) invert(1);
}

.mobile-nav-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 1.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mobile-nav-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

.mobile-nav-user {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1rem;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
}

.mobile-nav-user .user-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #eef2f7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #111827;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.mobile-nav-user .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mobile-nav-user .user-info {
    display: flex;
    flex-direction: column;
    color: #111827;
}

.mobile-nav-user .user-name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.mobile-nav-user .user-role {
    font-size: 0.875rem;
    color: #6b7280;
    text-transform: capitalize;
}

.mobile-nav-content {
    flex: 1;
    padding: 1rem 0.75rem 1rem 0.75rem;
    background: #ffffff;
}

.mobile-nav-section-title {
    padding: 0 0.25rem 0 0.25rem;
    margin: 0.75rem 0 0.5rem 0.25rem;
    color: #475569; /* mais contraste */
    font-size: 0.74rem;
    font-weight: 800; /* mais grossa */
    text-transform: uppercase;
    letter-spacing: 0.9px;
    position: relative;
}
.mobile-nav-section-title::after {
    content: "";
    display: block;
    height: 1px;
    background: #e5e7eb;
    margin-top: 0.5rem;
}

.mobile-nav-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.75rem 0.75rem;
    margin: 0.25rem 0.25rem;
    color: #0f172a; /* mais escuro */
    text-decoration: none;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    border-radius: 12px;
    font-weight: 600; /* mais grossinho */
}

.mobile-nav-item i {
    font-size: 1.05rem;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, <?php echo $sidebar_color; ?>22 0%, <?php echo $sidebar_color; ?>33 100%);
    color: #111827;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: transform 0.2s ease, background 0.2s ease;
}

.mobile-nav-item:hover {
    background: #f3f4f6;
    border-left-color: <?php echo $sidebar_color; ?>;
    padding-left: 0.95rem;
}

.mobile-nav-item:hover i,
.mobile-nav-item.active i {
    background: linear-gradient(135deg, <?php echo $sidebar_color; ?>33 0%, <?php echo $sidebar_color; ?>55 100%);
    transform: scale(1.06);
}

.mobile-nav-item.active {
    background: #eef2ff;
    border-left-color: <?php echo $sidebar_color; ?>;
    font-weight: 600;
}

.mobile-nav-item.logout {
    color: #ffcccc;
    border-left-color: #ffcccc;
}

.mobile-nav-item.logout:hover {
    background: rgba(255, 0, 0, 0.1);
}

.mobile-nav-footer {
    padding: 1rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

@media (max-width: 768px) {
    .mobile-nav-toggle {
        display: flex;
    }
}

@media (prefers-reduced-motion: reduce) {
    .mobile-nav,
    .mobile-nav-toggle,
    .mobile-nav-item i {
        transition: none !important;
    }
}

/* Responsivo para telas muito pequenas */
@media (max-width: 360px) {
    .mobile-nav {
        width: 100vw;
        max-width: 100vw;
    }
}
</style>

<!-- Mobile Navigation Toggle Button -->
<button id="mobileNavToggle" class="mobile-nav-toggle" aria-label="Abrir menu de navegação" aria-expanded="false">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
</button>

<!-- Mobile Navigation Overlay -->
<div id="mobileNavOverlay" class="mobile-nav-overlay"></div>

<!-- Mobile Navigation Menu -->
<nav id="mobileNav" class="mobile-nav" role="navigation" aria-label="Menu de navegação">
    <div class="mobile-nav-header">
        <img src="<?php echo $image_path; ?>" alt="Autopel" class="mobile-nav-logo">
        <button id="mobileNavClose" class="mobile-nav-close" aria-label="Fechar menu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="mobile-nav-user">
        <div class="user-avatar">
            <?php if (!empty($user_photo_url)): ?>
                <img src="<?php echo htmlspecialchars($user_photo_url); ?>" alt="Foto de Perfil" class="user-photo">
            <?php else: ?>
                <i class="fas fa-user-circle"></i>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($usuario['nome']); ?></span>
            <span class="user-role"><?php echo ucfirst($usuario['perfil']); ?></span>
        </div>
    </div>
    
    <div class="mobile-nav-content">
        <h3 class="mobile-nav-section-title">Comercial</h3>
        <a href="<?php echo base_url('home-comercial'); ?>" class="mobile-nav-item<?php if($current_page == 'home-comercial.php') echo ' active';?>">
            <i class="fas fa-home"></i>
            <span>Início</span>
        </a>
        <?php if (!in_array($perfil_usuario, ['representante', 'vendedor', 'licitação', 'assistente', 'ecommerce'])): ?>
        <a href="<?php echo base_url('dash'); ?>" class="mobile-nav-item<?php if($current_page == 'dash.php') echo ' active';?>">
            <i class="fas fa-chart-pie"></i>
            <span>Dashboards</span>
        </a>
        <?php endif; ?>
        
        <?php if (!in_array($perfil_usuario, ['licitação', 'assistente', 'ecommerce'])): ?>
        <a href="<?php echo base_url('leads'); ?>" class="mobile-nav-item<?php if($current_page == 'leads.php') echo ' active';?>">
            <i class="fas fa-user-friends"></i>
            <span>Leads</span>
        </a>
        <?php endif; ?>
        <?php if ($perfil_usuario !== 'licitação' && $perfil_usuario !== 'ecommerce'): ?>
        <a href="<?php echo base_url((in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) ? 'carteira-admin' : 'carteira-teste'); ?>" class="mobile-nav-item<?php if($current_page == 'carteira_teste_otimizada.php' || $current_page == 'carteira_admin_diretor_ultra.php') echo ' active';?>">
            <i class="fas fa-wallet"></i>
            <span>Carteira</span>
        </a>
        <?php endif; ?>
        <?php if (!in_array($perfil_usuario, ['licitação', 'assistente', 'ecommerce'])): ?>
        <a href="<?php echo base_url('cadastro'); ?>" class="mobile-nav-item<?php if($current_page == 'cadastro.php') echo ' active';?>">
            <i class="fas fa-user-plus"></i>
            <span>Cadastro</span>
        </a>
        <?php endif; ?>
        <?php if (in_array($perfil_usuario, ['vendedor', 'representante', 'supervisor', 'diretor', 'admin', 'assistente']) && $perfil_usuario !== 'ecommerce'): ?>
        <a href="<?php echo base_url('pedidos-abertos'); ?>" class="mobile-nav-item<?php if($current_page == 'pedidos_abertos.php') echo ' active';?>">
            <i class="fas fa-box-open"></i>
            <span>Pedidos em Aberto</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($perfil_usuario, ['admin', 'diretor', 'ecommerce'])): ?>
        <h3 class="mobile-nav-section-title">E-commerce</h3>
        <a href="<?php echo base_url('gestao-ecommerce'); ?>" class="mobile-nav-item<?php if($current_page == 'gestao-ecommerce' || $current_page == 'gestao-ecommerce.php') echo ' active';?>">
            <i class="fas fa-store"></i>
            <span>Gestão E-commerce</span>
        </a>
        <?php endif; ?>

		<?php 
			$__show_gestao = false;
			$doc_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
			$base_path_doc = defined('SITE_BASE_PATH') ? trim(SITE_BASE_PATH, '/') : 'Site';
			if (in_array($perfil_usuario, ['diretor','supervisor','admin'])) { $__show_gestao = true; }
			if (in_array($perfil_usuario, ['diretor','admin']) && file_exists($doc_root . '/' . $base_path_doc . '/pages/GESTAO/dashboard_volatilidade.php')) { $__show_gestao = true; }
			if (in_array($perfil_usuario, ['admin','diretor','licitação'])) { $__show_gestao = true; }
		?>
		<?php if ($__show_gestao): ?>
		<h3 class="mobile-nav-section-title">Gestão</h3>
		<?php if (in_array($perfil_usuario, ['diretor', 'supervisor', 'admin'])): ?>
        <a href="<?php echo base_url('admin/gestao-unificado'); ?>" class="mobile-nav-item<?php if($current_page == 'admin_gestao_unificado.php') echo ' active';?>">
            <i class="fas fa-cogs"></i>
            <span>Gestão Administrativa</span>
        </a>
        <?php endif; ?>
        <?php if (in_array($perfil_usuario, ['diretor', 'admin']) && file_exists($doc_root . '/' . $base_path_doc . '/pages/GESTAO/dashboard_volatilidade.php')): ?>
        <a href="<?php echo base_url('dashboard-volatilidade'); ?>" class="mobile-nav-item<?php if($current_page == 'dashboard_volatilidade.php') echo ' active';?>">
            <i class="fas fa-wave-square"></i>
            <span>Volatilidade</span>
        </a>
        <?php endif; ?>
		<?php if (in_array($perfil_usuario, ['admin', 'diretor', 'licitação'])): ?>
        <a href="<?php echo base_url('contratos'); ?>" class="mobile-nav-item<?php if($current_page == 'contratos.php') echo ' active';?>">
            <i class="fas fa-gavel"></i>
            <span>Contratos & Licitações</span>
        </a>
        <?php endif; ?>
		<?php endif; ?>
        <h3 class="mobile-nav-section-title">Ferramentas</h3>
        <?php if (in_array($perfil_usuario, ['vendedor', 'representante', 'supervisor', 'diretor', 'admin', 'assistente']) && $perfil_usuario !== 'ecommerce'): ?>
        <a href="<?php echo base_url('orcamentos'); ?>" class="mobile-nav-item<?php if($current_page == 'orcamentos.php') echo ' active';?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Orçamentos</span>
        </a>
        <?php endif; ?>
        <?php if (in_array($perfil_usuario, ['diretor', 'supervisor', 'admin'])): ?>
        <a href="<?php echo base_url('register'); ?>" class="mobile-nav-item<?php if($current_page == 'register.php') echo ' active';?>">
            <i class="fas fa-users-cog"></i>
            <span>Gestão de Usuários</span>
        </a>
        <?php endif; ?>

        <h3 class="mobile-nav-section-title">Conta</h3>
        <a href="<?php echo base_url('perfil'); ?>" class="mobile-nav-item<?php if($current_page == 'perfil_novo.php') echo ' active';?>">
            <i class="fas fa-user"></i>
            <span>Perfil</span>
        </a>
        <a href="<?php echo base_url('logout'); ?>" class="mobile-nav-item logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </div>
</nav>

