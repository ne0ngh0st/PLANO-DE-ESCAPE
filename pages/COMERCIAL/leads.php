<?php
// Definir codificação UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once dirname(__DIR__, 2) . '/includes/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/utils/gerar_identificador_lead.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once dirname(__DIR__, 2) . '/includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar permissões - usuários com perfil licitação não podem acessar leads
$perfilUsuario = strtolower($usuario['perfil'] ?? '');
if ($perfilUsuario === 'licitação') {
    header('Location: pages/contratos.php');
    exit;
}

$perfil_permitido = in_array($usuario['perfil'], ['diretor', 'supervisor', 'admin', 'representante', 'vendedor']);
$is_vendedor = $usuario['perfil'] === 'vendedor';
$is_representante = $usuario['perfil'] === 'representante';
$is_admin = $usuario['perfil'] === 'admin';

// Incluir conexão
require_once dirname(__DIR__, 2) . '/includes/config/conexao.php';
$pdo_leads = $pdo;

// Função para formatar telefone com zero na frente do DDD
function formatarTelefone($telefone) {
    if (empty($telefone) || $telefone === 'N/A') {
        return 'N/A';
    }
    
    // Se já tem o formato (0XX) XXXXX-XXXX, retornar como está
    if (strpos($telefone, '(0') === 0) {
        return $telefone;
    }
    
    // Se tem formato (XX) XXXXX-XXXX, adicionar o zero
    if (preg_match('/^\((\d{2})\)\s(.+)$/', $telefone, $matches)) {
        $ddd = $matches[1];
        $numero = $matches[2];
        return '(0' . $ddd . ') ' . $numero;
    }
    
    // Se tem apenas números, tentar formatar
    $numeros = preg_replace('/\D/', '', $telefone);
    if (strlen($numeros) >= 10) {
        $ddd = substr($numeros, 0, 2);
        $numero = substr($numeros, 2);
        
        if (strlen($numero) == 8) {
            return '(0' . $ddd . ') ' . substr($numero, 0, 4) . '-' . substr($numero, 4);
        } elseif (strlen($numero) == 9) {
            return '(0' . $ddd . ') ' . substr($numero, 0, 5) . '-' . substr($numero, 5);
        }
    }
    
    // Se não conseguiu formatar, retornar original
    return $telefone;
}


// Processar filtros
$filtro_estado = $_GET['filtro_estado'] ?? '';
$filtro_segmento = $_GET['filtro_segmento'] ?? '';
$filtro_valor_min = $_GET['filtro_valor_min'] ?? '';
$filtro_valor_max = $_GET['filtro_valor_max'] ?? '';
$filtro_nome = $_GET['filtro_nome'] ?? '';
// Seletor de visão (supervisor/vendedor)
$supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
$vendedor_selecionado = $_GET['visao_vendedor'] ?? '';
// Removido: filtro via checkbox. Padrão agora é sempre mostrar apenas usuários com leads

// Configurações de paginação
$itens_por_pagina = 50;
$pagina_atual = max(1, intval($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Buscar leads
$leads = [];
$total_leads = 0;
$total_registros = 0;
$total_paginas = 0;
$error_message = '';

// Verificar se a conexão está ativa
if (!isset($pdo_leads) || !$pdo_leads) {
    $error_message = "Erro: Conexão com o banco de dados não disponível.";
}

if ($perfil_permitido && isset($pdo_leads)) {
    try {
        // Construir condições WHERE (incluindo filtro para não mostrar leads excluídos)
        $where_conditions = ["bl.MARCAOPROSPECT = 'SAI PROSPECT'"];
        $params = [];
        
        // Adicionar filtro para excluir leads que estão na tabela leads_excluidos, EXCETO se foram restaurados
        $where_conditions[] = "NOT EXISTS (
            SELECT 1 FROM leads_excluidos le 
            WHERE le.email = bl.Email 
            AND NOT EXISTS (SELECT 1 FROM leads_restaurados lr WHERE lr.email = le.email)
        )";
        
        // Aplicar filtros
        if (!empty($filtro_estado)) {
            $where_conditions[] = "bl.UF = ?";
            $params[] = $filtro_estado;
        }
        
        if (!empty($filtro_segmento)) {
            $where_conditions[] = "bl.Descricao1 = ?";
            $params[] = $filtro_segmento;
        }
        
        if (!empty($filtro_valor_min)) {
            $where_conditions[] = "bl.VLR_TOTAL >= ?";
            $params[] = floatval($filtro_valor_min) * 100;
        }
        
        if (!empty($filtro_valor_max)) {
            $where_conditions[] = "bl.VLR_TOTAL <= ?";
            $params[] = floatval($filtro_valor_max) * 100;
        }
        
        // Filtro de nome será aplicado diretamente na query SQL
        
        // Filtro por visão (apenas para admin/diretor/supervisor via seletores)
        $perfil_lower = strtolower(trim($usuario['perfil']));
        if (in_array($perfil_lower, ['diretor', 'admin'])) {
            if (!empty($vendedor_selecionado)) {
                $where_conditions[] = "bl.CodigoVendedor = CAST(? AS UNSIGNED)";
                $params[] = $vendedor_selecionado;
            } elseif (!empty($supervisor_selecionado)) {
                $where_conditions[] = "bl.CodigoVendedor IN (
                    SELECT CAST(COD_VENDEDOR AS UNSIGNED) FROM USUARIOS 
                    WHERE (COD_SUPER = ? OR COD_VENDEDOR = ?) 
                    AND ATIVO = 1 
                    AND PERFIL IN ('vendedor','representante','supervisor','diretor')
                )";
                $params[] = $supervisor_selecionado;
                $params[] = $supervisor_selecionado;
            }
        } elseif ($perfil_lower === 'supervisor') {
            if (!empty($vendedor_selecionado)) {
                $where_conditions[] = "bl.CodigoVendedor = CAST(? AS UNSIGNED)";
                $params[] = $vendedor_selecionado;
            }
        }
 
        // Filtro automático por Código de Vendedor para representantes e vendedores
        if ($is_representante || $is_vendedor) {
            $sql_cod = "SELECT COD_VENDEDOR FROM USUARIOS WHERE EMAIL = ?";
            $stmt_cod = $pdo->prepare($sql_cod);
            $stmt_cod->execute([$usuario['email']]);
            $cod_vendedor_usuario = $stmt_cod->fetch(PDO::FETCH_COLUMN);

            if ($cod_vendedor_usuario) {
                $where_conditions[] = "bl.CodigoVendedor = CAST(? AS UNSIGNED)";
                $params[] = $cod_vendedor_usuario;
            }
        }
        
        // Filtro automático para supervisores - ver apenas leads de sua equipe
        if ($perfil_lower === 'supervisor' && empty($vendedor_selecionado)) {
            $cod_vendedor_supervisor = $usuario['cod_vendedor'] ?? null;
            if ($cod_vendedor_supervisor) {
                $where_conditions[] = "bl.CodigoVendedor IN (
                    SELECT CAST(COD_VENDEDOR AS UNSIGNED) FROM USUARIOS 
                    WHERE (COD_SUPER = ? OR COD_VENDEDOR = ?) 
                    AND ATIVO = 1 
                    AND PERFIL IN ('vendedor','representante','supervisor')
                )";
                $params[] = $cod_vendedor_supervisor;
                $params[] = $cod_vendedor_supervisor;
            }
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // LÓGICA OTIMIZADA: Usar busca no banco para filtro de nome (mais eficiente)
        
        // Adicionar filtro de nome diretamente na query SQL se existir
        if (!empty($filtro_nome)) {
            $nome_busca = strtolower($filtro_nome);
            $where_conditions[] = "(LOWER(bl.nomefinal) LIKE ? OR LOWER(bl.RAZAOSOCIAL) LIKE ? OR LOWER(bl.NOMEFANTASIA) LIKE ? OR LOWER(bl.Email) LIKE ?)";
            $params[] = "%$nome_busca%";
            $params[] = "%$nome_busca%";
            $params[] = "%$nome_busca%";
            $params[] = "%$nome_busca%";
            
            // Reconstruir WHERE clause
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // 1. Contar registros na BASE_LEADS
        $sql_count_simples = "SELECT COUNT(*) as total FROM `BASE_LEADS` bl $where_clause";
        $stmt_count = $pdo_leads->prepare($sql_count_simples);
        $stmt_count->execute($params);
        $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        $total_paginas = ceil($total_registros / $itens_por_pagina);
        
        // Ajustar página atual
        if ($pagina_atual > $total_paginas && $total_paginas > 0) {
            $pagina_atual = $total_paginas;
            $offset = ($pagina_atual - 1) * $itens_por_pagina;
        }
        
        // 2. Buscar leads da BASE_LEADS com paginação
        $sql_leads_simples = "SELECT bl.*
                              FROM `BASE_LEADS` bl
                              $where_clause
                              ORDER BY bl.DATAOBS DESC 
                              LIMIT $itens_por_pagina OFFSET $offset";
        
        // Debug: Verificar se há telefone específico na query
        if (strpos($sql_leads_simples, '3296-9600') !== false) {
            echo "<!-- DEBUG QUERY: Telefone encontrado na query -->";
        }
        
        $stmt = $pdo_leads->prepare($sql_leads_simples);
        $stmt->execute($params);
        $leads_base = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Verificar se o telefone está nos resultados
        foreach ($leads_base as $lead_debug) {
            if (strpos($lead_debug['TelefonePrincipalFINAL'] ?? '', '3296-9600') !== false) {
                echo "<!-- DEBUG RESULTADO: Telefone (48) 3296-9600 encontrado na base -->";
                echo "<!-- Valor bruto: '" . $lead_debug['TelefonePrincipalFINAL'] . "' -->";
                break;
            }
        }
        
        // 3. Buscar todas as edições de uma vez (por email e por lead_key para casos sem email)
        $edicoes_by_email = [];
        $edicoes_by_key = [];
        if (!empty($leads_base)) {
            $emails_para_edicoes = [];
            $lead_keys_para_edicoes = [];

            foreach ($leads_base as $lead_tmp) {
                $email_tmp = trim($lead_tmp['Email'] ?? '');
                // Tratar "-" e "N/A" como email vazio
                if ($email_tmp !== '' && $email_tmp !== '-' && strtoupper($email_tmp) !== 'N/A') {
                    $emails_para_edicoes[] = $email_tmp;
                    continue;
                }
                // Computar lead_key robusta para leads sem email: usa múltiplos campos para reduzir colisões
                $nome_candidato = ($lead_tmp['nomefinal'] ?? '');
                if ($nome_candidato === '' && isset($lead_tmp['RAZAOSOCIAL']) && $lead_tmp['RAZAOSOCIAL'] !== '') { $nome_candidato = $lead_tmp['RAZAOSOCIAL']; }
                if ($nome_candidato === '' && isset($lead_tmp['NOMEFANTASIA']) && $lead_tmp['NOMEFANTASIA'] !== '') { $nome_candidato = $lead_tmp['NOMEFANTASIA']; }

                $nome_base = trim($nome_candidato);
                $razao_base = trim($lead_tmp['RAZAOSOCIAL'] ?? '');
                $fantasia_base = trim($lead_tmp['NOMEFANTASIA'] ?? '');
                $telefone_base = preg_replace('/\D+/', '', $lead_tmp['TelefonePrincipalFINAL'] ?? '');
                $endereco_base = trim($lead_tmp['endereoCNPJJA'] ?? '');
                $uf_base = trim($lead_tmp['UF'] ?? '');
                $vend_base = trim((string)($lead_tmp['CodigoVendedor'] ?? ''));
                $dataobs_base = trim($lead_tmp['DATAOBS'] ?? '');

                $signature_payload = json_encode([
                    'nome' => mb_strtolower($nome_base),
                    'razao' => mb_strtolower($razao_base),
                    'fantasia' => mb_strtolower($fantasia_base),
                    'tel' => $telefone_base,
                    'end' => mb_strtolower($endereco_base),
                    'uf' => mb_strtolower($uf_base),
                    'vend' => $vend_base,
                    'dataobs' => $dataobs_base,
                ], JSON_UNESCAPED_UNICODE);

                $key = sha1($signature_payload);
                $lead_keys_para_edicoes[] = $key;
            }

            $cond_parts = [];
            $params_ed = [];
            if (!empty($emails_para_edicoes)) {
                $place_em = str_repeat('?,', count($emails_para_edicoes) - 1) . '?';
                $cond_parts[] = "email_original IN ($place_em)";
                $params_ed = array_merge($params_ed, $emails_para_edicoes);
            }
            if (!empty($lead_keys_para_edicoes)) {
                $place_k = str_repeat('?,', count($lead_keys_para_edicoes) - 1) . '?';
                $cond_parts[] = "(lead_key IS NOT NULL AND lead_key IN ($place_k))";
                $params_ed = array_merge($params_ed, $lead_keys_para_edicoes);
            }

            if (!empty($cond_parts)) {
                // Buscar edições ordenadas pela mais recente primeiro
                $sql_edicoes = "SELECT * FROM LEADS_EDICOES WHERE ativo = 1 AND (" . implode(' OR ', $cond_parts) . ") ORDER BY data_edicao DESC, id DESC";
                $stmt_edicoes = $pdo_leads->prepare($sql_edicoes);
                $stmt_edicoes->execute($params_ed);
                $edicoes_result = $stmt_edicoes->fetchAll(PDO::FETCH_ASSOC);

                foreach ($edicoes_result as $edicao) {
                    // Manter apenas a edição mais recente por identificador
                    if (isset($edicao['email_original']) && $edicao['email_original'] !== '') {
                        if (!isset($edicoes_by_email[$edicao['email_original']])) {
                            $edicoes_by_email[$edicao['email_original']] = $edicao;
                        }
                    }
                    if (isset($edicao['lead_key']) && $edicao['lead_key'] !== null && $edicao['lead_key'] !== '') {
                        if (!isset($edicoes_by_key[$edicao['lead_key']])) {
                            $edicoes_by_key[$edicao['lead_key']] = $edicao;
                        }
                    }
                }
            }
        }
        
        // 4. Aplicar edições aos dados base em PHP
        $leads_temp = [];
        foreach ($leads_base as $lead) {
            $email = trim($lead['Email'] ?? '');
            $edicao = null;
            // Preservar o email original antes de aplicar edições
            $email_original_lead = $email;

            // Calcular uma chave estável baseada SOMENTE na BASE (antes de aplicar edições)
            $nome_candidato_base = ($lead['nomefinal'] ?? '');
            if ($nome_candidato_base === '' && isset($lead['RAZAOSOCIAL']) && $lead['RAZAOSOCIAL'] !== '') { $nome_candidato_base = $lead['RAZAOSOCIAL']; }
            if ($nome_candidato_base === '' && isset($lead['NOMEFANTASIA']) && $lead['NOMEFANTASIA'] !== '') { $nome_candidato_base = $lead['NOMEFANTASIA']; }

            $nome_base = trim($nome_candidato_base);
            $razao_base = trim($lead['RAZAOSOCIAL'] ?? '');
            $fantasia_base = trim($lead['NOMEFANTASIA'] ?? '');
            $telefone_base = preg_replace('/\D+/', '', $lead['TelefonePrincipalFINAL'] ?? '');
            $endereco_base = trim($lead['endereoCNPJJA'] ?? '');
            $uf_base = trim($lead['UF'] ?? '');
            $vend_base = trim((string)($lead['CodigoVendedor'] ?? ''));
            $dataobs_base = trim($lead['DATAOBS'] ?? '');

            $signature_payload_base = json_encode([
                'nome' => mb_strtolower($nome_base),
                'razao' => mb_strtolower($razao_base),
                'fantasia' => mb_strtolower($fantasia_base),
                'tel' => $telefone_base,
                'end' => mb_strtolower($endereco_base),
                'uf' => mb_strtolower($uf_base),
                'vend' => $vend_base,
                'dataobs' => $dataobs_base,
            ], JSON_UNESCAPED_UNICODE);
            $lead_uid_base = sha1($signature_payload_base);

            // Tratar "-" e "N/A" como email vazio para buscar edições
            if ($email !== '' && $email !== '-' && strtoupper($email) !== 'N/A' && isset($edicoes_by_email[$email])) {
                $edicao = $edicoes_by_email[$email];
            } else {
                // Casar por chave estável quando não há email
                if ($lead_uid_base !== '' && isset($edicoes_by_key[$lead_uid_base])) {
                    $edicao = $edicoes_by_key[$lead_uid_base];
                }
            }

            // Aplicar edições se existirem
            if ($edicao !== null) {
                $lead['nomefinal'] = !empty($edicao['nome_editado']) ? $edicao['nome_editado'] : $lead['nomefinal'];
                $lead['RAZAOSOCIAL'] = !empty($edicao['nome_editado']) ? $edicao['nome_editado'] : $lead['RAZAOSOCIAL'];
                $lead['NOMEFANTASIA'] = !empty($edicao['nome_editado']) ? $edicao['nome_editado'] : $lead['NOMEFANTASIA'];
                $lead['Email'] = !empty($edicao['email_editado']) ? $edicao['email_editado'] : $lead['Email'];
                $lead['TelefonePrincipalFINAL'] = !empty($edicao['telefone_editado']) ? $edicao['telefone_editado'] : $lead['TelefonePrincipalFINAL'];
                $lead['endereoCNPJJA'] = !empty($edicao['endereco_editado']) ? $edicao['endereco_editado'] : ($lead['endereoCNPJJA'] ?? null);
                $lead['data_edicao'] = $edicao['data_edicao'];
                $lead['usuario_editor'] = $edicao['usuario_editor'];
                $lead['tem_edicao'] = 1;
            } else {
                $lead['data_edicao'] = null;
                $lead['usuario_editor'] = null;
                $lead['tem_edicao'] = 0;
            }
            // Anexar o email original e a chave base para uso nas ações
            $lead['email_original'] = $email_original_lead;
            $lead['lead_uid_base'] = $lead_uid_base;
            
            $leads_temp[] = $lead;
        }
        
        // 5. Os dados já vêm filtrados do banco, então apenas aplicar edições
        $leads = [];
        foreach ($leads_temp as $lead) {
            $leads[] = $lead;
        }
        
        $total_leads = count($leads);
        
    } catch (PDOException $e) {
        error_log("ERRO LEADS: " . $e->getMessage());
        $error_message = "Erro ao buscar leads: " . $e->getMessage();
    }
} else {
    if (!$perfil_permitido) {
        $error_message = "Você não tem permissão para acessar leads. Entre em contato com o administrador do sistema.";
    } elseif (!isset($pdo_leads)) {
        $error_message = "Erro de conexão com o banco de dados. Tente novamente mais tarde.";
    }
}

// Buscar leads manuais
$leads_manuais = [];
$total_leads_manuais = 0;

if (isset($pdo_leads)) {
    try {
        // Verificar se a tabela existe
        $stmt_check = $pdo_leads->prepare("SHOW TABLES LIKE 'LEADS_MANUAIS'");
        $stmt_check->execute();
        $tabela_existe = $stmt_check->fetch();
        
        if ($tabela_existe) {
            // Buscar código do vendedor do usuário
            $codigo_vendedor_usuario = null;
            if (isset($usuario['cod_vendedor'])) {
                $codigo_vendedor_usuario = $usuario['cod_vendedor'];
            } else {
                $stmt_cod = $pdo->prepare("SELECT COD_VENDEDOR FROM USUARIOS WHERE EMAIL = ?");
                $stmt_cod->execute([$usuario['email']]);
                $codigo_vendedor_usuario = $stmt_cod->fetch(PDO::FETCH_COLUMN);
            }
            
            // Construir condições WHERE para leads manuais
            $where_manuais = ["status = 'ativo'"];
            $params_manuais = [];
            
            // Filtro por vendedor
            if ($is_representante || $is_vendedor) {
                if ($codigo_vendedor_usuario) {
                    $where_manuais[] = "codigo_vendedor = ?";
                    $params_manuais[] = $codigo_vendedor_usuario;
                }
            } elseif (strtolower(trim($usuario['perfil'])) === 'supervisor') {
                // Para supervisores, mostrar leads da equipe
                if ($codigo_vendedor_usuario) {
                    $where_manuais[] = "codigo_vendedor IN (
                        SELECT CAST(COD_VENDEDOR AS UNSIGNED) FROM USUARIOS 
                        WHERE (COD_SUPER = ? OR COD_VENDEDOR = ?) 
                        AND ATIVO = 1 
                        AND PERFIL IN ('vendedor','representante','supervisor')
                    )";
                    $params_manuais[] = $codigo_vendedor_usuario;
                    $params_manuais[] = $codigo_vendedor_usuario;
                }
            }
            
            $where_clause_manuais = 'WHERE ' . implode(' AND ', $where_manuais);
            
            // Contar leads manuais
            $sql_count_manuais = "SELECT COUNT(*) as total FROM LEADS_MANUAIS $where_clause_manuais";
            $stmt_count_manuais = $pdo_leads->prepare($sql_count_manuais);
            $stmt_count_manuais->execute($params_manuais);
            $total_leads_manuais = $stmt_count_manuais->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Buscar leads manuais
            $sql_leads_manuais = "SELECT * FROM LEADS_MANUAIS $where_clause_manuais ORDER BY data_cadastro DESC LIMIT 100";
            $stmt_leads_manuais = $pdo_leads->prepare($sql_leads_manuais);
            $stmt_leads_manuais->execute($params_manuais);
            $leads_manuais = $stmt_leads_manuais->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar leads manuais: " . $e->getMessage());
    }
}

// Buscar dados para filtros
$estados = [];
$segmentos = [];

if (isset($pdo_leads)) {
    try {
        $sql_estados = "SELECT DISTINCT UF FROM BASE_LEADS WHERE UF IS NOT NULL AND UF != '' ORDER BY UF";
        $stmt_estados = $pdo_leads->query($sql_estados);
        $estados = $stmt_estados->fetchAll(PDO::FETCH_COLUMN);
        
        $sql_segmentos = "SELECT DISTINCT Descricao1 FROM BASE_LEADS WHERE Descricao1 IS NOT NULL AND Descricao1 != '' ORDER BY Descricao1";
        $stmt_segmentos = $pdo_leads->query($sql_segmentos);
        $segmentos = $stmt_segmentos->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Ignorar erros nos filtros
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Leads - Autopel</title>
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
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/carteira.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/leads.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/leads-mobile-cards.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/leads-mobile-responsive.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/leads-manuais.css'); ?>?v=<?php echo time(); ?>">
    
    <!-- Debug: Forçar reload -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Meta tags para garantir compatibilidade -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no">
            <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include dirname(__DIR__, 2) . '/includes/components/navbar.php'; ?>
        <?php include dirname(__DIR__, 2) . '/includes/components/nav_hamburguer.php'; ?>
        
        <div class="dashboard-container">
            <main class="dashboard-main">
                <?php if (false): ?>
                    <!-- Seção removida - vendedores agora têm acesso aos leads -->
                <?php else: ?>
                    <!-- Conteúdo para usuários autorizados -->
                    <section class="welcome-section">
                        <h2>Leads Comerciais</h2>
                        
                        <?php if ($is_representante || $is_vendedor): ?>
                            <?php
                            $sql_cod_msg = "SELECT COD_VENDEDOR FROM USUARIOS WHERE EMAIL = ?";
                            $stmt_cod_msg = $pdo->prepare($sql_cod_msg);
                            $stmt_cod_msg->execute([$usuario['email']]);
                            $cod_vendedor_msg = $stmt_cod_msg->fetch(PDO::FETCH_COLUMN);
                            ?>
                            <p>Leads atribuídas ao seu código - Vendedor: <?php echo htmlspecialchars($cod_vendedor_msg ?: 'Não definido'); ?></p>
                        <?php elseif (strtolower(trim($usuario['perfil'])) === 'supervisor'): ?>
                            <p>Leads da sua equipe de supervisão - Código: <?php echo htmlspecialchars($usuario['cod_vendedor'] ?? 'Não definido'); ?></p>
                        <?php else: ?>
                            <p>Visualização geral de leads do sistema</p>
                        <?php endif; ?>
                    </section>
                    
                    <div class="leads-container" style="max-width: 100%; width: 100%;">
                                <?php if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin'])): ?>
                                <!-- Seletor de Visão -->
                        <div class="visao-selector-container" style="margin-bottom: 1rem; padding: 1.5rem; border-radius: 8px; max-width: 100%; width: 100%;">
                            <div class="visao-selector-header">
                                <h3 class="visao-selector-title">
                                    <i class="fas fa-eye"></i>
                                    <span class="visao-selector-title-text">Filtros de Visão</span>
                                </h3>
                            </div>
                            <div class="visao-selector" style="display: flex; align-items: center; gap: 1.5rem; justify-content: center; flex-wrap: wrap; max-width: 100%;">
                                <?php if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])): ?>
                                <div class="visao-selector-item" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <label for="visao_supervisor" class="visao-selector-label" style="font-weight: 600; margin: 0;">
                                        <i class="fas fa-users"></i>
                                        <span class="visao-label-text">Supervisor:</span>
                                    </label>
                                    <select id="visao_supervisor" name="visao_supervisor" onchange="mudarVisao('supervisor')" class="visao-selector-select" style="padding: 0.5rem; border-radius: 4px; min-width: 200px;">
                                        <option value="">Todas as Equipes</option>
                                        <?php
                                        try {
                                            // Supervisores da nova hierarquia: supervisores que possuem vendedores vinculados via COD_SUPER
                                            $sql_supervisores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO ";
                                            $sql_supervisores .= "FROM USUARIOS u ";
                                            $sql_supervisores .= "INNER JOIN USUARIOS v ON v.COD_SUPER = u.COD_VENDEDOR AND v.ATIVO = 1 AND v.PERFIL IN ('vendedor','representante') ";
                                            $sql_supervisores .= "WHERE u.ATIVO = 1 AND u.PERFIL IN ('supervisor', 'diretor') ";
                                                                        // Apenas supervisores com pelo menos um vendedor com leads
                            $sql_supervisores .= "AND (EXISTS (SELECT 1 FROM BASE_LEADS bl WHERE bl.CodigoVendedor = CAST(v.COD_VENDEDOR AS UNSIGNED)) OR EXISTS (SELECT 1 FROM BASE_LEADS bl WHERE bl.CodigoVendedor = CAST(u.COD_VENDEDOR AS UNSIGNED))) ";
                                            $sql_supervisores .= "ORDER BY u.NOME_COMPLETO";
                                            $stmt_supervisores = $pdo->prepare($sql_supervisores);
                                            $stmt_supervisores->execute();
                                            $supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (PDOException $e) {
                                            $supervisores = [];
                                        }
                                        $supervisor_sel = $_GET['visao_supervisor'] ?? '';
                                        foreach ($supervisores as $supervisor):
                                            $is_selected = (string)$supervisor_sel === (string)$supervisor['COD_VENDEDOR'];
                                        ?>
                                            <option value="<?php echo htmlspecialchars($supervisor['COD_VENDEDOR']); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supervisor['NOME_COMPLETO']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <div class="visao-selector-item" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <label for="visao_vendedor" class="visao-selector-label" style="font-weight: 600; margin: 0;">
                                        <i class="fas fa-user"></i>
                                        <span class="visao-label-text">Vendedor:</span>
                                    </label>
                                    <select id="visao_vendedor" name="visao_vendedor" onchange="mudarVisao('vendedor')" class="visao-selector-select" style="padding: 0.5rem; border-radius: 4px; min-width: 200px;">
                                        <option value="">Todos os Vendedores</option>
                                        <?php
                                        try {
                                            if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
                                                // Para supervisores, mostrar apenas vendedores da sua equipe (via COD_SUPER)
                                                $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor','representante') ";
                                                $sql_vendedores .= "AND EXISTS (SELECT 1 FROM BASE_LEADS bl WHERE bl.CodigoVendedor = CAST(USUARIOS.COD_VENDEDOR AS UNSIGNED)) ";
                                                $sql_vendedores .= "ORDER BY NOME_COMPLETO";
                                                $stmt_vendedores = $pdo->prepare($sql_vendedores);
                                                $stmt_vendedores->execute([$usuario['cod_vendedor']]);
                                            } elseif (!empty($supervisor_selecionado)) {
                                                // Se um supervisor foi selecionado, mostrar vendedores dele + o próprio supervisor
                                                $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO FROM USUARIOS WHERE (COD_SUPER = ? OR COD_VENDEDOR = ?) AND ATIVO = 1 AND PERFIL IN ('vendedor','representante','supervisor','diretor') ";
                                                $sql_vendedores .= "AND EXISTS (SELECT 1 FROM BASE_LEADS bl WHERE bl.CodigoVendedor = CAST(USUARIOS.COD_VENDEDOR AS UNSIGNED)) ";
                                                $sql_vendedores .= "ORDER BY NOME_COMPLETO";
                                                $stmt_vendedores = $pdo->prepare($sql_vendedores);
                                                $stmt_vendedores->execute([$supervisor_selecionado, $supervisor_selecionado]);
                                            } else {
                                                // Se nenhum supervisor foi selecionado, mostrar todos os vendedores
                                                $sql_vendedores = "SELECT COD_VENDEDOR, NOME_COMPLETO FROM USUARIOS WHERE ATIVO = 1 AND PERFIL IN ('vendedor','representante') ";
                                                $sql_vendedores .= "AND EXISTS (SELECT 1 FROM BASE_LEADS bl WHERE bl.CodigoVendedor = CAST(USUARIOS.COD_VENDEDOR AS UNSIGNED)) ";
                                                $sql_vendedores .= "ORDER BY NOME_COMPLETO";
                                                $stmt_vendedores = $pdo->prepare($sql_vendedores);
                                                $stmt_vendedores->execute();
                                            }
                                            $vendedores = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (PDOException $e) {
                                            $vendedores = [];
                                        }
                                        $vendedor_sel = $_GET['visao_vendedor'] ?? '';
                                        foreach ($vendedores as $vendedor):
                                            $is_selected_v = (string)$vendedor_sel === (string)$vendedor['COD_VENDEDOR'];
                                        ?>
                                            <option value="<?php echo htmlspecialchars($vendedor['COD_VENDEDOR']); ?>" <?php echo $is_selected_v ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($vendedor['NOME_COMPLETO']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- Filtros -->
                        <div class="filtros-container" style="max-width: 100%; width: 100%;">
                            <div class="filtros-form" style="max-width: 100%; width: 100%;">
                                <div class="filtros-content" style="max-width: 100%; width: 100%;">
                                    <form method="GET" class="filtros-form-left" style="max-width: 100%; width: 100%;">
                                        <!-- Preservar filtros de visão -->
                                        <?php if (!empty($supervisor_selecionado)): ?>
                                            <input type="hidden" name="visao_supervisor" value="<?php echo htmlspecialchars($supervisor_selecionado); ?>">
                                        <?php endif; ?>
                                        <?php if (!empty($vendedor_selecionado)): ?>
                                            <input type="hidden" name="visao_vendedor" value="<?php echo htmlspecialchars($vendedor_selecionado); ?>">
                                        <?php endif; ?>
                                        
                                        <div class="filtros-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; max-width: 100%;">
                                            <div class="filtro-grupo">
                                                <label for="filtro_nome">Nome do Lead:</label>
                                                <input type="text" name="filtro_nome" id="filtro_nome" value="<?php echo htmlspecialchars($filtro_nome); ?>" placeholder="Buscar por nome, razão social ou email...">
                                            </div>
                                            
                                            <div class="filtro-grupo">
                                                <label for="filtro_estado">Estado:</label>
                                                <select name="filtro_estado" id="filtro_estado">
                                                    <option value="">Todos</option>
                                                    <?php foreach ($estados as $estado): ?>
                                                        <option value="<?php echo htmlspecialchars($estado); ?>" <?php echo $filtro_estado === $estado ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($estado); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="filtro-grupo">
                                                <label for="filtro_segmento">Segmento:</label>
                                                <select name="filtro_segmento" id="filtro_segmento">
                                                    <option value="">Todos</option>
                                                    <?php foreach ($segmentos as $segmento): ?>
                                                        <option value="<?php echo htmlspecialchars($segmento); ?>" <?php echo $filtro_segmento === $segmento ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($segmento); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="filtro-grupo">
                                                <label for="filtro_valor_min">Valor Mínimo (R$):</label>
                                                <input type="number" name="filtro_valor_min" id="filtro_valor_min" value="<?php echo htmlspecialchars($filtro_valor_min); ?>" step="0.01" min="0">
                                            </div>
                                            
                                            <div class="filtro-grupo">
                                                <label for="filtro_valor_max">Valor Máximo (R$):</label>
                                                <input type="number" name="filtro_valor_max" id="filtro_valor_max" value="<?php echo htmlspecialchars($filtro_valor_max); ?>" step="0.01" min="0">
                                            </div>
                                        </div>
                                        
                                        <div class="filtros-acoes">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-filter"></i> Filtrar
                                            </button>
                                                                                <a href="leads.php" class="btn btn-limpar-filtros">
                                        <i class="fas fa-times"></i> Limpar Filtros
                                    </a>
                                        </div>
                                    </form>
                                    
                                    <!-- Estatísticas -->
                                    <div class="filtros-stats">
                                        <div class="stat-card">
                                            <h3><?php echo isset($total_registros) ? number_format($total_registros) : number_format($total_leads); ?></h3>
                                            <p><i class="fas fa-users"></i> Total de Leads</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações dos filtros -->
                        <?php 
                        $filtros_ativos = [];
                        // Visão selecionada
                        $visao_supervisor_txt = $_GET['visao_supervisor'] ?? '';
                        $visao_vendedor_txt = $_GET['visao_vendedor'] ?? '';
                        if (in_array(strtolower(trim($usuario['perfil'])), ['diretor','admin'])) {
                            if (!empty($visao_supervisor_txt)) {
                                // Supervisor já é o label da nova hierarquia
                                $filtros_ativos[] = "Supervisor: " . $visao_supervisor_txt;
                            }
                            if (!empty($visao_vendedor_txt)) {
                                try {
                                    $stmt_nome_vend = $pdo->prepare("SELECT NOME_COMPLETO FROM USUARIOS WHERE COD_VENDEDOR = ? LIMIT 1");
                                    $stmt_nome_vend->execute([$visao_vendedor_txt]);
                                    $nome_vend = $stmt_nome_vend->fetch(PDO::FETCH_COLUMN);
                                    $filtros_ativos[] = "Vendedor: " . ($nome_vend ?: $visao_vendedor_txt);
                                } catch (PDOException $e) {
                                    $filtros_ativos[] = "Vendedor: " . $visao_vendedor_txt;
                                }
                            }
                        } elseif (strtolower(trim($usuario['perfil'])) === 'supervisor') {
                            if (!empty($visao_vendedor_txt)) {
                                try {
                                    $stmt_nome_vend = $pdo->prepare("SELECT NOME_COMPLETO FROM USUARIOS WHERE COD_VENDEDOR = ? LIMIT 1");
                                    $stmt_nome_vend->execute([$visao_vendedor_txt]);
                                    $nome_vend = $stmt_nome_vend->fetch(PDO::FETCH_COLUMN);
                                    $filtros_ativos[] = "Vendedor: " . ($nome_vend ?: $visao_vendedor_txt);
                                } catch (PDOException $e) {
                                    $filtros_ativos[] = "Vendedor: " . $visao_vendedor_txt;
                                }
                            }
                        }
                        if (!empty($filtro_nome)) $filtros_ativos[] = "Nome: " . htmlspecialchars($filtro_nome);
                        if (!empty($filtro_estado)) $filtros_ativos[] = "Estado: " . $filtro_estado;
                        if (!empty($filtro_segmento)) $filtros_ativos[] = "Segmento: " . $filtro_segmento;
                        if (!empty($filtro_valor_min)) $filtros_ativos[] = "Valor mínimo: R$ " . number_format($filtro_valor_min, 2, ',', '.');
                        if (!empty($filtro_valor_max)) $filtros_ativos[] = "Valor máximo: R$ " . number_format($filtro_valor_max, 2, ',', '.');
                        
                        if ($is_representante || $is_vendedor) {
                            $sql_cod_info = "SELECT COD_VENDEDOR FROM USUARIOS WHERE EMAIL = ?";
                            $stmt_cod_info = $pdo->prepare($sql_cod_info);
                            $stmt_cod_info->execute([$usuario['email']]);
                            $cod_vendedor_info = $stmt_cod_info->fetch(PDO::FETCH_COLUMN);
                            if ($cod_vendedor_info) {
                                $filtros_ativos[] = "Código Vendedor automático: " . $cod_vendedor_info;
                            }
                        } elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && empty($vendedor_selecionado)) {
                            if ($usuario['cod_vendedor']) {
                                $filtros_ativos[] = "Equipe de supervisão automática: " . $usuario['cod_vendedor'];
                            }
                        }
                        
                        if (!empty($filtros_ativos)): ?>
                            <div class="filtros-info">
                                <i class="fas fa-filter"></i>
                                <strong>Filtros aplicados:</strong> <?php echo implode(' | ', $filtros_ativos); ?>
                                <span class="filtros-count">(<?php echo isset($total_registros) ? number_format($total_registros) : number_format($total_leads); ?> leads encontrados)</span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Navegação por Abas -->
                        <div class="tabs-container">
                            <ul class="nav nav-tabs" id="leadsTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="lista-tab" data-bs-toggle="tab" data-bs-target="#lista" type="button" role="tab" aria-controls="lista" aria-selected="true">
                                        <i class="fas fa-list"></i> 
                                        <span class="tab-text">Leads do Sistema</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="leads-manuais-tab" data-bs-toggle="tab" data-bs-target="#leads-manuais" type="button" role="tab" aria-controls="leads-manuais" aria-selected="false">
                                        <i class="fas fa-user-plus"></i> 
                                        <span class="tab-text">Leads Manuais</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="calendario-leads-tab" data-bs-toggle="tab" data-bs-target="#calendario-leads" type="button" role="tab" aria-controls="calendario-leads" aria-selected="false">
                                        <i class="fas fa-calendar-alt"></i> 
                                        <span class="tab-text">Calendário</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="tab-content" id="leadsTabContent">
                            <!-- Aba Lista -->
                            <div class="tab-pane fade show active" id="lista" role="tabpanel" aria-labelledby="lista-tab">
                        
                        <!-- Tabela de leads -->
                        <?php if (empty($leads)): ?>
                            <div class="no-leads">
                                <i class="fas fa-users"></i>
                                <h3>Nenhum lead encontrado</h3>
                                <p>Não há leads disponíveis no momento.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-width: 100%; width: 100%; overflow-x: auto;">
                                <table class="leads-table" id="leadsTable" style="width: 100%; min-width: 1000px;">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Telefone</th>
                                            <th>Endereço</th>
                                            <th>UF</th>
                                            <th>Vendedor Responsável</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leads as $lead): ?>
                                            <?php
                                                // UID robusto por linha para leads sem email (mesma assinatura usada no match)
                                                $nome_candidato = ($lead['nomefinal'] ?? '');
                                                if ($nome_candidato === '' && isset($lead['RAZAOSOCIAL']) && $lead['RAZAOSOCIAL'] !== '') { $nome_candidato = $lead['RAZAOSOCIAL']; }
                                                if ($nome_candidato === '' && isset($lead['NOMEFANTASIA']) && $lead['NOMEFANTASIA'] !== '') { $nome_candidato = $lead['NOMEFANTASIA']; }

                                                $nome_base = trim($nome_candidato);
                                                $razao_base = trim($lead['RAZAOSOCIAL'] ?? '');
                                                $fantasia_base = trim($lead['NOMEFANTASIA'] ?? '');
                                                $telefone_base = preg_replace('/\D+/', '', $lead['TelefonePrincipalFINAL'] ?? '');
                                                $endereco_base = trim($lead['endereoCNPJJA'] ?? '');
                                                $uf_base = trim($lead['UF'] ?? '');
                                                $vend_base = trim((string)($lead['CodigoVendedor'] ?? ''));
                                                $dataobs_base = trim($lead['DATAOBS'] ?? '');

                                                // Usar a chave estável calculada antes das edições
                                                $lead_uid = $lead['lead_uid_base'] ?? '';
                                            ?>
                                            <?php
                                                $email_cell = trim($lead['Email'] ?? '');
                                                // Usar o email original preservado para identificar a lead na edição
                                                $email_para_edicao = isset($lead['email_original']) ? trim($lead['email_original']) : $email_cell;
                                                // Normalizar para exibição apenas
                                                if ($email_cell === '' || $email_cell === '-' || strtoupper($email_cell) === 'N/A') {
                                                    $email_cell = '';
                                                }
                                            ?>
                                            <tr data-lead-email="<?php echo htmlspecialchars($email_cell, ENT_QUOTES); ?>" data-lead-uid="<?php echo htmlspecialchars($lead_uid, ENT_QUOTES); ?>" data-email-original="<?php echo htmlspecialchars($lead['email_original'] ?? '', ENT_QUOTES); ?>" data-tem-edicao="<?php echo isset($lead['tem_edicao']) && (int)$lead['tem_edicao'] === 1 ? '1' : '0'; ?>" data-cod-vendedor="<?php echo htmlspecialchars((string)($lead['CodigoVendedor'] ?? ''), ENT_QUOTES); ?>" data-dataobs="<?php echo htmlspecialchars($lead['DATAOBS'] ?? '', ENT_QUOTES); ?>" data-uf="<?php echo htmlspecialchars($lead['UF'] ?? '', ENT_QUOTES); ?>" data-telefone-original="<?php echo htmlspecialchars($lead['TelefonePrincipalFINAL'] ?? '', ENT_QUOTES); ?>" data-endereco="<?php echo htmlspecialchars($lead['endereoCNPJJA'] ?? '', ENT_QUOTES); ?>" data-nome="<?php echo htmlspecialchars($lead['nomefinal'] ?? '', ENT_QUOTES); ?>" data-razao="<?php echo htmlspecialchars($lead['RAZAOSOCIAL'] ?? '', ENT_QUOTES); ?>" data-fantasia="<?php echo htmlspecialchars($lead['NOMEFANTASIA'] ?? '', ENT_QUOTES); ?>">
                                                <td>
                                                    <div class="lead-nome">
                                                        <div class="lead-nome-content">
                                                            <?php echo htmlspecialchars($lead['nomefinal'] ?? $lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? 'N/A'); ?>
                                                            <?php 
                                                            // Badge com CNPJ
                                                            $cnpj = $lead['CNPJ'] ?? $lead['cnpj'] ?? $lead['Cnpj'] ?? '';
                                                            if (!empty($cnpj) && $cnpj !== 'N/A' && $cnpj !== '-'): 
                                                            ?>
                                                                <span class="cnpj-badge">
                                                                    <span class="badge bg-info"><?php echo htmlspecialchars($cnpj); ?></span>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($lead['Email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars(formatarTelefone($lead['TelefonePrincipalFINAL'] ?? 'N/A')); ?></td>
                                                <td><?php echo htmlspecialchars($lead['endereoCNPJJA'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge uf-badge"><?php echo htmlspecialchars($lead['UF'] ?? 'N/A'); ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Buscar nome do vendedor responsável
                                                    $vendedor_responsavel = 'N/A';
                                                    $codigo_vendedor = $lead['CodigoVendedor'] ?? '';
                                                    if (!empty($codigo_vendedor) && $codigo_vendedor !== 'N/A') {
                                                        try {
                                                            $stmt_vendedor = $pdo->prepare("SELECT NOME_COMPLETO FROM USUARIOS WHERE CAST(COD_VENDEDOR AS UNSIGNED) = ? LIMIT 1");
                                                            $stmt_vendedor->execute([intval($codigo_vendedor)]);
                                                            $nome_vendedor = $stmt_vendedor->fetch(PDO::FETCH_COLUMN);
                                                            if ($nome_vendedor) {
                                                                $vendedor_responsavel = '<div class="vendor-meta">
                                                                    <div class="vendor-name">' . htmlspecialchars($nome_vendedor) . '</div>
                                                                    <div class="vendor-code">Cód: ' . htmlspecialchars($codigo_vendedor) . '</div>
                                                                </div>';
                                                            } else {
                                                                $vendedor_responsavel = '<div class="vendor-meta">
                                                                    <div class="vendor-code">Código: ' . htmlspecialchars($codigo_vendedor) . '</div>
                                                                </div>';
                                                            }
                                                        } catch (PDOException $e) {
                                                            $vendedor_responsavel = '<div class="vendor-meta">
                                                                <div class="vendor-code">Código: ' . htmlspecialchars($codigo_vendedor) . '</div>
                                                            </div>';
                                                        }
                                                    } else {
                                                        $vendedor_responsavel = '<span class="badge bg-secondary">N/A</span>';
                                                    }
                                                    ?>
                                                    <?php echo $vendedor_responsavel; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-lead">Lead</span>
                                                </td>
                                                <td>
                                                    <div class="table-actions">
                                                        <?php 
                                                        // Preparar dados do lead para ligação
                                                        $leadEmail = $lead['Email'] ?? '';
                                                        $leadNome = $lead['nomefinal'] ?? $lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? 'Lead sem nome';
                                                        $leadTelefone = $lead['TelefonePrincipalFINAL'] ?? '';
                                                        $leadEndereco = $lead['endereoCNPJJA'] ?? '';
                                                        
                                                        // Sanitizar dados
                                                        $leadEmail = htmlspecialchars(trim($leadEmail), ENT_QUOTES, 'UTF-8');
                                                        $leadNome = htmlspecialchars(trim($leadNome), ENT_QUOTES, 'UTF-8');
                                                        $leadTelefone = htmlspecialchars(trim(formatarTelefone($leadTelefone)), ENT_QUOTES, 'UTF-8');
                                                        $leadEndereco = htmlspecialchars(trim($leadEndereco), ENT_QUOTES, 'UTF-8');
                                                        
                                                        // Validar dados
                                                        $emailValido = !empty($leadEmail) && $leadEmail !== 'N/A';
                                                        $nomeValido = !empty($leadNome) && $leadNome !== 'Lead sem nome';
                                                        $telefoneValido = !empty($leadTelefone) && $leadTelefone !== 'N/A';
                                                        
                                                        // Para o botão de telefone, só precisamos do telefone válido
                                                        $dadosCompletos = $emailValido && $nomeValido && $telefoneValido;
                                                        $dadosMinimosTelefone = $telefoneValido; // Apenas telefone válido
                                                        
                                                        // Verificar se pode ligar
                                                        $numeroLimpo = preg_replace('/\D/', '', $leadTelefone);
                                                        $podeLigar = !empty($leadTelefone) && $leadTelefone !== 'N/A' && strlen($numeroLimpo) >= 10;
                                                        ?>
                                                        
                                                        <!-- Coluna Verde - Visualização e Contato -->
                                                        <div class="actions-column actions-green">
                                                            <?php if ($podeLigar && $dadosMinimosTelefone): ?>
                                                                <button class="btn btn-sm btn-ligar" 
                                                                        data-email="<?php echo htmlspecialchars($leadEmail, ENT_QUOTES); ?>" 
                                                                        data-nome="<?php echo htmlspecialchars($leadNome, ENT_QUOTES); ?>" 
                                                                        data-telefone="<?php echo htmlspecialchars($leadTelefone, ENT_QUOTES); ?>" 
                                                                        title="Ligar">
                                                                    <i class="fas fa-phone"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="btn btn-sm btn-ligar disabled" style="cursor: not-allowed;" 
                                                                      title="<?php echo !$podeLigar ? 'Telefone não disponível' : 'Telefone inválido ou ausente'; ?>">
                                                                    <i class="fas fa-phone"></i>
                                                                </span>
                                                            <?php endif; ?>
                                                            
                                                            <button class="btn btn-success btn-sm" 
                                                                    onclick="abrirAgendamentoLead('<?php echo (!empty($leadEmail) && strtoupper($leadEmail) !== 'N/A') ? htmlspecialchars($leadEmail, ENT_QUOTES) : htmlspecialchars($lead_uid, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($leadNome, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($leadTelefone, ENT_QUOTES); ?>')" 
                                                                    title="Agendar Ligação">
                                                                <i class="fas fa-calendar-alt"></i>
                                                            </button>
                                                        </div>
                                                        
                                                        <!-- Coluna Amarela - Observações e Exportação -->
                                                        <div class="actions-column actions-yellow">
                                                            <?php 
                                                            // Criar chave única para observações usando novo formato padronizado
                                                            $email_obs = trim($lead['Email'] ?? '');
                                                            $telefone_obs = $lead['TelefonePrincipalFINAL'] ?? '';
                                                            $cep_obs = $lead['CEP'] ?? '';
                                                            
                                                            // Gerar identificador padronizado: email-telefone-cep
                                                            $chave_obs = gerarIdentificadorLead($email_obs, $telefone_obs, $cep_obs);
                                                            ?>
                                                            <button class="btn btn-warning btn-sm" title="Observações" 
                                                                    onclick="abrirObservacoes('lead', '<?php echo htmlspecialchars($chave_obs, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['NOMEFANTASIA'] ?? $lead['RAZAOSOCIAL'] ?? $lead['nomefinal'] ?? '', ENT_QUOTES); ?>')">
                                                                <i class="fas fa-comment"></i>
                                                            </button>
                                                            
                                                        </div>
                                                        
                                                        <!-- Coluna Vermelha - Edição e Exclusão -->
                                                        <div class="actions-column actions-red">
                                                            <?php if (in_array($usuario['perfil'], ['admin', 'vendedor', 'representante', 'supervisor', 'diretor'])): ?>
                                                                <?php $tem_edicao_btn = isset($lead['tem_edicao']) && (int)$lead['tem_edicao'] === 1; ?>
                                                                <?php if ($tem_edicao_btn): ?>
                                                                    <button class="btn btn-outline-danger btn-sm" title="Resetar Edição" 
                                                                            onclick="resetarEdicaoLead('<?php echo htmlspecialchars($lead['email_original'] ?? $email_para_edicao, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead_uid, ENT_QUOTES); ?>')">
                                                                        <i class="fas fa-rotate-left"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button class="btn btn-danger btn-sm" title="Editar Lead" 
                                                                        onclick="abrirEdicaoLead('<?php echo htmlspecialchars($email_para_edicao, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['nomefinal'] ?? $lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['TelefonePrincipalFINAL'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['endereoCNPJJA'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead_uid, ENT_QUOTES); ?>', event)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="btn btn-danger btn-sm disabled" style="cursor: not-allowed;" title="Funcionalidade em manutenção">
                                                                    <i class="fas fa-edit"></i>
                                                                </span>
                                                                <span class="emoji-manutencao">⚠️</span>
                                                            <?php endif; ?>
                                                            
                                                            <button class="btn btn-danger btn-sm" title="Excluir Lead" 
                                                                    onclick="confirmarExclusaoLead('<?php echo htmlspecialchars($lead['Email'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['NOMEFANTASIA'] ?? $lead['RAZAOSOCIAL'] ?? $lead['nomefinal'] ?? '', ENT_QUOTES); ?>', event)"
                                                                    data-lead-email="<?php echo htmlspecialchars($lead['Email'] ?? '', ENT_QUOTES); ?>"
                                                                    data-lead-uid="<?php echo htmlspecialchars($lead_uid, ENT_QUOTES); ?>">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Layout Mobile - Cards -->
                            <div class="mobile-layout-indicator">
                                <i class="fas fa-mobile-alt"></i>
                                Visualização otimizada para dispositivos móveis
                            </div>
                            <div class="mobile-cards-container">
                                <?php foreach ($leads as $lead): ?>
                                    <?php
                                        // UID robusto por linha para leads sem email (mesma assinatura usada no match)
                                        $nome_candidato = ($lead['nomefinal'] ?? '');
                                        if ($nome_candidato === '' && isset($lead['RAZAOSOCIAL']) && $lead['RAZAOSOCIAL'] !== '') { $nome_candidato = $lead['RAZAOSOCIAL']; }
                                        if ($nome_candidato === '' && isset($lead['NOMEFANTASIA']) && $lead['NOMEFANTASIA'] !== '') { $nome_candidato = $lead['NOMEFANTASIA']; }

                                        $nome_base = trim($nome_candidato);
                                        $razao_base = trim($lead['RAZAOSOCIAL'] ?? '');
                                        $fantasia_base = trim($lead['NOMEFANTASIA'] ?? '');
                                        $telefone_base = preg_replace('/\D+/', '', $lead['TelefonePrincipalFINAL'] ?? '');
                                        $endereco_base = trim($lead['endereoCNPJJA'] ?? '');
                                        $uf_base = trim($lead['UF'] ?? '');
                                        $vend_base = trim((string)($lead['CodigoVendedor'] ?? ''));
                                        $dataobs_base = trim($lead['DATAOBS'] ?? '');

                                        // Usar a chave estável calculada antes das edições
                                        $lead_uid = $lead['lead_uid_base'] ?? '';
                                        
                                        $email_cell = trim($lead['Email'] ?? '');
                                        // Usar o email original preservado para identificar a lead na edição
                                        $email_para_edicao = isset($lead['email_original']) ? trim($lead['email_original']) : $email_cell;
                                        // Normalizar para exibição apenas
                                        if ($email_cell === '' || $email_cell === '-' || strtoupper($email_cell) === 'N/A') {
                                            $email_cell = '';
                                        }
                                        
                                        // Preparar dados do lead para ligação
                                        $leadEmail = $lead['Email'] ?? '';
                                        $leadNome = $lead['nomefinal'] ?? $lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? 'Lead sem nome';
                                        $leadTelefone = $lead['TelefonePrincipalFINAL'] ?? '';
                                        $leadEndereco = $lead['endereoCNPJJA'] ?? '';
                                        
                                        // Sanitizar dados
                                        $leadEmail = htmlspecialchars(trim($leadEmail), ENT_QUOTES, 'UTF-8');
                                        $leadNome = htmlspecialchars(trim($leadNome), ENT_QUOTES, 'UTF-8');
                                        $leadTelefone = htmlspecialchars(trim(formatarTelefone($leadTelefone)), ENT_QUOTES, 'UTF-8');
                                        $leadEndereco = htmlspecialchars(trim($leadEndereco), ENT_QUOTES, 'UTF-8');
                                        
                                        // Validar dados
                                        $emailValido = !empty($leadEmail) && $leadEmail !== 'N/A';
                                        $nomeValido = !empty($leadNome) && $leadNome !== 'Lead sem nome';
                                        $telefoneValido = !empty($leadTelefone) && $leadTelefone !== 'N/A';
                                        
                                        // Para o botão de telefone, só precisamos do telefone válido
                                        $dadosCompletos = $emailValido && $nomeValido && $telefoneValido;
                                        $dadosMinimosTelefone = $telefoneValido; // Apenas telefone válido
                                        
                                        // Verificar se pode ligar
                                        $numeroLimpo = preg_replace('/\D/', '', $leadTelefone);
                                        $podeLigar = !empty($leadTelefone) && $leadTelefone !== 'N/A' && strlen($numeroLimpo) >= 10;
                                    ?>
                                    <div class="lead-card" 
                                         data-lead-email="<?php echo htmlspecialchars($email_cell, ENT_QUOTES); ?>" 
                                         data-lead-uid="<?php echo htmlspecialchars($lead_uid, ENT_QUOTES); ?>" 
                                         data-email-original="<?php echo htmlspecialchars($lead['email_original'] ?? '', ENT_QUOTES); ?>" 
                                         data-tem-edicao="<?php echo isset($lead['tem_edicao']) && (int)$lead['tem_edicao'] === 1 ? '1' : '0'; ?>" 
                                         data-cod-vendedor="<?php echo htmlspecialchars((string)($lead['CodigoVendedor'] ?? ''), ENT_QUOTES); ?>" 
                                         data-dataobs="<?php echo htmlspecialchars($lead['DATAOBS'] ?? '', ENT_QUOTES); ?>" 
                                         data-uf="<?php echo htmlspecialchars($lead['UF'] ?? '', ENT_QUOTES); ?>" 
                                         data-telefone-original="<?php echo htmlspecialchars($lead['TelefonePrincipalFINAL'] ?? '', ENT_QUOTES); ?>" 
                                         data-endereco="<?php echo htmlspecialchars($lead['endereoCNPJJA'] ?? '', ENT_QUOTES); ?>" 
                                         data-nome="<?php echo htmlspecialchars($lead['nomefinal'] ?? '', ENT_QUOTES); ?>" 
                                         data-razao="<?php echo htmlspecialchars($lead['RAZAOSOCIAL'] ?? '', ENT_QUOTES); ?>" 
                                         data-fantasia="<?php echo htmlspecialchars($lead['NOMEFANTASIA'] ?? '', ENT_QUOTES); ?>">
                                        
                                        <!-- Header do Card -->
                                        <div class="lead-card-header">
                                            <div class="lead-card-nome">
                                                <h4><?php echo htmlspecialchars($lead['nomefinal'] ?? $lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? 'N/A'); ?></h4>
                                                <?php 
                                                // Badge com CNPJ
                                                $cnpj = $lead['CNPJ'] ?? $lead['cnpj'] ?? $lead['Cnpj'] ?? '';
                                                if (!empty($cnpj) && $cnpj !== 'N/A' && $cnpj !== '-'): 
                                                ?>
                                                    <span class="cnpj-badge"><?php echo htmlspecialchars($cnpj); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="lead-card-fantasia"><?php echo htmlspecialchars($lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? ''); ?></p>
                                            <div class="lead-card-status">
                                                <span class="status-badge status-lead">Lead</span>
                                                <span class="uf-badge"><?php echo htmlspecialchars($lead['UF'] ?? 'N/A'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <!-- Informações do Lead -->
                                        <div class="lead-card-info">
                                            <div class="lead-info-grid">
                                                <div class="lead-info-item">
                                                    <span class="lead-info-label">Email</span>
                                                    <span class="lead-info-value email"><?php echo htmlspecialchars($lead['Email'] ?? 'N/A'); ?></span>
                                                </div>
                                                
                                                <div class="lead-info-item">
                                                    <span class="lead-info-label">Telefone</span>
                                                    <span class="lead-info-value telefone"><?php echo htmlspecialchars(formatarTelefone($lead['TelefonePrincipalFINAL'] ?? 'N/A')); ?></span>
                                                </div>
                                                
                                                <div class="lead-info-item">
                                                    <span class="lead-info-label">Endereço</span>
                                                    <span class="lead-info-value"><?php echo htmlspecialchars($lead['endereoCNPJJA'] ?? 'N/A'); ?></span>
                                                </div>
                                                
                                                <div class="lead-info-item">
                                                    <span class="lead-info-label">Vendedor</span>
                                                    <span class="lead-info-value">
                                                        <?php
                                                        // Buscar nome do vendedor responsável
                                                        $vendedor_responsavel = 'N/A';
                                                        $codigo_vendedor = $lead['CodigoVendedor'] ?? '';
                                                        if (!empty($codigo_vendedor) && $codigo_vendedor !== 'N/A') {
                                                            try {
                                                                $stmt_vendedor = $pdo->prepare("SELECT NOME_COMPLETO FROM USUARIOS WHERE CAST(COD_VENDEDOR AS UNSIGNED) = ? LIMIT 1");
                                                                $stmt_vendedor->execute([intval($codigo_vendedor)]);
                                                                $nome_vendedor = $stmt_vendedor->fetch(PDO::FETCH_COLUMN);
                                                                if ($nome_vendedor) {
                                                                    $vendedor_responsavel = $nome_vendedor . ' (Cód: ' . $codigo_vendedor . ')';
                                                                } else {
                                                                    $vendedor_responsavel = 'Código: ' . $codigo_vendedor;
                                                                }
                                                            } catch (PDOException $e) {
                                                                $vendedor_responsavel = 'Código: ' . $codigo_vendedor;
                                                            }
                                                        }
                                                        echo htmlspecialchars($vendedor_responsavel);
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Ações do Card -->
                                        <div class="lead-card-actions">
                                            <div class="lead-actions-single-row">
                                                <?php if ($podeLigar && $dadosMinimosTelefone): ?>
                                                    <button class="btn btn-primary-action btn-ligar" 
                                                            data-email="<?php echo htmlspecialchars($leadEmail, ENT_QUOTES); ?>" 
                                                            data-nome="<?php echo htmlspecialchars($leadNome, ENT_QUOTES); ?>" 
                                                            data-telefone="<?php echo htmlspecialchars($leadTelefone, ENT_QUOTES); ?>" 
                                                            title="Ligar">
                                                        <i class="fas fa-phone"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="btn btn-primary-action btn-ligar disabled" 
                                                          title="<?php echo !$podeLigar ? 'Telefone não disponível' : 'Telefone inválido ou ausente'; ?>">
                                                        <i class="fas fa-phone"></i>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-primary-action" 
                                                        onclick="abrirAgendamentoLead('<?php echo (!empty($leadEmail) && strtoupper($leadEmail) !== 'N/A') ? htmlspecialchars($leadEmail, ENT_QUOTES) : htmlspecialchars($lead_uid, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($leadNome, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($leadTelefone, ENT_QUOTES); ?>')" 
                                                        title="Agendar Ligação">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </button>
                                                
                                                <?php 
                                                // Criar chave única para observações (versão mobile) usando novo formato padronizado
                                                $email_obs_mobile = trim($lead['Email'] ?? '');
                                                $telefone_obs_mobile = $lead['TelefonePrincipalFINAL'] ?? '';
                                                $cep_obs_mobile = $lead['CEP'] ?? '';
                                                
                                                // Gerar identificador padronizado: email-telefone-cep
                                                $chave_obs_mobile = gerarIdentificadorLead($email_obs_mobile, $telefone_obs_mobile, $cep_obs_mobile);
                                                ?>
                                                <button class="btn btn-warning-action" title="Observações" 
                                                        onclick="abrirObservacoes('lead', '<?php echo htmlspecialchars($chave_obs_mobile, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['NOMEFANTASIA'] ?? $lead['RAZAOSOCIAL'] ?? $lead['nomefinal'] ?? '', ENT_QUOTES); ?>')">
                                                    <i class="fas fa-comment"></i>
                                                </button>
                                                
                                                <?php if (in_array($usuario['perfil'], ['admin', 'vendedor', 'representante', 'supervisor', 'diretor'])): ?>
                                                    <?php $tem_edicao_btn = isset($lead['tem_edicao']) && (int)$lead['tem_edicao'] === 1; ?>
                                                    <?php if ($tem_edicao_btn): ?>
                                                        <button class="btn btn-warning-action" title="Resetar Edição" 
                                                                onclick="resetarEdicaoLead('<?php echo htmlspecialchars($lead['email_original'] ?? $email_para_edicao, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead_uid, ENT_QUOTES); ?>')">
                                                            <i class="fas fa-rotate-left"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-danger-action" title="Editar Lead" 
                                                            onclick="abrirEdicaoLead('<?php echo htmlspecialchars($email_para_edicao, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['nomefinal'] ?? $lead['RAZAOSOCIAL'] ?? $lead['NOMEFANTASIA'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['TelefonePrincipalFINAL'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['endereoCNPJJA'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead_uid, ENT_QUOTES); ?>', event)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="btn btn-danger-action disabled" title="Funcionalidade em manutenção">
                                                        <i class="fas fa-edit"></i>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-danger-action" title="Excluir Lead" 
                                                        onclick="confirmarExclusaoLead('<?php echo htmlspecialchars($lead['Email'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead['NOMEFANTASIA'] ?? $lead['RAZAOSOCIAL'] ?? $lead['nomefinal'] ?? '', ENT_QUOTES); ?>', event)"
                                                        data-lead-email="<?php echo htmlspecialchars($lead['Email'] ?? '', ENT_QUOTES); ?>"
                                                        data-lead-uid="<?php echo htmlspecialchars($lead_uid, ENT_QUOTES); ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Paginação -->
                            <?php if (isset($total_paginas) && $total_paginas > 1): ?>
                                <div class="paginacao-container">
                                    <div class="paginacao-info">
                                        <span class="paginacao-info-text">
                                            <i class="fas fa-info-circle"></i>
                                            Mostrando <?php echo ($offset + 1); ?> a <?php echo min($offset + $itens_por_pagina, $total_registros); ?> de <?php echo number_format($total_registros); ?> leads
                                        </span>
                                    </div>
                                    
                                    <div class="paginacao-controles">
                                        <div class="paginacao-navegacao">
                                            <?php if ($pagina_atual > 1): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>" class="btn btn-secondary btn-sm paginacao-btn">
                                                    <i class="fas fa-angle-double-left"></i> 
                                                    <span class="paginacao-btn-text">Primeira</span>
                                                </a>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])); ?>" class="btn btn-secondary btn-sm paginacao-btn">
                                                    <i class="fas fa-angle-left"></i> 
                                                    <span class="paginacao-btn-text">Anterior</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="paginacao-numeros">
                                            <?php
                                            $inicio = max(1, $pagina_atual - 2);
                                            $fim = min($total_paginas, $pagina_atual + 2);
                                            
                                            if ($inicio > 1): ?>
                                                <span class="paginacao-ellipsis">...</span>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" 
                                                   class="btn <?php echo $i == $pagina_atual ? 'btn-primary' : 'btn-secondary'; ?> btn-sm paginacao-numero">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                            
                                            <?php if ($fim < $total_paginas): ?>
                                                <span class="paginacao-ellipsis">...</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="paginacao-navegacao">
                                            <?php if ($pagina_atual < $total_paginas): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])); ?>" class="btn btn-secondary btn-sm paginacao-btn">
                                                    <span class="paginacao-btn-text">Próxima</span> 
                                                    <i class="fas fa-angle-right"></i>
                                                </a>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>" class="btn btn-secondary btn-sm paginacao-btn">
                                                    <span class="paginacao-btn-text">Última</span> 
                                                    <i class="fas fa-angle-double-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                            </div>
                            
                            <!-- Aba Leads Manuais -->
                            <div class="tab-pane fade" id="leads-manuais" role="tabpanel" aria-labelledby="leads-manuais-tab">
                                <div class="leads-manuais-container">
                                    <div class="leads-manuais-header">
                                        <h3><i class="fas fa-user-plus"></i> Leads Cadastrados Manualmente</h3>
                                        <p>Leads que você cadastrou diretamente no sistema</p>
                                        <div class="leads-manuais-stats">
                                            <div class="stat-card">
                                                <h4><?php echo number_format($total_leads_manuais); ?></h4>
                                                <p><i class="fas fa-users"></i> Total de Leads Manuais</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (empty($leads_manuais)): ?>
                                        <div class="no-leads">
                                            <i class="fas fa-user-plus"></i>
                                            <h3>Nenhum lead manual encontrado</h3>
                                            <p>Você ainda não cadastrou nenhum lead manualmente.</p>
                                            <a href="cadastro.php" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Cadastrar Primeiro Lead
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="leads-table leads-manuais-table">
                                                <thead>
                                                    <tr>
                                                        <th>Nome</th>
                                                        <th>Email</th>
                                                        <th>Telefone</th>
                                                        <th>Endereço</th>
                                                        <th>Estado</th>
                                                        <th>Segmento</th>
                                                        <th>Valor Estimado</th>
                                                        <th>Data Cadastro</th>
                                                        <th>Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($leads_manuais as $lead_manual): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="lead-nome">
                                                                    <div class="lead-nome-content">
                                                                        <?php echo htmlspecialchars($lead_manual['nome'] ?? 'N/A'); ?>
                                                                        <?php if (!empty($lead_manual['nome_fantasia'])): ?>
                                                                            <br><small class="text-muted"><?php echo htmlspecialchars($lead_manual['nome_fantasia']); ?></small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($lead_manual['email'] ?? 'N/A'); ?></td>
                                                            <td><?php echo htmlspecialchars(formatarTelefone($lead_manual['telefone'] ?? 'N/A')); ?></td>
                                                            <td>
                                                                <?php 
                                                                $endereco_completo = $lead_manual['endereco'] ?? '';
                                                                if (!empty($lead_manual['complemento'])) {
                                                                    $endereco_completo .= ', ' . $lead_manual['complemento'];
                                                                }
                                                                echo htmlspecialchars($endereco_completo ?: 'N/A'); 
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge uf-badge"><?php echo htmlspecialchars($lead_manual['estado'] ?? 'N/A'); ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-info"><?php echo htmlspecialchars($lead_manual['segmento_atuacao'] ?? 'N/A'); ?></span>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($lead_manual['valor_estimado'])): ?>
                                                                    <span class="valor-estimado">R$ <?php echo number_format($lead_manual['valor_estimado'], 2, ',', '.'); ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">N/A</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="data-cadastro">
                                                                    <?php echo date('d/m/Y H:i', strtotime($lead_manual['data_cadastro'])); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="table-actions">
                                                                    <!-- Coluna Verde - Contato -->
                                                                    <div class="actions-column actions-green">
                                                                        <?php 
                                                                        $telefone_valido = !empty($lead_manual['telefone']) && $lead_manual['telefone'] !== 'N/A';
                                                                        $numero_limpo = preg_replace('/\D/', '', $lead_manual['telefone'] ?? '');
                                                                        $pode_ligar = $telefone_valido && strlen($numero_limpo) >= 10;
                                                                        ?>
                                                                        <?php if ($pode_ligar): ?>
                                                                            <button class="btn btn-sm btn-ligar" 
                                                                                    data-email="<?php echo htmlspecialchars($lead_manual['email'] ?? '', ENT_QUOTES); ?>" 
                                                                                    data-nome="<?php echo htmlspecialchars($lead_manual['nome'] ?? '', ENT_QUOTES); ?>" 
                                                                                    data-telefone="<?php echo htmlspecialchars(formatarTelefone($lead_manual['telefone'] ?? ''), ENT_QUOTES); ?>" 
                                                                                    title="Ligar">
                                                                                <i class="fas fa-phone"></i>
                                                                            </button>
                                                                        <?php else: ?>
                                                                            <span class="btn btn-sm btn-ligar disabled" style="cursor: not-allowed;" 
                                                                                  title="Telefone não disponível">
                                                                                <i class="fas fa-phone"></i>
                                                                            </span>
                                                                        <?php endif; ?>
                                                                        
                                                                        <button class="btn btn-success btn-sm" 
                                                                                onclick="abrirAgendamentoLead('<?php echo htmlspecialchars($lead_manual['email'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead_manual['nome'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars(formatarTelefone($lead_manual['telefone'] ?? ''), ENT_QUOTES); ?>')" 
                                                                                title="Agendar Ligação">
                                                                            <i class="fas fa-calendar-alt"></i>
                                                                        </button>
                                                                    </div>
                                                                    
                                                                    <!-- Coluna Amarela - Observações -->
                                                                    <div class="actions-column actions-yellow">
                                                                        <button class="btn btn-warning btn-sm" title="Observações" 
                                                                                onclick="abrirObservacoes('lead_manual', '<?php echo htmlspecialchars($lead_manual['id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($lead_manual['nome'] ?? '', ENT_QUOTES); ?>')">
                                                                            <i class="fas fa-comment"></i>
                                                                        </button>
                                                                    </div>
                                                                    
                                                                    <!-- Coluna Vermelha - Edição e Exclusão -->
                                                                    <div class="actions-column actions-red">
                                                                        <?php if (in_array($usuario['perfil'], ['admin', 'vendedor', 'representante', 'supervisor', 'diretor'])): ?>
                                                                            <button class="btn btn-danger btn-sm" title="Editar Lead" 
                                                                                    onclick="editarLeadManual(<?php echo $lead_manual['id']; ?>)">
                                                                                <i class="fas fa-edit"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                        
                                                                        <button class="btn btn-danger btn-sm" title="Excluir Lead" 
                                                                                onclick="confirmarExclusaoLeadManual(<?php echo $lead_manual['id']; ?>, '<?php echo htmlspecialchars($lead_manual['nome'] ?? '', ENT_QUOTES); ?>')">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="leads-manuais-footer">
                                            <p class="text-muted">
                                                <i class="fas fa-info-circle"></i> 
                                                Mostrando os últimos 100 leads manuais. 
                                                <a href="cadastro.php">Cadastrar novo lead</a>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Aba Calendário -->
                            <div class="tab-pane fade" id="calendario-leads" role="tabpanel" aria-labelledby="calendario-leads-tab">
                                <?php include dirname(__DIR__, 2) . '/includes/calendar/calendario_agendamentos_unificado.php'; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
            
            <footer class="dashboard-footer">
                <p>© 2025 Autopel - Todos os direitos reservados</p>
                <p class="system-info">Sistema BI v1.0</p>
            </footer>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo base_url('assets/js/leads.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="<?php echo base_url('assets/js/calendario_unificado.js'); ?>?v=<?php echo time(); ?>"></script>
    
    <!-- Forçar reload da página -->
    <script>
        // Forçar reload se houver parâmetro v=1
        if (window.location.search.includes('v=1')) {
            console.log('Forçando reload da página...');
        }
        
        // Inicializar abas do Bootstrap corretamente
        document.addEventListener('DOMContentLoaded', function() {
            // Aguardar Bootstrap carregar
            function initTabs() {
                if (typeof bootstrap === 'undefined' || !bootstrap.Tab) {
                    setTimeout(initTabs, 100);
                    return;
                }
                
                // Usar API nativa do Bootstrap
                const tabTriggers = document.querySelectorAll('[data-bs-toggle="tab"]');
                tabTriggers.forEach(function(trigger) {
                    trigger.addEventListener('click', function(event) {
                        event.preventDefault();
                        const tab = new bootstrap.Tab(this);
                        tab.show();
                    });
                });
            }
            
            initTabs();
        });
    </script>
    
    <!-- Modal para edição de lead manual -->
    <div class="modal fade" id="modalEditarLeadManual" tabindex="-1" aria-labelledby="modalEditarLeadManualLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarLeadManualLabel">
                        <i class="fas fa-edit"></i> Editar Lead Manual
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarLeadManual">
                        <input type="hidden" id="lead_id_editar" name="lead_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nome_editar" class="form-label">Nome/Razão Social <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nome_editar" name="nome" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nome_fantasia_editar" class="form-label">Nome Fantasia</label>
                                    <input type="text" class="form-control" id="nome_fantasia_editar" name="nome_fantasia">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email_editar" class="form-label">E-mail <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email_editar" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telefone_editar" class="form-label">Telefone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="telefone_editar" name="telefone" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="endereco_editar" class="form-label">Endereço <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="endereco_editar" name="endereco" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="complemento_editar" class="form-label">Complemento</label>
                                    <input type="text" class="form-control" id="complemento_editar" name="complemento">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="cep_editar" class="form-label">CEP <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="cep_editar" name="cep" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="bairro_editar" class="form-label">Bairro <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="bairro_editar" name="bairro" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="municipio_editar" class="form-label">Município <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="municipio_editar" name="municipio" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="estado_editar" class="form-label">Estado <span class="text-danger">*</span></label>
                                    <select class="form-control" id="estado_editar" name="estado" required>
                                        <option value="">Selecione</option>
                                        <option value="AC">Acre</option>
                                        <option value="AL">Alagoas</option>
                                        <option value="AP">Amapá</option>
                                        <option value="AM">Amazonas</option>
                                        <option value="BA">Bahia</option>
                                        <option value="CE">Ceará</option>
                                        <option value="DF">Distrito Federal</option>
                                        <option value="ES">Espírito Santo</option>
                                        <option value="GO">Goiás</option>
                                        <option value="MA">Maranhão</option>
                                        <option value="MT">Mato Grosso</option>
                                        <option value="MS">Mato Grosso do Sul</option>
                                        <option value="MG">Minas Gerais</option>
                                        <option value="PA">Pará</option>
                                        <option value="PB">Paraíba</option>
                                        <option value="PR">Paraná</option>
                                        <option value="PE">Pernambuco</option>
                                        <option value="PI">Piauí</option>
                                        <option value="RJ">Rio de Janeiro</option>
                                        <option value="RN">Rio Grande do Norte</option>
                                        <option value="RS">Rio Grande do Sul</option>
                                        <option value="RO">Rondônia</option>
                                        <option value="RR">Roraima</option>
                                        <option value="SC">Santa Catarina</option>
                                        <option value="SP">São Paulo</option>
                                        <option value="SE">Sergipe</option>
                                        <option value="TO">Tocantins</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="cnpj_editar" class="form-label">CNPJ</label>
                                    <input type="text" class="form-control" id="cnpj_editar" name="cnpj">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="inscricao_estadual_editar" class="form-label">Inscrição Estadual</label>
                                    <input type="text" class="form-control" id="inscricao_estadual_editar" name="inscricao_estadual">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="segmento_atuacao_editar" class="form-label">Segmento <span class="text-danger">*</span></label>
                                    <select class="form-control" id="segmento_atuacao_editar" name="segmento_atuacao" required>
                                        <option value="">Selecione</option>
                                        <option value="Automotivo">Automotivo</option>
                                        <option value="Industrial">Industrial</option>
                                        <option value="Comercial">Comercial</option>
                                        <option value="Residencial">Residencial</option>
                                        <option value="Agrícola">Agrícola</option>
                                        <option value="Outros">Outros</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="valor_estimado_editar" class="form-label">Valor Estimado (R$)</label>
                                    <input type="number" class="form-control" id="valor_estimado_editar" name="valor_estimado" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="observacoes_editar" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoes_editar" name="observacoes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarEdicaoLeadManual()">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- Script para inicialização -->
    <script>
        // Função para mudar a visão (supervisor/vendedor)
        function mudarVisao(contexto) {
            try {
                const supervisorSelect = document.getElementById('visao_supervisor');
                const vendedorSelect = document.getElementById('visao_vendedor');

                const currentUrl = new URL(window.location.href);

                // Atualizar parâmetro do supervisor
                if (supervisorSelect) {
                    const supVal = supervisorSelect.value || '';
                    if (supVal) {
                        currentUrl.searchParams.set('visao_supervisor', supVal);
                    } else {
                        currentUrl.searchParams.delete('visao_supervisor');
                    }
                } else {
                    currentUrl.searchParams.delete('visao_supervisor');
                }

                // Atualizar parâmetro do vendedor
                if (vendedorSelect) {
                    const vendVal = vendedorSelect.value || '';
                    if (vendVal) {
                        currentUrl.searchParams.set('visao_vendedor', vendVal);
                    } else {
                        currentUrl.searchParams.delete('visao_vendedor');
                    }
                } else {
                    currentUrl.searchParams.delete('visao_vendedor');
                }

                // Se o contexto for mudança de supervisor, resetar vendedor
                if (contexto === 'supervisor') {
                    currentUrl.searchParams.delete('visao_vendedor');
                }

                // Removido: parametro somente_com_leads (padrão sempre ativo)

                // Sempre resetar a paginação ao mudar a visão
                currentUrl.searchParams.delete('pagina');

                // Navegar
                window.location.href = currentUrl.pathname + (currentUrl.searchParams.toString() ? '?' + currentUrl.searchParams.toString() : '');
            } catch (err) {
                console.error('Erro ao mudar visão:', err);
            }
        }
 
        // Verificar se há erros de JavaScript
        window.addEventListener('error', function(e) {
            console.error('Erro JavaScript detectado:', e.error);
        });
        
        // Verificar se o DOM está carregado
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado com sucesso');
        });
        
        // Funções para gerenciar leads manuais
        function editarLeadManual(leadId) {
            // Buscar dados do lead
            fetch(window.baseUrl('includes/api/buscar_lead_manual.php') + `?lead_id=${leadId}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Preencher o formulário
                    document.getElementById('lead_id_editar').value = data.data.id;
                    document.getElementById('nome_editar').value = data.data.nome || '';
                    document.getElementById('nome_fantasia_editar').value = data.data.nome_fantasia || '';
                    document.getElementById('email_editar').value = data.data.email || '';
                    document.getElementById('telefone_editar').value = data.data.telefone || '';
                    document.getElementById('endereco_editar').value = data.data.endereco || '';
                    document.getElementById('complemento_editar').value = data.data.complemento || '';
                    document.getElementById('cep_editar').value = data.data.cep || '';
                    document.getElementById('bairro_editar').value = data.data.bairro || '';
                    document.getElementById('municipio_editar').value = data.data.municipio || '';
                    document.getElementById('estado_editar').value = data.data.estado || '';
                    document.getElementById('cnpj_editar').value = data.data.cnpj || '';
                    document.getElementById('inscricao_estadual_editar').value = data.data.inscricao_estadual || '';
                    document.getElementById('segmento_atuacao_editar').value = data.data.segmento_atuacao || '';
                    document.getElementById('valor_estimado_editar').value = data.data.valor_estimado || '';
                    document.getElementById('observacoes_editar').value = data.data.observacoes || '';
                    
                    // Abrir modal
                    const modal = new bootstrap.Modal(document.getElementById('modalEditarLeadManual'));
                    modal.show();
                } else {
                    alert('Erro ao carregar dados do lead: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao carregar dados do lead');
            });
        }
        
        function salvarEdicaoLeadManual() {
            const form = document.getElementById('formEditarLeadManual');
            const formData = new FormData(form);
            
            fetch(window.baseUrl('includes/crud/editar_lead_manual.php'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Lead atualizado com sucesso!');
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarLeadManual'));
                    modal.hide();
                    // Recarregar página para mostrar as alterações
                    location.reload();
                } else {
                    alert('Erro ao atualizar lead: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao atualizar lead: ' + error.message);
            });
        }
        
        function confirmarExclusaoLeadManual(leadId, nomeLead) {
            if (confirm(`Tem certeza que deseja excluir o lead "${nomeLead}"?\n\nEsta ação não pode ser desfeita.`)) {
                excluirLeadManual(leadId);
            }
        }
        
        function excluirLeadManual(leadId) {
            const formData = new FormData();
            formData.append('lead_id', leadId);
            
            fetch(window.baseUrl('includes/crud/excluir_lead_manual.php'), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Lead excluído com sucesso!');
                    // Recarregar página para remover o lead da lista
                    location.reload();
                } else {
                    alert('Erro ao excluir lead: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir lead: ' + error.message);
            });
        }
    </script>

    <?php // Modais necessários para seleção de tipo de contato e roteiro de ligação ?>
    <?php include dirname(__DIR__, 2) . '/includes/modal/modais_leads.php'; ?>

    <?php // Scripts necessários para fluxo de contato (seleção de tipo + roteiro) ?>
    <script src="<?php echo base_url('assets/js/ligacao.js'); ?>?v=<?php echo time(); ?>"></script>
</body>
</html>






