<?php
/**
 * Endpoint AJAX para marcar/desmarcar clientes como ligados
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$usuario_id = $usuario['id'] ?? null;

if (!$usuario_id) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não encontrado']);
    exit;
}

// Verificar método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$acao = $input['acao'] ?? ''; // 'marcar' ou 'desmarcar'
$raiz_cnpj = $input['raiz_cnpj'] ?? '';
$cnpj_completo = $input['cnpj_completo'] ?? '';
$cliente_nome = $input['cliente_nome'] ?? '';

// Validar dados
if (empty($acao) || empty($raiz_cnpj)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

// Limpar raiz do CNPJ (apenas números, primeiros 8 dígitos)
$raiz_cnpj_limpo = preg_replace('/[^0-9]/', '', $raiz_cnpj);
$raiz_cnpj_limpo = substr($raiz_cnpj_limpo, 0, 8);

if (empty($raiz_cnpj_limpo) || strlen($raiz_cnpj_limpo) < 8) {
    echo json_encode(['success' => false, 'message' => 'CNPJ inválido']);
    exit;
}

try {
    if ($acao === 'marcar') {
        // Marcar cliente como ligado
        $sql = "INSERT INTO clientes_ligados (usuario_id, raiz_cnpj, cnpj_completo, cliente_nome, data_marcacao)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    cnpj_completo = VALUES(cnpj_completo),
                    cliente_nome = VALUES(cliente_nome),
                    data_atualizacao = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $raiz_cnpj_limpo, $cnpj_completo, $cliente_nome]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cliente marcado como ligado',
            'acao' => 'marcado'
        ]);
        
    } elseif ($acao === 'desmarcar') {
        // Desmarcar cliente
        $sql = "DELETE FROM clientes_ligados WHERE usuario_id = ? AND raiz_cnpj = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $raiz_cnpj_limpo]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cliente desmarcado',
            'acao' => 'desmarcado'
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao marcar/desmarcar cliente: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação']);
}




