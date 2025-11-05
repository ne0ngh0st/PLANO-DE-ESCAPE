<?php
require_once '../config.php';
session_start();

// Definir header JSON
header('Content-Type: application/json');

error_log("=== DEBUG EDITAR ===");

// Simular dados de teste
$_POST = [
    'id' => '1',
    'gerenciador' => 'TESTE',
    'status' => 'Vigente',
    'sigla' => 'TEST',
    'razao_social' => 'Empresa Teste',
    'numero_pregao' => '123/2024',
    'termo_contrato' => 'TC-001/2024',
    'valor_global' => '10000.00',
    'valor_consumido' => '5000.00',
    'data_inicio_vigencia' => '2024-01-01',
    'data_termino_vigencia' => '2024-12-31'
];

error_log("Dados simulados: " . print_r($_POST, true));

try {
    // Testar se a licitação existe
    $sql = "SELECT ID, ORGAO FROM LICITACAO WHERE ID = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $licitacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$licitacao) {
        error_log("Licitação ID 1 não encontrada");
        echo json_encode(['success' => false, 'message' => 'Licitação não encontrada']);
        exit;
    }
    
    error_log("Licitação encontrada: " . $licitacao['ORGAO']);
    
    // Testar UPDATE
    $sql = "UPDATE LICITACAO SET 
                GERENCIADOR = ?,
                STATUS = ?,
                ORGAO = ?,
                VALOR_GLOBAL = ?,
                VALOR_CONSUMIDO = ?
            WHERE ID = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'TESTE',
        'Vigente',
        'Empresa Teste',
        10000.00,
        5000.00,
        1
    ]);
    
    if ($result) {
        $rowsAffected = $stmt->rowCount();
        error_log("UPDATE executado com sucesso - Linhas afetadas: $rowsAffected");
        echo json_encode([
            'success' => true,
            'message' => 'Teste executado com sucesso',
            'rows_affected' => $rowsAffected
        ]);
    } else {
        error_log("Erro no UPDATE");
        echo json_encode(['success' => false, 'message' => 'Erro no UPDATE']);
    }
    
} catch (PDOException $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>

