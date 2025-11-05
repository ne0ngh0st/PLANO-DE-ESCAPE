<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$perfilUsuario = strtolower($usuario['perfil'] ?? '');

// Verificar permissões (admin, diretor e supervisor)
if (!in_array($perfilUsuario, ['admin', 'diretor', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado - apenas administradores, diretores e supervisores']);
    exit;
}

// Verificar se o CNPJ foi fornecido
if (!isset($_POST['cnpj']) || empty($_POST['cnpj'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CNPJ não fornecido']);
    exit;
}

$cnpj = trim($_POST['cnpj']);

try {
    require_once __DIR__ . '/conexao.php';
    
    // Verificar se a coluna 'no_lixao' existe na tabela
    $colCheck = $pdo->query("SHOW COLUMNS FROM clientes_excluidos LIKE 'no_lixao'");
    $hasLixaoColumn = $colCheck && $colCheck->fetch() ? true : false;
    
    if (!$hasLixaoColumn) {
        // Adicionar a coluna se não existir
        $pdo->exec("ALTER TABLE clientes_excluidos ADD COLUMN no_lixao TINYINT(1) DEFAULT 0 COMMENT 'Flag para indicar se está no lixão'");
    }
    
    // Verificar se o cliente existe
    $checkSql = "SELECT COUNT(*) as total FROM clientes_excluidos WHERE cnpj = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$cnpj]);
    $exists = $checkStmt->fetch();
    
    if ($exists['total'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit;
    }
    
    // Atualizar o registro para marcar como no lixão
    $sql = "UPDATE clientes_excluidos SET no_lixao = 1 WHERE cnpj = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$cnpj]);
    $rowCount = $stmt->rowCount();
    
    if ($result && $rowCount > 0) {
        // Log da ação
        $logMessage = "Cliente CNPJ: {$cnpj} movido para o lixão pelo usuário: {$usuario['nome_completo']} (ID: {$usuario['id']})";
        error_log($logMessage);
        
        echo json_encode(['success' => true, 'message' => 'Cliente movido para o lixão com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado ou já está no lixão']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao mover cliente para lixão: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
