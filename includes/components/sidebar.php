<?php
// Verificar se as variáveis necessárias estão definidas
if (!isset($usuario) || !isset($current_page)) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
    header('Location: ' . $basePath);
    exit;
}

// Determinar o caminho correto para a imagem baseado na localização atual
$image_path = file_exists('../assets/img/LOGO AUTOPEL VETOR-01.png') 
    ? '../assets/img/LOGO AUTOPEL VETOR-01.png' 
    : 'assets/img/LOGO AUTOPEL VETOR-01.png';

// Normalizar perfil do usuário - SEMPRE usar esta variável
$perfil_usuario = strtolower(trim($usuario['perfil']));
$is_admin = ($perfil_usuario === 'admin');
$show_notif = $is_admin; // Apenas admins
$admin_class = $is_admin ? ' admin-sidebar' : '';

// Obter cor personalizada da sidebar do usuário
$sidebar_color = isset($usuario['sidebar_color']) ? $usuario['sidebar_color'] : '#1a237e';
?>
<style>
/* Aplicar cor personalizada da sidebar - CSS com máxima especificidade */
aside.sidebar-modern#sidebar,
aside.sidebar-modern.admin-sidebar#sidebar {
    background: linear-gradient(135deg, <?php echo $sidebar_color; ?> 0%, <?php echo $sidebar_color; ?>dd 100%) !important;
}

aside.sidebar-modern#sidebar .nav-item-modern.active,
aside.sidebar-modern.admin-sidebar#sidebar .nav-item-modern.active {
    background: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
}

aside.sidebar-modern#sidebar .nav-item-modern:hover,
aside.sidebar-modern.admin-sidebar#sidebar .nav-item-modern:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

aside.sidebar-modern#sidebar .action-item-modern.active,
aside.sidebar-modern.admin-sidebar#sidebar .action-item-modern.active {
    background: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
}

aside.sidebar-modern#sidebar .action-item-modern:hover,
aside.sidebar-modern.admin-sidebar#sidebar .action-item-modern:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

aside.sidebar-modern#sidebar .nav-section-title,
aside.sidebar-modern.admin-sidebar#sidebar .nav-section-title {
    color: rgba(255, 255, 255, 0.8) !important;
}

aside.sidebar-modern#sidebar .user-name-modern,
aside.sidebar-modern#sidebar .user-role-modern,
aside.sidebar-modern.admin-sidebar#sidebar .user-name-modern,
aside.sidebar-modern.admin-sidebar#sidebar .user-role-modern {
    color: white !important;
}

/* Sobrescrever estilos específicos do admin */
aside.sidebar-modern.admin-sidebar#sidebar .sidebar-header-modern {
    background: linear-gradient(135deg, <?php echo $sidebar_color; ?> 0%, <?php echo $sidebar_color; ?>dd 100%) !important;
}

aside.sidebar-modern.admin-sidebar#sidebar .nav-item-modern::before {
    background: linear-gradient(90deg, <?php echo $sidebar_color; ?> 0%, <?php echo $sidebar_color; ?>dd 100%) !important;
}

aside.sidebar-modern.admin-sidebar#sidebar .nav-item-modern.active {
    background: linear-gradient(90deg, <?php echo $sidebar_color; ?> 0%, <?php echo $sidebar_color; ?>dd 100%) !important;
    color: white !important;
}

aside.sidebar-modern.admin-sidebar#sidebar .nav-item-modern:hover {
    color: white !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
}

aside.sidebar-modern.admin-sidebar#sidebar .nav-icon {
    background: rgba(255, 255, 255, 0.1) !important;
}

aside.sidebar-modern.admin-sidebar#sidebar .nav-item-modern:hover .nav-icon,
aside.sidebar-modern.admin-sidebar#sidebar .nav-item-modern.active .nav-icon {
    background: rgba(255, 255, 255, 0.2) !important;
}

aside.sidebar-modern.admin-sidebar#sidebar .user-avatar {
    background: linear-gradient(135deg, <?php echo $sidebar_color; ?> 0%, <?php echo $sidebar_color; ?>dd 100%) !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
}

/* Estilos para foto de perfil na sidebar */
aside.sidebar-modern#sidebar .user-avatar,
aside.sidebar-modern.admin-sidebar#sidebar .user-avatar {
    overflow: hidden !important;
    position: relative !important;
}

aside.sidebar-modern#sidebar .user-photo,
aside.sidebar-modern.admin-sidebar#sidebar .user-photo {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    border-radius: 50% !important;
}

aside.sidebar-modern.admin-sidebar#sidebar .action-icon {
    background: rgba(255, 255, 255, 0.1) !important;
}

aside.sidebar-modern.admin-sidebar#sidebar .action-item-modern:hover .action-icon {
    background: rgba(255, 255, 255, 0.2) !important;
}

/* Debug removido - funcionalidade funcionando */
</style>
<aside class="sidebar-modern<?php echo $admin_class; ?>" id="sidebar" data-sidebar-color="<?php echo $sidebar_color; ?>">
    <div class="sidebar-header-modern">
        <div class="logo-container">
            <img src="<?php echo $image_path; ?>" alt="Autopel" class="sidebar-logo-modern" />
        </div>
    </div>
    
    <nav class="sidebar-nav-modern">
        <div class="nav-section">
            <h3 class="nav-section-title">Navegação</h3>
            <a href="<?php echo base_url('home-comercial'); ?>" class="nav-item-modern<?php if($current_page == 'home.php' || $current_page == 'home-comercial.php') echo ' active';?>" title="Início">
                <div class="nav-icon">
                    <i class="fas fa-home"></i>
                </div>
                <span class="nav-text">Início</span>
            </a>
            
            <?php if (!in_array($perfil_usuario, ['representante', 'vendedor', 'licitação', 'assistente'])): ?>
            <a href="<?php echo base_url('dash'); ?>" class="nav-item-modern<?php if($current_page == 'dash.php') echo ' active';?>" title="Dashboards">
                <div class="nav-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <span class="nav-text">Dashboards</span>
            </a>
            <?php endif; ?>
            
            <?php if (!in_array($perfil_usuario, ['licitação', 'assistente'])): ?>
            <a href="<?php echo base_url('leads'); ?>" class="nav-item-modern<?php if($current_page == 'leads.php') echo ' active';?>" title="Leads">
                <div class="nav-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <span class="nav-text">Leads</span>
            </a>
            <?php endif; ?>
            
            <?php if ($perfil_usuario !== 'licitação'): ?>
            <a href="<?php echo (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) ? base_url('admin/carteira-admin-diretor-ultra') : base_url('carteira'); ?>" class="nav-item-modern<?php if($current_page == 'carteira_teste_otimizada.php' || $current_page == 'carteira_admin_diretor_ultra.php') echo ' active';?>" title="Carteira">
                <div class="nav-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <span class="nav-text">Carteira</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($perfil_usuario, ['vendedor', 'representante', 'supervisor', 'diretor', 'admin', 'assistente'])): ?>
            <a href="<?php echo base_url('pedidos-abertos'); ?>" class="nav-item-modern<?php if($current_page == 'pedidos_abertos.php') echo ' active';?>" title="Pedidos em Aberto">
                <div class="nav-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <span class="nav-text">Pedidos em Aberto</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($perfil_usuario, ['vendedor', 'representante', 'supervisor', 'diretor', 'admin', 'assistente'])): ?>
            <a href="<?php echo base_url('orcamentos'); ?>" class="nav-item-modern<?php if($current_page == 'orcamentos.php') echo ' active';?>" title="Orçamentos">
                <div class="nav-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <span class="nav-text">Orçamentos</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($perfil_usuario, ['diretor', 'supervisor', 'admin'])): ?>
            <a href="<?php echo base_url('admin/gestao-unificado'); ?>" class="nav-item-modern<?php if($current_page == 'admin_gestao_unificado.php') echo ' active';?>" title="Gestão Administrativa">
                <div class="nav-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <span class="nav-text">Gestão Administrativa</span>
            </a>
            <?php endif; ?>
            
            <?php if ($perfil_usuario === 'admin'): ?>
            <a href="<?php echo base_url('importar-bases'); ?>" class="nav-item-modern<?php if($current_page == 'importar_bases.php') echo ' active';?>" title="Importar Bases de Dados">
                <div class="nav-icon">
                    <i class="fas fa-database"></i>
                </div>
                <span class="nav-text">Importar Bases</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($perfil_usuario, ['diretor', 'admin'])): ?>
            <a href="<?php echo base_url('gestao-ecommerce'); ?>" class="nav-item-modern<?php if($current_page == 'gestao-ecommerce') echo ' active';?>" title="Gestão E-commerce">
                <div class="nav-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <span class="nav-text">Gestão E-commerce</span>
            </a>
            <?php endif; ?>
            
            <?php if (in_array($perfil_usuario, ['admin', 'diretor', 'licitação'])): ?>
            <a href="<?php echo base_url('contratos'); ?>" class="nav-item-modern<?php if($current_page == 'contratos.php') echo ' active';?>" title="Licitações e Contratos">
                <div class="nav-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                <span class="nav-text">Licitações</span>
            </a>
            <?php endif; ?>
            
            <?php if (!in_array($perfil_usuario, ['licitação', 'assistente'])): ?>
            <a href="<?php echo base_url('cadastro'); ?>" class="nav-item-modern<?php if($current_page == 'cadastro.php') echo ' active';?>" title="Cadastro">
                <div class="nav-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <span class="nav-text">Cadastro</span>
            </a>
            <?php endif; ?>
            

        </div>
    </nav>
    
    <div class="sidebar-footer-modern">
        <div class="user-profile-modern">
            <div class="user-avatar">
                <?php if (isset($usuario['foto_perfil']) && $usuario['foto_perfil'] && file_exists($usuario['foto_perfil'])): ?>
                    <img src="<?php echo htmlspecialchars($usuario['foto_perfil']); ?>" alt="Foto de Perfil" class="user-photo">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <div class="user-info-modern">
                <span class="user-name-modern"><?php echo htmlspecialchars($usuario['nome']); ?></span>
                <span class="user-role-modern"><?php echo ucfirst($usuario['perfil']); ?></span>
            </div>
            <?php if ($show_notif): ?>
            <div class="notif-area">
                <button id="notifBell" class="notif-bell" title="Notificações">
                    <i class="fas fa-bell"></i>
                    <span id="notifBadge" class="notif-badge" style="display:none;">0</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-actions">
            <a href="<?php echo base_url('perfil'); ?>" class="action-item-modern<?php if($current_page == 'perfil_novo.php') echo ' active';?>" title="Meu Perfil">
                <div class="action-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span class="action-text">Meu Perfil</span>
            </a>
            
            <a href="<?php echo base_url('logout'); ?>" class="action-item-modern logout-modern" title="Sair">
                <div class="action-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span class="action-text">Sair</span>
            </a>
        </div>
    </div>
</aside> 


<?php if ($show_notif): ?>
<!-- Dropdown de notificações (apenas admin durante testes) -->
<div id="notifDropdown" class="notif-dropdown hidden">
    <div class="notif-header">
        <span>Notificações</span>
        <div class="notif-actions">
            <?php if ($is_admin): ?>
            <button id="notifCreateTest" class="notif-action">Gerar teste</button>
            <?php endif; ?>
            <button id="notifMarkAll" class="notif-action">Marcar todas lidas</button>
        </div>
    </div>
    <div id="notifList" class="notif-list"></div>
    <div class="notif-footer">Sistema de notificações - Admin</div>
    <div class="notif-caret"></div>
    
    <style>
    .user-profile-modern{display:flex;align-items:center;gap:1rem;}
    .notif-area{margin-left:auto;}
    .notif-bell{position:relative;background:transparent;border:none;color:var(--dark-color);cursor:pointer;font-size:1.1rem;padding:.5rem;border-radius:8px;transition:all 0.3s ease;}
    .notif-bell:hover{background:rgba(0,0,0,.05);transform:scale(1.1);}    
    .notif-badge{position:absolute;top:2px;right:2px;background:#dc3545;color:#fff;border-radius:999px;font-size:.65rem;min-width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;padding:0 4px;}
    .notif-dropdown{position:fixed;bottom:80px;left:280px; /* posicionado acima do card do usuário */ background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);width:360px;max-height:70vh;overflow:auto;z-index:10001;border:1px solid #eee;}
    .notif-dropdown.hidden{display:none;}
    .notif-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid #eee;font-weight:600;}
    .notif-actions{display:flex;gap:.5rem;}
    .notif-action{background:var(--primary-color);color:#fff;border:none;border-radius:6px;padding:.35rem .6rem;font-size:.8rem;cursor:pointer}
    .notif-action:hover{background:var(--secondary-color)}
    .notif-list{display:flex;flex-direction:column}
    .notif-item{display:flex;gap:.75rem;padding:.75rem 1rem;border-bottom:1px solid #f2f2f2;color:inherit;text-decoration:none}
    .notif-item:hover{background:#f9fafb}
    .notif-icon{width:36px;height:36px;border-radius:8px;background:#eef2ff;color:var(--primary-color);display:flex;align-items:center;justify-content:center;flex:0 0 36px}
    .notif-title{font-weight:600;margin-bottom:2px}
    .notif-message{font-size:.9rem;color:#555}
    .notif-time{font-size:.75rem;color:#888;margin-top:2px}
    .notif-empty{padding:1rem;color:#666}
    .notif-footer{padding:.5rem 1rem;font-size:.75rem;color:#888;background:#fafafa;text-align:center}
    .notif-caret{position:absolute;bottom:-8px;left:16px;width:16px;height:16px;background:#fff;border-right:1px solid #eee;border-bottom:1px solid #eee;transform:rotate(45deg)}

    @media (max-width: 768px){
        .notif-dropdown{left:16px;right:16px;width:auto;bottom:60px;}
        .notif-caret{display:none}
    }
    </style>

    <script>
    // Determinar caminhos corretos quando o sidebar é incluído a partir de diferentes níveis
    (function(){
        // Usar caminhos absolutos para consistência em todas as páginas
        window.NOTIF_ENDPOINT = '<?php echo base_url("includes/crud/gerenciar_notificacoes.php"); ?>';
        var scriptPath = '<?php echo base_url("assets/js/notificacoes.js"); ?>';
        var s = document.createElement('script');
        s.src = scriptPath;
        document.head.appendChild(s);
        
    })();
    </script>
</div>
<?php endif; ?>