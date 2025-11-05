<?php
require_once dirname(__DIR__) . '/config.php';
session_start();

// Definir header JSON
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se foi passado o ID da licitação
$licitacao_id = $_GET['id'] ?? '';

if (empty($licitacao_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da licitação não informado']);
    exit;
}

try {
    // Log da busca
    error_log("Buscando licitação ID: $licitacao_id");
    
    // Buscar dados da licitação
    $sql = "SELECT 
                ID,
                COD_CLIENT,
                ORGAO,
                SIGLA,
                GERENCIADOR,
                NUMERO_ATA,
                NUMERO_CONTRATO,
                VALOR_GLOBAL,
                COALESCE(VALOR_CONSUMIDO, 0) as VALOR_CONSUMIDO,
                DATA_INICIO_CONTRATO,
                DATA_TERMINO_CONTRATO,
                STATUS,
                CNPJ,
                TIPO,
                PRODUTO,
                GRUPO,
                TABELA,
                DATA_INICIO_ATA,
                DATA_TERMINO_ATA,
                VALOR_ATA,
                SALDO_CONTRATO,
                CONSUMO_CONTRATO_PERCENT
            FROM LICITACAO
            WHERE ID = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$licitacao_id]);
    $licitacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licitacao) {
        error_log("Licitação não encontrada - ID: $licitacao_id");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        exit;
    }
    
    error_log("Licitação encontrada - ID: $licitacao_id, Órgão: " . ($licitacao['ORGAO'] ?? 'N/A'));
    
    echo json_encode([
        'success' => true,
        'licitacao' => $licitacao
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar licitação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>