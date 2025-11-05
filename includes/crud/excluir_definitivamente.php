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

// Verificar permissões (apenas admin e diretor)
if (!in_array($perfilUsuario, ['admin', 'diretor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado - apenas administradores e diretores']);
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
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Buscar dados do cliente antes de excluir (para log)
    $sqlSelect = "SELECT cliente, nome_fantasia, valor_total, data_exclusao FROM clientes_excluidos WHERE cnpj = ?";
    $stmtSelect = $pdo->prepare($sqlSelect);
    $stmtSelect->execute([$cnpj]);
    $clienteData = $stmtSelect->fetch(PDO::FETCH_ASSOC);
    
    if (!$clienteData) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit;
    }
    
    // Excluir o cliente da tabela clientes_excluidos
    $sqlDelete = "DELETE FROM clientes_excluidos WHERE cnpj = ?";
    $stmtDelete = $pdo->prepare($sqlDelete);
    $result = $stmtDelete->execute([$cnpj]);
    
    if ($result && $stmtDelete->rowCount() > 0) {
        // Log da ação
        $logMessage = "Cliente EXCLUÍDO DEFINITIVAMENTE - CNPJ: {$cnpj}, Nome: {$clienteData['cliente']}, Valor: {$clienteData['valor_total']}, Data Exclusão: {$clienteData['data_exclusao']}, Usuário: {$usuario['nome_completo']} (ID: {$usuario['id']})";
        error_log($logMessage);
        
        // Confirmar transação
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Cliente excluído definitivamente com sucesso']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir cliente']);
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Erro ao excluir cliente definitivamente: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
