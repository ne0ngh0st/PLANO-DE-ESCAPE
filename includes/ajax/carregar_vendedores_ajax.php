<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/config.php';

function json_error($message) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    if (!isset($_SESSION['usuario'])) {
        json_error('Sessão expirada. Faça login novamente.');
    }
    
    $usuario = $_SESSION['usuario'];
    
    // Verificar se é admin ou diretor
    $is_admin_diretor = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin']);
    if (!$is_admin_diretor) {
        json_error('Acesso negado');
    }
    
    $supervisor_selecionado = $_GET['supervisor'] ?? '';
    
    if (empty($supervisor_selecionado)) {
        // Retornar todos os vendedores da tabela USUARIOS
        $sql_vendedores = "SELECT DISTINCT COD_VENDEDOR, NOME_COMPLETO as NOME_VENDEDOR 
                          FROM USUARIOS 
                          WHERE ATIVO = 1 AND PERFIL IN ('vendedor', 'representante') 
                          ORDER BY NOME_COMPLETO";
        $stmt_vendedores = $pdo->query($sql_vendedores);
    } else {
        // Retornar apenas vendedores do supervisor selecionado da tabela USUARIOS
        $sql_vendedores = "SELECT DISTINCT u.COD_VENDEDOR, u.NOME_COMPLETO as NOME_VENDEDOR 
                          FROM USUARIOS u 
                          WHERE u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante')
                          ORDER BY u.NOME_COMPLETO";
        $stmt_vendedores = $pdo->prepare($sql_vendedores);
        $stmt_vendedores->execute([str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT)]);
    }
    
    $vendedores = $stmt_vendedores->fetchAll(PDO::FETCH_ASSOC);
    
    // Gerar HTML das opções
    $html = '<option value="">Todos</option>';
    foreach ($vendedores as $vendedor) {
        $html .= '<option value="' . htmlspecialchars($vendedor['COD_VENDEDOR']) . '">' . 
                 htmlspecialchars($vendedor['NOME_VENDEDOR']) . '</option>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($vendedores),
        'message' => 'Carregados ' . count($vendedores) . ' vendedores'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    json_error('Erro ao carregar vendedores: ' . $e->getMessage());
} catch (Throwable $e) {
    json_error('Erro interno: ' . $e->getMessage());
}
?>
