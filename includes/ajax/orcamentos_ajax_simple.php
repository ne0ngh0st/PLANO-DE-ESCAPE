<?php
// Versão simplificada para debug
header('Content-Type: application/json; charset=utf-8');

// Capturar qualquer output antes do JSON
ob_start();

try {
    // Iniciar sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar se o usuário está logado
    if (!isset($_SESSION['usuario'])) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
        exit;
    }
    
    $usuario = $_SESSION['usuario'];
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Limpar buffer antes de processar
    ob_clean();
    
    switch ($action) {
        case 'test':
            echo json_encode([
                'success' => true,
                'message' => 'Teste funcionando',
                'usuario' => $usuario['nome'] ?? 'N/A',
                'action' => $action
            ]);
            break;
            
        case 'gerar_link_aprovacao':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            // Simular geração de link
            $token = 'test_token_' . time();
            $link = 'https://aprovacao.autopel.com.br/aprovar.php?token=' . $token;
            
            echo json_encode([
                'success' => true,
                'message' => 'Link gerado com sucesso',
                'link' => $link,
                'orcamento' => ['id' => $id, 'cliente_nome' => 'Cliente Teste']
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida: ' . $action]);
    }
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Erro fatal: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>




