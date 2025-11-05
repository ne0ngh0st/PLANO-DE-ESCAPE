<?php
require_once __DIR__ . '/../config/config.php';

// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$perfil_usuario = strtolower(trim($usuario['perfil']));


// Verificar permissões
if (!in_array($perfil_usuario, ['vendedor', 'representante', 'supervisor', 'diretor', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'load_pedidos':
            loadPedidos();
            break;
        case 'export':
            exportPedidos();
            break;
        default:
            throw new Exception('Ação não reconhecida');
    }
} catch (Exception $e) {
    error_log("Erro em pedidos_abertos_ajax.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function loadPedidos() {
    global $pdo, $usuario, $perfil_usuario;
    
    // Parâmetros de filtro
    $busca_cliente = $_POST['busca_cliente'] ?? '';
    $visao_supervisor = $_POST['visao_supervisor'] ?? '';
    $filtro_vendedor = $_POST['filtro_vendedor'] ?? '';
    $filtro_segmento = $_POST['filtro_segmento'] ?? '';
    $filtro_ano = $_POST['filtro_ano'] ?? '';
    $filtro_mes = $_POST['filtro_mes'] ?? '';
    $filtro_status = $_POST['filtro_status'] ?? '';
    $filtro_atraso = $_POST['filtro_atraso'] ?? '';
    $page = intval($_POST['page'] ?? 1);
    $perPage = 50;
    $offset = ($page - 1) * $perPage;
    
    // Debug: log dos filtros recebidos
    error_log("DEBUG - Filtros recebidos: busca_cliente=$busca_cliente, visao_supervisor=$visao_supervisor, vendedor=$filtro_vendedor, segmento=$filtro_segmento, ano=$filtro_ano, mes=$filtro_mes, status=$filtro_status, atraso=$filtro_atraso, page=$page");
    
    // Construir condições WHERE
    $where_conditions = [];
    $params = [];
    
    // Debug: log inicial
    error_log("DEBUG - Iniciando construção de WHERE conditions");
    
    // Filtro por perfil do usuário
    if ($perfil_usuario === 'vendedor' || $perfil_usuario === 'representante') {
        $where_conditions[] = "COD_VENDEDOR = ?";
        $params[] = $usuario['cod_vendedor'];
    } elseif ($perfil_usuario === 'supervisor') {
        // Para supervisores, mostrar vendedores da sua equipe
        if (!empty($filtro_vendedor)) {
            $where_conditions[] = "COD_VENDEDOR = ?";
            $params[] = $filtro_vendedor;
        } else {
            $where_conditions[] = "COD_VENDEDOR IN (
                SELECT COD_VENDEDOR FROM USUARIOS 
                WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
            )";
            $params[] = $usuario['cod_vendedor'];
        }
    }
    // Filtro de supervisão (apenas para supervisores, diretores e admins)
    if (!empty($visao_supervisor) && in_array($perfil_usuario, ['supervisor', 'diretor', 'admin'])) {
        $where_conditions[] = "COD_VENDEDOR IN (
            SELECT COD_VENDEDOR FROM USUARIOS 
            WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
        )";
        $params[] = str_pad($visao_supervisor, 3, '0', STR_PAD_LEFT);
        
        if (!empty($filtro_vendedor)) {
            $where_conditions[] = "COD_VENDEDOR = ?";
            $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
        }
    }
    
    // Filtro de vendedor (apenas para supervisores, diretores e admins)
    if (!empty($filtro_vendedor) && empty($visao_supervisor) && in_array($perfil_usuario, ['supervisor', 'diretor', 'admin'])) {
        $where_conditions[] = "COD_VENDEDOR = ?";
        $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
    }
    
    if (!empty($filtro_segmento)) {
        $where_conditions[] = "SEGMENTO = ?";
        $params[] = $filtro_segmento;
    }
    
    
    // Filtro de ano/mês usando data do pedido
    if (!empty($filtro_ano)) {
        $where_conditions[] = "YEAR(STR_TO_DATE(DATA_PEDIDO, '%d/%m/%Y')) = ?";
        $params[] = intval($filtro_ano);
    }
    if (!empty($filtro_mes)) {
        $where_conditions[] = "MONTH(STR_TO_DATE(DATA_PEDIDO, '%d/%m/%Y')) = ?";
        $params[] = intval($filtro_mes);
    }
    
    // Busca discricionária por nome ou CNPJ
    if (!empty($busca_cliente)) {
        $busca_termo = trim($busca_cliente);
        if (strlen($busca_termo) >= 2) { // Mínimo 2 caracteres para busca
            $where_conditions[] = "(CLIENTE LIKE ? OR CNPJ LIKE ?)";
            $busca_param = '%' . $busca_termo . '%';
            $params[] = $busca_param;
            $params[] = $busca_param;
        }
    }
    
    // Filtro de status (baseado no campo HISTORICO)
    if (!empty($filtro_status)) {
        switch ($filtro_status) {
            case 'separacao':
                $where_conditions[] = "(HISTORICO LIKE '%SEPARACAO%' OR HISTORICO LIKE '%SEPARAÇÃO%')";
                break;
            case 'bloqueio':
                $where_conditions[] = "HISTORICO LIKE '%BLOQUEIO%'";
                break;
            case 'wms':
                $where_conditions[] = "HISTORICO LIKE '%WMS%'";
                break;
        }
    }
    
    // Filtro de atraso (baseado nos diferentes tipos de atraso)
    if (!empty($filtro_atraso)) {
        $hoje = date('d/m/Y');
        switch ($filtro_atraso) {
            case 'atraso_faturamento':
                $where_conditions[] = "STR_TO_DATE(DATA_PREVISAO_FATURAMENTO, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')";
                $where_conditions[] = "DATA_PREVISAO_FATURAMENTO IS NOT NULL AND DATA_PREVISAO_FATURAMENTO != ''";
                $params[] = $hoje;
                break;
            case 'atraso_entrega':
                $where_conditions[] = "STR_TO_DATE(DATA_ENTREGA, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')";
                $where_conditions[] = "DATA_ENTREGA IS NOT NULL AND DATA_ENTREGA != ''";
                $params[] = $hoje;
                break;
            case 'atrasados':
                $where_conditions[] = "ATRASO > 0";
                break;
            case 'no_prazo':
                $where_conditions[] = "ATRASO <= 0";
                break;
        }
    }
    
    
    // Construir query base
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Debug: log da query construída
    error_log("DEBUG - WHERE clause: $where_clause");
    error_log("DEBUG - Parâmetros: " . implode(', ', $params));
    
    // Query para contar total
    $count_sql = "SELECT COUNT(*) as total FROM PEDIDOS_EM_ABERTO {$where_clause}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $perPage);
    
    // Query principal
    $sql = "SELECT 
                FILIAL,
                COD_CLIENT,
                LOJA,
                CNPJ,
                CLIENTE,
                NOME_FANTASIA,
                Endereco,
                Estado,
                COD_VENDEDOR,
                NOME_VENDEDOR,
                DATA_PEDIDO,
                NUMERO_PEDIDO,
                DIGITACAO,
                CARGA,
                DATA_ENTREGA,
                DATA_PREVISAO_FATURAMENTO,
                DATA_PCP,
                ATRASO,
                TEMPO_VIAGEM,
                CONDICAO_PAGAMENTO,
                COD_PRODUTO,
                DESCRICAO_PRODUTO,
                QUANTIDADE_VENDA,
                QUANTIDADE_LIBERADA,
                VLR_TOTAL,
                EMailNFe,
                CONTATO,
                DDD,
                Telefone,
                DATA_HISTORICO,
                HORA_HISTORICO,
                USUARIO,
                HISTORICO
            FROM PEDIDOS_EM_ABERTO 
            {$where_clause}
            ORDER BY 
                CASE WHEN ATRASO > 0 THEN 0 ELSE 1 END,
                ABS(ATRASO) DESC,
                NUMERO_PEDIDO DESC
            LIMIT {$perPage} OFFSET {$offset}";
    
     $stmt = $pdo->prepare($sql);
     $stmt->execute($params);
     $pedidos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
     
     // Converter nomes das colunas para minúsculo
     $pedidos = [];
     foreach ($pedidos_raw as $pedido) {
         $pedido_lower = [];
         foreach ($pedido as $key => $value) {
             $pedido_lower[strtolower($key)] = $value;
         }
         $pedidos[] = $pedido_lower;
     }
     
     // Calcular estatísticas
     $stats = calculateStats($where_clause, $params);
    
    // Preparar resposta
    $response = [
        'success' => true,
        'pedidos' => $pedidos,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'per_page' => $perPage
        ],
        'stats' => $stats
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

function calculateStats($where_clause, $params) {
    global $pdo;
    
    $stats = [
        'total_pedidos' => 0,
        'pedidos_atrasados' => 0,
        'atraso_faturamento' => 0,
        'atraso_entrega' => 0,
        'valor_total' => 0,
        'total_vendedores' => 0
    ];
    
    try {
        // Total de pedidos
        $count_sql = "SELECT COUNT(*) as total FROM PEDIDOS_EM_ABERTO {$where_clause}";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $stats['total_pedidos'] = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Pedidos atrasados (apenas ATRASO > 0)
        $atrasados_where = $where_clause ? $where_clause . ' AND ATRASO > 0' : 'WHERE ATRASO > 0';
        $atrasados_sql = "SELECT COUNT(*) as total FROM PEDIDOS_EM_ABERTO {$atrasados_where}";
        $atrasados_stmt = $pdo->prepare($atrasados_sql);
        $atrasados_stmt->execute($params);
        
        // Calcular atraso de faturamento (baseado em DATA_PREVISAO_FATURAMENTO no formato DD/MM/YYYY)
        $hoje = date('d/m/Y');
        $sql_atraso_fat = "SELECT COUNT(*) as total FROM PEDIDOS_EM_ABERTO 
                           WHERE STR_TO_DATE(DATA_PREVISAO_FATURAMENTO, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')
                           AND DATA_PREVISAO_FATURAMENTO IS NOT NULL 
                           AND DATA_PREVISAO_FATURAMENTO != ''";
        if (!empty($where_clause)) {
            $sql_atraso_fat .= " AND " . str_replace("WHERE ", "", $where_clause);
        }
        $params_fat = array_merge([$hoje], $params);
        $stmt_atraso_fat = $pdo->prepare($sql_atraso_fat);
        $stmt_atraso_fat->execute($params_fat);
        $stats['atraso_faturamento'] = $stmt_atraso_fat->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Calcular atraso de entrega (baseado em DATA_ENTREGA no formato DD/MM/YYYY)
        $sql_atraso_ent = "SELECT COUNT(*) as total FROM PEDIDOS_EM_ABERTO 
                           WHERE STR_TO_DATE(DATA_ENTREGA, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')
                           AND DATA_ENTREGA IS NOT NULL 
                           AND DATA_ENTREGA != ''";
        if (!empty($where_clause)) {
            $sql_atraso_ent .= " AND " . str_replace("WHERE ", "", $where_clause);
        }
        $params_ent = array_merge([$hoje], $params);
        $stmt_atraso_ent = $pdo->prepare($sql_atraso_ent);
        $stmt_atraso_ent->execute($params_ent);
        $stats['atraso_entrega'] = $stmt_atraso_ent->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stats['pedidos_atrasados'] = $atrasados_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Valor total
        $valor_sql = "SELECT SUM(VLR_TOTAL) as total FROM PEDIDOS_EM_ABERTO {$where_clause}";
        $valor_stmt = $pdo->prepare($valor_sql);
        $valor_stmt->execute($params);
        $stats['valor_total'] = floatval($valor_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        // Total de vendedores únicos
        $vendedores_sql = "SELECT COUNT(DISTINCT COD_VENDEDOR) as total FROM PEDIDOS_EM_ABERTO {$where_clause}";
        $vendedores_stmt = $pdo->prepare($vendedores_sql);
        $vendedores_stmt->execute($params);
        $stats['total_vendedores'] = $vendedores_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
    } catch (Exception $e) {
        error_log("Erro ao calcular estatísticas: " . $e->getMessage());
    }
    
    return $stats;
}

function exportPedidos() {
    global $pdo, $usuario, $perfil_usuario;
    
    // Parâmetros de filtro (mesmos da função loadPedidos)
    $busca_cliente = $_POST['busca_cliente'] ?? '';
    $visao_supervisor = $_POST['visao_supervisor'] ?? '';
    $filtro_vendedor = $_POST['filtro_vendedor'] ?? '';
    $filtro_segmento = $_POST['filtro_segmento'] ?? '';
    $filtro_ano = $_POST['filtro_ano'] ?? '';
    $filtro_mes = $_POST['filtro_mes'] ?? '';
    $filtro_status = $_POST['filtro_status'] ?? '';
    $filtro_atraso = $_POST['filtro_atraso'] ?? '';
    
    // Construir condições WHERE (mesma lógica da função loadPedidos)
    $where_conditions = [];
    $params = [];
    
    // Filtro por perfil do usuário
    if ($perfil_usuario === 'vendedor' || $perfil_usuario === 'representante') {
        $where_conditions[] = "COD_VENDEDOR = ?";
        $params[] = $usuario['cod_vendedor'];
    } elseif ($perfil_usuario === 'supervisor') {
        // Para supervisores, mostrar vendedores da sua equipe
        if (!empty($filtro_vendedor)) {
            $where_conditions[] = "COD_VENDEDOR = ?";
            $params[] = $filtro_vendedor;
        } else {
            $where_conditions[] = "COD_VENDEDOR IN (
                SELECT COD_VENDEDOR FROM USUARIOS 
                WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
            )";
            $params[] = $usuario['cod_vendedor'];
        }
    }
    // Filtro de supervisão (apenas para supervisores, diretores e admins)
    if (!empty($visao_supervisor) && in_array($perfil_usuario, ['supervisor', 'diretor', 'admin'])) {
        $where_conditions[] = "COD_VENDEDOR IN (
            SELECT COD_VENDEDOR FROM USUARIOS 
            WHERE COD_SUPER = ? AND ATIVO = 1 AND PERFIL IN ('vendedor', 'representante')
        )";
        $params[] = str_pad($visao_supervisor, 3, '0', STR_PAD_LEFT);
        
        if (!empty($filtro_vendedor)) {
            $where_conditions[] = "COD_VENDEDOR = ?";
            $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
        }
    }
    
    // Filtro de vendedor (apenas para supervisores, diretores e admins)
    if (!empty($filtro_vendedor) && empty($visao_supervisor) && in_array($perfil_usuario, ['supervisor', 'diretor', 'admin'])) {
        $where_conditions[] = "COD_VENDEDOR = ?";
        $params[] = str_pad($filtro_vendedor, 3, '0', STR_PAD_LEFT);
    }
    
    if (!empty($filtro_segmento)) {
        $where_conditions[] = "SEGMENTO = ?";
        $params[] = $filtro_segmento;
    }
    
    // Filtro de ano/mês usando data do pedido
    if (!empty($filtro_ano)) {
        $where_conditions[] = "YEAR(STR_TO_DATE(DATA_PEDIDO, '%d/%m/%Y')) = ?";
        $params[] = intval($filtro_ano);
    }
    if (!empty($filtro_mes)) {
        $where_conditions[] = "MONTH(STR_TO_DATE(DATA_PEDIDO, '%d/%m/%Y')) = ?";
        $params[] = intval($filtro_mes);
    }
    
    // Busca discricionária por nome ou CNPJ
    if (!empty($busca_cliente)) {
        $busca_termo = trim($busca_cliente);
        if (strlen($busca_termo) >= 2) { // Mínimo 2 caracteres para busca
            $where_conditions[] = "(CLIENTE LIKE ? OR CNPJ LIKE ?)";
            $busca_param = '%' . $busca_termo . '%';
            $params[] = $busca_param;
            $params[] = $busca_param;
        }
    }
    
    // Filtro de status (baseado no campo HISTORICO)
    if (!empty($filtro_status)) {
        switch ($filtro_status) {
            case 'separacao':
                $where_conditions[] = "(HISTORICO LIKE '%SEPARACAO%' OR HISTORICO LIKE '%SEPARAÇÃO%')";
                break;
            case 'bloqueio':
                $where_conditions[] = "HISTORICO LIKE '%BLOQUEIO%'";
                break;
            case 'wms':
                $where_conditions[] = "HISTORICO LIKE '%WMS%'";
                break;
        }
    }
    
    // Filtro de atraso (baseado nos diferentes tipos de atraso)
    if (!empty($filtro_atraso)) {
        $hoje = date('d/m/Y');
        switch ($filtro_atraso) {
            case 'atraso_faturamento':
                $where_conditions[] = "STR_TO_DATE(DATA_PREVISAO_FATURAMENTO, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')";
                $where_conditions[] = "DATA_PREVISAO_FATURAMENTO IS NOT NULL AND DATA_PREVISAO_FATURAMENTO != ''";
                $params[] = $hoje;
                break;
            case 'atraso_entrega':
                $where_conditions[] = "STR_TO_DATE(DATA_ENTREGA, '%d/%m/%Y') < STR_TO_DATE(?, '%d/%m/%Y')";
                $where_conditions[] = "DATA_ENTREGA IS NOT NULL AND DATA_ENTREGA != ''";
                $params[] = $hoje;
                break;
            case 'atrasados':
                $where_conditions[] = "ATRASO > 0";
                break;
            case 'no_prazo':
                $where_conditions[] = "ATRASO <= 0";
                break;
        }
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Query para exportação
    $sql = "SELECT 
                NUMERO_PEDIDO as 'Número do Pedido',
                FILIAL as 'Filial',
                CLIENTE as 'Cliente',
                CNPJ as 'CNPJ',
                NOME_FANTASIA as 'Nome Fantasia',
                Endereco as 'Endereço',
                Estado as 'Estado',
                NOME_VENDEDOR as 'Vendedor',
                COD_VENDEDOR as 'Código Vendedor',
                DATA_PEDIDO as 'Data do Pedido',
                DATA_ENTREGA as 'Data de Entrega',
                DATA_PREVISAO_FATURAMENTO as 'Previsão Faturamento',
                ATRASO as 'Atraso (dias)',
                COD_PRODUTO as 'Código Produto',
                DESCRICAO_PRODUTO as 'Descrição Produto',
                QUANTIDADE_VENDA as 'Quantidade Venda',
                QUANTIDADE_LIBERADA as 'Quantidade Liberada',
                VLR_TOTAL as 'Valor Total',
                HISTORICO as 'Histórico',
                DATA_HISTORICO as 'Data Histórico',
                HORA_HISTORICO as 'Hora Histórico',
                USUARIO as 'Usuário',
                DIGITACAO as 'Digitação',
                CARGA as 'Carga',
                TEMPO_VIAGEM as 'Tempo Viagem',
                CONDICAO_PAGAMENTO as 'Condição Pagamento',
                CONTATO as 'Contato',
                DDD as 'DDD',
                Telefone as 'Telefone',
                EMailNFe as 'Email NFe'
            FROM PEDIDOS_EM_ABERTO 
            {$where_clause}
            ORDER BY 
                CASE WHEN ATRASO > 0 THEN 0 ELSE 1 END,
                ABS(ATRASO) DESC,
                NUMERO_PEDIDO DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Converter nomes das colunas para minúsculo
    $pedidos = [];
    foreach ($pedidos_raw as $pedido) {
        $pedido_lower = [];
        foreach ($pedido as $key => $value) {
            $pedido_lower[strtolower($key)] = $value;
        }
        $pedidos[] = $pedido_lower;
    }
    
    // Gerar CSV
    $filename = 'pedidos_abertos_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    if (!empty($pedidos)) {
        // Cabeçalhos
        fputcsv($output, array_keys($pedidos[0]), ';');
        
        // Dados
        foreach ($pedidos as $pedido) {
            fputcsv($output, $pedido, ';');
        }
    }
    
    fclose($output);
    exit;
}
?>
