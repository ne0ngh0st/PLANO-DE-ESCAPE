<?php
session_start();

// Verificação de login
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once __DIR__ . '/conexao.php';

try {
    $supervisor = $_GET['supervisor'] ?? '';
    
    // Buscar vendedores baseado no supervisor
    $sql = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO 
            FROM USUARIOS u 
            WHERE u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')";
    
    $params = [];
    if (!empty($supervisor)) {
        $sql .= " AND u.COD_SUPER = ?";
        $params[] = $supervisor;
    }
    
    $sql .= " ORDER BY u.NOME_COMPLETO";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'vendedores' => $vendedores
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar vendedores: ' . $e->getMessage()
    ]);
}
?>

