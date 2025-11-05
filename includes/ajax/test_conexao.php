<?php
require_once '../config.php';
session_start();

// Definir header JSON
header('Content-Type: application/json');

error_log("=== TESTE CONEXÃO ===");

try {
    // Testar conexão
    $sql = "SELECT COUNT(*) as total FROM LICITACAO";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Conexão OK - Total de licitações: " . $result['total']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conexão OK',
        'total_licitacoes' => $result['total']
    ]);
    
} catch (PDOException $e) {
    error_log("Erro de conexão: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro de conexão: ' . $e->getMessage()
    ]);
}
?>

