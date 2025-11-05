<?php
require_once dirname(__DIR__, 2) . '/includes/config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Sistema de cache simples para otimização
class SimpleCache {
    private static $cache = [];
    
    public static function get($key) {
        return self::$cache[$key] ?? null;
    }
    
    public static function set($key, $value, $ttl = 300) { // 5 minutos por padrão
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }
    
    public static function has($key) {
        if (!isset(self::$cache[$key])) {
            return false;
        }
        
        if (time() > self::$cache[$key]['expires']) {
            unset(self::$cache[$key]);
            return false;
        }
        
        return true;
    }
}

// Verificação de login e permissão
if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once dirname(__DIR__, 2) . '/includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$perfilUsuario = strtolower($usuario['perfil'] ?? '');
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar permissões combinadas
$perfil_permitido_ligacoes = in_array($perfilUsuario, ['diretor', 'supervisor', 'admin']);
$perfil_permitido_admin = in_array($perfilUsuario, ['admin', 'diretor', 'supervisor']);

// Usuários com perfil licitação não podem acessar gestão administrativa
if ($perfilUsuario === 'licitação') {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'contratos');
    exit;
}

if (!$perfil_permitido_ligacoes && !$perfil_permitido_admin) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'home');
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/config/conexao.php';

// Controle de abas
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['ligacoes', 'admin', 'metas']) ? $_GET['tab'] : 'ligacoes';

// Se não tem permissão para ligações, forçar aba admin
if (!$perfil_permitido_ligacoes && $tab === 'ligacoes') {
    $tab = 'admin';
}

// Se não tem permissão para admin, forçar aba ligações
if (!$perfil_permitido_admin && $tab === 'admin') {
    $tab = 'ligacoes';
}

// Se não tem permissão para metas, forçar aba ligações
if (!in_array($perfilUsuario, ['admin', 'diretor', 'supervisor']) && $tab === 'metas') {
    $tab = 'ligacoes';
}

// Variáveis para controle de dados
$ligacoes = [];
$estatisticas = [];
$clientesExcluidos = [];
$leadsExcluidos = [];
$observacoes = [];
$observacoesExcluidas = [];
$clientesLixao = [];
$usuarios_metas = [];

// Contadores para as abas
$totalLigacoes = 0;
$totalExcluidos = 0;
$totalLeadsExcluidos = 0;
$totalObservacoes = 0;
$totalObservacoesExcluidas = 0;
$totalLixao = 0;
$totalMetas = 0;
$totalPaginasMetas = 1;

// Variáveis de paginação para metas (disponíveis globalmente)
$pagina_metas = max(1, intval($_GET['pagina_metas'] ?? 1));
$por_pagina_metas = 20;
$offset_metas = ($pagina_metas - 1) * $por_pagina_metas;

// ===== LÓGICA DA ABA LIGAÇÕES =====
if ($tab === 'ligacoes' && $perfil_permitido_ligacoes) {
    // Processar filtros
    $filtro_status = $_GET['filtro_status'] ?? '';
    $filtro_data_inicio = $_GET['filtro_data_inicio'] ?? '';
    $filtro_data_fim = $_GET['filtro_data_fim'] ?? '';
    $filtro_usuario = $_GET['filtro_usuario'] ?? '';
    $filtro_cliente = $_GET['filtro_cliente'] ?? '';
    $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
    $vendedor_selecionado = $_GET['visao_vendedor'] ?? '';

    // Configurações de paginação
    $itens_por_pagina = 20;
    $pagina_atual = max(1, intval($_GET['pagina'] ?? 1));
    $offset = ($pagina_atual - 1) * $itens_por_pagina;

    // Construir condições WHERE baseadas no perfil do usuário e filtros
    $where_conditions = ["1=1"];
    $params = [];

    // Aplicar filtros baseados no perfil do usuário
    if (strtolower(trim($usuario['perfil'])) === 'vendedor' && !empty($usuario['cod_vendedor'])) {
        $where_conditions[] = "l.usuario_id = ?";
        $params[] = $usuario['id'];
    }
    elseif (strtolower(trim($usuario['perfil'])) === 'representante' && !empty($usuario['cod_vendedor'])) {
        $where_conditions[] = "l.usuario_id = ?";
        $params[] = $usuario['id'];
    }
    elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        if (!empty($vendedor_selecionado)) {
            $sql_buscar_id = "SELECT ID FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1";
            $stmt_buscar_id = $pdo->prepare($sql_buscar_id);
            $stmt_buscar_id->execute([$vendedor_selecionado]);
            $usuario_encontrado = $stmt_buscar_id->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_encontrado) {
                $where_conditions[] = "l.usuario_id = ?";
                $params[] = $usuario_encontrado['ID'];
            }
        } else {
            $where_conditions[] = "u.COD_SUPER = ?";
            $params[] = $usuario['cod_vendedor'];
        }
    }
    elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
        if (!empty($vendedor_selecionado)) {
            $sql_buscar_id = "SELECT ID FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1";
            $stmt_buscar_id = $pdo->prepare($sql_buscar_id);
            $stmt_buscar_id->execute([$vendedor_selecionado]);
            $usuario_encontrado = $stmt_buscar_id->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_encontrado) {
                $where_conditions[] = "l.usuario_id = ?";
                $params[] = $usuario_encontrado['ID'];
            }
        } elseif (!empty($supervisor_selecionado)) {
            $where_conditions[] = "u.COD_SUPER = ?";
            $params[] = $supervisor_selecionado;
        }
    }

    // Não mostrar ligações excluídas
    $where_conditions[] = "l.status != 'excluida'";

    // Aplicar filtros adicionais
    if (!empty($filtro_status)) {
        if ($filtro_status === 'finalizada' || $filtro_status === 'cancelada') {
            $where_conditions[] = "l.status = ?";
            $params[] = $filtro_status;
        }
    }

    if (!empty($filtro_data_inicio)) {
        $where_conditions[] = "DATE(l.data_ligacao) >= ?";
        $params[] = $filtro_data_inicio;
    }

    if (!empty($filtro_data_fim)) {
        $where_conditions[] = "DATE(l.data_ligacao) <= ?";
        $params[] = $filtro_data_fim;
    }

    if (!empty($filtro_usuario)) {
        $where_conditions[] = "u.NOME_COMPLETO LIKE ?";
        $params[] = "%$filtro_usuario%";
    }

    if (!empty($filtro_cliente)) {
        $where_conditions[] = "(uf.CLIENTE LIKE ? OR uf.CNPJ LIKE ? OR l.cliente_id LIKE ?)";
        $params[] = "%$filtro_cliente%";
        $params[] = "%$filtro_cliente%";
        $params[] = "%$filtro_cliente%";
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // Consulta principal para buscar ligações
    // Otimização: Cache do total de perguntas obrigatórias
    static $total_obrigatorias_cache = null;
    if ($total_obrigatorias_cache === null) {
        $stmt_cache = $pdo->query("SELECT COUNT(*) FROM PERGUNTAS_LIGACAO WHERE obrigatoria = TRUE");
        $total_obrigatorias_cache = $stmt_cache->fetchColumn();
    }

    $sql = "
        SELECT l.*, 
               u.NOME_COMPLETO as usuario_nome,
               COALESCE(uf.CLIENTE, l.cliente_id) as cliente_nome,
               COALESCE(uf.CNPJ, l.cliente_id) as cliente_identificador,
               COALESCE(rl.total_respostas, 0) as total_respostas,
               $total_obrigatorias_cache as total_obrigatorias
        FROM LIGACOES l
        LEFT JOIN USUARIOS u ON l.usuario_id = u.ID
        LEFT JOIN (
            SELECT DISTINCT CNPJ, CLIENTE
            FROM ultimo_faturamento
            WHERE CNPJ IS NOT NULL AND CNPJ != ''
        ) uf ON l.cliente_id = uf.CNPJ
        LEFT JOIN (
            SELECT ligacao_id, COUNT(DISTINCT pergunta_id) as total_respostas
            FROM RESPOSTAS_LIGACAO r
            INNER JOIN PERGUNTAS_LIGACAO p ON r.pergunta_id = p.id
            WHERE p.obrigatoria = TRUE
            GROUP BY ligacao_id
        ) rl ON l.id = rl.ligacao_id
        $where_clause
        ORDER BY l.data_ligacao DESC
        LIMIT $itens_por_pagina OFFSET $offset
    ";

    // Otimização: Usar SQL_CALC_FOUND_ROWS para evitar consulta dupla
    $sql = str_replace('SELECT l.*,', 'SELECT SQL_CALC_FOUND_ROWS l.*,', $sql);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ligacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar total usando FOUND_ROWS() - mais eficiente
    $stmt_count = $pdo->query("SELECT FOUND_ROWS()");
    $totalLigacoes = $stmt_count->fetch(PDO::FETCH_COLUMN);

    $total_paginas = ceil($totalLigacoes / $itens_por_pagina);

    // Buscar estatísticas otimizadas
    $sql_stats = "
        SELECT 
            COUNT(DISTINCT l.id) as total_ligacoes,
            COUNT(DISTINCT CASE WHEN l.status = 'finalizada' THEN l.id END) as ligacoes_finalizadas,
            COUNT(DISTINCT CASE WHEN l.status != 'finalizada' THEN l.id END) as ligacoes_canceladas,
            AVG(CASE WHEN l.status = 'finalizada' THEN COALESCE(rl.total_respostas, 0) END) as media_respostas
        FROM LIGACOES l
        LEFT JOIN USUARIOS u ON l.usuario_id = u.ID
        LEFT JOIN (
            SELECT DISTINCT CNPJ, CLIENTE
            FROM ultimo_faturamento
            WHERE CNPJ IS NOT NULL AND CNPJ != ''
        ) uf ON l.cliente_id = uf.CNPJ
        LEFT JOIN (
            SELECT ligacao_id, COUNT(DISTINCT pergunta_id) as total_respostas
            FROM RESPOSTAS_LIGACAO r
            INNER JOIN PERGUNTAS_LIGACAO p ON r.pergunta_id = p.id
            WHERE p.obrigatoria = TRUE
            GROUP BY ligacao_id
        ) rl ON l.id = rl.ligacao_id
        $where_clause
    ";

    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($params);
    $estatisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);
}

// ===== LÓGICA DA ABA ADMINISTRATIVA =====
if ($tab === 'admin' && $perfil_permitido_admin) {
    // Verificar se a coluna parent_id existe na tabela observacoes
    $hasParentColumn = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM observacoes LIKE 'parent_id'");
        $hasParentColumn = $colCheck && $colCheck->fetch() ? true : false;
    } catch (Exception $e) {
        $hasParentColumn = false;
    }

    // Controle de sub-abas e paginação
    $sub_tab = isset($_GET['sub_tab']) && in_array($_GET['sub_tab'], ['excluidos', 'leads_excluidos', 'observacoes', 'obs_excluidas', 'lixao', 'metas']) ? $_GET['sub_tab'] : 'observacoes';

    $pagina_excluidos = max(1, intval($_GET['pagina_excluidos'] ?? 1));
    $pagina_leads_excluidos = max(1, intval($_GET['pagina_leads_excluidos'] ?? 1));
    $pagina_observacoes = max(1, intval($_GET['pagina_observacoes'] ?? 1));
    $pagina_lixao = max(1, intval($_GET['pagina_lixao'] ?? 1));

    $por_pagina_excluidos = 50;
    $por_pagina_leads_excluidos = 50;
    $por_pagina_observacoes = 50;
    $por_pagina_lixao = 50;

    $offset_excluidos = ($pagina_excluidos - 1) * $por_pagina_excluidos;
    $offset_leads_excluidos = ($pagina_leads_excluidos - 1) * $por_pagina_leads_excluidos;
    $offset_observacoes = ($pagina_observacoes - 1) * $por_pagina_observacoes;
    $offset_lixao = ($pagina_lixao - 1) * $por_pagina_lixao;

    // Filtros para clientes excluídos
    $filtro_cliente_excluido = $_GET['filtro_cliente_excluido'] ?? '';
    $filtro_cnpj_excluido = $_GET['filtro_cnpj_excluido'] ?? '';
    $filtro_vendedor_excluido = $_GET['filtro_vendedor_excluido'] ?? '';
    $filtro_data_inicio_excluido = $_GET['filtro_data_inicio_excluido'] ?? '';
    $filtro_data_fim_excluido = $_GET['filtro_data_fim_excluido'] ?? '';
    $filtro_motivo_excluido = $_GET['filtro_motivo_excluido'] ?? '';

    // Filtros para leads excluídos
    $filtro_lead_excluido = $_GET['filtro_lead_excluido'] ?? '';
    $filtro_email_excluido = $_GET['filtro_email_excluido'] ?? '';
    $filtro_data_inicio_lead = $_GET['filtro_data_inicio_lead'] ?? '';
    $filtro_data_fim_lead = $_GET['filtro_data_fim_lead'] ?? '';
    $filtro_motivo_lead = $_GET['filtro_motivo_lead'] ?? '';

    // Buscar clientes excluídos (não do lixão)
    $where_excluidos = "1=1";
    $params_excluidos = [];
    
    // Verificar se a coluna 'no_lixao' existe e filtrar adequadamente
    try {
        $colCheckExcluidos = $pdo->query("SHOW COLUMNS FROM clientes_excluidos LIKE 'no_lixao'");
        $hasLixaoColumnExcluidos = $colCheckExcluidos && $colCheckExcluidos->fetch() ? true : false;
        
        if ($hasLixaoColumnExcluidos) {
            $where_excluidos .= " AND (no_lixao = 0 OR no_lixao IS NULL)";
        }
    } catch (Exception $e) {
        // Se não conseguir verificar a coluna, continuar sem filtro
    }
    
    // Aplicar filtros baseados no perfil do usuário
    if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $where_excluidos .= " AND cod_vendedor IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1)";
        $params_excluidos[] = $usuario['cod_vendedor'];
    }

    // Aplicar filtros adicionais para clientes excluídos
    if (!empty($filtro_cliente_excluido)) {
        $where_excluidos .= " AND (cliente LIKE ? OR nome_fantasia LIKE ?)";
        $params_excluidos[] = "%$filtro_cliente_excluido%";
        $params_excluidos[] = "%$filtro_cliente_excluido%";
    }

    if (!empty($filtro_cnpj_excluido)) {
        $where_excluidos .= " AND cnpj LIKE ?";
        $params_excluidos[] = "%$filtro_cnpj_excluido%";
    }

    if (!empty($filtro_vendedor_excluido)) {
        $where_excluidos .= " AND (nome_vendedor LIKE ? OR cod_vendedor LIKE ?)";
        $params_excluidos[] = "%$filtro_vendedor_excluido%";
        $params_excluidos[] = "%$filtro_vendedor_excluido%";
    }

    if (!empty($filtro_data_inicio_excluido)) {
        $where_excluidos .= " AND DATE(data_exclusao) >= ?";
        $params_excluidos[] = $filtro_data_inicio_excluido;
    }

    if (!empty($filtro_data_fim_excluido)) {
        $where_excluidos .= " AND DATE(data_exclusao) <= ?";
        $params_excluidos[] = $filtro_data_fim_excluido;
    }

    if (!empty($filtro_motivo_excluido)) {
        $where_excluidos .= " AND motivo_exclusao LIKE ?";
        $params_excluidos[] = "%$filtro_motivo_excluido%";
    }
    
    $sql_excluidos = "SELECT cnpj, cliente, nome_fantasia, estado, cod_vendedor, nome_vendedor, valor_total, data_exclusao, usuario_exclusao, motivo_exclusao, observacao_exclusao FROM clientes_excluidos WHERE $where_excluidos ORDER BY data_exclusao DESC LIMIT $por_pagina_excluidos OFFSET $offset_excluidos";
    $stmtEx = $pdo->prepare($sql_excluidos);
    $stmtEx->execute($params_excluidos);
    $clientesExcluidos = $stmtEx->fetchAll(PDO::FETCH_ASSOC);

    // Preparar mapa de clientes já restaurados
    $restauradosPorRaiz = [];
    try {
        $tblCheck = $pdo->query("SHOW TABLES LIKE 'clientes_restaurados'");
        if ($tblCheck && $tblCheck->fetch()) {
            $stmtRest = $pdo->prepare("SELECT raiz_cnpj FROM clientes_restaurados");
            $stmtRest->execute();
            $raizes = $stmtRest->fetchAll(PDO::FETCH_COLUMN);
            foreach ($raizes as $raiz) { $restauradosPorRaiz[$raiz] = true; }
        }
    } catch (Exception $e) {
        $restauradosPorRaiz = [];
    }

    // Controle de exibição do botão de restauração (admin, diretor e supervisor)
    $showRestoreActions = in_array($perfilUsuario, ['admin', 'diretor', 'supervisor']);

    // Otimização: Cache para contagem de excluídos
    $cache_key_excluidos = 'total_excluidos_' . md5($where_excluidos . serialize($params_excluidos));
    if (!SimpleCache::has($cache_key_excluidos)) {
        $stmtExCount = $pdo->prepare("SELECT COUNT(*) as total FROM clientes_excluidos WHERE $where_excluidos");
        $stmtExCount->execute($params_excluidos);
        $totalExcluidos = (int)($stmtExCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        SimpleCache::set($cache_key_excluidos, $totalExcluidos, 180); // 3 minutos
    } else {
        $totalExcluidos = SimpleCache::get($cache_key_excluidos)['value'];
    }
    $totalPaginasExcluidos = max(1, (int)ceil($totalExcluidos / $por_pagina_excluidos));

    // Buscar leads excluídos
    $where_leads_excluidos = "1=1";
    $params_leads_excluidos = [];
    
    // Aplicar filtros baseados no perfil do usuário para leads excluídos
    if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $where_leads_excluidos .= " AND usuario_exclusao IN (SELECT ID FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1)";
        $params_leads_excluidos[] = $usuario['cod_vendedor'];
    }

    // Aplicar filtros adicionais para leads excluídos
    if (!empty($filtro_lead_excluido)) {
        $where_leads_excluidos .= " AND (nome_fantasia LIKE ? OR razao_social LIKE ?)";
        $params_leads_excluidos[] = "%$filtro_lead_excluido%";
        $params_leads_excluidos[] = "%$filtro_lead_excluido%";
    }

    if (!empty($filtro_email_excluido)) {
        $where_leads_excluidos .= " AND email LIKE ?";
        $params_leads_excluidos[] = "%$filtro_email_excluido%";
    }

    if (!empty($filtro_data_inicio_lead)) {
        $where_leads_excluidos .= " AND DATE(data_exclusao) >= ?";
        $params_leads_excluidos[] = $filtro_data_inicio_lead;
    }

    if (!empty($filtro_data_fim_lead)) {
        $where_leads_excluidos .= " AND DATE(data_exclusao) <= ?";
        $params_leads_excluidos[] = $filtro_data_fim_lead;
    }

    if (!empty($filtro_motivo_lead)) {
        $where_leads_excluidos .= " AND motivo_exclusao LIKE ?";
        $params_leads_excluidos[] = "%$filtro_motivo_lead%";
    }
    
    // Preparar mapa de leads já restaurados
    $restauradosPorEmail = [];
    try {
        $tblCheckLeadsRest = $pdo->query("SHOW TABLES LIKE 'leads_restaurados'");
        if ($tblCheckLeadsRest && $tblCheckLeadsRest->fetch()) {
            $stmtRestLeads = $pdo->prepare("SELECT email FROM leads_restaurados");
            $stmtRestLeads->execute();
            $emails = $stmtRestLeads->fetchAll(PDO::FETCH_COLUMN);
            foreach ($emails as $email) { $restauradosPorEmail[$email] = true; }
        }
    } catch (Exception $e) {
        $restauradosPorEmail = [];
    }

    // Verificar se a tabela leads_excluidos existe
    try {
        $tblCheckLeads = $pdo->query("SHOW TABLES LIKE 'leads_excluidos'");
        if ($tblCheckLeads && $tblCheckLeads->fetch()) {
            $sql_leads_excluidos = "SELECT id, nome_fantasia, razao_social, email, telefone, marcao_prospect, data_cadastro, data_exclusao, usuario_exclusao, motivo_exclusao, observacao_exclusao FROM leads_excluidos WHERE $where_leads_excluidos ORDER BY data_exclusao DESC LIMIT $por_pagina_leads_excluidos OFFSET $offset_leads_excluidos";
            $stmtLeadsEx = $pdo->prepare($sql_leads_excluidos);
            $stmtLeadsEx->execute($params_leads_excluidos);
            $leadsExcluidos = $stmtLeadsEx->fetchAll(PDO::FETCH_ASSOC);

            // Otimização: Cache para contagem de leads excluídos
            $cache_key_leads = 'total_leads_excluidos_' . md5($where_leads_excluidos . serialize($params_leads_excluidos));
            if (!SimpleCache::has($cache_key_leads)) {
                $stmtLeadsExCount = $pdo->prepare("SELECT COUNT(*) as total FROM leads_excluidos WHERE $where_leads_excluidos");
                $stmtLeadsExCount->execute($params_leads_excluidos);
                $totalLeadsExcluidos = (int)($stmtLeadsExCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
                SimpleCache::set($cache_key_leads, $totalLeadsExcluidos, 180); // 3 minutos
            } else {
                $totalLeadsExcluidos = SimpleCache::get($cache_key_leads)['value'];
            }
            $totalPaginasLeadsExcluidos = max(1, (int)ceil($totalLeadsExcluidos / $por_pagina_leads_excluidos));
        } else {
            $leadsExcluidos = [];
            $totalLeadsExcluidos = 0;
            $totalPaginasLeadsExcluidos = 1;
        }
    } catch (Exception $e) {
        $leadsExcluidos = [];
        $totalLeadsExcluidos = 0;
        $totalPaginasLeadsExcluidos = 1;
    }

    // Filtros para observações
    $filtro_obs_status = $_GET['filtro_obs_status'] ?? '';
    $filtro_obs_data_inicio = $_GET['filtro_obs_data_inicio'] ?? '';
    $filtro_obs_data_fim = $_GET['filtro_obs_data_fim'] ?? '';
    $filtro_obs_usuario = $_GET['filtro_obs_usuario'] ?? '';
    $filtro_obs_cliente = $_GET['filtro_obs_cliente'] ?? '';
    $supervisor_obs_selecionado = $_GET['visao_supervisor_obs'] ?? '';
    $vendedor_obs_selecionado = $_GET['visao_vendedor_obs'] ?? '';

    // Buscar observações (todas) categorizadas por cliente
    $where_observacoes = "NOT EXISTS (SELECT 1 FROM observacoes_excluidas ox WHERE ox.observacao_id = o.id)";
    $params_observacoes = [];
    
    // Aplicar filtros baseados no perfil do usuário para observações
    if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $where_observacoes .= " AND o.usuario_id IN (SELECT ID FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1)";
        $params_observacoes[] = $usuario['cod_vendedor'];
    }

    // Aplicar filtros adicionais para observações
    if (!empty($filtro_obs_data_inicio)) {
        $where_observacoes .= " AND DATE(o.data_criacao) >= ?";
        $params_observacoes[] = $filtro_obs_data_inicio;
    }

    if (!empty($filtro_obs_data_fim)) {
        $where_observacoes .= " AND DATE(o.data_criacao) <= ?";
        $params_observacoes[] = $filtro_obs_data_fim;
    }

    if (!empty($filtro_obs_usuario)) {
        $where_observacoes .= " AND o.usuario_nome LIKE ?";
        $params_observacoes[] = "%$filtro_obs_usuario%";
    }

    if (!empty($filtro_obs_cliente)) {
        $where_observacoes .= " AND (uf.CLIENTE LIKE ? OR o.identificador LIKE ?)";
        $params_observacoes[] = "%$filtro_obs_cliente%";
        $params_observacoes[] = "%$filtro_obs_cliente%";
    }

    // Aplicar filtros de visão para diretores e supervisores
    if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin'])) {
        if (!empty($vendedor_obs_selecionado)) {
            $sql_buscar_id_vendedor = "SELECT ID FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1";
            $stmt_buscar_id_vendedor = $pdo->prepare($sql_buscar_id_vendedor);
            $stmt_buscar_id_vendedor->execute([$vendedor_obs_selecionado]);
            $usuario_vendedor_encontrado = $stmt_buscar_id_vendedor->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_vendedor_encontrado) {
                $where_observacoes .= " AND o.usuario_id = ?";
                $params_observacoes[] = $usuario_vendedor_encontrado['ID'];
            }
        } elseif (!empty($supervisor_obs_selecionado)) {
            $where_observacoes .= " AND o.usuario_id IN (SELECT ID FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1)";
            $params_observacoes[] = $supervisor_obs_selecionado;
        }
    }
    
    $obsSelect = $hasParentColumn
        ? "o.id, o.tipo, o.identificador, o.observacao, o.usuario_id, o.usuario_nome, o.parent_id, o.data_criacao, DATE_FORMAT(o.data_criacao, '%d/%m/%Y %H:%i') as data_formatada, COALESCE(uf.CLIENTE, o.identificador) as cliente_nome"
        : "o.id, o.tipo, o.identificador, o.observacao, o.usuario_id, o.usuario_nome, NULL AS parent_id, o.data_criacao, DATE_FORMAT(o.data_criacao, '%d/%m/%Y %H:%i') as data_formatada, COALESCE(uf.CLIENTE, o.identificador) as cliente_nome";
    
    // Otimização: Adicionar paginação para observações
    $por_pagina_observacoes = 50; // Limitar observações carregadas
    $offset_observacoes = 0;
    
    $sqlObs = "SELECT $obsSelect, 
                      CASE 
                          WHEN o.identificador IS NOT NULL AND o.identificador != '' 
                          THEN SUBSTRING(REPLACE(REPLACE(REPLACE(o.identificador, '.', ''), '/', ''), '-', ''), 1, 8)
                          ELSE 'sem_raiz'
                      END as raiz_cnpj
               FROM observacoes o 
               LEFT JOIN (
                   SELECT DISTINCT CNPJ, CLIENTE
                   FROM ultimo_faturamento
                   WHERE CNPJ IS NOT NULL AND CNPJ != ''
               ) uf ON o.identificador = uf.CNPJ
               WHERE $where_observacoes 
               ORDER BY o.data_criacao DESC
               LIMIT $por_pagina_observacoes OFFSET $offset_observacoes";
               
    $stmtObs = $pdo->prepare($sqlObs);
    $stmtObs->execute($params_observacoes);
    $observacoesRaw = $stmtObs->fetchAll(PDO::FETCH_ASSOC);
    
    // Otimização: Processamento mais eficiente das observações
    $observacoesPorCliente = [];
    
    if (!empty($observacoesRaw)) {
        // Usar array_map para processamento mais eficiente
        $observacoesProcessadas = array_map(function($obs) {
            return [
                'raiz_cnpj' => $obs['raiz_cnpj'] ?? 'sem_raiz',
                'data_criacao' => $obs['data_criacao'] ?? date('Y-m-d H:i:s'),
                'cliente_nome' => $obs['cliente_nome'] ?: 'Cliente não encontrado',
                'identificador' => $obs['identificador'] ?? '',
                'obs' => $obs
            ];
        }, $observacoesRaw);
        
        // Agrupar de forma mais eficiente
        foreach ($observacoesProcessadas as $item) {
            $raizCnpj = $item['raiz_cnpj'];
            
            if (!isset($observacoesPorCliente[$raizCnpj])) {
                $observacoesPorCliente[$raizCnpj] = [
                    'cliente_nome' => $item['cliente_nome'],
                    'raiz_cnpj' => $raizCnpj,
                    'cnpjs_relacionados' => [],
                    'observacoes' => [],
                    'ultima_observacao' => $item['data_criacao']
                ];
            }
            
            // Adicionar CNPJ relacionado se não existir
            if (!empty($item['identificador']) && !in_array($item['identificador'], $observacoesPorCliente[$raizCnpj]['cnpjs_relacionados'])) {
                $observacoesPorCliente[$raizCnpj]['cnpjs_relacionados'][] = $item['identificador'];
            }
            
            // Atualizar última observação se necessário
            if (strtotime($item['data_criacao']) > strtotime($observacoesPorCliente[$raizCnpj]['ultima_observacao'])) {
                $observacoesPorCliente[$raizCnpj]['ultima_observacao'] = $item['data_criacao'];
                if ($item['cliente_nome'] !== 'Cliente não encontrado') {
                    $observacoesPorCliente[$raizCnpj]['cliente_nome'] = $item['cliente_nome'];
                }
            }
            
            $observacoesPorCliente[$raizCnpj]['observacoes'][] = $item['obs'];
        }
        
        // Ordenação otimizada usando array_multisort
        $ultimasObservacoes = array_column($observacoesPorCliente, 'ultima_observacao');
        array_multisort($ultimasObservacoes, SORT_DESC, $observacoesPorCliente);
        
        // Ordenar observações dentro de cada grupo
        foreach ($observacoesPorCliente as &$grupo) {
            if (!empty($grupo['observacoes'])) {
                $datas = array_column($grupo['observacoes'], 'data_criacao');
                array_multisort($datas, SORT_DESC, $grupo['observacoes']);
            }
        }
    unset($grupo); // Limpar referência
    
    // Aplicar paginação nos grupos (não nas observações individuais)
    $totalGrupos = count($observacoesPorCliente);
    $gruposParaExibir = array_slice($observacoesPorCliente, $offset_observacoes, $por_pagina_observacoes, true);
    $observacoesPorCliente = $gruposParaExibir;
    }
    
    // Manter variável $observacoes para compatibilidade (lista simples)
    $observacoes = $observacoesRaw;

    // Contar total de grupos únicos por raiz CNPJ para paginação correta
    $sqlCountGrupos = "
        SELECT COUNT(DISTINCT CASE 
            WHEN o.identificador IS NOT NULL AND o.identificador != '' 
            THEN SUBSTRING(REPLACE(REPLACE(REPLACE(o.identificador, '.', ''), '/', ''), '-', ''), 1, 8)
            ELSE 'sem_raiz'
        END) as total_grupos
        FROM observacoes o 
        LEFT JOIN (
            SELECT DISTINCT CNPJ, CLIENTE
            FROM ultimo_faturamento
            WHERE CNPJ IS NOT NULL AND CNPJ != ''
        ) uf ON o.identificador = uf.CNPJ
        WHERE $where_observacoes
    ";
    $stmtObsCount = $pdo->prepare($sqlCountGrupos);
    $stmtObsCount->execute($params_observacoes);
    $totalObservacoes = (int)($stmtObsCount->fetch(PDO::FETCH_ASSOC)['total_grupos'] ?? 0);
    $totalPaginasObservacoes = max(1, (int)ceil($totalObservacoes / $por_pagina_observacoes));

    // Filtros para observações excluídas
    $filtro_obs_excluida_tipo = $_GET['filtro_obs_excluida_tipo'] ?? '';
    $filtro_obs_excluida_identificador = $_GET['filtro_obs_excluida_identificador'] ?? '';
    $filtro_obs_excluida_usuario = $_GET['filtro_obs_excluida_usuario'] ?? '';
    $filtro_obs_excluida_data_inicio = $_GET['filtro_obs_excluida_data_inicio'] ?? '';
    $filtro_obs_excluida_data_fim = $_GET['filtro_obs_excluida_data_fim'] ?? '';
    $filtro_obs_excluida_motivo = $_GET['filtro_obs_excluida_motivo'] ?? '';

    // Buscar observações excluídas (se tabela existir)
    try {
        $where_obs_excluidas = "1=1";
        $params_obs_excluidas = [];
        
        // Aplicar filtros baseados no perfil do usuário para observações excluídas
        if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            $where_obs_excluidas .= " AND usuario_id IN (SELECT ID FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1)";
            $params_obs_excluidas[] = $usuario['cod_vendedor'];
        }

        // Aplicar filtros adicionais para observações excluídas
        if (!empty($filtro_obs_excluida_tipo)) {
            $where_obs_excluidas .= " AND tipo = ?";
            $params_obs_excluidas[] = $filtro_obs_excluida_tipo;
        }

        if (!empty($filtro_obs_excluida_identificador)) {
            $where_obs_excluidas .= " AND identificador LIKE ?";
            $params_obs_excluidas[] = "%$filtro_obs_excluida_identificador%";
        }

        if (!empty($filtro_obs_excluida_usuario)) {
            $where_obs_excluidas .= " AND usuario_nome LIKE ?";
            $params_obs_excluidas[] = "%$filtro_obs_excluida_usuario%";
        }

        if (!empty($filtro_obs_excluida_data_inicio)) {
            $where_obs_excluidas .= " AND DATE(data_exclusao) >= ?";
            $params_obs_excluidas[] = $filtro_obs_excluida_data_inicio;
        }

        if (!empty($filtro_obs_excluida_data_fim)) {
            $where_obs_excluidas .= " AND DATE(data_exclusao) <= ?";
            $params_obs_excluidas[] = $filtro_obs_excluida_data_fim;
        }

        if (!empty($filtro_obs_excluida_motivo)) {
            $where_obs_excluidas .= " AND motivo_exclusao LIKE ?";
            $params_obs_excluidas[] = "%$filtro_obs_excluida_motivo%";
        }
        
        $stmtObsExc = $pdo->prepare("SELECT id, observacao_id, tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao, data_exclusao, usuario_exclusao, motivo_exclusao FROM observacoes_excluidas WHERE $where_obs_excluidas ORDER BY data_exclusao DESC LIMIT $por_pagina_observacoes OFFSET $offset_observacoes");
        $stmtObsExc->execute($params_obs_excluidas);
        $observacoesExcluidas = $stmtObsExc->fetchAll(PDO::FETCH_ASSOC);

        $stmtObsExcCount = $pdo->prepare("SELECT COUNT(*) as total FROM observacoes_excluidas WHERE $where_obs_excluidas");
        $stmtObsExcCount->execute($params_obs_excluidas);
        $totalObservacoesExcluidas = (int)($stmtObsExcCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $totalPaginasObservacoesExcluidas = max(1, (int)ceil($totalObservacoesExcluidas / $por_pagina_observacoes));
    } catch (Exception $e) {
        $observacoesExcluidas = [];
        $totalObservacoesExcluidas = 0;
        $totalPaginasObservacoesExcluidas = 1;
    }

    // ===== LÓGICA DA ABA LIXÃO =====
    if ($sub_tab === 'lixao' && in_array($perfilUsuario, ['admin', 'diretor', 'supervisor'])) {
        // Verificar se foi solicitada a instalação da coluna
        if (isset($_GET['install']) && $_GET['install'] == '1') {
            try {
                // Verificar se a coluna já existe
                $colCheckInstall = $pdo->query("SHOW COLUMNS FROM clientes_excluidos LIKE 'no_lixao'");
                $hasLixaoColumnInstall = $colCheckInstall && $colCheckInstall->fetch() ? true : false;
                
                if (!$hasLixaoColumnInstall) {
                    // Adicionar a coluna
                    $pdo->exec("ALTER TABLE clientes_excluidos ADD COLUMN no_lixao TINYINT(1) DEFAULT 0 COMMENT 'Flag para indicar se está no lixão'");
                    
                    // Criar índice
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clientes_excluidos_no_lixao ON clientes_excluidos(no_lixao)");
                    
                    $installSuccess = true;
                    $installMessage = "Coluna 'no_lixao' adicionada com sucesso!";
                } else {
                    $installSuccess = true;
                    $installMessage = "Coluna 'no_lixao' já existe na tabela.";
                }
            } catch (Exception $e) {
                $installSuccess = false;
                $installMessage = "Erro ao adicionar coluna: " . $e->getMessage();
            }
        }
        // Filtros para clientes do lixão
        $filtro_lixao_cliente = $_GET['filtro_lixao_cliente'] ?? '';
        $filtro_lixao_cnpj = $_GET['filtro_lixao_cnpj'] ?? '';
        $filtro_lixao_vendedor = $_GET['filtro_lixao_vendedor'] ?? '';
        $filtro_lixao_data_inicio = $_GET['filtro_lixao_data_inicio'] ?? '';
        $filtro_lixao_data_fim = $_GET['filtro_lixao_data_fim'] ?? '';
        $filtro_lixao_motivo = $_GET['filtro_lixao_motivo'] ?? '';

        // Buscar clientes do lixão (clientes_excluidos com flag de lixão)
        $where_lixao = "1=1";
        $params_lixao = [];
        
        // Aplicar filtros baseados no perfil do usuário
        if (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            $where_lixao .= " AND cod_vendedor IN (SELECT COD_VENDEDOR FROM USUARIOS WHERE COD_SUPER = ? AND ATIVO = 1)";
            $params_lixao[] = $usuario['cod_vendedor'];
        }
        // Admin e diretor podem ver todos os registros do lixão

        // Aplicar filtros adicionais para clientes do lixão
        if (!empty($filtro_lixao_cliente)) {
            $where_lixao .= " AND (cliente LIKE ? OR nome_fantasia LIKE ?)";
            $params_lixao[] = "%$filtro_lixao_cliente%";
            $params_lixao[] = "%$filtro_lixao_cliente%";
        }

        if (!empty($filtro_lixao_cnpj)) {
            $where_lixao .= " AND cnpj LIKE ?";
            $params_lixao[] = "%$filtro_lixao_cnpj%";
        }

        if (!empty($filtro_lixao_vendedor)) {
            $where_lixao .= " AND (nome_vendedor LIKE ? OR cod_vendedor LIKE ?)";
            $params_lixao[] = "%$filtro_lixao_vendedor%";
            $params_lixao[] = "%$filtro_lixao_vendedor%";
        }

        if (!empty($filtro_lixao_data_inicio)) {
            $where_lixao .= " AND DATE(data_exclusao) >= ?";
            $params_lixao[] = $filtro_lixao_data_inicio;
        }

        if (!empty($filtro_lixao_data_fim)) {
            $where_lixao .= " AND DATE(data_exclusao) <= ?";
            $params_lixao[] = $filtro_lixao_data_fim;
        }

        if (!empty($filtro_lixao_motivo)) {
            $where_lixao .= " AND motivo_exclusao LIKE ?";
            $params_lixao[] = "%$filtro_lixao_motivo%";
        }

        // Verificar se a coluna 'no_lixao' existe e filtrar adequadamente
        try {
            $colCheckLixao = $pdo->query("SHOW COLUMNS FROM clientes_excluidos LIKE 'no_lixao'");
            $hasLixaoColumn = $colCheckLixao && $colCheckLixao->fetch() ? true : false;
            
            if ($hasLixaoColumn) {
                $where_lixao .= " AND no_lixao = 1";
                
                // Verificar se a coluna 'oculto' existe
                $colCheckOculto = $pdo->query("SHOW COLUMNS FROM clientes_excluidos LIKE 'oculto'");
                $hasOcultoColumn = $colCheckOculto && $colCheckOculto->fetch() ? true : false;
                
                if ($hasOcultoColumn) {
                    // Se for admin, pode ver todos (incluindo ocultos)
                    // Se for diretor, não vê os ocultos
                    if ($perfilUsuario !== 'admin') {
                        $where_lixao .= " AND (oculto = 0 OR oculto IS NULL)";
                    }
                }
            } else {
                // Se não existe a coluna, mostrar mensagem informativa
                $clientesLixao = [];
                $totalLixao = 0;
                $totalPaginasLixao = 1;
                $lixaoColumnMissing = true;
            }
        } catch (Exception $e) {
            $hasLixaoColumn = false;
            $lixaoColumnMissing = true;
        }
        
        $sql_lixao = "SELECT cnpj, cliente, nome_fantasia, estado, cod_vendedor, nome_vendedor, valor_total, data_exclusao, usuario_exclusao, motivo_exclusao, observacao_exclusao FROM clientes_excluidos WHERE $where_lixao ORDER BY data_exclusao DESC LIMIT $por_pagina_lixao OFFSET $offset_lixao";
        $stmtLixao = $pdo->prepare($sql_lixao);
        $stmtLixao->execute($params_lixao);
        $clientesLixao = $stmtLixao->fetchAll(PDO::FETCH_ASSOC);

        $stmtLixaoCount = $pdo->prepare("SELECT COUNT(*) as total FROM clientes_excluidos WHERE $where_lixao");
        $stmtLixaoCount->execute($params_lixao);
        $totalLixao = (int)($stmtLixaoCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $totalPaginasLixao = max(1, (int)ceil($totalLixao / $por_pagina_lixao));
    }

}

// ===== LÓGICA DA ABA METAS =====
if ($tab === 'metas' && in_array($perfilUsuario, ['admin', 'diretor', 'supervisor'])) {
        // Processar edição de meta se foi enviada
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar_meta') {
            $cod_vendedor = $_POST['cod_vendedor'] ?? '';
            $nova_meta = floatval($_POST['nova_meta'] ?? 0);
            
            if (!empty($cod_vendedor) && $nova_meta >= 0) {
                try {
                    // Verificar se o usuário tem permissão para editar esta meta
                    $can_edit = false;
                    
                    if ($perfilUsuario === 'admin') {
                        $can_edit = true; // Admin pode editar qualquer meta
                    } elseif ($perfilUsuario === 'diretor') {
                        $can_edit = true; // Diretor pode editar qualquer meta
                    } elseif ($perfilUsuario === 'supervisor') {
                        // Supervisor só pode editar metas de seus vendedores
                        $sql_check = "SELECT COUNT(*) FROM USUARIOS WHERE COD_VENDEDOR = ? AND COD_SUPER = ? AND ATIVO = 1";
                        $stmt_check = $pdo->prepare($sql_check);
                        $stmt_check->execute([$cod_vendedor, $usuario['cod_vendedor']]);
                        $can_edit = $stmt_check->fetchColumn() > 0;
                    }
                    
                    if ($can_edit) {
                        // Atualizar a meta
                        $sql_update = "UPDATE USUARIOS SET META_FATURAMENTO = ? WHERE COD_VENDEDOR = ?";
                        $stmt_update = $pdo->prepare($sql_update);
                        $stmt_update->execute([$nova_meta, $cod_vendedor]);
                        
                        $meta_editada_sucesso = true;
                        $meta_editada_mensagem = "Meta atualizada com sucesso!";
                    } else {
                        $meta_editada_erro = true;
                        $meta_editada_mensagem = "Você não tem permissão para editar esta meta.";
                    }
                } catch (Exception $e) {
                    $meta_editada_erro = true;
                    $meta_editada_mensagem = "Erro ao atualizar meta: " . $e->getMessage();
                }
            } else {
                $meta_editada_erro = true;
                $meta_editada_mensagem = "Dados inválidos fornecidos.";
            }
        }

        // Buscar usuários para gerenciamento de metas
        $where_metas = "1=1";
        $params_metas = [];
        
        // Aplicar filtros baseados no perfil do usuário
        if ($perfilUsuario === 'supervisor') {
            $where_metas .= " AND COD_SUPER = ?";
            $params_metas[] = $usuario['cod_vendedor'];
        }
        // Admin e diretor podem ver todos os usuários
        
        $where_metas .= " AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante', 'supervisor')";
        
        // Buscar todos os usuários primeiro (sem paginação)
        $sql_metas = "SELECT COD_VENDEDOR, NOME_COMPLETO, META_FATURAMENTO, PERFIL, COD_SUPER 
                     FROM USUARIOS 
                     WHERE $where_metas 
                     ORDER BY NOME_COMPLETO";
        
        $stmt_metas = $pdo->prepare($sql_metas);
        $stmt_metas->execute($params_metas);
        $usuarios_metas_raw = $stmt_metas->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular faturamento e percentual para cada usuário
        $usuarios_com_percentual = [];
        foreach ($usuarios_metas_raw as $usuario_meta) {
            $meta_atual = floatval($usuario_meta['META_FATURAMENTO'] ?? 0);
            
            // Calcular faturamento do usuário no mês atual
            $mes_atual = date('m');
            $ano_atual = date('Y');
            
            $sql_fat_usuario = "SELECT 
                SUM(VLR_TOTAL) as total_faturamento
                FROM FATURAMENTO 
                WHERE COD_VENDEDOR = ? 
                AND MONTH(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ? 
                AND YEAR(STR_TO_DATE(EMISSAO, '%d/%m/%Y')) = ?";
            
            $stmt_fat_usuario = $pdo->prepare($sql_fat_usuario);
            $stmt_fat_usuario->execute([$usuario_meta['COD_VENDEDOR'], $mes_atual, $ano_atual]);
            $fat_result_usuario = $stmt_fat_usuario->fetch(PDO::FETCH_ASSOC);
            
            // Se não encontrar na FATURAMENTO, tentar ultimo_faturamento
            if (!$fat_result_usuario || $fat_result_usuario['total_faturamento'] == 0) {
                $sql_fat_usuario = "SELECT 
                    SUM(VLR_TOTAL) as total_faturamento
                    FROM ultimo_faturamento 
                    WHERE COD_VENDEDOR = ? 
                    AND MONTH(STR_TO_DATE(DT_FAT, '%d/%m/%Y')) = ? 
                    AND YEAR(STR_TO_DATE(DT_FAT, '%d/%m/%Y')) = ?";
                
                $stmt_fat_usuario = $pdo->prepare($sql_fat_usuario);
                $stmt_fat_usuario->execute([$usuario_meta['COD_VENDEDOR'], $mes_atual, $ano_atual]);
                $fat_result_usuario = $stmt_fat_usuario->fetch(PDO::FETCH_ASSOC);
            }
            
            $faturamento_atual = floatval($fat_result_usuario['total_faturamento'] ?? 0);
            
            // Se o valor é muito grande (>= 10000000), assumir que está em centavos
            if ($faturamento_atual >= 10000000) {
                $faturamento_atual = $faturamento_atual / 100;
            }
            
            // Calcular percentual
            $percentual_fat = $meta_atual > 0 ? ($faturamento_atual / $meta_atual) * 100 : 0;
            
            $usuarios_com_percentual[] = [
                'usuario' => $usuario_meta,
                'faturamento_atual' => $faturamento_atual,
                'meta_atual' => $meta_atual,
                'percentual_fat' => $percentual_fat
            ];
        }
        
        // Ordenar por percentual de atingimento (maior para menor)
        usort($usuarios_com_percentual, function($a, $b) {
            return $b['percentual_fat'] - $a['percentual_fat'];
        });
        
        // Aplicar paginação
        $totalMetas = count($usuarios_com_percentual);
        $por_pagina_metas = max(1, $por_pagina_metas); // Garantir que não seja zero
        $totalPaginasMetas = max(1, (int)ceil($totalMetas / $por_pagina_metas));
        $usuarios_metas = array_slice($usuarios_com_percentual, $offset_metas, $por_pagina_metas);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="secondary-color" content="<?php echo htmlspecialchars($usuario['secondary_color'] ?? '#ff8f00'); ?>">
    <title>Gestão Administrativa - Sistema BI</title>
    
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/gestao-ligacoes.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin-gestao-mobile.css'); ?>?v=<?php echo time(); ?>">
    <style>
        /* Estilos para linhas expandidas das observações */
        .obs-expandida {
            background-color: #f8f9fa !important;
            border-left: 3px solid #007bff;
        }
        
        .obs-expandida td {
            padding: 8px 12px;
            border-top: 1px solid #e9ecef;
        }
        
        .obs-expandida-cliente {
            font-style: italic;
        }
        
        .obs-expandida-info {
            font-size: 0.85em;
            color: #666;
        }
        
        .obs-expandida-texto {
            padding: 8px 12px;
        }
        
        .obs-texto-expandida {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            line-height: 1.4;
        }
        
        .btn-ver-todas-inline i {
            transition: transform 0.2s ease;
        }
        
        /* Melhorar a animação do botão de expandir */
        .btn-ver-todas-inline:hover i {
            transform: scale(1.1);
        }
        
        /* Centralizar colunas específicas */
        .cliente-cell, .raiz-cell {
            text-align: center !important;
            vertical-align: middle !important;
        }
        
        .cliente-info-table {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
            gap: 8px;
            width: 100%;
        }
        
        .cliente-info-table strong {
            text-align: center;
            word-break: break-word;
            flex: 0 1 auto;
            min-width: 0;
        }
        
        .obs-count-badge {
            flex-shrink: 0;
        }
        
        .raiz-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        /* Centralizar também nas linhas expandidas */
        .obs-expandida-cliente, .obs-expandida-info {
            text-align: center !important;
            vertical-align: middle !important;
        }
        
        /* Melhorar visibilidade dos botões de ação */
        .obs-acoes-inline {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        
        .btn-responder-inline, .btn-ver-todas-inline, .btn-responder-cliente, .btn-ver-todas-cliente {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-responder-inline:hover, .btn-ver-todas-inline:hover, .btn-responder-cliente:hover, .btn-ver-todas-cliente:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.4);
        }
        
        .btn-ver-todas-inline {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }
        
        .btn-ver-todas-inline:hover {
            background: linear-gradient(135deg, #1e7e34 0%, #155724 100%);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.4);
        }
        
        .btn-responder-inline i, .btn-ver-todas-inline i, .btn-responder-cliente i, .btn-ver-todas-cliente i {
            font-size: 11px;
        }
        
        .btn-responder-inline span, .btn-ver-todas-inline span, .btn-responder-cliente span, .btn-ver-todas-cliente span {
            font-size: 11px;
        }
        
        /* Estilos para área de ações do cliente */
        .cliente-acoes {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        
        .btn-responder-cliente {
            background: linear-gradient(135deg, #a5d6a7 0%, #81c784 100%);
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(165, 214, 167, 0.4);
        }
        
        .btn-responder-cliente:hover {
            background: linear-gradient(135deg, #81c784 0%, #66bb6a 100%);
            box-shadow: 0 4px 8px rgba(165, 214, 167, 0.6);
            color: #ffffff;
        }
        
        .btn-ver-todas-cliente {
            background: linear-gradient(135deg, #ffeb3b 0%, #ffc107 100%);
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(255, 235, 59, 0.4);
        }
        
        .btn-ver-todas-cliente:hover {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            box-shadow: 0 4px 8px rgba(255, 235, 59, 0.6);
            color: #ffffff;
        }
        
        .btn-responder-expandida {
            background: linear-gradient(135deg, #a5d6a7 0%, #81c784 100%);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(165, 214, 167, 0.4);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-responder-expandida:hover {
            background: linear-gradient(135deg, #81c784 0%, #66bb6a 100%);
            box-shadow: 0 4px 8px rgba(165, 214, 167, 0.6);
            color: #ffffff;
            transform: translateY(-1px);
        }
        
        .btn-responder-expandida i {
            font-size: 11px;
        }
        
        .btn-responder-expandida span {
            font-size: 11px;
        }
        
        /* Estilos para meta informações fora do balão */
        .obs-expandida-texto .obs-meta-inline {
            margin-bottom: 8px;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #007bff;
            font-size: 0.85em;
            color: #666;
        }
        
        .obs-expandida-texto .obs-meta-inline span {
            margin-right: 12px;
            font-weight: 500;
        }
        
        .obs-expandida-texto .obs-meta-inline .obs-date-inline {
            color: #28a745 !important;
        }
        
        .obs-expandida-texto .obs-meta-inline .obs-user-inline {
            color: #007bff !important;
        }
        
        .obs-expandida-texto .obs-meta-inline .obs-cnpj-inline {
            color: #6f42c1 !important;
        }
        
        /* Cores padronizadas para meta informações em linha */
        .obs-meta-inline span {
            margin-right: 12px;
            font-weight: 500;
        }
        
        .obs-meta-inline .obs-date-inline,
        .obs-date-inline {
            color: #28a745 !important;
            font-weight: 500;
        }
        
        .obs-meta-inline .obs-user-inline,
        .obs-user-inline {
            color: #007bff !important;
            font-weight: 500;
        }
        
        .obs-meta-inline .obs-cnpj-inline,
        .obs-cnpj-inline {
            color: #6f42c1 !important;
            font-weight: 500;
        }
        
        /* Estilo para raiz CNPJ */
        .raiz-badge {
            color: #28a745 !important;
            font-weight: 600;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        
        /* Estilo para informações de vendedor nas outras tabelas */
        .vendedor-info {
            font-size: 0.85em;
            color: #666;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            border-left: 3px solid #007bff;
            margin: 2px 0;
        }
        
        .vendedor-nome {
            color: #007bff !important;
            font-weight: 500;
            display: block;
        }
        
        .vendedor-codigo {
            color: #6f42c1 !important;
            font-weight: 500;
            font-size: 0.8em;
            display: block;
        }
        
        /* Padronizar todas as datas para verde */
        .obs-date-inline, .obs-meta-inline .obs-date-inline {
            color: #28a745 !important;
            font-weight: 500;
        }
        
        /* Garantir que observações nunca sejam truncadas */
        .obs-texto-content, .obs-texto-expandida, .obs-texto-inline {
            word-wrap: break-word !important;
            word-break: break-word !important;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: unset !important;
            max-width: none !important;
            width: 100% !important;
        }
        
        /* Garantir que as células da tabela se expandam conforme necessário */
        .observacoes-table td {
            word-wrap: break-word !important;
            word-break: break-word !important;
            white-space: normal !important;
            overflow: visible !important;
            vertical-align: top;
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #dee2e6;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        /* Garantir que a coluna de observação tenha espaço suficiente */
        .observacoes-table {
            table-layout: auto !important;
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
        }
        
        .observacoes-table th {
            background: #1a1a1a;
            padding: 0.75rem 0.5rem;
            text-align: center;
            font-weight: 600;
            color: white;
            border-bottom: 2px solid #dee2e6;
            border-right: 1px solid rgba(255,255,255,0.15);
            font-size: 0.85rem;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .observacoes-table tr:hover {
            background: #f8f9fa;
        }
        
        /* Sobrescrever qualquer CSS externo que possa truncar texto */
        .table-container .table td, 
        .table-container .observacoes-table td,
        .obs-inline,
        .obs-texto-inline,
        .obs-texto-content {
            max-width: none !important;
            text-overflow: unset !important;
            overflow: visible !important;
            white-space: normal !important;
            word-wrap: break-word !important;
            hyphens: auto;
        }
        
        /* Garantir altura automática das linhas */
        .table-container .table tr,
        .cliente-row,
        .obs-expandida {
            height: auto !important;
            min-height: auto !important;
        }
        
        /* Estilos para o modal de responder observação */
        .modal-responder {
            max-width: 700px;
            width: 90%;
        }
        
        .observacao-original {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .observacao-original h4 {
            color: #495057;
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        .obs-original-container .obs-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 0.85em;
            padding: 8px 12px;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        
        .obs-original-container .obs-meta .obs-date {
            color: #28a745;
            font-weight: 500;
        }
        
        .obs-original-container .obs-meta .obs-user {
            color: #007bff;
            font-weight: 500;
        }
        
        .obs-original-container .obs-meta .obs-cnpj {
            color: #6f42c1;
            font-weight: 500;
        }
        
        .obs-original-container .obs-texto {
            background: white;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            line-height: 1.5;
            word-wrap: break-word;
        }
        
        .resposta-container {
            margin-bottom: 20px;
        }
        
        .resposta-container h4 {
            color: #495057;
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        .resposta-container textarea {
            width: 100%;
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
            line-height: 1.5;
            resize: vertical;
            min-height: 120px;
            transition: border-color 0.3s ease;
        }
        
        .resposta-container textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .contador-caracteres {
            text-align: right;
            margin-top: 5px;
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .modal-footer .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .modal-footer .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .modal-footer .btn-secondary:hover {
            background: #5a6268;
        }
        
        .modal-footer .btn-success {
            background: linear-gradient(135deg, #a5d6a7 0%, #81c784 100%);
            color: white;
        }
        
        .modal-footer .btn-success:hover {
            background: linear-gradient(135deg, #81c784 0%, #66bb6a 100%);
            transform: translateY(-1px);
        }
        
        /* Estilos para botões de excluir observação */
        .btn-excluir-obs, .btn-excluir-obs-expandida {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: none;
            border-radius: 3px;
            padding: 4px 6px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 2px rgba(220, 53, 69, 0.2);
            display: flex;
            align-items: center;
            gap: 2px;
            min-width: 22px;
            height: 22px;
            justify-content: center;
        }
        
        .btn-excluir-obs:hover, .btn-excluir-obs-expandida:hover {
            background: linear-gradient(135deg, #f5c6cb 0%, #f1b0b7 100%);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
            transform: translateY(-1px);
            color: #58151c;
        }
        
        .btn-excluir-obs i, .btn-excluir-obs-expandida i {
            font-size: 9px;
        }
        
        /* Botões da tabela padronizados */
        .btn-sm {
            padding: 4px 6px;
            font-size: 11px;
            min-width: 22px;
            height: 22px;
            line-height: 1;
            border-radius: 3px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
        }

        /* Estilos para paginação */
        .paginacao-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            padding: 15px;
        }

        .paginacao {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
        }

        .paginacao-btn {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(33, 150, 243, 0.2);
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 40px;
            justify-content: center;
        }

        .paginacao-btn:hover {
            background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%);
            box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
            transform: translateY(-1px);
            color: #0d47a1;
            text-decoration: none;
        }

        .paginacao-btn.active {
            background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
            color: #2e7d32;
            box-shadow: 0 3px 6px rgba(76, 175, 80, 0.3);
            font-weight: 600;
        }

        .paginacao-btn.active:hover {
            background: linear-gradient(135deg, #a5d6a7 0%, #81c784 100%);
            color: #1b5e20;
        }

        .paginacao-info {
            color: #666;
            font-size: 13px;
            text-align: center;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #007bff;
        }

        /* Responsividade para paginação */
        @media (max-width: 768px) {
            .paginacao-btn {
                padding: 6px 10px;
                font-size: 12px;
                min-width: 35px;
            }
            
            .paginacao {
                gap: 4px;
            }
        }

        /* Estilos para barras de progresso das metas */
        .meta-progress-fill.meta-atingida {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .meta-progress-fill.meta-proxima {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .meta-progress-fill.meta-baixa {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }

        /* Estilos para cards de metas limpos */
        .meta-card-clean {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .meta-card-clean:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }


        /* Estilos para modal de edição de meta */
        .modal-content {
            max-width: 500px;
            margin: 5% auto;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }

    </style>
</head>
<body<?php echo in_array($perfilUsuario, ['admin', 'diretor', 'supervisor']) ? ' class="admin-user"' : ''; ?>>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include __DIR__ . '/../../includes/components/navbar.php'; ?>
        
        <?php include __DIR__ . '/../../includes/components/nav_hamburguer.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                <!-- Navegação Global por Abas -->
                <div class="tab-navigation">
                    <div class="tab-navigation-container">
                        <?php if ($perfil_permitido_ligacoes): ?>
                        <a href="?tab=ligacoes" class="btn <?php echo $tab === 'ligacoes' ? 'active' : ''; ?>">
                            <i class="fas fa-phone"></i>
                            <span>Gestão de Ligações</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($perfil_permitido_admin): ?>
                        <a href="?tab=admin" class="btn <?php echo $tab === 'admin' ? 'active' : ''; ?>">
                            <i class="fas fa-shield-alt"></i>
                            <span>Área Administrativa</span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($perfilUsuario, ['admin', 'diretor', 'supervisor'])): ?>
                        <a href="?tab=metas" class="btn <?php echo $tab === 'metas' ? 'active' : ''; ?>">
                            <i class="fas fa-bullseye"></i>
                            <span>Gerenciar Metas</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Estatísticas - apenas para aba de ligações -->
                <?php if ($tab === 'ligacoes'): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo number_format($estatisticas['total_ligacoes'] ?? 0); ?></h3>
                        <p><i class="fas fa-phone"></i> Total de Ligações</p>
                    </div>
                    <div class="stat-card finalizadas">
                        <h3><?php echo number_format($estatisticas['ligacoes_finalizadas'] ?? 0); ?></h3>
                        <p><i class="fas fa-check-circle"></i> Finalizadas</p>
                    </div>
                    <div class="stat-card canceladas">
                        <h3><?php echo number_format($estatisticas['ligacoes_canceladas'] ?? 0); ?></h3>
                        <p><i class="fas fa-times-circle"></i> Canceladas</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($estatisticas['media_respostas'] ?? 0, 1); ?></h3>
                        <p><i class="fas fa-chart-line"></i> Média de Respostas</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Conteúdo das Abas -->
                <div class="tab-content">
                    <?php if ($tab === 'ligacoes'): ?>
                        <div id="conteudo-ligacoes">
                            
                            <!-- Filtros -->
                            <div class="filtros-container">
                                
                                <form method="GET" class="filtros-form">
                                    <input type="hidden" name="tab" value="ligacoes">
                                    
                                    <!-- Seletor de Visão para Diretores e Supervisores -->
                                    <?php if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin'])): ?>
                                    <div class="visao-selector-container">
                                        <div class="visao-selector">
                                            <?php if (strtolower(trim($usuario['perfil'])) === 'diretor' || strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                            <div>
                                                <label for="visao_supervisor">Supervisor:</label>
                                                <select id="visao_supervisor" name="visao_supervisor" onchange="mudarVisao()">
                                                    <option value="">Todas as Equipes</option>
                                                    <?php
                                                    // Buscar supervisores disponíveis
                                                    $sql_supervisores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO 
                                                                       FROM USUARIOS u 
                                                                       INNER JOIN USUARIOS v ON v.COD_SUPER = u.COD_VENDEDOR 
                                                                       WHERE u.ATIVO = 1 AND u.PERFIL = 'supervisor' 
                                                                       AND v.ATIVO = 1 AND v.PERFIL IN ('vendedor', 'representante')
                                                                       ORDER BY u.NOME_COMPLETO";
                                                    $stmt_supervisores = $pdo->prepare($sql_supervisores);
                                                    $stmt_supervisores->execute();
                                                    $supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    foreach ($supervisores as $supervisor):
                                                        $is_selected = (string)$supervisor_selecionado === (string)$supervisor['COD_VENDEDOR'];
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($supervisor['COD_VENDEDOR']); ?>" 
                                                            <?php echo $is_selected ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($supervisor['NOME_COMPLETO']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div>
                                                <label for="visao_vendedor">Vendedor:</label>
                                                <select id="visao_vendedor" name="visao_vendedor" onchange="mudarVisao()">
                                                    <option value="">Todos os Vendedores</option>
                                                    <?php
                                                    // Buscar vendedores disponíveis baseado no supervisor selecionado
                                                    $sql_vendedores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO 
                                                                     FROM USUARIOS u 
                                                                     WHERE u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')";
                                                    
                                                    $params_vendedores = [];
                                                    if (!empty($supervisor_selecionado)) {
                                                        $sql_vendedores .= " AND u.COD_SUPER = ?";
                                                        $params_vendedores[] = $supervisor_selecionado;
                                                    }
                                                    
                                                    $sql_vendedores .= " ORDER BY u.NOME_COMPLETO";
                                                    $stmt_vendedores = $pdo->prepare($sql_vendedores);
                                                    $stmt_vendedores->execute($params_vendedores);
                                                    $vendedores = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    foreach ($vendedores as $vendedor):
                                                        $is_selected = (string)$vendedor_selecionado === (string)$vendedor['COD_VENDEDOR'];
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
                                    
                                    <div class="filtros-grid">
                                        <div class="filtro-grupo">
                                            <label for="filtro_status">Status:</label>
                                            <select name="filtro_status" id="filtro_status">
                                                <option value="">Todos</option>
                                                <option value="finalizada" <?php echo ($filtro_status ?? '') === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                                                <option value="cancelada" <?php echo ($filtro_status ?? '') === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                            </select>
                                        </div>
                                        
                                        <div class="filtro-grupo">
                                            <label for="filtro_data_inicio">Data Início:</label>
                                            <input type="date" name="filtro_data_inicio" id="filtro_data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio ?? ''); ?>">
                                        </div>
                                        
                                        <div class="filtro-grupo">
                                            <label for="filtro_data_fim">Data Fim:</label>
                                            <input type="date" name="filtro_data_fim" id="filtro_data_fim" value="<?php echo htmlspecialchars($filtro_data_fim ?? ''); ?>">
                                        </div>
                                        
                                        <div class="filtro-grupo">
                                            <label for="filtro_usuario">Usuário:</label>
                                            <input type="text" name="filtro_usuario" id="filtro_usuario" placeholder="Nome do usuário" value="<?php echo htmlspecialchars($filtro_usuario ?? ''); ?>">
                                        </div>
                                        
                                        <div class="filtro-grupo">
                                            <label for="filtro_cliente">Cliente:</label>
                                            <input type="text" name="filtro_cliente" id="filtro_cliente" placeholder="Nome ou CNPJ do cliente" value="<?php echo htmlspecialchars($filtro_cliente ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="filtros-acoes">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter"></i> Filtrar
                                        </button>
                                        <a href="?tab=ligacoes" class="btn btn-limpar-filtros">
                                            <i class="fas fa-times"></i> Limpar Filtros
                                        </a>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Tabela de Ligações -->
                            <?php if (empty($ligacoes)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-phone-slash empty-icon"></i>
                                        <p class="empty-text">Nenhuma ligação encontrada.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-wrapper">
                                        <table class="ligacoes-table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Cliente</th>
                                                    <th>Telefone</th>
                                                    <th>Tipo de Contato</th>
                                                    <th>Usuário</th>
                                                    <th>Data</th>
                                                    <th>Status</th>
                                                    <th>Progresso</th>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($ligacoes as $ligacao): ?>
                                                    <tr>
                                                        <td>#<?php echo $ligacao['id']; ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($ligacao['cliente_nome'] ?: 'Cliente não encontrado'); ?></strong>
                                                            <?php if ($ligacao['cliente_identificador']): ?>
                                                                <br><small><?php echo htmlspecialchars($ligacao['cliente_identificador']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($ligacao['telefone']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $tipo_contato = $ligacao['tipo_contato'] ?? 'telefonica';
                                                            $tipo_icon = '';
                                                            $tipo_class = '';
                                                            switch($tipo_contato) {
                                                                case 'telefonica':
                                                                    $tipo_icon = 'fas fa-phone';
                                                                    $tipo_class = 'tipo-telefonica';
                                                                    $tipo_text = 'Telefônica';
                                                                    break;
                                                                case 'presencial':
                                                                    $tipo_icon = 'fas fa-handshake';
                                                                    $tipo_class = 'tipo-presencial';
                                                                    $tipo_text = 'Presencial';
                                                                    break;
                                                                case 'whatsapp':
                                                                    $tipo_icon = 'fab fa-whatsapp';
                                                                    $tipo_class = 'tipo-whatsapp';
                                                                    $tipo_text = 'WhatsApp';
                                                                    break;
                                                                case 'email':
                                                                    $tipo_icon = 'fas fa-envelope';
                                                                    $tipo_class = 'tipo-email';
                                                                    $tipo_text = 'Email';
                                                                    break;
                                                                default:
                                                                    $tipo_icon = 'fas fa-phone';
                                                                    $tipo_class = 'tipo-telefonica';
                                                                    $tipo_text = 'Telefônica';
                                                            }
                                                            ?>
                                                            <span class="tipo-contato <?php echo $tipo_class; ?>">
                                                                <i class="<?php echo $tipo_icon; ?>"></i>
                                                                <?php echo $tipo_text; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($ligacao['usuario_nome'] ?: 'Usuário não encontrado'); ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($ligacao['data_ligacao'])); ?></td>
                                                        <td>
                                                            <?php 
                                                            $status_class = '';
                                                            $status_text = '';
                                                            switch($ligacao['status']) {
                                                                case 'finalizada':
                                                                    $status_class = 'finalizada';
                                                                    $status_text = 'Finalizada';
                                                                    break;
                                                                case 'cancelada':
                                                                    $status_class = 'cancelada';
                                                                    $status_text = 'Cancelada';
                                                                    break;
                                                                default:
                                                                    $status_class = 'cancelada';
                                                                    $status_text = 'Cancelada';
                                                            }
                                                            ?>
                                                            <span class="status-badge status-<?php echo $status_class; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $total_obrigatorias = $ligacao['total_obrigatorias'] ?: 1;
                                                            $total_respostas = $ligacao['total_respostas'] ?: 0;
                                                            $percentual = ($total_respostas / $total_obrigatorias) * 100;
                                                            ?>
                                                            <div class="progresso-bar">
                                                                <div class="progresso-fill" data-width="<?php echo min(100, $percentual); ?>"></div>
                                                            </div>
                                                            <small><?php echo $total_respostas; ?>/<?php echo $total_obrigatorias; ?> respostas</small>
                                                        </td>
                                                        <td>
                                                            <div class="btn-acoes">
                                                                <button class="btn btn-info btn-detalhes" data-ligacao-id="<?php echo $ligacao['id']; ?>" title="Ver Detalhes">
                                                                    <i class="fas fa-eye"></i> Detalhes
                                                                </button>
                                                                <?php if (strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                                                    <button class="btn btn-danger btn-excluir" data-ligacao-id="<?php echo $ligacao['id']; ?>" title="Excluir Ligação">
                                                                        <i class="fas fa-trash"></i> Excluir
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Container para cards mobile -->
                                    <div class="mobile-cards-container">
                                        <?php foreach ($ligacoes as $ligacao): ?>
                                            <div class="ligacao-card">
                                                <div class="ligacao-card-header">
                                                    <h4 class="ligacao-card-cliente">
                                                        <?php echo htmlspecialchars($ligacao['cliente_nome'] ?: 'Cliente não encontrado'); ?>
                                                    </h4>
                                                    <span class="ligacao-card-status <?php echo strtolower($ligacao['status']); ?>">
                                                        <?php echo ucfirst($ligacao['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="ligacao-card-info">
                                                    <div class="ligacao-info-item">
                                                        <span class="ligacao-info-label">ID:</span>
                                                        <span class="ligacao-info-value">#<?php echo $ligacao['id']; ?></span>
                                                    </div>
                                                    <div class="ligacao-info-item">
                                                        <span class="ligacao-info-label">Telefone:</span>
                                                        <span class="ligacao-info-value"><?php echo htmlspecialchars($ligacao['telefone']); ?></span>
                                                    </div>
                                                    <div class="ligacao-info-item">
                                                        <span class="ligacao-info-label">Tipo:</span>
                                                        <span class="ligacao-info-value">
                                                            <?php 
                                                            $tipo_contato = $ligacao['tipo_contato'] ?? 'telefonica';
                                                            switch($tipo_contato) {
                                                                case 'telefonica': echo 'Telefônica'; break;
                                                                case 'presencial': echo 'Presencial'; break;
                                                                case 'whatsapp': echo 'WhatsApp'; break;
                                                                case 'email': echo 'Email'; break;
                                                                default: echo ucfirst($tipo_contato);
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <div class="ligacao-info-item">
                                                        <span class="ligacao-info-label">Usuário:</span>
                                                        <span class="ligacao-info-value"><?php echo htmlspecialchars($ligacao['usuario_nome']); ?></span>
                                                    </div>
                                                    <div class="ligacao-info-item">
                                                        <span class="ligacao-info-label">Data:</span>
                                                        <span class="ligacao-info-value"><?php echo date('d/m/Y H:i', strtotime($ligacao['data_ligacao'])); ?></span>
                                                    </div>
                                                    <div class="ligacao-info-item">
                                                        <span class="ligacao-info-label">Progresso:</span>
                                                        <span class="ligacao-info-value">
                                                            <?php
                                                            $total_respostas = $ligacao['total_respostas'] ?? 0;
                                                            $total_obrigatorias = $ligacao['total_obrigatorias'] ?? 0;
                                                            ?>
                                                            <?php echo $total_respostas; ?>/<?php echo $total_obrigatorias; ?> respostas
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="ligacao-card-actions">
                                                    <button class="btn btn-primary btn-detalhes" data-ligacao-id="<?php echo $ligacao['id']; ?>">
                                                        <i class="fas fa-eye"></i> Detalhes
                                                    </button>
                                                    <?php if (strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                                        <button class="btn btn-danger btn-excluir" data-ligacao-id="<?php echo $ligacao['id']; ?>">
                                                            <i class="fas fa-trash"></i> Excluir
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if ($total_paginas > 1): ?>
                                        <div class="paginacao-container">
                                            <div class="paginacao">
                                                <?php
                                                    $baseParams = ['tab' => 'ligacoes'];
                                                    if (!empty($filtro_status)) $baseParams['filtro_status'] = $filtro_status;
                                                    if (!empty($filtro_data_inicio)) $baseParams['filtro_data_inicio'] = $filtro_data_inicio;
                                                    if (!empty($filtro_data_fim)) $baseParams['filtro_data_fim'] = $filtro_data_fim;
                                                    if (!empty($filtro_usuario)) $baseParams['filtro_usuario'] = $filtro_usuario;
                                                    if (!empty($filtro_cliente)) $baseParams['filtro_cliente'] = $filtro_cliente;
                                                    if (!empty($supervisor_selecionado)) $baseParams['visao_supervisor'] = $supervisor_selecionado;
                                                    if (!empty($vendedor_selecionado)) $baseParams['visao_vendedor'] = $vendedor_selecionado;
                                                ?>
                                                <?php if ($pagina_atual > 1): ?>
                                                    <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina' => $pagina_atual - 1])); ?>" class="paginacao-btn">‹ Anterior</a>
                                                <?php endif; ?>
                                                <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                                    <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina' => $i])); ?>" 
                                                       class="paginacao-btn <?php echo $i === $pagina_atual ? 'active' : ''; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                <?php endfor; ?>
                                                <?php if ($pagina_atual < $total_paginas): ?>
                                                    <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina' => $pagina_atual + 1])); ?>" class="paginacao-btn">Próxima ›</a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="paginacao-info">
                                                Mostrando <?php echo count($ligacoes); ?> de <?php echo $totalLigacoes; ?> ligações
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                        </div>
                    <?php elseif ($tab === 'admin'): ?>
                        <div id="conteudo-admin">
                            
                            <div class="admin-page">
                                <div class="page-header-modern">
                                    <div class="header-actions header-actions-centered">
                                        <a class="btn <?php echo ($sub_tab ?? 'observacoes') === 'observacoes' ? 'active' : ''; ?>" href="?tab=admin&sub_tab=observacoes">
                                            <i class="fas fa-comments"></i>
                                            <span class="btn-label">Observações</span>
                                            <span class="count-badge"><?php echo $totalObservacoes; ?></span>
                                        </a>
                                        <a class="btn <?php echo ($sub_tab ?? '') === 'excluidos' ? 'active' : ''; ?>" href="?tab=admin&sub_tab=excluidos">
                                            <i class="fas fa-trash"></i>
                                            <span class="btn-label">Clientes Excluídos</span>
                                            <span class="count-badge"><?php echo $totalExcluidos; ?></span>
                                        </a>
                                        <a class="btn <?php echo ($sub_tab ?? '') === 'leads_excluidos' ? 'active' : ''; ?>" href="?tab=admin&sub_tab=leads_excluidos">
                                            <i class="fas fa-user-times"></i>
                                            <span class="btn-label">Leads Excluídos</span>
                                            <span class="count-badge"><?php echo $totalLeadsExcluidos; ?></span>
                                        </a>
                                        <?php if (strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                        <a class="btn <?php echo ($sub_tab ?? '') === 'obs_excluidas' ? 'active' : ''; ?>" href="?tab=admin&sub_tab=obs_excluidas">
                                            <i class="fas fa-comment-slash"></i>
                                            <span class="btn-label">Observações Excluídas</span>
                                            <span class="count-badge"><?php echo $totalObservacoesExcluidas; ?></span>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($perfilUsuario, ['admin', 'diretor', 'supervisor'])): ?>
                                        <a class="btn <?php echo ($sub_tab ?? '') === 'lixao' ? 'active' : ''; ?>" href="?tab=admin&sub_tab=lixao">
                                            <i class="fas fa-trash-alt"></i>
                                            <span class="btn-label">LIXÃO</span>
                                            <span class="count-badge"><?php echo $totalLixao; ?></span>
                                        </a>
                                        <?php endif; ?>
                                        
                                    </div>
                                </div>

                                <?php if (($sub_tab ?? 'observacoes') === 'observacoes'): ?>
                                    <div class="card">
                                        <!-- Filtros para Observações -->
                                        <div class="filtros-container">
                                            <form method="GET" class="filtros-form">
                                                <input type="hidden" name="tab" value="admin">
                                                <input type="hidden" name="sub_tab" value="observacoes">
                                                
                                                <!-- Seletor de Visão para Diretores e Supervisores -->
                                                <?php if (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin'])): ?>
                                                <div class="visao-selector-container">
                                                    <div class="visao-selector">
                                                        <?php if (strtolower(trim($usuario['perfil'])) === 'diretor' || strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                                        <div>
                                                            <label for="visao_supervisor_obs">Supervisor:</label>
                                                            <select id="visao_supervisor_obs" name="visao_supervisor_obs" onchange="mudarVisaoObservacoes()">
                                                                <option value="">Todas as Equipes</option>
                                                                <?php
                                                                // Buscar supervisores disponíveis
                                                                $sql_supervisores_obs = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO 
                                                                                       FROM USUARIOS u 
                                                                                       INNER JOIN USUARIOS v ON v.COD_SUPER = u.COD_VENDEDOR 
                                                                                       WHERE u.ATIVO = 1 AND u.PERFIL = 'supervisor' 
                                                                                       AND v.ATIVO = 1 AND v.PERFIL IN ('vendedor', 'representante')
                                                                                       ORDER BY u.NOME_COMPLETO";
                                                                $stmt_supervisores_obs = $pdo->prepare($sql_supervisores_obs);
                                                                $stmt_supervisores_obs->execute();
                                                                $supervisores_obs = $stmt_supervisores_obs->fetchAll(PDO::FETCH_ASSOC);
                                                                
                                                                foreach ($supervisores_obs as $supervisor):
                                                                    $is_selected = (string)$supervisor_obs_selecionado === (string)$supervisor['COD_VENDEDOR'];
                                                                ?>
                                                                <option value="<?php echo htmlspecialchars($supervisor['COD_VENDEDOR']); ?>" 
                                                                        <?php echo $is_selected ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($supervisor['NOME_COMPLETO']); ?>
                                                                </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <div>
                                                            <label for="visao_vendedor_obs">Vendedor:</label>
                                                            <select id="visao_vendedor_obs" name="visao_vendedor_obs" onchange="mudarVisaoObservacoes()">
                                                                <option value="">Todos os Vendedores</option>
                                                                <?php
                                                                // Buscar vendedores disponíveis baseado no supervisor selecionado
                                                                $sql_vendedores_obs = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO 
                                                                                     FROM USUARIOS u 
                                                                                     WHERE u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')";
                                                                
                                                                $params_vendedores_obs = [];
                                                                if (!empty($supervisor_obs_selecionado)) {
                                                                    $sql_vendedores_obs .= " AND u.COD_SUPER = ?";
                                                                    $params_vendedores_obs[] = $supervisor_obs_selecionado;
                                                                }
                                                                
                                                                $sql_vendedores_obs .= " ORDER BY u.NOME_COMPLETO";
                                                                $stmt_vendedores_obs = $pdo->prepare($sql_vendedores_obs);
                                                                $stmt_vendedores_obs->execute($params_vendedores_obs);
                                                                $vendedores_obs = $stmt_vendedores_obs->fetchAll(PDO::FETCH_ASSOC);
                                                                
                                                                foreach ($vendedores_obs as $vendedor):
                                                                    $is_selected = (string)$vendedor_obs_selecionado === (string)$vendedor['COD_VENDEDOR'];
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
                                                
                                                <div class="filtros-grid">
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_data_inicio">Data Início:</label>
                                                        <input type="date" name="filtro_obs_data_inicio" id="filtro_obs_data_inicio" value="<?php echo htmlspecialchars($filtro_obs_data_inicio ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_data_fim">Data Fim:</label>
                                                        <input type="date" name="filtro_obs_data_fim" id="filtro_obs_data_fim" value="<?php echo htmlspecialchars($filtro_obs_data_fim ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_usuario">Usuário:</label>
                                                        <input type="text" name="filtro_obs_usuario" id="filtro_obs_usuario" placeholder="Nome do usuário" value="<?php echo htmlspecialchars($filtro_obs_usuario ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_cliente">Cliente:</label>
                                                        <input type="text" name="filtro_obs_cliente" id="filtro_obs_cliente" placeholder="Nome ou CNPJ do cliente" value="<?php echo htmlspecialchars($filtro_obs_cliente ?? ''); ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="filtros-acoes">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-filter"></i> Filtrar
                                                    </button>
                                                    <a href="?tab=admin&sub_tab=observacoes" class="btn btn-limpar-filtros">
                                                        <i class="fas fa-times"></i> Limpar Filtros
                                                    </a>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <?php if (empty($observacoesPorCliente)): ?>
                                            <div class="placeholder-container">Nenhuma observação encontrada.</div>
                                        <?php else: ?>
                                            <!-- Debug temporário -->
                                            <?php if (isset($_GET['debug'])): ?>
                                                <div class="info-box">
                                                    <strong>Debug:</strong> <?php echo count($observacoesPorCliente); ?> grupos na página atual<br>
                                                    <strong>Total observações raw:</strong> <?php echo count($observacoesRaw ?? []); ?><br>
                                                    <strong>Total grupos no sistema:</strong> <?php echo $totalObservacoes; ?><br>
                                                    <strong>Página atual:</strong> <?php echo $pagina_observacoes; ?> de <?php echo $totalPaginasObservacoes; ?><br>
                                                    <strong>Grupos antes da paginação:</strong> <?php echo $totalGrupos ?? 'N/A'; ?>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Tabela de observações agrupadas por cliente -->
                                            <div class="table-container">
                                                <table class="table observacoes-table">
                                                                                                    <thead>
                                                        <tr>
                                                            <th width="20%" class="text-center">Cliente / Empresa</th>
                                                            <th width="12%" class="text-center">Raiz CNPJ</th>
                                                            <th width="68%">Última Observação</th>
                                                        </tr>
                                                    </thead>
                                                <tbody>
                                                        <?php foreach ($observacoesPorCliente as $raizKey => $dadosCliente): ?>
                                                            <!-- Linha principal do cliente -->
                                                            <tr class="cliente-row" data-raiz="<?php echo htmlspecialchars($raizKey); ?>">
                                                                <td class="cliente-cell">
                                                                    <div class="cliente-info-table">
                                                                        <strong><?php echo htmlspecialchars($dadosCliente['cliente_nome']); ?></strong>
                                                                    </div>
                                                                    <div class="cliente-acoes" style="margin-top: 8px;">
                                                                        <?php $primeiraObs = $dadosCliente['observacoes'][0] ?? null; ?>
                                                                        <?php if ($primeiraObs): ?>
                                                                            <button class="btn-responder-cliente" onclick="responderObservacao(<?php echo (int)$primeiraObs['id']; ?>)" title="Responder">
                                                                                <i class="fas fa-reply"></i>
                                                                                <span>Responder</span>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                        <?php if (count($dadosCliente['observacoes']) > 1): ?>
                                                                            <button class="btn-ver-todas-cliente" onclick="toggleObservacoesCliente('<?php echo $raizKey; ?>')" title="Ver todas as <?php echo count($dadosCliente['observacoes']); ?> observações">
                                                                                <i class="fas fa-chevron-down" id="icon-<?php echo $raizKey; ?>"></i>
                                                                                <span>Ver todas (<?php echo count($dadosCliente['observacoes']); ?>)</span>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                        <?php if (strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                                                            <button class="btn-excluir-obs" onclick="excluirObservacao(<?php echo (int)$primeiraObs['id']; ?>)" title="Excluir observação">
                                                                                <i class="fas fa-times"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td class="raiz-cell">
                                                                    <div class="raiz-info">
                                                                        <span class="raiz-badge"><?php echo htmlspecialchars($dadosCliente['raiz_cnpj']); ?></span>
                                                                        <?php 
                                                                        $cnpjs = $dadosCliente['cnpjs_relacionados'] ?? []; 
                                                                        if (!empty($cnpjs)): ?>
                                                                            <small class="cnpjs-count" title="<?php echo htmlspecialchars(implode(', ', $cnpjs)); ?>">
                                                                                <?php echo count($cnpjs); ?> CNPJ<?php echo count($cnpjs) > 1 ? 's' : ''; ?>
                                                                            </small>
                                                                        <?php else: ?>
                                                                            <small class="cnpjs-count">0 CNPJs</small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td class="observacao-principal-cell">
                                                                    <?php 
                                                                    $primeiraObs = $dadosCliente['observacoes'][0] ?? null;
                                                                    if ($primeiraObs): ?>
                                                                        <div class="obs-inline">
                                                                            <div class="obs-meta-inline">
                                                                                <span class="obs-date-inline"><?php echo htmlspecialchars($primeiraObs['data_formatada'] ?? ''); ?></span>
                                                                                <span class="obs-user-inline"><?php echo htmlspecialchars($primeiraObs['usuario_nome'] ?? ''); ?></span>
                                                                                <span class="obs-cnpj-inline"><?php echo htmlspecialchars($primeiraObs['identificador'] ?? ''); ?></span>
                                                                            </div>
                                                                            <div class="obs-texto-inline">
                                                                                <div class="obs-texto-content">
                                                                                    <?php if (!empty($primeiraObs['parent_id'])): ?>
                                                                                        <span class="reply-indicator-inline">↳</span>
                                                                                    <?php endif; ?>
                                                                                    <?php 
                                                                                    $textoObs = $primeiraObs['observacao'] ?? '';
                                                                                        echo htmlspecialchars($textoObs);
                                                                                    ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="no-obs">Nenhuma observação</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>

                                                            <!-- Linhas expandidas das observações adicionais (inicialmente ocultas) -->
                                                            <?php if (count($dadosCliente['observacoes']) > 1): ?>
                                                                <?php for ($i = 1; $i < count($dadosCliente['observacoes']); $i++): ?>
                                                                    <?php $obsAdicional = $dadosCliente['observacoes'][$i]; ?>
                                                                    <tr class="obs-expandida hidden-row" id="obs-expandida-<?php echo $raizKey; ?>-<?php echo $i; ?>" data-raiz="<?php echo htmlspecialchars($raizKey); ?>">
                                                                        <td class="obs-expandida-cliente">
                                                                            <div class="obs-content">
                                                                                <button class="btn-responder-expandida" onclick="responderObservacao(<?php echo (int)$obsAdicional['id']; ?>)" title="Responder">
                                                                                    <i class="fas fa-reply"></i>
                                                                                    <span>Responder</span>
                                                                                </button>
                                                                                <?php if (strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                                                                    <button class="btn-excluir-obs-expandida" onclick="excluirObservacao(<?php echo (int)$obsAdicional['id']; ?>)" title="Excluir observação">
                                                                                        <i class="fas fa-times"></i>
                                                                                    </button>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>
                                                                        <td class="obs-expandida-info">
                                                                            <div class="raiz-info">
                                                                                <span class="raiz-badge"><?php echo htmlspecialchars($dadosCliente['raiz_cnpj']); ?></span>
                                                                                <?php 
                                                                                $cnpjs = $dadosCliente['cnpjs_relacionados'] ?? []; 
                                                                                if (!empty($cnpjs)): ?>
                                                                                    <small class="cnpjs-count" title="<?php echo htmlspecialchars(implode(', ', $cnpjs)); ?>">
                                                                                        <?php echo count($cnpjs); ?> CNPJ<?php echo count($cnpjs) > 1 ? 's' : ''; ?>
                                                                                    </small>
                                                                                <?php else: ?>
                                                                                    <small class="cnpjs-count">0 CNPJs</small>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>
                                                                                                                                                                                                                        <td class="obs-expandida-texto">
                                                                            <div class="obs-meta-inline">
                                                                                <span class="obs-date-inline"><?php echo htmlspecialchars($obsAdicional['data_formatada'] ?? ''); ?></span>
                                                                                <span class="obs-user-inline"><?php echo htmlspecialchars($obsAdicional['usuario_nome'] ?? ''); ?></span>
                                                                                <span class="obs-cnpj-inline"><?php echo htmlspecialchars($obsAdicional['identificador'] ?? ''); ?></span>
                                                                            </div>
                                                                            <div class="obs-texto-expandida">
                                                                                <div class="obs-texto-content">
                                                                                    <?php if (!empty($obsAdicional['parent_id'])): ?>
                                                                                        <span class="reply-indicator-inline reply-indicator">↳ Resposta:</span>
                                                                                    <?php endif; ?>
                                                                                    <?php echo htmlspecialchars($obsAdicional['observacao'] ?? ''); ?>
                                                                                </div>
                                                                            </div>
                                                                        </td>
                                                            </tr>
                                                                <?php endfor; ?>
                                                            <?php endif; ?>

                                                    <?php endforeach; ?>
                                                </tbody>
                                                                                            </table>
                                            </div>
                                            
                                            <!-- Container para cards mobile - Observações -->
                                            <div class="mobile-cards-container">
                                                <?php foreach ($observacoesPorCliente as $raizKey => $dadosCliente): ?>
                                                    <div class="observacao-card">
                                                        <div class="observacao-card-header">
                                                            <h4 class="observacao-card-cliente">
                                                                <?php echo htmlspecialchars($dadosCliente['cliente_nome']); ?>
                                                            </h4>
                                                            <span class="observacao-card-data">
                                                                <?php echo count($dadosCliente['observacoes']); ?> observação(ões)
                                                            </span>
                                                        </div>
                                                        
                                                        <div class="observacao-card-conteudo">
                                                            <?php $primeiraObs = $dadosCliente['observacoes'][0] ?? null; ?>
                                                            <?php if ($primeiraObs): ?>
                                                                <strong>CNPJ:</strong> <?php echo htmlspecialchars($dadosCliente['raiz_cnpj']); ?><br>
                                                                <strong>Data:</strong> <?php echo htmlspecialchars($primeiraObs['data_formatada'] ?? ''); ?><br>
                                                                <strong>Usuário:</strong> <?php echo htmlspecialchars($primeiraObs['usuario_nome'] ?? ''); ?><br>
                                                                <strong>Identificador:</strong> <?php echo htmlspecialchars($primeiraObs['identificador'] ?? ''); ?><br><br>
                                                                <strong>Observação:</strong><br>
                                                                <?php echo htmlspecialchars($primeiraObs['observacao'] ?? ''); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="observacao-card-actions">
                                                            <?php if ($primeiraObs): ?>
                                                                <button class="btn btn-primary" onclick="responderObservacao(<?php echo (int)$primeiraObs['id']; ?>)">
                                                                    <i class="fas fa-reply"></i> Responder
                                                                </button>
                                                                <?php if (strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                                                    <button class="btn btn-danger" onclick="excluirObservacao(<?php echo (int)$primeiraObs['id']; ?>)">
                                                                        <i class="fas fa-times"></i> Excluir
                                                                    </button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <!-- Paginação para observações -->
                                            <?php if ($totalPaginasObservacoes > 1): ?>
                                                <div class="paginacao-container">
                                                    <div class="paginacao">
                                                        <?php
                                                            $baseParams = ['tab' => 'admin', 'sub_tab' => 'observacoes'];
                                                            if (!empty($filtro_obs_data_inicio)) $baseParams['filtro_obs_data_inicio'] = $filtro_obs_data_inicio;
                                                            if (!empty($filtro_obs_data_fim)) $baseParams['filtro_obs_data_fim'] = $filtro_obs_data_fim;
                                                            if (!empty($filtro_obs_usuario)) $baseParams['filtro_obs_usuario'] = $filtro_obs_usuario;
                                                            if (!empty($filtro_obs_cliente)) $baseParams['filtro_obs_cliente'] = $filtro_obs_cliente;
                                                            if (!empty($supervisor_obs_selecionado)) $baseParams['visao_supervisor_obs'] = $supervisor_obs_selecionado;
                                                            if (!empty($vendedor_obs_selecionado)) $baseParams['visao_vendedor_obs'] = $vendedor_obs_selecionado;
                                                        ?>
                                                        <?php if ($pagina_observacoes > 1): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_observacoes' => $pagina_observacoes - 1])); ?>" class="paginacao-btn">‹ Anterior</a>
                                                        <?php endif; ?>
                                                        
                                                        <?php for ($i = max(1, $pagina_observacoes - 2); $i <= min($totalPaginasObservacoes, $pagina_observacoes + 2); $i++): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_observacoes' => $i])); ?>" 
                                                               class="paginacao-btn <?php echo $i === $pagina_observacoes ? 'active' : ''; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endfor; ?>
                                                        
                                                        <?php if ($pagina_observacoes < $totalPaginasObservacoes): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_observacoes' => $pagina_observacoes + 1])); ?>" class="paginacao-btn">Próxima ›</a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="paginacao-info">
                                                        Mostrando <?php echo count($observacoesPorCliente); ?> de <?php echo $totalObservacoes; ?> grupos de clientes
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (($sub_tab ?? '') === 'excluidos'): ?>
                                    <div class="card">
                                        <!-- Filtros para Clientes Excluídos -->
                                        <div class="filtros-container">
                                            <form method="GET" class="filtros-form">
                                                <input type="hidden" name="tab" value="admin">
                                                <input type="hidden" name="sub_tab" value="excluidos">
                                                
                                                <div class="filtros-grid">
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_cliente_excluido">Cliente:</label>
                                                        <input type="text" name="filtro_cliente_excluido" id="filtro_cliente_excluido" placeholder="Nome do cliente" value="<?php echo htmlspecialchars($filtro_cliente_excluido ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_cnpj_excluido">CNPJ:</label>
                                                        <input type="text" name="filtro_cnpj_excluido" id="filtro_cnpj_excluido" placeholder="CNPJ do cliente" value="<?php echo htmlspecialchars($filtro_cnpj_excluido ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_vendedor_excluido">Vendedor:</label>
                                                        <input type="text" name="filtro_vendedor_excluido" id="filtro_vendedor_excluido" placeholder="Nome ou código do vendedor" value="<?php echo htmlspecialchars($filtro_vendedor_excluido ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_data_inicio_excluido">Data Início:</label>
                                                        <input type="date" name="filtro_data_inicio_excluido" id="filtro_data_inicio_excluido" value="<?php echo htmlspecialchars($filtro_data_inicio_excluido ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_data_fim_excluido">Data Fim:</label>
                                                        <input type="date" name="filtro_data_fim_excluido" id="filtro_data_fim_excluido" value="<?php echo htmlspecialchars($filtro_data_fim_excluido ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_motivo_excluido">Motivo:</label>
                                                        <input type="text" name="filtro_motivo_excluido" id="filtro_motivo_excluido" placeholder="Motivo da exclusão" value="<?php echo htmlspecialchars($filtro_motivo_excluido ?? ''); ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="filtros-acoes">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-filter"></i> Filtrar
                                                    </button>
                                                    <a href="?tab=admin&sub_tab=excluidos" class="btn btn-limpar-filtros">
                                                        <i class="fas fa-times"></i> Limpar Filtros
                                                    </a>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <?php if (empty($clientesExcluidos)): ?>
                                            <div class="placeholder-container">Nenhum cliente excluído encontrado.</div>
                                        <?php else: ?>
                                            <div class="table-container">
                                            <table class="table admin-table">
                                                <thead>
                                                    <tr class="table-header-row">
                                                        <th class="table-header-cell">Cliente</th>
                                                        <th class="table-header-cell">CNPJ</th>
                                                        <th class="table-header-cell">Vendedor</th>
                                                        <th class="table-header-cell">Valor Total</th>
                                                        <th class="table-header-cell">Data de Exclusão</th>
                                                        <th class="table-header-cell">Usuário</th>
                                                        <th class="table-header-cell">Motivo</th>
                                                        <th class="table-header-cell">Observação de Exclusão</th>
                                                        <?php if ($showRestoreActions): ?>
                                                            <th class="table-header-cell table-header-cell-last">Ações</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($clientesExcluidos as $reg): ?>
                                                        <tr>
                                                            <td>
                                                                <div><?php echo htmlspecialchars($reg['cliente'] ?? ''); ?></div>
                                                                <div class="data-text"><?php echo htmlspecialchars($reg['nome_fantasia'] ?? ''); ?></div>
                                                            </td>
                                                            <td class="cnpj-cell"><?php echo htmlspecialchars($reg['cnpj'] ?? ''); ?></td>
                                                            <td>
                                                                <div class="vendedor-info">
                                                                    <span class="vendedor-nome"><?php echo htmlspecialchars($reg['nome_vendedor'] ?? ''); ?></span>
                                                                    <span class="vendedor-codigo">Cód: <?php echo htmlspecialchars($reg['cod_vendedor'] ?? ''); ?></span>
                                                                </div>
                                                            </td>
                                                            <td class="valor-text" >R$ <?php echo number_format((float)($reg['valor_total'] ?? 0), 2, ',', '.'); ?></td>
                                                            <td class="data-text" ><?php echo $reg['data_exclusao'] ? date('d/m/Y H:i', strtotime($reg['data_exclusao'])) : ''; ?></td>
                                                            <td><?php echo htmlspecialchars((string)($reg['usuario_exclusao'] ?? 'N/A')); ?></td>
                                                            <td><?php echo htmlspecialchars($reg['motivo_exclusao'] ?? ''); ?></td>
                                                            <td><?php echo nl2br(htmlspecialchars($reg['observacao_exclusao'] ?? 'N/A')); ?></td>
                                                            <?php if ($showRestoreActions): ?>
                                                                <td>
                                                                    <div class="actions-container">
                                                                        <?php
                                                                            $cnpjLimpo = preg_replace('/\D/', '', (string)($reg['cnpj'] ?? ''));
                                                                            $raiz = substr($cnpjLimpo, 0, 8);
                                                                            $jaRestaurado = !empty($raiz) && isset($restauradosPorRaiz[$raiz]);
                                                                        ?>
                                                                        <button class="btn btn-sm <?php echo $jaRestaurado ? 'btn-secondary' : 'btn-success'; ?>" onclick="<?php echo $jaRestaurado ? 'return false;' : 'restaurarCliente(\'' . htmlspecialchars($reg['cnpj'] ?? '', ENT_QUOTES) . '\')'; ?>" <?php echo $jaRestaurado ? 'disabled' : ''; ?>>
                                                                            <i class="fas fa-undo"></i> <?php echo $jaRestaurado ? 'Já restaurado' : 'Restaurar'; ?>
                                                                        </button>
                                                                        
                                                                        <?php if (in_array($perfilUsuario, ['admin', 'diretor', 'supervisor'])): ?>
                                                                        <button class="btn btn-sm btn-warning" onclick="moverParaLixao('<?php echo htmlspecialchars($reg['cnpj'] ?? '', ENT_QUOTES); ?>')" title="Mover para Lixão">
                                                                            <i class="fas fa-trash-alt"></i> Para Lixão
                                                                        </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            </div>
                                            
                                            <!-- Paginação para clientes excluídos -->
                                            <?php if ($totalPaginasExcluidos > 1): ?>
                                                <div class="paginacao-container">
                                                    <div class="paginacao">
                                                        <?php
                                                            $baseParams = ['tab' => 'admin', 'sub_tab' => 'excluidos'];
                                                            if (!empty($filtro_cliente_excluido)) $baseParams['filtro_cliente_excluido'] = $filtro_cliente_excluido;
                                                            if (!empty($filtro_cnpj_excluido)) $baseParams['filtro_cnpj_excluido'] = $filtro_cnpj_excluido;
                                                            if (!empty($filtro_vendedor_excluido)) $baseParams['filtro_vendedor_excluido'] = $filtro_vendedor_excluido;
                                                            if (!empty($filtro_data_inicio_excluido)) $baseParams['filtro_data_inicio_excluido'] = $filtro_data_inicio_excluido;
                                                            if (!empty($filtro_data_fim_excluido)) $baseParams['filtro_data_fim_excluido'] = $filtro_data_fim_excluido;
                                                            if (!empty($filtro_motivo_excluido)) $baseParams['filtro_motivo_excluido'] = $filtro_motivo_excluido;
                                                        ?>
                                                        <?php if ($pagina_excluidos > 1): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_excluidos' => $pagina_excluidos - 1])); ?>" class="paginacao-btn">‹ Anterior</a>
                                                        <?php endif; ?>
                                                        
                                                        <?php for ($i = max(1, $pagina_excluidos - 2); $i <= min($totalPaginasExcluidos, $pagina_excluidos + 2); $i++): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_excluidos' => $i])); ?>" 
                                                               class="paginacao-btn <?php echo $i === $pagina_excluidos ? 'active' : ''; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endfor; ?>
                                                        
                                                        <?php if ($pagina_excluidos < $totalPaginasExcluidos): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_excluidos' => $pagina_excluidos + 1])); ?>" class="paginacao-btn">Próxima ›</a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="paginacao-info">
                                                        Mostrando <?php echo count($clientesExcluidos); ?> de <?php echo $totalExcluidos; ?> clientes excluídos
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (($sub_tab ?? '') === 'leads_excluidos'): ?>
                                    <div class="card">
                                        <!-- Filtros para Leads Excluídos -->
                                        <div class="filtros-container">
                                            <form method="GET" class="filtros-form">
                                                <input type="hidden" name="tab" value="admin">
                                                <input type="hidden" name="sub_tab" value="leads_excluidos">
                                                
                                                <div class="filtros-grid">
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_lead_excluido">Lead:</label>
                                                        <input type="text" name="filtro_lead_excluido" id="filtro_lead_excluido" placeholder="Nome fantasia ou razão social" value="<?php echo htmlspecialchars($filtro_lead_excluido ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_email_excluido">Email:</label>
                                                        <input type="text" name="filtro_email_excluido" id="filtro_email_excluido" placeholder="Email do lead" value="<?php echo htmlspecialchars($filtro_email_excluido ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_data_inicio_lead">Data Início:</label>
                                                        <input type="date" name="filtro_data_inicio_lead" id="filtro_data_inicio_lead" value="<?php echo htmlspecialchars($filtro_data_inicio_lead ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_data_fim_lead">Data Fim:</label>
                                                        <input type="date" name="filtro_data_fim_lead" id="filtro_data_fim_lead" value="<?php echo htmlspecialchars($filtro_data_fim_lead ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_motivo_lead">Motivo:</label>
                                                        <input type="text" name="filtro_motivo_lead" id="filtro_motivo_lead" placeholder="Motivo da exclusão" value="<?php echo htmlspecialchars($filtro_motivo_lead ?? ''); ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="filtros-acoes">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-filter"></i> Filtrar
                                                    </button>
                                                    <a href="?tab=admin&sub_tab=leads_excluidos" class="btn btn-limpar-filtros">
                                                        <i class="fas fa-times"></i> Limpar Filtros
                                                    </a>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <?php if (empty($leadsExcluidos)): ?>
                                            <div class="placeholder-container">Nenhum lead excluído encontrado.</div>
                                        <?php else: ?>
                                            <div class="table-container">
                                            <table class="table admin-table">
                                                <thead>
                                                    <tr style="background: #1a1a1a;">
                                                        <th class="table-header-cell">Nome</th>
                                                        <th class="table-header-cell">Email</th>
                                                        <th class="table-header-cell">Telefone</th>
                                                        <th class="table-header-cell">Status</th>
                                                        <th class="table-header-cell">Data de Exclusão</th>
                                                        <th class="table-header-cell">Usuário</th>
                                                        <th class="table-header-cell">Motivo</th>
                                                        <th class="table-header-cell">Observação de Exclusão</th>
                                                        <?php if ($showRestoreActions): ?>
                                                            <th class="table-header-cell table-header-cell-last">Ações</th>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($leadsExcluidos as $lead): ?>
                                                        <tr>
                                                            <td>
                                                                <div><?php echo htmlspecialchars($lead['nome_fantasia'] ?? $lead['razao_social'] ?? 'N/A'); ?></div>
                                                                <?php if (!empty($lead['razao_social']) && $lead['razao_social'] !== $lead['nome_fantasia']): ?>
                                                                    <div class="data-text"><?php echo htmlspecialchars($lead['razao_social']); ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($lead['email'] ?? ''); ?></td>
                                                            <td><?php echo htmlspecialchars($lead['telefone'] ?? 'N/A'); ?></td>
                                                            <td>
                                                                <span class="status-badge status-lead">
                                                                    <?php echo htmlspecialchars($lead['marcao_prospect'] ?? 'N/A'); ?>
                                                                </span>
                                                            </td>
                                                            <td class="data-text" ><?php echo $lead['data_exclusao'] ? date('d/m/Y H:i', strtotime($lead['data_exclusao'])) : 'N/A'; ?></td>
                                                            <td>
                                                                <?php 
                                                                if ($lead['usuario_exclusao']) {
                                                                    try {
                                                                        $stmtUsuario = $pdo->prepare("SELECT NOME_COMPLETO FROM USUARIOS WHERE ID = ?");
                                                                        $stmtUsuario->execute([$lead['usuario_exclusao']]);
                                                                        $nomeUsuario = $stmtUsuario->fetch(PDO::FETCH_COLUMN);
                                                                        echo htmlspecialchars($nomeUsuario ?: 'N/A');
                                                                    } catch (Exception $e) {
                                                                        echo 'N/A';
                                                                    }
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($lead['motivo_exclusao'] ?? 'N/A'); ?></td>
                                                            <td><?php echo nl2br(htmlspecialchars($lead['observacao_exclusao'] ?? 'N/A')); ?></td>
                                                            <?php if ($showRestoreActions): ?>
                                                                <td>
                                                                    <?php
                                                                        $jaRestaurado = !empty($lead['email']) && isset($restauradosPorEmail[$lead['email']]);
                                                                    ?>
                                                                    <button class="btn btn-sm <?php echo $jaRestaurado ? 'btn-secondary' : 'btn-success'; ?>" onclick="<?php echo $jaRestaurado ? 'return false;' : 'restaurarLead(\'' . htmlspecialchars($lead['email'], ENT_QUOTES) . '\')'; ?>" <?php echo $jaRestaurado ? 'disabled' : ''; ?>>
                                                                        <i class="fas fa-undo"></i> <?php echo $jaRestaurado ? 'Já restaurado' : 'Restaurar'; ?>
                                                                    </button>
                                                                </td>
                                                            <?php endif; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            </div>
                                            
                                            <!-- Paginação para leads excluídos -->
                                            <?php if ($totalPaginasLeadsExcluidos > 1): ?>
                                                <div class="paginacao-container">
                                                    <div class="paginacao">
                                                        <?php
                                                            $baseParams = ['tab' => 'admin', 'sub_tab' => 'leads_excluidos'];
                                                            if (!empty($filtro_lead_excluido)) $baseParams['filtro_lead_excluido'] = $filtro_lead_excluido;
                                                            if (!empty($filtro_email_excluido)) $baseParams['filtro_email_excluido'] = $filtro_email_excluido;
                                                            if (!empty($filtro_data_inicio_lead)) $baseParams['filtro_data_inicio_lead'] = $filtro_data_inicio_lead;
                                                            if (!empty($filtro_data_fim_lead)) $baseParams['filtro_data_fim_lead'] = $filtro_data_fim_lead;
                                                            if (!empty($filtro_motivo_lead)) $baseParams['filtro_motivo_lead'] = $filtro_motivo_lead;
                                                        ?>
                                                        <?php if ($pagina_leads_excluidos > 1): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_leads_excluidos' => $pagina_leads_excluidos - 1])); ?>" class="paginacao-btn">‹ Anterior</a>
                                                        <?php endif; ?>
                                                        
                                                        <?php for ($i = max(1, $pagina_leads_excluidos - 2); $i <= min($totalPaginasLeadsExcluidos, $pagina_leads_excluidos + 2); $i++): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_leads_excluidos' => $i])); ?>" 
                                                               class="paginacao-btn <?php echo $i === $pagina_leads_excluidos ? 'active' : ''; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endfor; ?>
                                                        
                                                        <?php if ($pagina_leads_excluidos < $totalPaginasLeadsExcluidos): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_leads_excluidos' => $pagina_leads_excluidos + 1])); ?>" class="paginacao-btn">Próxima ›</a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="paginacao-info">
                                                        Mostrando <?php echo count($leadsExcluidos); ?> de <?php echo $totalLeadsExcluidos; ?> leads excluídos
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (($sub_tab ?? '') === 'obs_excluidas' && strtolower(trim($usuario['perfil'])) === 'admin'): ?>
                                    <div class="card">
                                        <!-- Filtros para Observações Excluídas -->
                                        <div class="filtros-container">
                                            <form method="GET" class="filtros-form">
                                                <input type="hidden" name="tab" value="admin">
                                                <input type="hidden" name="sub_tab" value="obs_excluidas">
                                                
                                                <div class="filtros-grid">
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_excluida_tipo">Tipo:</label>
                                                        <select name="filtro_obs_excluida_tipo" id="filtro_obs_excluida_tipo">
                                                            <option value="">Todos</option>
                                                            <option value="cliente" <?php echo (isset($_GET['filtro_obs_excluida_tipo']) && $_GET['filtro_obs_excluida_tipo'] === 'cliente') ? 'selected' : ''; ?>>Cliente</option>
                                                            <option value="lead" <?php echo (isset($_GET['filtro_obs_excluida_tipo']) && $_GET['filtro_obs_excluida_tipo'] === 'lead') ? 'selected' : ''; ?>>Lead</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_excluida_identificador">Identificador:</label>
                                                        <input type="text" name="filtro_obs_excluida_identificador" id="filtro_obs_excluida_identificador" placeholder="CNPJ ou email" value="<?php echo htmlspecialchars($_GET['filtro_obs_excluida_identificador'] ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_excluida_usuario">Usuário:</label>
                                                        <input type="text" name="filtro_obs_excluida_usuario" id="filtro_obs_excluida_usuario" placeholder="Nome do usuário" value="<?php echo htmlspecialchars($_GET['filtro_obs_excluida_usuario'] ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_excluida_data_inicio">Data Início:</label>
                                                        <input type="date" name="filtro_obs_excluida_data_inicio" id="filtro_obs_excluida_data_inicio" value="<?php echo htmlspecialchars($_GET['filtro_obs_excluida_data_inicio'] ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_excluida_data_fim">Data Fim:</label>
                                                        <input type="date" name="filtro_obs_excluida_data_fim" id="filtro_obs_excluida_data_fim" value="<?php echo htmlspecialchars($_GET['filtro_obs_excluida_data_fim'] ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_obs_excluida_motivo">Motivo:</label>
                                                        <input type="text" name="filtro_obs_excluida_motivo" id="filtro_obs_excluida_motivo" placeholder="Motivo da exclusão" value="<?php echo htmlspecialchars($_GET['filtro_obs_excluida_motivo'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="filtros-acoes">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-filter"></i> Filtrar
                                                    </button>
                                                    <a href="?tab=admin&sub_tab=obs_excluidas" class="btn btn-limpar-filtros">
                                                        <i class="fas fa-times"></i> Limpar Filtros
                                                    </a>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <?php if (empty($observacoesExcluidas)): ?>
                                            <div class="placeholder-container">Nenhuma observação excluída encontrada.</div>
                                        <?php else: ?>
                                            <div class="table-container">
                                            <table class="table admin-table">
                                                <thead>
                                                    <tr style="background: #1a1a1a;">
                                                        <th class="table-header-cell">Excluída em</th>
                                                        <th class="table-header-cell">Motivo</th>
                                                        <th class="table-header-cell">Tipo</th>
                                                        <th class="table-header-cell">Identificador</th>
                                                        <th class="table-header-cell">Usuário</th>
                                                        <th class="table-header-cell">Texto</th>
                                                        <th class="table-header-cell table-header-cell-last">Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($observacoesExcluidas as $obs): ?>
                                                        <tr>
                                                            <td class="data-text" ><?php echo $obs['data_exclusao'] ? date('d/m/Y H:i', strtotime($obs['data_exclusao'])) : ''; ?></td>
                                                            <td><?php echo htmlspecialchars($obs['motivo_exclusao'] ?? ''); ?></td>
                                                            <td><?php echo htmlspecialchars(ucfirst($obs['tipo'] ?? '')); ?></td>
                                                            <td style="color: #6f42c1; font-weight: 500; font-family: 'Courier New', monospace;"><?php echo htmlspecialchars($obs['identificador'] ?? ''); ?></td>
                                                            <td><?php echo htmlspecialchars(($obs['usuario_nome'] ?? '') . ' (#' . ($obs['usuario_id'] ?? '') . ')'); ?></td>
                                                            <td><?php echo nl2br(htmlspecialchars($obs['observacao'] ?? '')); ?></td>
                                                            <td>
                                                                <div style="display:flex; gap:8px;">
                                                                    <button class="btn btn-sm btn-success" onclick="restaurarObservacao(<?php echo (int)$obs['observacao_id']; ?>)"><i class="fas fa-undo"></i> Restaurar</button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            </div>
                                            
                                            <!-- Paginação para observações excluídas -->
                                            <?php if ($totalPaginasObservacoesExcluidas > 1): ?>
                                                <div class="paginacao-container">
                                                    <div class="paginacao">
                                                        <?php
                                                            $baseParams = ['tab' => 'admin', 'sub_tab' => 'obs_excluidas'];
                                                            if (!empty($filtro_obs_excluida_tipo)) $baseParams['filtro_obs_excluida_tipo'] = $filtro_obs_excluida_tipo;
                                                            if (!empty($filtro_obs_excluida_identificador)) $baseParams['filtro_obs_excluida_identificador'] = $filtro_obs_excluida_identificador;
                                                            if (!empty($filtro_obs_excluida_usuario)) $baseParams['filtro_obs_excluida_usuario'] = $filtro_obs_excluida_usuario;
                                                            if (!empty($filtro_obs_excluida_data_inicio)) $baseParams['filtro_obs_excluida_data_inicio'] = $filtro_obs_excluida_data_inicio;
                                                            if (!empty($filtro_obs_excluida_data_fim)) $baseParams['filtro_obs_excluida_data_fim'] = $filtro_obs_excluida_data_fim;
                                                            if (!empty($filtro_obs_excluida_motivo)) $baseParams['filtro_obs_excluida_motivo'] = $filtro_obs_excluida_motivo;
                                                        ?>
                                                        <?php if ($pagina_observacoes > 1): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_observacoes' => $pagina_observacoes - 1])); ?>" class="paginacao-btn">‹ Anterior</a>
                                                        <?php endif; ?>
                                                        
                                                        <?php for ($i = max(1, $pagina_observacoes - 2); $i <= min($totalPaginasObservacoesExcluidas, $pagina_observacoes + 2); $i++): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_observacoes' => $i])); ?>" 
                                                               class="paginacao-btn <?php echo $i === $pagina_observacoes ? 'active' : ''; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endfor; ?>
                                                        
                                                        <?php if ($pagina_observacoes < $totalPaginasObservacoesExcluidas): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_observacoes' => $pagina_observacoes + 1])); ?>" class="paginacao-btn">Próxima ›</a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="paginacao-info">
                                                        Mostrando <?php echo count($observacoesExcluidas); ?> de <?php echo $totalObservacoesExcluidas; ?> observações excluídas
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (($sub_tab ?? '') === 'obs_excluidas'): ?>
                                    <div class="card">
                                        <div class="placeholder-container">
                                            <i class="fas fa-lock" style="font-size: 48px; color: #6c757d; margin-bottom: 16px;"></i>
                                            <h3>Acesso Restrito</h3>
                                            <p>Apenas administradores podem visualizar observações excluídas.</p>
                                        </div>
                                    </div>
                                <?php elseif (($sub_tab ?? '') === 'lixao'): ?>
                                    <div class="card">
                                        <!-- Filtros para Clientes do Lixão -->
                                        <div class="filtros-container">
                                            <form method="GET" class="filtros-form">
                                                <input type="hidden" name="tab" value="admin">
                                                <input type="hidden" name="sub_tab" value="lixao">
                                                
                                                <div class="filtros-grid">
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_lixao_cliente">Cliente:</label>
                                                        <input type="text" name="filtro_lixao_cliente" id="filtro_lixao_cliente" placeholder="Nome do cliente" value="<?php echo htmlspecialchars($filtro_lixao_cliente ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_lixao_cnpj">CNPJ:</label>
                                                        <input type="text" name="filtro_lixao_cnpj" id="filtro_lixao_cnpj" placeholder="CNPJ do cliente" value="<?php echo htmlspecialchars($filtro_lixao_cnpj ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_lixao_vendedor">Vendedor:</label>
                                                        <input type="text" name="filtro_lixao_vendedor" id="filtro_lixao_vendedor" placeholder="Nome ou código do vendedor" value="<?php echo htmlspecialchars($filtro_lixao_vendedor ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_lixao_data_inicio">Data Início:</label>
                                                        <input type="date" name="filtro_lixao_data_inicio" id="filtro_lixao_data_inicio" value="<?php echo htmlspecialchars($filtro_lixao_data_inicio ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_lixao_data_fim">Data Fim:</label>
                                                        <input type="date" name="filtro_lixao_data_fim" id="filtro_lixao_data_fim" value="<?php echo htmlspecialchars($filtro_lixao_data_fim ?? ''); ?>">
                                                    </div>
                                                    
                                                    <div class="filtro-grupo">
                                                        <label for="filtro_lixao_motivo">Motivo:</label>
                                                        <input type="text" name="filtro_lixao_motivo" id="filtro_lixao_motivo" placeholder="Motivo da exclusão" value="<?php echo htmlspecialchars($filtro_lixao_motivo ?? ''); ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="filtros-acoes">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-filter"></i> Filtrar
                                                    </button>
                                                    <a href="?tab=admin&sub_tab=lixao" class="btn btn-limpar-filtros">
                                                        <i class="fas fa-times"></i> Limpar Filtros
                                                    </a>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <?php if (isset($installSuccess)): ?>
                                            <div class="alert <?php echo $installSuccess ? 'alert-success' : 'alert-danger'; ?>" style="margin-bottom: 20px; padding: 15px; border-radius: 8px; border-left: 4px solid <?php echo $installSuccess ? '#28a745' : '#dc3545'; ?>; background: <?php echo $installSuccess ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $installSuccess ? '#155724' : '#721c24'; ?>;">
                                                <i class="fas <?php echo $installSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                                                <?php echo htmlspecialchars($installMessage); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($lixaoColumnMissing) && $lixaoColumnMissing): ?>
                                            <div class="placeholder-container">
                                                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ffc107; margin-bottom: 16px;"></i>
                                                <h3>Configuração Necessária</h3>
                                                <p>A coluna 'no_lixao' não existe na tabela clientes_excluidos.</p>
                                                <p>Execute o script SQL: <code>sql/adicionar_coluna_lixao.sql</code></p>
                                                <?php if (in_array($perfilUsuario, ['admin', 'diretor'])): ?>
                                                <p>Ou acesse: <a href="?tab=admin&sub_tab=lixao&install=1" class="btn btn-warning">Instalar Coluna</a></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (empty($clientesLixao)): ?>
                                            <div class="placeholder-container">
                                                <i class="fas fa-trash-alt" style="font-size: 48px; color: #6c757d; margin-bottom: 16px;"></i>
                                                <h3>Lixão Vazio</h3>
                                                <p>Nenhum cliente encontrado no lixão.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-container">
                                            <table class="table admin-table">
                                                <thead>
                                                    <tr class="table-header-row metas-header">
                                                        <th class="table-header-cell">Cliente</th>
                                                        <th class="table-header-cell">CNPJ</th>
                                                        <th class="table-header-cell">Vendedor</th>
                                                        <th class="table-header-cell">Valor Total</th>
                                                        <th class="table-header-cell">Data de Exclusão</th>
                                                        <th class="table-header-cell">Usuário</th>
                                                        <th class="table-header-cell">Motivo</th>
                                                        <th class="table-header-cell">Observação de Exclusão</th>
                                                        <th class="table-header-cell table-header-cell-last">Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($clientesLixao as $reg): ?>
                                                        <tr>
                                                            <td>
                                                                <div><?php echo htmlspecialchars($reg['cliente'] ?? ''); ?></div>
                                                                <div class="data-text"><?php echo htmlspecialchars($reg['nome_fantasia'] ?? ''); ?></div>
                                                            </td>
                                                            <td class="cnpj-cell"><?php echo htmlspecialchars($reg['cnpj'] ?? ''); ?></td>
                                                            <td>
                                                                <div class="vendedor-info">
                                                                    <span class="vendedor-nome"><?php echo htmlspecialchars($reg['nome_vendedor'] ?? ''); ?></span>
                                                                    <span class="vendedor-codigo">Cód: <?php echo htmlspecialchars($reg['cod_vendedor'] ?? ''); ?></span>
                                                                </div>
                                                            </td>
                                                            <td class="valor-text" >R$ <?php echo number_format((float)($reg['valor_total'] ?? 0), 2, ',', '.'); ?></td>
                                                            <td class="data-text" ><?php echo $reg['data_exclusao'] ? date('d/m/Y H:i', strtotime($reg['data_exclusao'])) : ''; ?></td>
                                                            <td><?php echo htmlspecialchars((string)($reg['usuario_exclusao'] ?? 'N/A')); ?></td>
                                                            <td><?php echo htmlspecialchars($reg['motivo_exclusao'] ?? ''); ?></td>
                                                            <td><?php echo nl2br(htmlspecialchars($reg['observacao_exclusao'] ?? 'N/A')); ?></td>
                                                            <td>
                                                                <div class="actions-container">
                                                                    <?php if (in_array($perfilUsuario, ['admin', 'diretor'])): ?>
                                                                    <button class="btn btn-sm btn-danger" onclick="excluirDefinitivamente('<?php echo htmlspecialchars($reg['cnpj'] ?? '', ENT_QUOTES); ?>')" title="Excluir Definitivamente">
                                                                        <i class="fas fa-trash"></i> Excluir Definitivamente
                                                                    </button>
                                                                    <?php endif; ?>
                                                                    <?php if ($perfilUsuario === 'admin'): ?>
                                                                    <button class="btn btn-sm btn-secondary" onclick="ocultarRegistro('<?php echo htmlspecialchars($reg['cnpj'] ?? '', ENT_QUOTES); ?>')" title="Ocultar Registro">
                                                                        <i class="fas fa-eye-slash"></i> Ocultar
                                                                    </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            </div>
                                            
                                            <!-- Paginação para clientes do lixão -->
                                            <?php if ($totalPaginasLixao > 1): ?>
                                                <div class="paginacao-container">
                                                    <div class="paginacao">
                                                        <?php
                                                            $baseParams = ['tab' => 'admin', 'sub_tab' => 'lixao'];
                                                            if (!empty($filtro_lixao_cliente)) $baseParams['filtro_lixao_cliente'] = $filtro_lixao_cliente;
                                                            if (!empty($filtro_lixao_cnpj)) $baseParams['filtro_lixao_cnpj'] = $filtro_lixao_cnpj;
                                                            if (!empty($filtro_lixao_vendedor)) $baseParams['filtro_lixao_vendedor'] = $filtro_lixao_vendedor;
                                                            if (!empty($filtro_lixao_data_inicio)) $baseParams['filtro_lixao_data_inicio'] = $filtro_lixao_data_inicio;
                                                            if (!empty($filtro_lixao_data_fim)) $baseParams['filtro_lixao_data_fim'] = $filtro_lixao_data_fim;
                                                            if (!empty($filtro_lixao_motivo)) $baseParams['filtro_lixao_motivo'] = $filtro_lixao_motivo;
                                                        ?>
                                                        <?php if ($pagina_lixao > 1): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_lixao' => $pagina_lixao - 1])); ?>" class="paginacao-btn">‹ Anterior</a>
                                                        <?php endif; ?>
                                                        
                                                        <?php for ($i = max(1, $pagina_lixao - 2); $i <= min($totalPaginasLixao, $pagina_lixao + 2); $i++): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_lixao' => $i])); ?>" 
                                                               class="paginacao-btn <?php echo $i === $pagina_lixao ? 'active' : ''; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endfor; ?>
                                                        
                                                        <?php if ($pagina_lixao < $totalPaginasLixao): ?>
                                                            <a href="?<?php echo http_build_query(array_merge($baseParams, ['pagina_lixao' => $pagina_lixao + 1])); ?>" class="paginacao-btn">Próxima ›</a>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="paginacao-info">
                                                        Mostrando <?php echo count($clientesLixao); ?> de <?php echo $totalLixao; ?> clientes no lixão
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <!-- ===== SEÇÃO DE GERENCIAMENTO DE METAS ===== -->
                <?php if ($tab === 'metas'): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-bullseye"></i> Gerenciamento de Metas</h3>
                                <p>Gerencie as metas de faturamento dos usuários do sistema</p>
                            </div>
                            
                            <!-- Mensagens de sucesso/erro -->
                            <?php if (isset($meta_editada_sucesso) && $meta_editada_sucesso): ?>
                                <div class="alert alert-success" style="margin: 1rem; padding: 0.75rem; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;">
                                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($meta_editada_mensagem); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($meta_editada_erro) && $meta_editada_erro): ?>
                                <div class="alert alert-danger" style="margin: 1rem; padding: 0.75rem; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($meta_editada_mensagem); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <?php if (!empty($usuarios_metas)): ?>
                                    <div class="metas-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                                        <?php 
                                        $contador_ranking = 0;
                                        foreach ($usuarios_metas as $dados_usuario): 
                                            $contador_ranking++;
                                            $posicao_ranking = $offset_metas + $contador_ranking;
                                            $usuario_meta = $dados_usuario['usuario'];
                                            $faturamento_atual = $dados_usuario['faturamento_atual'];
                                            $meta_atual = $dados_usuario['meta_atual'];
                                            $percentual_fat = $dados_usuario['percentual_fat'];
                                            
                                            // Determinar status
                                            $status_class = $percentual_fat >= 100 ? 'meta-atingida' : ($percentual_fat >= 80 ? 'meta-proxima' : 'meta-baixa');
                                        ?>
                                        <div class="meta-card-clean" style="position: relative;">
                                            <!-- Indicador de Ranking -->
                                            <div class="ranking-indicator <?php echo $posicao_ranking <= 3 ? 'top-3' : 'regular'; ?>">
                                                #<?php echo $posicao_ranking; ?>
                                            </div>
                                            
                                            <!-- Header -->
                                            <div style="text-align: center; margin-bottom: 1rem;">
                                                <h3 style="margin: 0; font-size: 1.5rem; font-weight: bold; color: #333;">R$ <?php echo number_format($faturamento_atual, 2, ',', '.'); ?></h3>
                                                <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">Faturamento do Mês</p>
                                            </div>
                                            
                                            <!-- Barra de Progresso -->
                                            <div style="margin-bottom: 1rem;">
                                                <div style="width: 100%; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                                    <div style="height: 100%; background: <?php echo $percentual_fat >= 100 ? '#4caf50' : ($percentual_fat >= 80 ? '#ff9800' : '#f44336'); ?>; width: <?php echo min($percentual_fat, 100); ?>%; transition: width 0.3s ease;"></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Métricas -->
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; font-size: 0.85rem;">
                                                <span style="color: #333; font-weight: 500;">R$ <?php echo number_format($faturamento_atual, 2, ',', '.'); ?></span>
                                                <span style="color: #333; font-weight: bold;"><?php echo number_format($percentual_fat, 1, ',', '.'); ?>%</span>
                                                <span style="color: #666;">R$ <?php echo number_format($meta_atual, 2, ',', '.'); ?></span>
                                            </div>
                                            
                                            <!-- Meta -->
                                            <div style="text-align: center; margin-bottom: 0.75rem; font-size: 0.9rem; color: #666;">
                                                Meta: R$ <?php echo number_format($meta_atual, 2, ',', '.'); ?>
                                            </div>
                                            
                                            <!-- Percentual Badge -->
                                            <div style="text-align: right; margin-bottom: 0.75rem;">
                                                <span class="percentual-badge <?php echo $percentual_fat >= 100 ? 'meta-atingida' : ($percentual_fat >= 80 ? 'meta-proxima' : 'meta-baixa'); ?>">
                                                    <?php echo number_format($percentual_fat, 1, ',', '.'); ?>%
                                                </span>
                                            </div>
                                            
                                            <!-- Status Button -->
                                            <div style="text-align: center; margin-bottom: 1rem;">
                                                <?php if ($percentual_fat >= 100): ?>
                                                    <span class="status-badge-meta meta-atingida">
                                                        META ATINGIDA!
                                                    </span>
                                                <?php elseif ($percentual_fat >= 80): ?>
                                                    <span class="status-badge-meta meta-proxima">
                                                        PRÓXIMO DA META
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge-meta meta-baixa">
                                                        BAIXA PERFORMANCE
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Informações do Usuário -->
                                            <div style="text-align: center; margin-bottom: 1rem; padding-top: 0.75rem; border-top: 1px solid #e0e0e0;">
                                                <div style="font-weight: 600; color: #333; margin-bottom: 0.25rem; font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($usuario_meta['NOME_COMPLETO']); ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #666;">
                                                    <?php echo $usuario_meta['COD_VENDEDOR']; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Botão Editar -->
                                            <div class="text-center">
                                                <button class="btn btn-sm btn-primary" onclick="editarMeta('<?php echo $usuario_meta['COD_VENDEDOR']; ?>', '<?php echo htmlspecialchars($usuario_meta['NOME_COMPLETO']); ?>', <?php echo $meta_atual; ?>)" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                                                    <i class="fas fa-edit"></i> Editar Meta
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Paginação -->
                                    <?php if ($totalPaginasMetas > 1): ?>
                                    <div class="pagination-container" style="text-align: center; margin-top: 2rem;">
                                        <div class="pagination-info" style="margin-bottom: 0.5rem; color: #666; font-size: 0.9rem;">
                                            Mostrando <?php echo $offset_metas + 1; ?> a <?php echo min($offset_metas + $por_pagina_metas, $totalMetas); ?> de <?php echo $totalMetas; ?> usuários
                                        </div>
                                        <div class="pagination">
                                            <?php if ($pagina_metas > 1): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina_metas' => $pagina_metas - 1])); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-chevron-left"></i> Anterior
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $pagina_metas - 2); $i <= min($totalPaginasMetas, $pagina_metas + 2); $i++): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina_metas' => $i])); ?>" 
                                                   class="btn btn-sm <?php echo $i === $pagina_metas ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                            
                                            <?php if ($pagina_metas < $totalPaginasMetas): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina_metas' => $pagina_metas + 1])); ?>" class="btn btn-sm btn-outline-primary">
                                                    Próximo <i class="fas fa-chevron-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <div class="no-data" style="text-align: center; padding: 2rem; color: #666;">
                                        <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; color: #ccc;"></i>
                                        <p>Nenhum usuário encontrado para gerenciamento de metas.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                <?php endif; ?>
            </main>
            <?php include __DIR__ . '/../../includes/components/footer.php'; ?>
        </div>
    </div>

    <!-- Modal de Detalhes da Ligação -->
    <div id="modalDetalhesLigacao" class="modal hidden-row">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-phone"></i> Detalhes da Ligação</h3>
                <button class="btn-fechar-modal" onclick="fecharModalDetalhes()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalDetalhesBody">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Modal de Observações -->
    <div id="modalObservacoes" class="modal hidden-row">
        <div class="modal-content modal-observacoes">
            <div class="modal-header">
                <h3><i class="fas fa-comments"></i> Todas as Observações</h3>
                <button class="btn-fechar-modal" onclick="fecharModalObservacoes()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalObservacoesBody">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>

    <!-- Modal de Responder Observação -->
    <div id="modalResponderObservacao" class="modal hidden-row">
        <div class="modal-content modal-responder">
            <div class="modal-header">
                <h3><i class="fas fa-reply"></i> Responder Observação</h3>
                <button class="btn-fechar-modal" onclick="fecharModalResponder()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formResponderObservacao">
                    <div class="observacao-original">
                        <h4><i class="fas fa-quote-left"></i> Observação Original</h4>
                        <div id="observacaoOriginalTexto" class="obs-original-container">
                            <!-- Será preenchido via JavaScript -->
                        </div>
                    </div>
                    
                    <div class="resposta-container">
                        <h4><i class="fas fa-edit"></i> Sua Resposta</h4>
                        <textarea 
                            id="textoResposta" 
                            name="resposta" 
                            placeholder="Digite sua resposta aqui..." 
                            rows="5" 
                            required
                            maxlength="1000"
                        ></textarea>
                        <div class="contador-caracteres">
                            <span id="contadorCaracteres">0</span>/1000 caracteres
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="fecharModalResponder()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Enviar Resposta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Edição de Meta -->
    <div id="modalEditarMeta" class="modal hidden-row">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-bullseye"></i> Editar Meta de Faturamento</h3>
                <button class="btn-fechar-modal" onclick="fecharModalEditarMeta()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formEditarMeta" method="POST">
                    <input type="hidden" name="action" value="editar_meta">
                    <input type="hidden" name="cod_vendedor" id="cod_vendedor_meta">
                    
                    <div class="form-group">
                        <label for="nome_usuario_meta">Usuário:</label>
                        <input type="text" id="nome_usuario_meta" class="form-control" readonly style="background: #f8f9fa;">
                    </div>
                    
                    <div class="form-group">
                        <label for="nova_meta">Nova Meta de Faturamento (R$):</label>
                        <input type="number" id="nova_meta" name="nova_meta" class="form-control" step="0.01" min="0" required>
                        <small class="form-text text-muted">Digite o valor da meta em reais (ex: 50000.00)</small>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="fecharModalEditarMeta()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Meta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url('assets/js/admin.js'); ?>"></script>
    <script src="<?php echo base_url('assets/js/gestao-ligacoes.js'); ?>"></script>
    
    
    <script>
    // Função para mudar a visão do diretor
    function mudarVisao() {
        console.log('Função mudarVisao() chamada em admin_gestao_unificado.php');
        
        const supervisorSelect = document.getElementById('visao_supervisor');
        const vendedorSelect = document.getElementById('visao_vendedor');

        if (!vendedorSelect) {
            console.log('Seletor de vendedor não encontrado');
            return;
        }

        const supervisorSelecionado = supervisorSelect ? supervisorSelect.value : '';
        const vendedorSelecionado = vendedorSelect.value;

        // Construir URL com parâmetros
        const urlParams = new URLSearchParams();
        urlParams.set('tab', 'ligacoes');

        if (supervisorSelecionado) {
            urlParams.set('visao_supervisor', supervisorSelecionado);
        }

        if (vendedorSelecionado) {
            urlParams.set('visao_vendedor', vendedorSelecionado);
        }

        // Manter os filtros existentes
        const filtroStatus = document.getElementById('filtro_status').value;
        const filtroDataInicio = document.getElementById('filtro_data_inicio').value;
        const filtroDataFim = document.getElementById('filtro_data_fim').value;
        const filtroUsuario = document.getElementById('filtro_usuario').value;
        const filtroCliente = document.getElementById('filtro_cliente').value;

        if (filtroStatus) urlParams.set('filtro_status', filtroStatus);
        if (filtroDataInicio) urlParams.set('filtro_data_inicio', filtroDataInicio);
        if (filtroDataFim) urlParams.set('filtro_data_fim', filtroDataFim);
        if (filtroUsuario) urlParams.set('filtro_usuario', filtroUsuario);
        if (filtroCliente) urlParams.set('filtro_cliente', filtroCliente);

        const novaURL = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        
        // Recarregar a página com os novos parâmetros
        window.location.href = novaURL;
    }

    // Função para mudar a visão das observações
    function mudarVisaoObservacoes() {
        console.log('Função mudarVisaoObservacoes() chamada em admin_gestao_unificado.php');
        
        const supervisorSelect = document.getElementById('visao_supervisor_obs');
        const vendedorSelect = document.getElementById('visao_vendedor_obs');

        if (!vendedorSelect) {
            console.log('Seletor de vendedor de observações não encontrado');
            return;
        }

        const supervisorSelecionado = supervisorSelect ? supervisorSelect.value : '';
        const vendedorSelecionado = vendedorSelect.value;

        // Construir URL com parâmetros
        const urlParams = new URLSearchParams();
        urlParams.set('tab', 'admin');
        urlParams.set('sub_tab', 'observacoes');

        if (supervisorSelecionado) {
            urlParams.set('visao_supervisor_obs', supervisorSelecionado);
        }

        if (vendedorSelecionado) {
            urlParams.set('visao_vendedor_obs', vendedorSelecionado);
        }

        // Manter os filtros existentes
        const filtroDataInicio = document.getElementById('filtro_obs_data_inicio').value;
        const filtroDataFim = document.getElementById('filtro_obs_data_fim').value;
        const filtroUsuario = document.getElementById('filtro_obs_usuario').value;
        const filtroCliente = document.getElementById('filtro_obs_cliente').value;

        if (filtroDataInicio) urlParams.set('filtro_obs_data_inicio', filtroDataInicio);
        if (filtroDataFim) urlParams.set('filtro_obs_data_fim', filtroDataFim);
        if (filtroUsuario) urlParams.set('filtro_obs_usuario', filtroUsuario);
        if (filtroCliente) urlParams.set('filtro_obs_cliente', filtroCliente);

        const novaURL = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        
        // Recarregar a página com os novos parâmetros
        window.location.href = novaURL;
    }

    // Função para atualizar vendedores quando supervisor mudar
    function atualizarVendedores() {
        const supervisorSelect = document.getElementById('visao_supervisor');
        const vendedorSelect = document.getElementById('visao_vendedor');
        
        if (!supervisorSelect || !vendedorSelect) {
            return;
        }
        
        const supervisorSelecionado = supervisorSelect.value;
        
        // Mostrar loading no seletor de vendedores
        vendedorSelect.innerHTML = '<option value="">Carregando vendedores...</option>';
        vendedorSelect.disabled = true;
        
        if (supervisorSelecionado) {
            // Buscar vendedores do supervisor selecionado via AJAX
            fetch(window.baseUrl(`includes/api/buscar_vendedores.php`) + `?supervisor=${encodeURIComponent(supervisorSelecionado)}`)
                .then(response => response.json())
                .then(data => {
                    vendedorSelect.disabled = false;
                    if (data.success && data.vendedores) {
                        vendedorSelect.innerHTML = '<option value="">Todos os Vendedores</option>';
                        data.vendedores.forEach(vendedor => {
                            const option = document.createElement('option');
                            option.value = vendedor.COD_VENDEDOR;
                            option.textContent = vendedor.NOME_COMPLETO;
                            vendedorSelect.appendChild(option);
                        });
                    } else {
                        vendedorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar vendedores:', error);
                    vendedorSelect.disabled = false;
                    vendedorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
                });
        } else {
            // Se nenhum supervisor selecionado, carregar todos os vendedores
            fetch(window.baseUrl('includes/api/buscar_vendedores.php'))
                .then(response => response.json())
                .then(data => {
                    vendedorSelect.disabled = false;
                    if (data.success && data.vendedores) {
                        vendedorSelect.innerHTML = '<option value="">Todos os Vendedores</option>';
                        data.vendedores.forEach(vendedor => {
                            const option = document.createElement('option');
                            option.value = vendedor.COD_VENDEDOR;
                            option.textContent = vendedor.NOME_COMPLETO;
                            vendedorSelect.appendChild(option);
                        });
                    } else {
                        vendedorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar vendedores:', error);
                    vendedorSelect.disabled = false;
                    vendedorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
                });
        }
    }

    // Função para atualizar vendedores das observações quando supervisor mudar
    function atualizarVendedoresObservacoes() {
        const supervisorSelect = document.getElementById('visao_supervisor_obs');
        const vendedorSelect = document.getElementById('visao_vendedor_obs');
        
        if (!supervisorSelect || !vendedorSelect) {
            return;
        }
        
        const supervisorSelecionado = supervisorSelect.value;
        
        // Mostrar loading no seletor de vendedores
        vendedorSelect.innerHTML = '<option value="">Carregando vendedores...</option>';
        vendedorSelect.disabled = true;
        
        if (supervisorSelecionado) {
            // Buscar vendedores do supervisor selecionado via AJAX
            fetch(window.baseUrl(`includes/api/buscar_vendedores.php`) + `?supervisor=${encodeURIComponent(supervisorSelecionado)}`)
                .then(response => response.json())
                .then(data => {
                    vendedorSelect.disabled = false;
                    if (data.success && data.vendedores) {
                        vendedorSelect.innerHTML = '<option value="">Todos os Vendedores</option>';
                        data.vendedores.forEach(vendedor => {
                            const option = document.createElement('option');
                            option.value = vendedor.COD_VENDEDOR;
                            option.textContent = vendedor.NOME_COMPLETO;
                            vendedorSelect.appendChild(option);
                        });
                    } else {
                        vendedorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar vendedores das observações:', error);
                    vendedorSelect.disabled = false;
                    vendedorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
                });
        } else {
            // Se nenhum supervisor selecionado, carregar todos os vendedores
            fetch(window.baseUrl('includes/api/buscar_vendedores.php'))
                .then(response => response.json())
                .then(data => {
                    vendedorSelect.disabled = false;
                    if (data.success && data.vendedores) {
                        vendedorSelect.innerHTML = '<option value="">Todos os Vendedores</option>';
                        data.vendedores.forEach(vendedor => {
                            const option = document.createElement('option');
                            option.value = vendedor.COD_VENDEDOR;
                            option.textContent = vendedor.NOME_COMPLETO;
                            vendedorSelect.appendChild(option);
                        });
                    } else {
                        vendedorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar vendedores das observações:', error);
                    vendedorSelect.disabled = false;
                    vendedorSelect.innerHTML = '<option value="">Erro ao carregar vendedores</option>';
                });
        }
    }

    // Adicionar event listeners quando o DOM estiver carregado
    document.addEventListener('DOMContentLoaded', function() {
        try {
            const supervisorSelect = document.getElementById('visao_supervisor');
            const vendedorSelect = document.getElementById('visao_vendedor');
            const supervisorObsSelect = document.getElementById('visao_supervisor_obs');
            const vendedorObsSelect = document.getElementById('visao_vendedor_obs');

            if (supervisorSelect) {
                supervisorSelect.addEventListener('change', function() {
                    // Atualizar vendedores primeiro
                    atualizarVendedores();
                    // Depois mudar a visão
                    setTimeout(mudarVisao, 100);
                });
            }

            if (vendedorSelect) {
                vendedorSelect.addEventListener('change', function() {
                    mudarVisao();
                });
            }

            // Event listeners para filtros de observações
            if (supervisorObsSelect) {
                supervisorObsSelect.addEventListener('change', function() {
                    // Atualizar vendedores primeiro
                    atualizarVendedoresObservacoes();
                    // Depois mudar a visão
                    setTimeout(mudarVisaoObservacoes, 100);
                });
            }

            if (vendedorObsSelect) {
                vendedorObsSelect.addEventListener('change', function() {
                    mudarVisaoObservacoes();
                });
            }

            // Debug: Verificar se os botões de detalhes estão sendo encontrados
            console.log('Verificando botões de detalhes...');
            const botoesDetalhes = document.querySelectorAll('.btn-detalhes');
            console.log('Botões de detalhes encontrados:', botoesDetalhes.length);
            
            // Verificar se o modal existe
            const modal = document.getElementById('modalDetalhesLigacao');
            console.log('Modal encontrado:', modal ? 'Sim' : 'Não');
            
        } catch (error) {
            console.error('Erro ao configurar seletores de visão:', error);
        }
    });

    // Função para restaurar lead excluído
    function restaurarLead(email) {
        if (!email) {
            alert('Email do lead não encontrado.');
            return;
        }

        if (!confirm('Tem certeza que deseja restaurar este lead?')) {
            return;
        }

        fetch(window.baseUrl('includes/crud/restaurar_lead.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Lead restaurado com sucesso!');
                window.location.reload();
            } else {
                alert('Erro ao restaurar lead: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro na comunicação com o servidor.');
        });
    }

    // Função para mover cliente para o lixão
    function moverParaLixao(cnpj) {
        if (!cnpj) {
            alert('CNPJ do cliente não encontrado.');
            return;
        }

        if (!confirm('ATENÇÃO: Tem certeza que deseja mover este cliente para o LIXÃO?\n\nEsta ação é IRREVERSÍVEL e o cliente será excluído definitivamente do sistema.')) {
            return;
        }

        // Confirmação adicional
        if (!confirm('CONFIRMAÇÃO FINAL: Você tem certeza absoluta?\n\nO cliente será removido permanentemente do banco de dados.')) {
            return;
        }

        fetch(window.baseUrl('includes/crud/mover_para_lixao.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'cnpj=' + encodeURIComponent(cnpj)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cliente movido para o lixão com sucesso!');
                window.location.reload();
            } else {
                alert('Erro ao mover cliente para o lixão: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro na comunicação com o servidor.');
        });
    }

    // Função para excluir definitivamente do lixão
    function excluirDefinitivamente(cnpj) {
        if (!cnpj) {
            alert('CNPJ do cliente não encontrado.');
            return;
        }

        if (!confirm('ATENÇÃO: Tem certeza que deseja EXCLUIR DEFINITIVAMENTE este cliente?\n\nEsta ação é IRREVERSÍVEL e o cliente será removido permanentemente do banco de dados.')) {
            return;
        }

        // Confirmação adicional
        if (!confirm('CONFIRMAÇÃO FINAL: Você tem certeza absoluta?\n\nO cliente será DELETADO PERMANENTEMENTE do sistema.')) {
            return;
        }

        fetch(window.baseUrl('includes/crud/excluir_definitivamente.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'cnpj=' + encodeURIComponent(cnpj)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cliente excluído definitivamente com sucesso!');
                window.location.reload();
            } else {
                alert('Erro ao excluir cliente definitivamente: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro na comunicação com o servidor.');
        });
    }

    // Função para ocultar registro (apenas admin)
    function ocultarRegistro(cnpj) {
        if (!cnpj) {
            alert('CNPJ do cliente não encontrado.');
            return;
        }

        if (!confirm('Tem certeza que deseja ocultar este registro do lixão?\n\nO registro ficará invisível para diretores, mas permanecerá no banco de dados.')) {
            return;
        }

        fetch(window.baseUrl('includes/crud/ocultar_registro.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'cnpj=' + encodeURIComponent(cnpj)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Registro ocultado com sucesso!');
                window.location.reload();
            } else {
                alert('Erro ao ocultar registro: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro na comunicação com o servidor.');
        });
    }

    // Dados das observações (para uso na interface)
    const observacoesData = <?php echo json_encode($observacoesPorCliente ?? []); ?>;

    // Função para expandir/recolher observações na tabela
    function toggleObservacoesCliente(raizKey) {
        const linhasExpandidas = document.querySelectorAll(`tr.obs-expandida[data-raiz="${raizKey}"]`);
        const icon = document.getElementById(`icon-${raizKey}`);
        
        // Verificar se as linhas estão visíveis
        const primeiraLinha = linhasExpandidas[0];
        const estaVisivel = primeiraLinha && primeiraLinha.style.display !== 'none';
        
        linhasExpandidas.forEach(linha => {
            if (estaVisivel) {
                linha.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
            } else {
                linha.style.display = 'table-row';
                icon.className = 'fas fa-chevron-up';
            }
        });
    }

    // Função para abrir modal com todas as observações (mantida para compatibilidade)
    function abrirModalObservacoes(raizKey) {
        const dadosCliente = observacoesData[raizKey];
        if (!dadosCliente) return;

        const modal = document.getElementById('modalObservacoes');
        const modalBody = document.getElementById('modalObservacoesBody');
        
        let html = `
            <div class="modal-cliente-info">
                <h4><i class="fas fa-building"></i> ${dadosCliente.cliente_nome}</h4>
                <div class="modal-badges">
                    <span class="badge-raiz">Raiz: ${dadosCliente.raiz_cnpj}</span>
                    <span class="badge-total">${dadosCliente.observacoes.length} observações</span>
                </div>
            </div>
            <div class="modal-observacoes-lista">
        `;

        dadosCliente.observacoes.forEach((obs, index) => {
            const isReply = obs.parent_id ? true : false;
            html += `
                <div class="modal-obs-item ${index % 2 === 0 ? 'even' : 'odd'}">
                    <div class="modal-obs-header">
                        <div class="modal-obs-meta">
                            <span class="modal-obs-date">${obs.data_formatada || ''}</span>
                            <span class="modal-obs-user">${obs.usuario_nome || ''}</span>
                            <span class="modal-obs-cnpj">${obs.identificador || ''}</span>
                        </div>
                        <div class="modal-obs-actions">
                            <button class="btn btn-xs btn-danger" onclick="excluirObservacao(${obs.id})" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="btn btn-xs btn-info" onclick="responderObservacao(${obs.id})" title="Responder">
                                <i class="fas fa-reply"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-obs-content">
                        ${isReply ? '<span class="reply-indicator">↳ Resposta:</span>' : ''}
                        ${(obs.observacao || '').replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        modalBody.innerHTML = html;
        modal.style.display = 'flex';
    }

    // Função para fechar modal de observações
    function fecharModalObservacoes() {
        document.getElementById('modalObservacoes').style.display = 'none';
    }

    // Função para abrir modal de responder observação
    function responderObservacao(observacaoId) {
        // Buscar dados da observação
        fetch(window.baseUrl(`includes/api/buscar_observacao.php`) + `?id=${observacaoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = document.getElementById('modalResponderObservacao');
                    const observacaoOriginal = document.getElementById('observacaoOriginalTexto');
                    
                    // Preencher observação original
                    observacaoOriginal.innerHTML = `
                        <div class="obs-meta">
                            <span class="obs-date">${data.observacao.data_formatada}</span>
                            <span class="obs-user">${data.observacao.usuario_nome}</span>
                            <span class="obs-cnpj">${data.observacao.identificador}</span>
                        </div>
                        <div class="obs-texto">${data.observacao.observacao}</div>
                    `;
                    
                    // Armazenar ID da observação para envio
                    document.getElementById('formResponderObservacao').dataset.observacaoId = observacaoId;
                    
                    // Limpar textarea
                    document.getElementById('textoResposta').value = '';
                    document.getElementById('contadorCaracteres').textContent = '0';
                    
                    // Mostrar modal
                    modal.style.display = 'flex';
                } else {
                    alert('Erro ao carregar observação: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro na comunicação com o servidor.');
            });
    }

    // Função para fechar modal de responder
    function fecharModalResponder() {
        document.getElementById('modalResponderObservacao').style.display = 'none';
        document.getElementById('textoResposta').value = '';
        document.getElementById('contadorCaracteres').textContent = '0';
    }

    // Função para excluir observação (apenas admins)
    function excluirObservacao(observacaoId) {
        if (!confirm('Tem certeza que deseja excluir esta observação?')) {
            return;
        }

        fetch(window.baseUrl('includes/crud/excluir_observacao.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `observacao_id=${observacaoId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Observação excluída com sucesso!');
                window.location.reload(); // Recarregar para atualizar a lista
            } else {
                alert('Erro ao excluir observação: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro na comunicação com o servidor.');
        });
    }

    // Função para abrir modal de edição de meta
    function editarMeta(codVendedor, nomeUsuario, metaAtual) {
        document.getElementById('cod_vendedor_meta').value = codVendedor;
        document.getElementById('nome_usuario_meta').value = nomeUsuario;
        document.getElementById('nova_meta').value = metaAtual;
        document.getElementById('modalEditarMeta').style.display = 'block';
    }

    // Função para fechar modal de edição de meta
    function fecharModalEditarMeta() {
        document.getElementById('modalEditarMeta').style.display = 'none';
        document.getElementById('formEditarMeta').reset();
    }

    // Event listener para fechar modal clicando fora
    document.addEventListener('DOMContentLoaded', function() {
        window.addEventListener('click', function(event) {
            const modalObs = document.getElementById('modalObservacoes');
            const modalResp = document.getElementById('modalResponderObservacao');
            const modalMeta = document.getElementById('modalEditarMeta');
            
            if (event.target === modalObs) {
                fecharModalObservacoes();
            }
            if (event.target === modalResp) {
                fecharModalResponder();
            }
            if (event.target === modalMeta) {
                fecharModalEditarMeta();
            }
        });

        // Contador de caracteres para o textarea
        const textoResposta = document.getElementById('textoResposta');
        const contador = document.getElementById('contadorCaracteres');
        
        if (textoResposta && contador) {
            textoResposta.addEventListener('input', function() {
                contador.textContent = this.value.length;
                
                // Mudar cor quando próximo do limite
                if (this.value.length > 900) {
                    contador.style.color = '#dc3545';
                } else if (this.value.length > 800) {
                    contador.style.color = '#ffc107';
                } else {
                    contador.style.color = '#6c757d';
                }
            });
        }

        // Submit do formulário de resposta
        const formResponder = document.getElementById('formResponderObservacao');
        if (formResponder) {
            formResponder.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const observacaoId = this.dataset.observacaoId;
                const resposta = document.getElementById('textoResposta').value.trim();
                
                if (!resposta) {
                    alert('Por favor, digite uma resposta.');
                    return;
                }
                
                // Enviar resposta
                const formData = new FormData();
                formData.append('observacao_id', observacaoId);
                formData.append('resposta', resposta);
                
                fetch(window.baseUrl('includes/crud/enviar_resposta_observacao.php'), {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // Verificar se a resposta é JSON válida
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Resposta não é JSON válida');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Resposta do servidor:', data); // Debug
                    if (data.success) {
                        alert('Resposta enviada com sucesso!');
                        fecharModalResponder();
                        window.location.reload(); // Recarregar para mostrar a nova resposta
                    } else {
                        alert('Erro ao enviar resposta: ' + (data.message || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    console.error('Erro detalhado:', error);
                    alert('Erro na comunicação com o servidor: ' + error.message);
                });
            });
        }
        
        // Controle de exibição mobile
        function toggleMobileElements() {
            const isMobile = window.innerWidth <= 768;
            const tables = document.querySelectorAll('.table-wrapper, .table-container, .table');
            const mobileCards = document.querySelectorAll('.mobile-cards-container');
            
            tables.forEach(table => {
                if (isMobile) {
                    table.style.display = 'none';
                } else {
                    table.style.display = 'block';
                }
            });
            
            mobileCards.forEach(cards => {
                if (isMobile) {
                    cards.style.display = 'block';
                } else {
                    cards.style.display = 'none';
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
        
        // Nota: As funções verDetalhesLigacao e excluirLigacao estão definidas em gestao-ligacoes.js
        // e serão usadas automaticamente através dos event listeners configurados lá
        
        // Melhorar experiência mobile
        if (window.innerWidth <= 768) {
            // Adicionar indicador visual para cards mobile
            const cards = document.querySelectorAll('.ligacao-card, .observacao-card');
            cards.forEach(card => {
                card.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                
                card.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }
    });
    </script>
</body>
</html>
