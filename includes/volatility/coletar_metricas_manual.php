<?php
require_once 'config.php';
session_start();
require_once 'conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];

// Verificar se o usuário tem permissão (admin ou diretor)
if (!in_array(strtolower(trim($usuario['perfil'])), ['admin', 'diretor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissão negada']);
    exit;
}

try {
    // Incluir o script de coleta de métricas
    require_once 'coletar_metricas_volatilidade.php';
    
    // Se chegou até aqui, a coleta foi bem-sucedida
    echo json_encode([
        'success' => true, 
        'message' => 'Métricas coletadas com sucesso!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao coletar métricas: ' . $e->getMessage()
    ]);
}
?>
