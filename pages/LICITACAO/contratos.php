<?php
require_once __DIR__ . '/../../includes/config/config.php';

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
$page_title = 'Contratos';

// Verificar perfil do usuário
$perfil_usuario = strtolower(trim($usuario['perfil'] ?? ''));

// Exibir tela de manutenção para usuários com perfil "licitação"
if ($perfil_usuario === 'licitação') {
    include __DIR__ . '/../../includes/components/navbar.php';
    include __DIR__ . '/../../includes/components/sidebar.php';
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Contratos - Manutenção</title>
        <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            .maintenance-wrapper { display:flex; align-items:center; justify-content:center; min-height: calc(100vh - 120px); padding: 2rem; }
            .maintenance-card { max-width: 780px; width:100%; background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.12); padding:2rem; text-align:center; border:1px solid #f5c2c7; }
            .maintenance-icon { width:84px; height:84px; margin:0 auto 1rem; border-radius:16px; display:flex; align-items:center; justify-content:center; background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%); color:#fff; box-shadow:0 12px 24px rgba(220,53,69,.35); }
            .maintenance-icon i { font-size:2rem; }
            .maintenance-title { font-size:1.5rem; font-weight:800; color:#b02a37; margin:0 0 .5rem; }
            .maintenance-subtitle { color:#6c757d; margin:0 0 1.25rem; }
            .maintenance-note { background:#fff1f2; color:#b02a37; border:1px solid #f5c2c7; border-radius:12px; padding:1rem; font-weight:600; display:inline-block; }
        </style>
    </head>
    <body>
        <div class="dashboard-layout">
            <div class="dashboard-container">
                <div class="dashboard-main">
                    <div class="maintenance-wrapper">
                        <div class="maintenance-card" role="alert" aria-live="polite">
                            <div class="maintenance-icon"><i class="fas fa-triangle-exclamation"></i></div>
                            <h1 class="maintenance-title">Página em manutenção</h1>
                            <p class="maintenance-subtitle">A área de Contratos está temporariamente indisponível para seu perfil.</p>
                            <span class="maintenance-note"><i class="fas fa-ban" style="margin-right:.5rem;"></i> Não tente acessar agora. Tente novamente mais tarde.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="<?php echo base_url('assets/js/estilo.js'); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

// Permitir apenas admin e diretor
if (!in_array($perfil_usuario, ['admin', 'diretor'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'home');
    exit;
}

// Buscar dados de contratos agrupados por gerenciador
$contratos_por_gerenciador = [];
$erro_mensagem = '';
$total_registros = 0;
$tem_valor_consumido = false;

try {
    // Verificar se deve mostrar licitações excluídas
    $mostrar_excluidas = isset($_GET['mostrar_excluidas']) && $_GET['mostrar_excluidas'] === '1';
    
// Já definido acima: $perfil_usuario

// Verificar se é a Renata Bryar (cod_vendedor: 010585) para aplicar restrição
$cod_vendedor = $usuario['COD_VENDEDOR'] ?? $usuario['cod_vendedor'] ?? '';
$eh_renata_bryar = ($cod_vendedor === '010585');
    
    // Primeiro, verificar quantos registros existem na tabela
    $sql_count = "SELECT COUNT(*) as total FROM LICITACAO";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute();
    $result_count = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_registros = $result_count['total'];
    
    // Verificar se a coluna VALOR_CONSUMIDO existe (sempre existe na tabela LICITACAO)
    $tem_valor_consumido = true;
    
    // Construir filtro para licitações excluídas
    // Se mostrar_excluidas = false, filtrar apenas ativas (excluir apenas as com status 'Excluído')
    // Se mostrar_excluidas = true, mostrar todas (sem filtro)
    $filtro_excluidas = !$mostrar_excluidas ? "AND (STATUS IS NULL OR STATUS = '' OR STATUS != 'Excluído')" : '';
    
    // Verificar se o usuário pode ver AVN, MGI, AMERICANAS e DPSP (apenas Renata, Admin e Diretor)
    $pode_ver_avn_mgi = $eh_renata_bryar || in_array($perfil_usuario, ['admin', 'diretor']);
    
    // Aplicar filtro específico baseado no usuário
    $filtro_renata = '';
    if ($eh_renata_bryar) {
        // Renata vê APENAS MGI, AVN, IF-MG, AMERICANAS e DPSP
        $filtro_renata = "AND (
            (UPPER(TRIM(GERENCIADOR)) LIKE '%AVN%' OR UPPER(TRIM(GERENCIADOR)) LIKE '%A V N%') OR
            (UPPER(TRIM(GERENCIADOR)) LIKE '%IF%' AND UPPER(TRIM(GERENCIADOR)) LIKE '%MG%') OR
            UPPER(TRIM(GERENCIADOR)) LIKE '%MGI%' OR
            UPPER(TRIM(GERENCIADOR)) LIKE '%AMERICANAS%' OR
            UPPER(TRIM(GERENCIADOR)) LIKE '%DPSP%'
        )";
    } elseif (!$pode_ver_avn_mgi) {
        // Outros usuários (exceto admin/diretor) NÃO veem MGI, AVN, IF-MG, AMERICANAS e DPSP
        $filtro_renata = "AND NOT (
            (UPPER(TRIM(GERENCIADOR)) LIKE '%AVN%' OR UPPER(TRIM(GERENCIADOR)) LIKE '%A V N%') OR
            (UPPER(TRIM(GERENCIADOR)) LIKE '%IF%' AND UPPER(TRIM(GERENCIADOR)) LIKE '%MG%') OR
            UPPER(TRIM(GERENCIADOR)) LIKE '%MGI%' OR
            UPPER(TRIM(GERENCIADOR)) LIKE '%AMERICANAS%' OR
            UPPER(TRIM(GERENCIADOR)) LIKE '%DPSP%'
        )";
    }
    
    // Filtro adicional: apenas a Renata, Admin e Diretor podem ver registros que ela criou
    $filtro_criacao = '';
    if (!$eh_renata_bryar && !in_array($perfil_usuario, ['admin', 'diretor'])) {
        $filtro_criacao = "AND (COD_VENDEDOR_CRIACAO IS NULL OR COD_VENDEDOR_CRIACAO != '010585')";
    }

    // Buscar grupos de exibição (inclui GRUPO AVN) e agregar TODAS as licitações por grupo
    // Versão corrigida: incluir apenas gerenciadores ativos e licitações não excluídas
    $sql = "SELECT 
                g_all.gerenciador,
                COALESCE(a.total_contratos, 0) AS total_contratos,
                a.siglas,
                COALESCE(a.valor_ata_total, 0) AS valor_ata_total,
                COALESCE(a.valor_contratado_total, 0) AS valor_contratado_total,
                COALESCE(a.valor_consumido_total, 0) AS valor_consumido_total,
                COALESCE(a.saldo_disponivel, 0) AS saldo_disponivel
            FROM (
                -- Lista unificada de gerenciadores vindos tanto da tabela de GERENCIADOR (ativos)
                -- quanto dos nomes presentes na tabela LICITACAO (apenas não excluídos)
                SELECT DISTINCT gerenciador FROM (
                    SELECT 
                        CASE 
                            WHEN UPPER(TRIM(NOME)) LIKE '%AVN%' OR UPPER(TRIM(NOME)) LIKE '%A V N%' THEN 'GRUPO AVN'
                            WHEN UPPER(TRIM(NOME)) LIKE '%IF%' AND UPPER(TRIM(NOME)) LIKE '%MG%' THEN 'IF - MG'
                            ELSE TRIM(NOME)
                        END AS gerenciador
                    FROM GERENCIADOR
                    WHERE ATIVO = 1
                    " . ($eh_renata_bryar ? "AND (
                        (UPPER(TRIM(NOME)) LIKE '%AVN%' OR UPPER(TRIM(NOME)) LIKE '%A V N%') OR
                        (UPPER(TRIM(NOME)) LIKE '%IF%' AND UPPER(TRIM(NOME)) LIKE '%MG%') OR
                        UPPER(TRIM(NOME)) LIKE '%MGI%' OR
                        UPPER(TRIM(NOME)) LIKE '%AMERICANAS%' OR
                        UPPER(TRIM(NOME)) LIKE '%DPSP%'
                    )" : (!$pode_ver_avn_mgi ? "AND NOT (
                        (UPPER(TRIM(NOME)) LIKE '%AVN%' OR UPPER(TRIM(NOME)) LIKE '%A V N%') OR
                        (UPPER(TRIM(NOME)) LIKE '%IF%' AND UPPER(TRIM(NOME)) LIKE '%MG%') OR
                        UPPER(TRIM(NOME)) LIKE '%MGI%' OR
                        UPPER(TRIM(NOME)) LIKE '%AMERICANAS%' OR
                        UPPER(TRIM(NOME)) LIKE '%DPSP%'
                    ) AND (COD_VENDEDOR_CRIACAO IS NULL OR COD_VENDEDOR_CRIACAO != '010585')" : "")) . "
                    UNION
                    SELECT 
                        CASE 
                            WHEN UPPER(TRIM(GERENCIADOR)) LIKE '%AVN%' OR UPPER(TRIM(GERENCIADOR)) LIKE '%A V N%' THEN 'GRUPO AVN'
                            WHEN UPPER(TRIM(GERENCIADOR)) LIKE '%IF%' AND UPPER(TRIM(GERENCIADOR)) LIKE '%MG%' THEN 'IF - MG'
                            ELSE TRIM(GERENCIADOR)
                        END AS gerenciador
                    FROM LICITACAO
                    WHERE GERENCIADOR IS NOT NULL 
                      AND TRIM(GERENCIADOR) <> ''
                      $filtro_excluidas
                      $filtro_renata
                      $filtro_criacao
                ) AS todos
            ) AS g_all
            LEFT JOIN (
                SELECT 
                    CASE 
                        WHEN UPPER(TRIM(GERENCIADOR)) LIKE '%AVN%' OR UPPER(TRIM(GERENCIADOR)) LIKE '%A V N%' THEN 'GRUPO AVN'
                        WHEN UPPER(TRIM(GERENCIADOR)) LIKE '%IF%' AND UPPER(TRIM(GERENCIADOR)) LIKE '%MG%' THEN 'IF - MG'
                        ELSE TRIM(GERENCIADOR)
                    END AS gerenciador,
                    COUNT(*) AS total_contratos,
                    GROUP_CONCAT(DISTINCT COALESCE(SIGLA, ORGAO) ORDER BY COALESCE(SIGLA, ORGAO) SEPARATOR ', ') AS siglas,
                    ROUND(SUM(COALESCE(VALOR_ATA, 0)), 2) AS valor_ata_total,
                    ROUND(SUM(VALOR_GLOBAL), 2) AS valor_contratado_total,
                    ROUND(SUM(COALESCE(VALOR_CONSUMIDO, 0)), 2) AS valor_consumido_total,
                    ROUND(SUM(VALOR_GLOBAL - COALESCE(VALOR_CONSUMIDO, 0)), 2) AS saldo_disponivel
                FROM LICITACAO
                WHERE GERENCIADOR IS NOT NULL 
                  AND TRIM(GERENCIADOR) <> ''
                  $filtro_excluidas
                  $filtro_renata
                  $filtro_criacao
                GROUP BY 
                    CASE 
                        WHEN UPPER(TRIM(GERENCIADOR)) LIKE '%AVN%' OR UPPER(TRIM(GERENCIADOR)) LIKE '%A V N%' THEN 'GRUPO AVN'
                        WHEN UPPER(TRIM(GERENCIADOR)) LIKE '%IF%' AND UPPER(TRIM(GERENCIADOR)) LIKE '%MG%' THEN 'IF - MG'
                        ELSE TRIM(GERENCIADOR)
                    END
            ) AS a ON a.gerenciador = g_all.gerenciador
            ORDER BY g_all.gerenciador";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $contratos_por_gerenciador = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ordenar os contratos por porcentagem de consumo (decrescente) no PHP
    // para evitar reorganização no JavaScript
    usort($contratos_por_gerenciador, function($a, $b) {
        $valor_contratado_a = floatval($a['valor_contratado_total']);
        $valor_consumido_a = floatval($a['valor_consumido_total']);
        $percentual_a = $valor_contratado_a > 0 ? ($valor_consumido_a / $valor_contratado_a) * 100 : 0;
        
        $valor_contratado_b = floatval($b['valor_contratado_total']);
        $valor_consumido_b = floatval($b['valor_consumido_total']);
        $percentual_b = $valor_contratado_b > 0 ? ($valor_consumido_b / $valor_contratado_b) * 100 : 0;
        
        return $percentual_b <=> $percentual_a; // Decrescente (maior percentual primeiro)
    });
} catch (PDOException $e) {
    error_log("Erro ao buscar licitações: " . $e->getMessage());
    $erro_mensagem = $e->getMessage();
    $contratos_por_gerenciador = [];
}

// Verificar permissões para exclusão
$pode_excluir = false;
if (isset($usuario['perfil'])) {
    $perfil = strtolower(trim($usuario['perfil']));
    $pode_excluir = in_array($perfil, ['admin', 'diretor', 'licitação']);
}

// Verificar permissões para criar/editar (todos os perfis podem criar/editar)
$pode_criar_editar = true;


// Calcular totais gerais
$total_geral_ata = 0;
$total_geral_contratado = 0;
$total_geral_consumido = 0;
$total_geral_saldo = 0;

foreach ($contratos_por_gerenciador as $contrato) {
    $total_geral_ata += floatval($contrato['valor_ata_total']);
    $total_geral_contratado += floatval($contrato['valor_contratado_total']);
    $total_geral_consumido += floatval($contrato['valor_consumido_total']);
    $total_geral_saldo += floatval($contrato['saldo_disponivel']);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="secondary-color" content="<?php echo htmlspecialchars($usuario['secondary_color'] ?? '#ff8f00'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/contratos.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/contratos-mobile.css'); ?>?v=<?php echo time(); ?>">
    <!-- Cache bust: <?php echo date('Y-m-d H:i:s'); ?> -->
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
                
                <!-- Avisos de Status -->
                <div class="status-alerts">
                    <?php if (!$tem_valor_consumido): ?>
                    <?php elseif ($total_geral_consumido == 0 && count($contratos_por_gerenciador) > 0): ?>
                    <?php endif; ?>
                </div>

                <!-- Cards de Resumo -->
                <div class="resumo-cards">
                    <div class="resumo-card card-ata">
                        <div class="resumo-card-content">
                            <h3>Valor ATA</h3>
                            <p class="resumo-card-valor">R$ <?php echo number_format($total_geral_ata, 2, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="resumo-card card-contratado">
                        <div class="resumo-card-content">
                            <h3>Total Contratado</h3>
                            <p class="resumo-card-valor">R$ <?php echo number_format($total_geral_contratado, 2, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="resumo-card card-consumido">
                        <div class="resumo-card-content">
                            <h3>Total Faturado</h3>
                            <p class="resumo-card-valor">R$ <?php echo number_format($total_geral_consumido, 2, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="resumo-card card-saldo">
                        <div class="resumo-card-content">
                            <h3>Saldo Disponível</h3>
                            <p class="resumo-card-valor">R$ <?php echo number_format($total_geral_saldo, 2, ',', '.'); ?></p>
                        </div>
                    </div>

                    <div class="resumo-card card-percentual">
                        <div class="resumo-card-content">
                            <h3>% FATURADO</h3>
                            <p class="resumo-card-valor">
                                <?php 
                                $percentual_consumido = $total_geral_contratado > 0 
                                    ? ($total_geral_consumido / $total_geral_contratado) * 100 
                                    : 0;
                                echo number_format($percentual_consumido, 1, ',', '.'); 
                                ?>%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contratos por Gerenciador -->
                <div class="contratos-section">
                    <div class="section-header">
                        <!-- Barra de Pesquisa no lugar do título -->
                        <div class="search-container">
                            <div class="search-box">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="searchInput" placeholder="Pesquisar por gerenciador, órgão, sigla..." 
                                       onkeyup="filtrarContratos()" oninput="filtrarContratos()">
                                <button class="btn-clear-search" id="clearSearchBtn" onclick="limparPesquisa()" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="search-results-info" id="searchResultsInfo" style="display: none;">
                                <span id="searchResultsText"></span>
                            </div>
                        </div>
                        <div class="section-actions">
                            <?php if ($pode_excluir): ?>
                            <div class="toggle-container">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="toggle-excluidas" <?php echo $mostrar_excluidas ? 'checked' : ''; ?> onchange="toggleExcluidas()">
                                    <span class="toggle-slider"></span>
                                    <span class="toggle-label">Mostrar Excluídas</span>
                                </label>
                            </div>
                            <?php endif; ?>
                            <?php if ($pode_criar_editar): ?>
                            <button class="btn-action-compact btn-primary-compact" onclick="abrirModalGerenciador()" title="Novo Gerenciador">
                                <i class="fas fa-user-plus"></i>
                            </button>
                            <button class="btn-action-compact btn-secondary-compact" onclick="abrirModalContrato()" title="Nova Licitação">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn-action-compact btn-upload-compact" onclick="abrirModalUpload()" title="Upload Contratos">
                                <i class="fas fa-upload"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn-action-compact btn-refresh-compact" onclick="atualizarDados()" title="Atualizar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>

                    <?php if (empty($contratos_por_gerenciador)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Nenhuma licitação encontrada</p>
                            
                            <?php if ($total_registros > 0): ?>
                                <div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; text-align: left;">
                                    <p style="margin: 0 0 0.5rem 0; font-weight: 600; color: #e65100;">
                                        <i class="fas fa-info-circle"></i> Informação
                                    </p>
                                    <p style="margin: 0; color: #5f6368; font-size: 0.9rem;">
                                        Existem <strong><?php echo $total_registros; ?> licitação(ões)</strong> na tabela.
                                        <br><br>
                                        Todas as licitações cadastradas serão exibidas, independente do status.
                                    </p>
                                </div>
                            <?php else: ?>
                                <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; text-align: left;">
                                    <p style="margin: 0 0 0.5rem 0; font-weight: 600; color: #1565c0;">
                                        <i class="fas fa-lightbulb"></i> A tabela está vazia
                                    </p>
                                    <p style="margin: 0; color: #5f6368; font-size: 0.9rem;">
                                        Não há licitações cadastradas no sistema.
                                        <br><br>
                                        A tabela LICITACAO está vazia.
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($erro_mensagem)): ?>
                                <div style="background: #ffebee; border-left: 4px solid #f44336; padding: 1rem; border-radius: 8px; margin-top: 1rem; text-align: left;">
                                    <p style="margin: 0 0 0.5rem 0; font-weight: 600; color: #c62828;">
                                        <i class="fas fa-exclamation-triangle"></i> Erro
                                    </p>
                                    <p style="margin: 0; color: #5f6368; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($erro_mensagem); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 1.5rem;">
                                <a href="<?php echo base_url('tests/debug_licitacoes.php'); ?>" style="display: inline-block; padding: 0.75rem 1.5rem; background: #9c27b0; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;">
                                    <i class="fas fa-bug"></i> Executar Diagnóstico Completo
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="contratos-grid">
                            <?php foreach ($contratos_por_gerenciador as $contrato): 
                                $valor_contratado = floatval($contrato['valor_contratado_total']);
                                $valor_consumido = floatval($contrato['valor_consumido_total']);
                                $saldo = floatval($contrato['saldo_disponivel']);
                                $percentual = $valor_contratado > 0 ? ($valor_consumido / $valor_contratado) * 100 : 0;
                                
                                // Determinar classe de status baseado no percentual - LÓGICA INVERTIDA
                                $status_class = '';
                                if ($percentual >= 70) {
                                    $status_class = 'status-normal'; // Verde - bem consumido (70%+)
                                } elseif ($percentual >= 30) {
                                    $status_class = 'status-alerta'; // Amarelo - consumo médio (30-69%)
                                } else {
                                    $status_class = 'status-critico'; // Vermelho - pouco consumido (0-29%)
                                }
                            ?>
                                <div class="contrato-card <?php echo $status_class; ?>">
                                    <div class="contrato-header">
                                        <h3 class="gerenciador-nome">
                                            <i class="fas fa-user-tie"></i>
                                            <?php echo htmlspecialchars(strtoupper($contrato['gerenciador'] ?? $contrato['GERENCIADOR'] ?? 'Não informado')); ?>
                                        </h3>
                                        <span class="total-contratos">
                                            <?php echo $contrato['total_contratos']; ?> licitação(ões)
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($contrato['siglas'])): ?>
                                    <div class="orgaos-info">
                                        <div class="orgaos-header">
                                            <i class="fas fa-building"></i>
                                            <span>Órgãos/Siglas</span>
                                        </div>
                                        <div class="orgaos-content">
                                            <?php 
                                            $siglas = explode(', ', $contrato['siglas']);
                                            $siglas_limitadas = array_slice($siglas, 0, 5);
                                            echo htmlspecialchars(implode(', ', $siglas_limitadas));
                                            if (count($siglas) > 5) {
                                                echo '<span class="orgaos-more"> +' . (count($siglas) - 5) . ' mais</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Botão para expandir/recolher valores -->
                                    <div class="expand-button-container">
                                        <button class="btn-expand" onclick="toggleValores(this)">
                                            <i class="fas fa-chevron-down"></i>
                                            <span>Ver Valores</span>
                                        </button>
                                    </div>

                                    <!-- Valores (inicialmente ocultos) -->
                                    <div class="contrato-valores collapsed">
                                        <div class="valor-item">
                                            <span class="valor-label">Contratado</span>
                                            <span class="valor-amount">R$ <?php echo number_format($valor_contratado, 2, ',', '.'); ?></span>
                                        </div>
                                        <div class="valor-item">
                                            <span class="valor-label">Consumido</span>
                                            <span class="valor-amount valor-consumido">R$ <?php echo number_format($valor_consumido, 2, ',', '.'); ?></span>
                                        </div>
                                        <div class="valor-item">
                                            <span class="valor-label">Saldo</span>
                                            <span class="valor-amount valor-saldo">R$ <?php echo number_format($saldo, 2, ',', '.'); ?></span>
                                        </div>
                                    </div>

                                    <!-- Barra de Progresso (inicialmente oculta) -->
                                    <div class="progress-container collapsed">
                                        <div class="progress-header">
                                            <span>Consumo</span>
                                            <span class="progress-percentual"><?php echo number_format($percentual, 1, ',', '.'); ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min($percentual, 100); ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Ações -->
                                    <div class="contrato-actions">
                                        <button class="btn-detalhes-compact" onclick="verDetalhes('<?php echo htmlspecialchars($contrato['gerenciador'] ?? $contrato['GERENCIADOR'] ?? ''); ?>')" title="Ver Detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-documentos-compact" onclick="verContratos('<?php echo htmlspecialchars($contrato['gerenciador'] ?? $contrato['GERENCIADOR'] ?? ''); ?>')" title="Contratos">
                                            <i class="fas fa-file-alt"></i>
                                        </button>
                                        <?php if ($pode_criar_editar): ?>
                                        <button class="btn-add-compact" onclick="abrirModalContrato('<?php echo htmlspecialchars($contrato['gerenciador'] ?? $contrato['GERENCIADOR'] ?? ''); ?>')" title="Nova Licitação">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($pode_excluir): ?>
                                        <button class="btn-danger-compact" onclick="excluirGerenciador('<?php echo htmlspecialchars($contrato['gerenciador'] ?? $contrato['GERENCIADOR'] ?? ''); ?>')" title="Excluir Gerenciador">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </main>
            <?php include __DIR__ . '/../../includes/components/footer.php'; ?>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div id="modalDetalhes" class="modal">
        <div class="modal-content modal-extra-large">
            <div class="modal-header">
                <h2><i class="fas fa-info-circle"></i> Detalhes das Licitações</h2>
                <button class="modal-close" onclick="fecharModal('modalDetalhes')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>


    <!-- Modal de Gerenciador -->
    <div id="modalGerenciador" class="modal">
        <div class="modal-content modal-medium">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> <span id="modalGerenciadorTitulo">Novo Gerenciador</span></h2>
                <button class="modal-close" onclick="fecharModal('modalGerenciador')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formGerenciador">
                    <input type="hidden" id="gerenciador_id" name="id">
                    
                    <div class="form-group">
                        <label for="gerenciador_nome">Nome do Gerenciador *</label>
                        <input type="text" id="gerenciador_nome" name="gerenciador" required 
                               placeholder="Digite o nome do gerenciador" maxlength="100">
                        <small class="form-help">Nome completo do gerenciador responsável pelas licitações</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="fecharModal('modalGerenciador')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Salvar Gerenciador
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Contrato -->
    <div id="modalContrato" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2><i class="fas fa-file-contract"></i> <span id="modalContratoTitulo">Nova Licitação</span></h2>
                <button class="modal-close" onclick="fecharModal('modalContrato')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formContrato">
                    <input type="hidden" id="contrato_id" name="id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contrato_gerenciador">Gerenciador *</label>
                            <select id="contrato_gerenciador" name="gerenciador" required>
                                <option value="">Selecione o gerenciador</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="contrato_status">Status</label>
                            <select id="contrato_status" name="status">
                                <option value="Vigente">Vigente</option>
                                <option value="Finalizado">Finalizado</option>
                                <option value="Suspenso">Suspenso</option>
                                <option value="Excluído">Excluído</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contrato_sigla">Sigla</label>
                            <input type="text" id="contrato_sigla" name="sigla" 
                                   placeholder="Ex: TJSP, PMSP">
                        </div>
                        <div class="form-group">
                            <label for="contrato_razao_social">Razão Social *</label>
                            <input type="text" id="contrato_razao_social" name="razao_social" required 
                                   placeholder="Nome da empresa/órgão">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contrato_numero_pregao">Número do Pregão</label>
                            <input type="text" id="contrato_numero_pregao" name="numero_pregao" 
                                   placeholder="Ex: 123/2024">
                        </div>
                        <div class="form-group">
                            <label for="contrato_termo_contrato">Termo do Contrato</label>
                            <input type="text" id="contrato_termo_contrato" name="termo_contrato" 
                                   placeholder="Ex: TC-001/2024">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contrato_valor_global">Valor Global *</label>
                            <input type="number" id="contrato_valor_global" name="valor_global" required 
                                   step="0.01" min="0" placeholder="0,00">
                        </div>
                        <div class="form-group">
                            <label for="contrato_valor_consumido">Valor Consumido</label>
                            <input type="number" id="contrato_valor_consumido" name="valor_consumido" 
                                   step="0.01" min="0" placeholder="0,00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contrato_data_inicio">Data de Início da Vigência</label>
                            <input type="date" id="contrato_data_inicio" name="data_inicio_vigencia">
                        </div>
                        <div class="form-group">
                            <label for="contrato_data_termino">Data de Término da Vigência</label>
                            <input type="date" id="contrato_data_termino" name="data_termino_vigencia">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="fecharModal('modalContrato')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Upload de Contratos -->
    <div id="modalUpload" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2><i class="fas fa-upload"></i> Upload de Contratos</h2>
                <button class="modal-close" onclick="fecharModal('modalUpload')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formUpload" enctype="multipart/form-data">
                    <!-- Seleção de Gerenciador -->
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label for="upload_gerenciador">Gerenciador *</label>
                        <select id="upload_gerenciador" name="gerenciador" required>
                            <option value="">Selecione o gerenciador</option>
                        </select>
                        <small class="form-help">Selecione o gerenciador para organizar os contratos</small>
                    </div>
                    
                    <div class="upload-section">
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-content">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <h3>Arraste e solte os arquivos aqui</h3>
                                <p>ou clique para selecionar</p>
                                <input type="file" id="fileInput" name="contratos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx" style="display: none;">
                                <button type="button" class="btn-select-files" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-folder-open"></i> Selecionar Arquivos
                                </button>
                            </div>
                        </div>
                        
                        <div class="upload-info">
                            <h4><i class="fas fa-info-circle"></i> Informações sobre o Upload</h4>
                            <ul>
                                <li><strong>Formatos aceitos:</strong> PDF, DOC, DOCX, XLS, XLSX</li>
                                <li><strong>Tamanho máximo:</strong> 10MB por arquivo</li>
                                <li><strong>Múltiplos arquivos:</strong> Você pode selecionar vários arquivos de uma vez</li>
                                <li><strong>Organização:</strong> Os arquivos serão organizados por gerenciador automaticamente</li>
                            </ul>
                        </div>
                    </div>

                    <div class="selected-files" id="selectedFiles" style="display: none;">
                        <h4><i class="fas fa-list"></i> Arquivos Selecionados</h4>
                        <div class="files-list" id="filesList"></div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="fecharModal('modalUpload')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-save" id="btnUpload" disabled>
                            <i class="fas fa-upload"></i> Fazer Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Visualização de Contratos -->
    <div id="modalContratos" class="modal">
        <div class="modal-content modal-extra-large">
            <div class="modal-header">
                <h2><i class="fas fa-file-alt"></i> <span id="modalContratosTitulo">Contratos do Gerenciador</span></h2>
                <button class="modal-close" onclick="fecharModal('modalContratos')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalContratosBody">
                <div class="loading-container" style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #1a237e;"></i>
                    <p style="margin-top: 1rem; color: #5f6368;">Carregando contratos...</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?php echo base_url('assets/js/contratos.js'); ?>?v=<?php echo time(); ?>"></script>
    
    <?php include __DIR__ . '/../../includes/components/nav_hamburguer.php'; ?>
</body>
</html>

