<?php
// Teste AJAX sem dependências de banco
header('Content-Type: application/json; charset=utf-8');

// Capturar qualquer output
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
    
    // Limpar buffer
    ob_clean();
    
    switch ($action) {
        case 'test':
            echo json_encode([
                'success' => true,
                'message' => 'Teste funcionando sem banco',
                'usuario' => $usuario['nome'] ?? 'N/A',
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'gerar_link_aprovacao':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            // Simular geração de link sem banco
            $token = 'test_token_' . time() . '_' . rand(1000, 9999);
            $link = 'https://aprovacao.autopel.com.br/aprovar.php?token=' . $token;
            
            echo json_encode([
                'success' => true,
                'message' => 'Link gerado com sucesso (simulado)',
                'link' => $link,
                'orcamento' => [
                    'id' => $id,
                    'cliente_nome' => 'Cliente Teste',
                    'valor_total' => '1000.00'
                ],
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false, 
                'message' => 'Ação não reconhecida: ' . $action,
                'available_actions' => ['test', 'gerar_link_aprovacao']
            ]);
    }
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
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




