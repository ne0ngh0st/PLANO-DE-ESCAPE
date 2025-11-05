<?php
session_start();
require_once __DIR__ . '/../config/conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Agora TODOS os usuários autenticados podem ver todas as sugestões VISÍVEIS
// Buscar todas as sugestões visíveis para todos os usuários
try {
    $sql = "SELECT id, categoria, sugestao, data_criacao, status, visivel, resposta_admin, admin_respondeu_id
            FROM sugestoes 
            WHERE visivel = 1
            ORDER BY data_criacao DESC 
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $sugestoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar as sugestões para exibição
    $sugestoes_formatadas = [];
    foreach ($sugestoes as $sugestao) {
        $status_texto = '';
        switch ($sugestao['status']) {
            case 'implementada':
                $status_texto = 'Implementada';
                break;
            case 'aprovada':
                $status_texto = 'Aprovada';
                break;
            case 'pendente':
                $status_texto = 'Pendente';
                break;
            case 'em_analise':
                $status_texto = 'Em análise';
                break;
            case 'rejeitada':
                $status_texto = 'Rejeitada';
                break;
            default:
                $status_texto = ucfirst($sugestao['status']);
        }
        
        $sugestoes_formatadas[] = [
            'id' => (int)$sugestao['id'],
            'categoria' => ucfirst($sugestao['categoria']),
            'sugestao' => $sugestao['sugestao'],
            'data' => date('d/m/Y', strtotime($sugestao['data_criacao'])),
            'status' => $status_texto,
            'status_raw' => $sugestao['status'],
            'visivel' => (bool)$sugestao['visivel'],
            'resposta_admin' => $sugestao['resposta_admin'] ?: null,
            'admin_respondeu_id' => $sugestao['admin_respondeu_id'] ?: null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'sugestoes' => $sugestoes_formatadas
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar sugestões: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar sugestões'
    ]);
}
?>
