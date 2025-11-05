<?php
require_once __DIR__ . '/../../includes/config/config.php';

if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Página Inicial';

// Redirecionar usuários com perfil de licitação para a página de contratos
$perfilUsuario = strtolower($usuario['perfil'] ?? '');
if ($perfilUsuario === 'licitação') {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'contratos');
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce (apenas podem acessar gestão e-commerce)
if ($perfilUsuario === 'ecommerce') {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'gestao-ecommerce');
    exit;
}

$supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
$vendedor_selecionado = $_GET['visao_vendedor'] ?? '';


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
    <link rel="stylesheet" href="<?php echo base_url('assets/css/home.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/gestao-ligacoes.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/bobinito-reminder.css'); ?>?v=<?php echo time(); ?>">
            <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include dirname(__DIR__, 2) . '/includes/components/navbar.php'; ?>
        
        <div class="dashboard-container">
            <main class="dashboard-main">
                <!-- Seção de Status do Sistema -->
                <section class="system-status-section">
                    <?php
                    // Buscar data da última atualização das bases
                    $ultima_atualizacao = null;
                    $status_sistema = 'desconhecido';
                    
                    if (isset($pdo)) {
                        try {
                            // Verificar apenas a tabela FATURAMENTO (que é sobrescrita diariamente)
                            $sql_check = "SHOW TABLES LIKE 'FATURAMENTO'";
                            $stmt_check = $pdo->prepare($sql_check);
                            $stmt_check->execute();
                            
                            if ($stmt_check->rowCount() > 0) {
                                // Buscar informações da tabela FATURAMENTO
                                $sql_info = "SELECT UPDATE_TIME FROM information_schema.TABLES 
                                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FATURAMENTO'";
                                $stmt_info = $pdo->prepare($sql_info);
                                $stmt_info->execute();
                                $result = $stmt_info->fetch(PDO::FETCH_ASSOC);
                                
                                if ($result && $result['UPDATE_TIME']) {
                                    $ultima_atualizacao = strtotime($result['UPDATE_TIME']);
                                    $agora = time();
                                    $diferenca_horas = ($agora - $ultima_atualizacao) / 3600;
                                    
                                    if ($diferenca_horas < 24) {
                                        $status_sistema = 'atualizado';
                                    } elseif ($diferenca_horas < 48) {
                                        $status_sistema = 'atencao';
                                    } else {
                                        $status_sistema = 'desatualizado';
                                    }
                                }
                            }
                            
                        } catch (PDOException $e) {
                            error_log("Erro ao verificar status do sistema: " . $e->getMessage());
                        }
                    }
                    ?>
                    
                    <div class="system-status-card">
                        <div class="system-status-header">
                            <div>
                                <h3><i class="fas fa-database"></i> Status do Sistema</h3>
                                <p class="system-status-subtitle">Dados sempre referentes ao dia anterior (D-1)</p>
                            </div>
                            <div class="system-status-indicator status-<?php echo $status_sistema; ?>">
                                <i class="fas fa-circle"></i>
                                <?php 
                                switch($status_sistema) {
                                    case 'atualizado':
                                        echo 'Atualizado';
                                        break;
                                    case 'atencao':
                                        echo 'Atenção';
                                        break;
                                    case 'desatualizado':
                                        echo 'Desatualizado';
                                        break;
                                    default:
                                        echo 'Verificando...';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="system-status-info">
                            <div class="system-status-item">
                                <i class="fas fa-clock"></i>
                                <span class="label">Última Atualização:</span>
                                <span class="value">
                                    <?php 
                                    if ($ultima_atualizacao) {
                                        echo date('d/m/Y H:i', $ultima_atualizacao);
                                    } else {
                                        echo 'Não disponível';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <?php if ($ultima_atualizacao): ?>
                            <div class="system-status-item">
                                <i class="fas fa-history"></i>
                                <span class="label">Tempo desde atualização:</span>
                                <span class="value">
                                    <?php 
                                    $agora = time();
                                    $diferenca = $agora - $ultima_atualizacao;
                                    $horas = floor($diferenca / 3600);
                                    $minutos = floor(($diferenca % 3600) / 60);
                                    
                                    if ($horas > 0) {
                                        echo $horas . 'h ' . $minutos . 'min';
                                    } else {
                                        echo $minutos . 'min';
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                        
                        <?php if ($status_sistema === 'desatualizado'): ?>
                        <div class="system-status-alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Os dados podem estar desatualizados. Lembre-se: os dados são sempre D-1 (dia anterior). Entre em contato com o administrador se necessário.</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Seletor de Visão para Diretores, Supervisores e Admins -->
                <?php if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin'])): ?>
                <div class="visao-selector-container" style="margin-bottom: 2rem; padding: 1rem; background: var(--white); border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                         <div class="visao-selector" style="display: flex; align-items: center; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                                                 <?php if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])): ?>
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
                            
                            $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
                            
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
                                 
                                 $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
                                 
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

                <!-- Estatísticas de Ligações (apenas para vendedores e representantes, mas NÃO para assistentes) -->
                <?php if (in_array(strtolower(trim($usuario['perfil'])), ['vendedor', 'representante']) && strtolower(trim($usuario['perfil'])) !== 'assistente'): ?>
                <?php include dirname(__DIR__, 2) . '/includes/reports/estatisticas_ligacoes_gerais.php'; ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo number_format($estatisticas_gerais['total_ligacoes']); ?></h3>
                        <p><i class="fas fa-phone"></i> Total de Ligações</p>
                    </div>
                    <div class="stat-card finalizadas">
                        <h3><?php echo number_format($estatisticas_gerais['ligacoes_finalizadas']); ?></h3>
                        <p><i class="fas fa-check-circle"></i> Finalizadas</p>
                    </div>
                    <div class="stat-card canceladas">
                        <h3><?php echo number_format($estatisticas_gerais['ligacoes_canceladas']); ?></h3>
                        <p><i class="fas fa-times-circle"></i> Canceladas</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($estatisticas_gerais['media_respostas'], 1); ?></h3>
                        <p><i class="fas fa-chart-line"></i> Média de Respostas</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cards de Resumo do Gráfico de Comparação - VISÍVEL NO MOBILE -->
                <div class="cards-resumo-grafico">
                    <?php
                    // Incluir apenas os cards de resumo do gráfico
                    ob_start();
                    include dirname(__DIR__, 2) . '/includes/reports/grafico_comparacao_faturamento.php';
                    $conteudo_grafico = ob_get_clean();
                    
                    // Extrair o card de destaque (meta de referência) completo com todos os atributos e conteúdo
                    if (preg_match('/<div class="card-referencia-destaque"[^>]*>.*?<\/div>\s*<\/div>/s', $conteudo_grafico, $card_destaque_match)) {
                        echo $card_destaque_match[0];
                    }
                    
                    // Extrair apenas a seção dos cards de resumo usando regex mais robusto
                    if (preg_match('/<div class="resumo-cards"[^>]*>(.*?)<\/div>\s*(?:<!-- Container do Gráfico|<div style="position: relative")/s', $conteudo_grafico, $matches)) {
                        echo '<div class="resumo-cards">';
                        echo $matches[1];
                        echo '</div>';
                    } else {
                        // Fallback: tentar extrair apenas os cards individuais (excluindo o card de destaque)
                        preg_match_all('/<div style="background: linear-gradient(135deg, (?:#ff9800|#4caf50|#[0-9a-f]+|#[0-9a-f]+)[^>]*>.*?<\/div>/s', $conteudo_grafico, $cards);
                        if (!empty($cards[0])) {
                            echo '<div class="resumo-cards">';
                            foreach ($cards[0] as $card) {
                                echo $card;
                            }
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
                
                <?php
                // Banner de Parabéns para Sthefany quando o crescimento estiver alto
                $nome_usuario_lower = strtolower(trim($usuario['nome'] ?? ''));
                if (
                    strpos($nome_usuario_lower, 'sthefany') !== false &&
                    isset($crescimento_percentual) &&
                    floatval($crescimento_percentual) >= 100
                ): 
                    $crescimento_formatado = ($crescimento_percentual >= 0 ? '+' : '') . number_format($crescimento_percentual, 1, ',', '.');
                ?>
                <div id="parabens-sthefany" class="parabens-sthefany" role="alert" aria-live="polite" style="background: rgba(26, 188, 156, 0.15); backdrop-filter: blur(8px); border: 2px solid rgba(46, 204, 113, 0.3); color: #fff; border-radius: 10px; padding: 24px 16px; margin: 10px 0 18px 0; box-shadow: 0 6px 14px rgba(0,0,0,0.08); position: relative; overflow: visible;">
                    <button type="button" aria-label="Fechar aviso" onclick="dismissParabensSthefany()" style="position: absolute; top: 8px; right: 10px; background: rgba(0,0,0,.2); color: rgba(255,255,255,.9); border: 0; font-size: 18px; cursor: pointer; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">&times;</button>
                    <div class="stf-banner-content" style="display: flex; flex-direction: column; align-items: center; gap: 16px; text-align: center;">
                        <div class="stf-text" style="font-weight: 700; font-size: 20px; z-index: 2; line-height: 1.3; color: #2c3e50;">
                            Parabéns, <span class="stf-colorful">Sthefany</span>! 🎉<br>
                            Crescimento de <span class="stf-colorful"><?php echo $crescimento_formatado; ?></span> — É DECOLAGEM! ✨
                        </div>
                        <button type="button" onclick="stfReplayRocket()" class="stf-replay-btn" style="background: linear-gradient(135deg, #1abc9c, #2ecc71); color: white; border: none; padding: 10px 20px; border-radius: 25px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 3px 8px rgba(0,0,0,0.15); transition: all 0.3s ease;">
                            <i class="fas fa-rocket"></i> Ver Decolagem Novamente
                        </button>
                        <div class="stf-rocket-area" aria-hidden="true" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
                            <div class="stf-rocket" id="stf-rocket-animation" style="position: absolute; left: -60px; bottom: 50%; font-size: 32px; transform: rotate(45deg); opacity: 0;">
                                <i class="fas fa-rocket"></i>
                                <span class="stf-flame"></span>
                                <span class="stf-smoke s1"></span>
                                <span class="stf-smoke s2"></span>
                                <span class="stf-smoke s3"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <style>
                /* Trajetória do foguete - UMA VEZ SÓ */
                @keyframes stf-rocket-path {
                    0% { left: -60px; bottom: 50%; opacity: 1; }
                    70% { left: calc(100% + 20px); bottom: 50%; opacity: 1; }
                    100% { left: calc(100% + 20px); bottom: 50%; opacity: 0; }
                }
                /* Chama do foguete */
                @keyframes stf-flame-flicker {
                    0%, 100% { transform: scale(1) translate(-6px, 10px) rotate(20deg); opacity: .95; }
                    50% { transform: scale(1.25) translate(-8px, 12px) rotate(25deg); opacity: .7; }
                }
                /* Fumaça */
                @keyframes stf-smoke-pop {
                    0% { transform: translate(-10px, 6px) scale(.6); opacity: .6; }
                    80% { transform: translate(-30px, 20px) scale(1.1); opacity: .15; }
                    100% { transform: translate(-34px, 24px) scale(1.2); opacity: 0; }
                }
                /* Confete - ATÉ O FIM DA PÁGINA */
                @keyframes stf-confetti-fall {
                    0% { transform: translateY(-10px) rotate(0deg); opacity: 1; }
                    100% { transform: translateY(100vh) rotate(520deg); opacity: 0; }
                }
                .parabens-sthefany .stf-rocket.stf-animating { animation: stf-rocket-path 3.5s cubic-bezier(.22,.61,.36,1) forwards; filter: drop-shadow(0 2px 6px rgba(0,0,0,.2)); }
                .parabens-sthefany .stf-rocket.stf-animating .stf-flame { position: absolute; width: 14px; height: 14px; background: radial-gradient(circle at 30% 30%, #ffd54f 0%, #ff9800 60%, rgba(255,152,0,0) 70%); border-radius: 50%; left: 0; bottom: 0; animation: stf-flame-flicker .25s infinite; }
                .parabens-sthefany .stf-rocket.stf-animating .stf-smoke { position: absolute; width: 12px; height: 12px; background: rgba(255,255,255,.7); border-radius: 50%; left: 4px; bottom: 6px; opacity: .5; animation: stf-smoke-pop 1.2s ease-out infinite; filter: blur(0.5px); }
                .parabens-sthefany .stf-rocket.stf-animating .stf-smoke.s2 { animation-delay: .2s; left: 8px; bottom: 8px; }
                .parabens-sthefany .stf-rocket.stf-animating .stf-smoke.s3 { animation-delay: .4s; left: 12px; bottom: 10px; }
                .parabens-sthefany .stf-confetti { position: fixed; top: 0; width: 8px; height: 12px; opacity: 0.9; border-radius: 2px; animation: stf-confetti-fall 3s linear forwards; z-index: 9999; }
                .parabens-sthefany .stf-colorful { background: linear-gradient(90deg, #ffeb3b, #ff9800, #ff5252, #03a9f4, #4caf50, #e91e63); -webkit-background-clip: text; background-clip: text; color: transparent; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .parabens-sthefany .stf-replay-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.25); }
                .parabens-sthefany .stf-replay-btn:active { transform: translateY(0); }
                @media (max-width: 520px) { .parabens-sthefany .stf-text { font-size: 17px !important; } }
                </style>
                <script>
                function dismissParabensSthefany(){
                    var el = document.getElementById('parabens-sthefany');
                    if(el){ el.style.display = 'none'; try { localStorage.setItem('hide_parabens_sthefany','1'); } catch(e){} }
                }
                function stfLaunchConfetti(){
                    var wrap = document.body;
                    if(!wrap) return;
                    var banner = document.getElementById('parabens-sthefany');
                    if(!banner) return;
                    var bannerRect = banner.getBoundingClientRect();
                    var colors = ['#ffeb3b','#ff9800','#ff5252','#03a9f4','#4caf50','#e91e63','#9c27b0','#00bcd4','#ffc107','#f44336'];
                    for(var i=0;i<350;i++){
                        (function(i){
                            setTimeout(function(){
                                var piece = document.createElement('div');
                                piece.className = 'stf-confetti';
                                piece.style.position = 'fixed';
                                piece.style.left = (bannerRect.left + (Math.random() * bannerRect.width)) + 'px';
                                piece.style.top = bannerRect.top + 'px';
                                piece.style.width = '8px';
                                piece.style.height = '12px';
                                piece.style.background = colors[Math.floor(Math.random()*colors.length)];
                                piece.style.borderRadius = '2px';
                                piece.style.opacity = '0.9';
                                piece.style.zIndex = '9999';
                                piece.style.pointerEvents = 'none';
                                wrap.appendChild(piece);
                                
                                // Iniciar animação
                                requestAnimationFrame(function(){
                                    piece.style.transition = 'transform 3s linear, opacity 3s linear';
                                    piece.style.transform = 'translateY(calc(100vh - ' + bannerRect.top + 'px)) rotate(' + (Math.random()*720-360) + 'deg)';
                                    piece.style.opacity = '0';
                                });
                                
                                setTimeout(function(){ try{ wrap.removeChild(piece); }catch(e){} }, 3200);
                            }, i * 8);
                        })(i);
                    }
                }
                function stfLaunchRocket(){
                    var rocket = document.getElementById('stf-rocket-animation');
                    if(!rocket) return;
                    // Remover classe de animação existente
                    rocket.classList.remove('stf-animating');
                    // Forçar reflow para reiniciar animação
                    void rocket.offsetWidth;
                    // Adicionar classe novamente
                    rocket.classList.add('stf-animating');
                }
                function stfReplayRocket(){
                    stfLaunchRocket();
                    setTimeout(stfLaunchConfetti, 100);
                }
                document.addEventListener('DOMContentLoaded', function(){
                    try {
                        if(localStorage.getItem('hide_parabens_sthefany') === '1'){
                            var el = document.getElementById('parabens-sthefany');
                            if(el){ el.style.display = 'none'; }
                            return;
                        }
                    } catch(e){}
                    // Animação inicial (apenas uma vez)
                    setTimeout(function(){
                        stfLaunchRocket();
                        setTimeout(stfLaunchConfetti, 100);
                    }, 300);
                });
                </script>
                <?php endif; ?>
                
                <!-- Gráfico de Comparação de Faturamento - OCULTO NO MOBILE -->
                <div class="grafico-desktop">
                    <?php include dirname(__DIR__, 2) . '/includes/reports/grafico_comparacao_faturamento.php'; ?>
                </div>

                <!-- Layout Principal - RESPONSIVO -->
                <div class="cards-grid">
                    <!-- Coluna 1: Metas do Mês -->
                    <div class="card-metas">
                        <?php
                        // Verificar se deve mostrar as metas
                        $should_show_metas_cards = true;
                        
                        // Verificar se é representante para usar "objetivo" em vez de "meta"
                        $is_representante = strtolower(trim($usuario['perfil'])) === 'representante';
                        $termo_meta = $is_representante ? 'Objetivo' : 'Meta';
                        $termo_metas = $is_representante ? 'Objetivos' : 'Metas';
                        $termo_atingida = $is_representante ? 'Objetivo Atingido!' : 'Meta Atingida!';
                        $termo_proxima = $is_representante ? 'Próximo do Objetivo' : 'Próximo da Meta';
                        $termo_baixa = $is_representante ? 'Abaixo do Objetivo' : 'Abaixo da Meta';
                        $termo_superada = $is_representante ? 'Objetivo superado em' : 'Meta superada em';
                        
                        // Buscar metas do usuário logado
                        $metafat = 0;
                        $faturamento_mes_atual = 0;
                        $mes_atual = date('m');
                        $ano_atual = date('Y');
                        
                        try {
                            // Buscar metas do usuário baseado no perfil
                            $cod_vendedor_meta = $usuario['cod_vendedor'];
                            
                            // Se for supervisor, buscar metas da equipe
                            if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
                                 $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
                                 
                                 if (!empty($vendedor_selecionado)) {
                                     // Se um vendedor específico foi selecionado, buscar apenas sua meta
                                     $sql_metas = "SELECT 
                                         META_FATURAMENTO as metafat 
                                         FROM USUARIOS 
                                         WHERE COD_VENDEDOR = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')";
                                     $stmt_metas = $pdo->prepare($sql_metas);
                                     $stmt_metas->execute([$vendedor_selecionado]);
                                 } else {
                                     // Buscar metas de todos os vendedores da equipe
                                $sql_metas = "SELECT 
                                    SUM(META_FATURAMENTO) as metafat 
                                    FROM USUARIOS 
                                    WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')";
                                $stmt_metas = $pdo->prepare($sql_metas);
                                     $stmt_metas->execute([$usuario['cod_vendedor']]);
                                 }
                            }
                            // Se for diretor ou admin, buscar metas de todos os vendedores
                            elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
                                $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
                                $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
                                
                                if (!empty($vendedor_selecionado)) {
                                    // Se um vendedor específico foi selecionado, buscar apenas sua meta
                                    $sql_metas = "SELECT 
                                        META_FATURAMENTO as metafat 
                                        FROM USUARIOS 
                                        WHERE COD_VENDEDOR = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')";
                                    $stmt_metas = $pdo->prepare($sql_metas);
                                    $stmt_metas->execute([$vendedor_selecionado]);
                                } elseif (!empty($supervisor_selecionado)) {
                                    // Se um supervisor foi selecionado, buscar metas dos vendedores sob sua supervisão
                                    $sql_metas = "SELECT 
                                        SUM(META_FATURAMENTO) as metafat 
                                        FROM USUARIOS 
                                        WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')";
                                    $stmt_metas = $pdo->prepare($sql_metas);
                                    $stmt_metas->execute([$supervisor_selecionado]);
                                } else {
                                    // Buscar metas de todos os usuários ativos (visão geral da empresa)
                                    $sql_metas = "SELECT 
                                        SUM(META_FATURAMENTO) as metafat 
                                        FROM USUARIOS 
                                        WHERE ATIVO = 1";
                                    $stmt_metas = $pdo->prepare($sql_metas);
                                    $stmt_metas->execute();
                                }
                            }
                            // Para vendedor individual
                            else {
                                $sql_metas = "SELECT META_FATURAMENTO as metafat FROM USUARIOS WHERE COD_VENDEDOR = ?";
                                $stmt_metas = $pdo->prepare($sql_metas);
                                $stmt_metas->execute([$cod_vendedor_meta]);
                            }
                            
                            $metas = $stmt_metas->fetch(PDO::FETCH_ASSOC);
                            
                            if ($metas) {
                                $metafat = floatval($metas['metafat'] ?? 0);
                                
                                // Se o valor da meta é muito grande (>= 10000000), assumir que está em centavos
                                // REMOVIDO: Esta conversão estava causando erro para metas de diretores
                                // if ($metafat >= 10000000) {
                                //     $metafat = $metafat / 100;
                                // }
                            }
                            
                        } catch (PDOException $e) {
                            error_log("Erro ao buscar metas: " . $e->getMessage());
                            $metafat = 0;
                        }
                        
                        if (isset($pdo)) {
                            try {
                                // Preparar condições WHERE baseadas no perfil
                                $where_conditions_fat = [];
                                $params_fat = [];
                                
                                // Se for supervisor, buscar faturamento da equipe
                                if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
                                     $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
                                     
                                     if (!empty($vendedor_selecionado)) {
                                         // Se um vendedor específico foi selecionado, buscar apenas seu faturamento
                                         $where_conditions_fat[] = "COD_VENDEDOR = ?";
                                         $params_fat[] = $vendedor_selecionado;
                                     } else {
                                         // Buscar faturamento de todos os vendedores da equipe
                                    $where_conditions_fat[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = FATURAMENTO.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
                                    $params_fat[] = $usuario['cod_vendedor'];
                                     }
                                }
                                // Se for diretor ou admin, buscar faturamento total da empresa
                                elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
                                    $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
                                    $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
                                    
                                    if (!empty($vendedor_selecionado)) {
                                        // Se um vendedor específico foi selecionado, buscar apenas seu faturamento
                                        $where_conditions_fat[] = "COD_VENDEDOR = ?";
                                        $params_fat[] = $vendedor_selecionado;
                                    } elseif (!empty($supervisor_selecionado)) {
                                        // Se um supervisor foi selecionado, buscar faturamento dos vendedores sob sua supervisão
                                        $where_conditions_fat[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = FATURAMENTO.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
                                        $params_fat[] = $supervisor_selecionado;
                                    } else {
                                        // Visão geral da empresa
                                        $where_conditions_fat[] = "EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = FATURAMENTO.COD_VENDEDOR AND u.ATIVO = 1)";
                                    }
                                }
                                // Para vendedor/representante individual
                                else {
                                    $where_conditions_fat[] = "COD_VENDEDOR = ?";
                                    $params_fat[] = $usuario['cod_vendedor'];
                                }
                                
                                $where_conditions_fat[] = "MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
                                $where_conditions_fat[] = "YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
                                $params_fat[] = $mes_atual;
                                $params_fat[] = $ano_atual;
                                
                                $where_clause_fat = implode(' AND ', $where_conditions_fat);
                                
                                // Buscar faturamento
                                $sql_faturamento_mes = "SELECT SUM(VLR_TOTAL) as total_faturamento FROM FATURAMENTO WHERE $where_clause_fat";
                                $stmt_faturamento = $pdo->prepare($sql_faturamento_mes);
                                $stmt_faturamento->execute($params_fat);
                                $faturamento_result = $stmt_faturamento->fetch(PDO::FETCH_ASSOC);
                                
                                if ($faturamento_result) {
                                    $faturamento_mes_atual = floatval($faturamento_result['total_faturamento'] ?? 0);
                                    
                                    // Se o valor é muito grande (>= 10000000), assumir que está em centavos
                                    // REMOVIDO: Esta conversão estava causando erro para valores de diretores
                                    // if ($faturamento_mes_atual >= 10000000) {
                                    //     $faturamento_mes_atual = $faturamento_mes_atual / 100;
                                    // }
                                }
                                
                            } catch (PDOException $e) {
                                $faturamento_mes_atual = 0;
                            }
                        }
                        
                        // Calcular percentual atingido
                        $percentual_fat = $metafat > 0 ? ($faturamento_mes_atual / $metafat) * 100 : 0;
                        
                        if ($should_show_metas_cards): 
                        ?>
                        <div class="card-metas-header">
                            <h3 class="card-metas-title">
                                <i class="fas fa-chart-pie"></i>
                                <?php echo $termo_metas; ?> do Mês
                            </h3>
                            <div class="card-metas-date">
                                <?php 
                                $meses = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                                         7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
                                echo $meses[date('n')] . '/' . date('Y');
                                ?>
                            </div>
                        </div>
                        
                        <!-- Gauge Circular Compacto -->
                        <div class="card-metas-gauge">
                            <?php 
                            $raio = 80;
                            $circunferencia = 2 * M_PI * $raio;
                            $stroke_dasharray = $circunferencia;
                            $stroke_dashoffset = $circunferencia - (($percentual_fat / 100) * $circunferencia);
                            $cor_gauge = $percentual_fat >= 100 ? '#4caf50' : ($percentual_fat >= 80 ? '#ff9800' : '#f44336');
                            ?>
                            
                            <svg width="200" height="200">
                                <!-- Fundo do gauge -->
                                <circle 
                                    cx="100" cy="100" r="<?php echo $raio; ?>" 
                                    stroke="#e0e0e0" 
                                    stroke-width="10" 
                                    fill="none"
                                />
                                
                                <!-- Gauge de progresso -->
                                <circle 
                                    cx="100" cy="100" r="<?php echo $raio; ?>" 
                                    stroke="<?php echo $cor_gauge; ?>" 
                                    stroke-width="10" 
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-dasharray="<?php echo $stroke_dasharray; ?>"
                                    stroke-dashoffset="<?php echo $stroke_dashoffset; ?>"
                                />
                            </svg>
                            
                            <!-- Conteúdo central do gauge -->
                            <div class="card-metas-gauge-content">
                                <div class="card-metas-percentual">
                                    <?php echo number_format($percentual_fat, 1, ',', '.'); ?>%
                                </div>
                                <div class="card-metas-label">
                                    Atingimento
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações detalhadas compactas -->
                        <div class="card-metas-info">
                            <div class="card-metas-info-item">
                                <div class="card-metas-info-valor faturamento">
                                    R$ <?php echo number_format($faturamento_mes_atual, 0, ',', '.'); ?>
                                </div>
                                <div class="card-metas-info-label">Faturamento</div>
                            </div>
                            
                            <div class="card-metas-info-item">
                                <div class="card-metas-info-valor meta">
                                    R$ <?php echo number_format($metafat, 0, ',', '.'); ?>
                                </div>
                                <div class="card-metas-info-label"><?php echo $termo_meta; ?></div>
                            </div>
                        </div>
                        
                        <!-- Status compacto -->
                        <div class="card-metas-status" data-percentual="<?php echo $percentual_fat; ?>">
                            <?php 
                            $diferenca = $metafat - $faturamento_mes_atual;
                            $status_text = $percentual_fat >= 100 ? $termo_atingida : ($percentual_fat >= 80 ? $termo_proxima : $termo_baixa);
                            ?>
                            <div class="card-metas-status-texto" data-percentual="<?php echo $percentual_fat; ?>">
                                <?php echo $status_text; ?>
                            </div>
                            <?php if ($percentual_fat < 100): ?>
                                <div class="card-metas-status-detalhe">
                                    Faltam R$ <?php echo number_format($diferenca, 0, ',', '.'); ?>
                                </div>
                            <?php else: ?>
                                <div class="card-metas-status-detalhe">
                                    <?php echo $termo_superada; ?> R$ <?php echo number_format($faturamento_mes_atual - $metafat, 0, ',', '.'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Coluna 2: Estatísticas de Ligações -->
                    <div class="card-ligacoes-container">
                        <?php include dirname(__DIR__, 2) . '/includes/reports/estatisticas_ligacoes.php'; ?>
                    </div>
                    
                </div>
                
                <!-- Seção de Sugestões para Melhorias -->
                <div class="suggestions-section">
                    <div class="suggestions-card">
                        <div class="suggestions-header" onclick="toggleSuggestions()">
                            <div class="suggestions-header-content">
                                <h3><i class="fas fa-lightbulb"></i> Sugestões e Melhorias</h3>
                                <p style="margin: 8px 0 0 0; font-size: 12px; opacity: 0.8;">
                                    Compartilhe suas ideias para melhorar o sistema
                                </p>
                            </div>
                            <button class="suggestions-toggle-btn" id="suggestionsToggleBtn">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        
                        <div class="suggestions-content" id="suggestionsContent" style="display: none;">
                            <form id="sugestaoForm" method="POST" action="includes/enviar_sugestao.php">
                                <div class="form-group">
                                    <label for="categoria">Categoria:</label>
                                    <select name="categoria" id="categoria" required>
                                        <option value="">Selecione uma categoria</option>
                                        <option value="interface">Interface e Usabilidade</option>
                                        <option value="funcionalidade">Nova Funcionalidade</option>
                                        <option value="relatorio">Relatórios e Dashboards</option>
                                        <option value="performance">Performance e Velocidade</option>
                                        <option value="outro">Outro</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sugestao">Sua Sugestão:</label>
                                    <textarea name="sugestao" id="sugestao" rows="3" 
                                              placeholder="Descreva sua sugestão de melhoria..." required></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn-sugestao">
                                        <i class="fas fa-paper-plane"></i> Enviar Sugestão
                                    </button>
                                    <button type="button" class="btn-limpar" onclick="limparFormulario()">
                                        <i class="fas fa-eraser"></i> Limpar
                                    </button>
                                </div>
                            </form>
                            
                            <div id="mensagemSugestao" class="mensagem-sugestao" style="display: none;"></div>
                            
                            <!-- Seção de Sugestões Existentes -->
                            <div class="suggestions-display">
                                <h4><i class="fas fa-comments"></i> Sugestões da Equipe</h4>
                                <div id="sugestoesLista" class="sugestoes-lista">
                                    <!-- As sugestões serão carregadas aqui via JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </main>
            <?php include dirname(__DIR__, 2) . '/includes/components/footer.php'; ?>
        </div>
    </div>
    
    <script src="<?php echo base_url('assets/js/home.js'); ?>?v=<?php echo time(); ?>"></script>
    
    
    <script>
    // Função para expandir/colapsar a seção de sugestões
    function toggleSuggestions() {
        const content = document.getElementById('suggestionsContent');
        const toggleBtn = document.getElementById('suggestionsToggleBtn');
        const icon = toggleBtn.querySelector('i');
        
        if (content.style.display === 'none' || content.style.display === '') {
            // Expandir
            content.style.display = 'block';
            icon.className = 'fas fa-chevron-up';
            toggleBtn.setAttribute('aria-expanded', 'true');
            
            // Carregar sugestões quando expandir pela primeira vez
            if (document.getElementById('sugestoesLista').children.length === 0) {
                carregarSugestoes();
            }
        } else {
            // Colapsar
            content.style.display = 'none';
            icon.className = 'fas fa-chevron-down';
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    }
    
    // Funcionalidade para o formulário de sugestões
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('sugestaoForm');
        const mensagemDiv = document.getElementById('mensagemSugestao');
        
        // Não carregar sugestões automaticamente - apenas quando expandir
        // carregarSugestoes();
        
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const submitBtn = form.querySelector('.btn-sugestao');
                const originalText = submitBtn.innerHTML;
                
                // Desabilitar botão e mostrar loading
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                
                fetch(window.baseUrl('includes/enviar_sugestao.php'), {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Mostrar mensagem
                    mensagemDiv.textContent = data.message;
                    mensagemDiv.className = 'mensagem-sugestao ' + (data.success ? 'sucesso' : 'erro');
                    mensagemDiv.style.display = 'block';
                    
                    if (data.success) {
                        // Limpar formulário se sucesso
                        form.reset();
                        
                        // Recarregar sugestões após envio bem-sucedido
                        carregarSugestoes();
                        
                        // Ocultar mensagem após 5 segundos
                        setTimeout(() => {
                            mensagemDiv.style.display = 'none';
                        }, 5000);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    mensagemDiv.textContent = 'Erro ao enviar sugestão. Tente novamente.';
                    mensagemDiv.className = 'mensagem-sugestao erro';
                    mensagemDiv.style.display = 'block';
                })
                .finally(() => {
                    // Reabilitar botão
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
        }
    });
    
    function limparFormulario() {
        const form = document.getElementById('sugestaoForm');
        const mensagemDiv = document.getElementById('mensagemSugestao');
        
        if (form) {
            form.reset();
        }
        
        if (mensagemDiv) {
            mensagemDiv.style.display = 'none';
        }
    }
    
    function carregarSugestoes() {
        const sugestoesLista = document.getElementById('sugestoesLista');
        
        if (!sugestoesLista) return;
        
        fetch(window.baseUrl('includes/api/buscar_sugestoes.php'))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.sugestoes.length === 0) {
                        sugestoesLista.innerHTML = '<div class="sem-sugestoes">Nenhuma sugestão ainda. Seja o primeiro a contribuir!</div>';
                    } else {
                        let html = '';
                        const isAdminLike = ['admin','diretor'].includes('<?php echo strtolower(trim($usuario['perfil'])); ?>');
                        data.sugestoes.forEach(sugestao => {
                            html += `
                                <div class="sugestao-item" data-id="${sugestao.id}">
                                    <div class="sugestao-header-item">
                                        <span class="sugestao-categoria-item">${sugestao.categoria}</span>
                                        <span class="sugestao-status-item status-${(sugestao.status_raw || sugestao.status).toLowerCase()}">${sugestao.status}</span>
                                    </div>
                                    <div class="sugestao-texto">${sugestao.sugestao}</div>
                                    ${sugestao.resposta_admin ? `
                                        <div class="sugestao-resposta-admin">
                                            <div class="resposta-admin-header">
                                                <i class="fas fa-comment"></i>
                                                <span>Comentário</span>
                                            </div>
                                            <div class="resposta-admin-texto">${sugestao.resposta_admin}</div>
                                        </div>
                                    ` : ''}
                                    <div class="sugestao-data">${sugestao.data}</div>
                                    ${isAdminLike ? `
                                        <div class="sugestao-admin-controles">
                                            <select class="sugestao-status-select">
                                                <option value="pendente" ${sugestao.status_raw==='pendente'?'selected':''}>Pendente</option>
                                                <option value="em_analise" ${sugestao.status_raw==='em_analise'?'selected':''}>Em análise</option>
                                                <option value="aprovada" ${sugestao.status_raw==='aprovada'?'selected':''}>Aprovada</option>
                                                <option value="rejeitada" ${sugestao.status_raw==='rejeitada'?'selected':''}>Rejeitada</option>
                                                <option value="implementada" ${sugestao.status_raw==='implementada'?'selected':''}>Implementada</option>
                                            </select>
                                            <input class="sugestao-resposta-admin" type="text" placeholder="Comentário (opcional)" />
                                            <button class="btn-atualizar-status">
                                                <i class="fas fa-save"></i> Salvar
                                            </button>
                                            <button class="btn-excluir-sugestao">
                                                <i class="fas fa-trash"></i> Excluir
                                            </button>
                                            <button class="btn-toggle-visibilidade" title="Ocultar sugestão">
                                                <i class="fas fa-eye-slash"></i> Ocultar
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                        });
                        sugestoesLista.innerHTML = html;

                        if (isAdminLike) {
                            // Bind eventos de atualização de status
                            sugestoesLista.querySelectorAll('.btn-atualizar-status').forEach(btn => {
                                btn.addEventListener('click', (e) => {
                                    const item = e.target.closest('.sugestao-item');
                                    const id = item.getAttribute('data-id');
                                    const select = item.querySelector('.sugestao-status-select');
                                    const input = item.querySelector('.sugestao-resposta-admin');
                                    const status = select ? select.value : '';
                                    const resposta = input ? input.value : '';

                                    const formData = new FormData();
                                    formData.append('id', id);
                                    formData.append('status', status);
                                    formData.append('resposta_admin', resposta);

                                    btn.disabled = true;
                                    btn.textContent = 'Salvando...';

                                    fetch(window.baseUrl('includes/atualizar_status_sugestao.php'), {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(r => r.json())
                                    .then(d => {
                                        if (!d.success) {
                                            alert(d.message || 'Falha ao atualizar.');
                                        } else {
                                            carregarSugestoes();
                                        }
                                    })
                                    .catch(() => alert('Erro ao atualizar.'))
                                    .finally(() => {
                                        btn.disabled = false;
                                        btn.textContent = 'Salvar';
                                    });
                                });
                            });
                            // Bind eventos de exclusão
                            sugestoesLista.querySelectorAll('.btn-excluir-sugestao').forEach(btn => {
                                btn.addEventListener('click', (e) => {
                                    const item = e.target.closest('.sugestao-item');
                                    const id = item.getAttribute('data-id');
                                    if (!confirm('Excluir esta sugestão? Esta ação não poderá ser desfeita.')) return;

                                    const formData = new FormData();
                                    formData.append('id', id);

                                    btn.disabled = true;
                                    btn.textContent = 'Excluindo...';

                                    fetch(window.baseUrl('includes/excluir_sugestao.php'), {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(r => r.json())
                                    .then(d => {
                                        if (!d.success) {
                                            alert(d.message || 'Falha ao excluir.');
                                        } else {
                                            carregarSugestoes();
                                        }
                                    })
                                    .catch(() => alert('Erro ao excluir.'))
                                    .finally(() => {
                                        btn.disabled = false;
                                        btn.textContent = 'Excluir';
                                    });
                                });
                            });
                        }
                        
                        // Bind eventos de visibilidade
                        sugestoesLista.querySelectorAll('.btn-toggle-visibilidade').forEach(btn => {
                            btn.addEventListener('click', (e) => {
                                const item = e.target.closest('.sugestao-item');
                                const id = item.getAttribute('data-id');
                                if (!confirm('Ocultar esta sugestão? Ela não será mais visível para outros usuários.')) return;

                                const formData = new FormData();
                                formData.append('id', id);
                                formData.append('acao', 'ocultar');

                                btn.disabled = true;
                                btn.textContent = '...';

                                fetch(window.baseUrl('includes/toggle_visibilidade_sugestao.php'), {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(r => r.json())
                                .then(d => {
                                    if (!d.success) {
                                        alert(d.message || 'Falha ao ocultar sugestão.');
                                    } else {
                                        carregarSugestoes();
                                    }
                                })
                                .catch(() => alert('Erro ao ocultar sugestão.'))
                                .finally(() => {
                                    btn.disabled = false;
                                    btn.textContent = '👁️';
                                });
                            });
                        });
                    }
                } else {
                    sugestoesLista.innerHTML = '<div class="erro-carregamento">Erro ao carregar sugestões</div>';
                }
            })
            .catch(error => {
                console.error('Erro ao carregar sugestões:', error);
                sugestoesLista.innerHTML = '<div class="erro-carregamento">Erro ao carregar sugestões</div>';
            });
    }
    </script>
    
    <?php 
    // Popup de lembrete do Bobinito
    include dirname(__DIR__, 2) . '/includes/components/bobinito_reminder.php'; 
    include dirname(__DIR__, 2) . '/includes/components/nav_hamburguer.php'; 
    ?>
    
    <script src="<?php echo base_url('assets/js/bobinito-reminder.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>
