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

// Verificar permissões (apenas admin)
if ($perfilUsuario !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado - apenas administradores']);
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
    
    // Verificar se a coluna 'oculto' existe na tabela
    $colCheck = $pdo->query("SHOW COLUMNS FROM clientes_excluidos LIKE 'oculto'");
    $hasOcultoColumn = $colCheck && $colCheck->fetch() ? true : false;
    
    if (!$hasOcultoColumn) {
        // Adicionar a coluna se não existir
        $pdo->exec("ALTER TABLE clientes_excluidos ADD COLUMN oculto TINYINT(1) DEFAULT 0 COMMENT 'Flag para ocultar registro do lixão'");
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
    
    // Atualizar o registro para marcar como oculto
    $sql = "UPDATE clientes_excluidos SET oculto = 1 WHERE cnpj = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$cnpj]);
    $rowCount = $stmt->rowCount();
    
    if ($result && $rowCount > 0) {
        // Log da ação
        $logMessage = "Cliente CNPJ: {$cnpj} ocultado do lixão pelo admin: {$usuario['nome_completo']} (ID: {$usuario['id']})";
        error_log($logMessage);
        
        echo json_encode(['success' => true, 'message' => 'Registro ocultado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao ocultar registro']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao ocultar registro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
