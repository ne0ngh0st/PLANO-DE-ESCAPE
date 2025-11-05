<?php
session_start();
require_once __DIR__ . '/conexao.php';

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$perfil = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));
if (!in_array($perfil, ['admin', 'diretor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem alterar a visibilidade das sugestões.']);
    exit;
}

// Verificar se os dados necessários foram enviados
if (!isset($_POST['id']) || !isset($_POST['acao'])) {
    echo json_encode(['success' => false, 'message' => 'Dados insuficientes']);
    exit;
}

$id = (int)$_POST['id'];
$acao = $_POST['acao'];

try {
    // Verificar se a sugestão existe
    $sql_check = "SELECT id, visivel FROM sugestoes WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $sugestao = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$sugestao) {
        echo json_encode(['success' => false, 'message' => 'Sugestão não encontrada']);
        exit;
    }
    
    // Determinar nova visibilidade
    $nova_visibilidade = 0; // Ocultar
    $mensagem = 'Sugestão ocultada com sucesso';
    
    if ($acao === 'mostrar') {
        $nova_visibilidade = 1; // Mostrar
        $mensagem = 'Sugestão tornada visível com sucesso';
    }
    
    // Atualizar visibilidade
    $sql_update = "UPDATE sugestoes SET visivel = ? WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $resultado = $stmt_update->execute([$nova_visibilidade, $id]);
    
    if ($resultado) {
        // Log da ação
        $usuario_id = $_SESSION['usuario']['id'];
        $usuario_nome = $_SESSION['usuario']['nome'];
        $acao_log = $acao === 'mostrar' ? 'tornou visível' : 'ocultou';
        
        error_log("ADMIN $usuario_nome (ID: $usuario_id) $acao_log a sugestão ID: $id");
        
        echo json_encode([
            'success' => true, 
            'message' => $mensagem,
            'visivel' => $nova_visibilidade
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar visibilidade']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao alterar visibilidade da sugestão: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>





