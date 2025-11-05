<?php
// Definir header JSON antes de qualquer output
header('Content-Type: application/json; charset=utf-8');

// Suprimir warnings que podem corromper o JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Endpoint para buscar dados de uma licitação específica
require_once __DIR__ . '/../config/config.php';

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar se foi passado o ID
$id = $_GET['id'] ?? '';
if (empty($id) || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
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
    $stmt->execute([$id]);
    $licitacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licitacao) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        exit;
    }
    
    // Garantir que o STATUS tenha um valor padrão se estiver vazio
    if (empty($licitacao['STATUS'])) {
        $licitacao['STATUS'] = 'Vigente';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $licitacao
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar licitação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>

