<?php
// Endpoint AJAX para buscar transições de status dos clientes
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once 'config.php';
session_start();

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verificar permissões
$usuario = $_SESSION['usuario'];
$perfil_permitido = in_array(strtolower(trim($usuario['perfil'])), ['admin', 'diretor', 'supervisor']);
if (!$perfil_permitido) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão']);
    exit;
}

try {
    $tipo_transicao = $_GET['tipo'] ?? 'todas';
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    $limite = intval($_GET['limite'] ?? 50);
    
    // Construir query base
    $sql = "
        SELECT 
            t.id,
            t.cnpj_cliente,
            t.status_anterior,
            t.status_novo,
            t.data_transicao,
            t.data_ultima_compra,
            t.dias_sem_comprar,
            t.valor_ultima_compra,
            t.data_criacao,
            uf.CLIENTE as RAZAO_SOCIAL,
            uf.CNPJ as cnpj_original
        FROM transicoes_status_clientes t
        LEFT JOIN ultimo_faturamento uf ON SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = t.cnpj_cliente
        WHERE t.data_transicao BETWEEN ? AND ?
    ";
    
    $params = [$data_inicio, $data_fim];
    
    // Filtrar por tipo de transição
    switch ($tipo_transicao) {
        case 'ativo_inativando':
            $sql .= " AND t.status_anterior = 'ativo' AND t.status_novo = 'inativando'";
            break;
        case 'inativando_ativo':
            $sql .= " AND t.status_anterior = 'inativando' AND t.status_novo = 'ativo'";
            break;
        case 'inativando_inativo':
            $sql .= " AND t.status_anterior = 'inativando' AND t.status_novo = 'inativo'";
            break;
        case 'inativo_ativo':
            $sql .= " AND t.status_anterior = 'inativo' AND t.status_novo = 'ativo'";
            break;
        case 'novo_ativo':
            $sql .= " AND t.status_anterior = 'novo' AND t.status_novo = 'ativo'";
            break;
    }
    
    $sql .= " ORDER BY t.data_transicao DESC, t.data_criacao DESC LIMIT " . intval($limite);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar estatísticas resumidas
    $sql_stats = "
        SELECT 
            CONCAT(t.status_anterior, ' -> ', t.status_novo) as transicao,
            COUNT(*) as quantidade
        FROM transicoes_status_clientes t
        WHERE t.data_transicao BETWEEN ? AND ?
        GROUP BY t.status_anterior, t.status_novo
        ORDER BY quantidade DESC
    ";
    
    $stmt = $pdo->prepare($sql_stats);
    $stmt->execute([$data_inicio, $data_fim]);
    $estatisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados para resposta
    $response = [
        'success' => true,
        'data' => [
            'transicoes' => $transicoes,
            'estatisticas' => $estatisticas,
            'filtros' => [
                'tipo' => $tipo_transicao,
                'data_inicio' => $data_inicio,
                'data_fim' => $data_fim,
                'limite' => $limite
            ],
            'total_encontrado' => count($transicoes)
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
    error_log("Erro ao buscar transições de status: " . $e->getMessage());
}
?>
