<?php
/**
 * AJAX - Toggle Modo Manutenção
 * Permite admins ativarem/desativarem o modo manutenção
 */

require_once __DIR__ . '/../config/config.php';

// Verificar se usuário está logado e é admin
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$perfil_usuario = strtolower(trim($_SESSION['usuario']['perfil'] ?? ''));
if ($perfil_usuario !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Apenas administradores podem alterar o modo manutenção']);
    exit;
}

// Verificar método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter ação (ativar/desativar)
$acao = $_POST['acao'] ?? '';

if (!in_array($acao, ['ativar', 'desativar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

// Caminho do arquivo flag
$flag_file = __DIR__ . '/../config/manutencao.flag';
$existe = file_exists($flag_file);

try {
    if ($acao === 'ativar') {
        // Criar arquivo flag se não existir
        if (!$existe) {
            file_put_contents($flag_file, "# Modo manutenção ativo\n# Arquivo criado em: " . date('Y-m-d H:i:s') . "\n");
            echo json_encode([
                'success' => true, 
                'message' => 'Modo manutenção ATIVADO com sucesso',
                'status' => 'ativo'
            ]);
        } else {
            echo json_encode([
                'success' => true, 
                'message' => 'Modo manutenção já estava ATIVO',
                'status' => 'ativo'
            ]);
        }
    } else {
        // Desativar: remover arquivo flag
        if ($existe) {
            if (unlink($flag_file)) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Modo manutenção DESATIVADO com sucesso',
                    'status' => 'inativo'
                ]);
            } else {
                throw new Exception('Erro ao remover arquivo de manutenção');
            }
        } else {
            echo json_encode([
                'success' => true, 
                'message' => 'Modo manutenção já estava DESATIVADO',
                'status' => 'inativo'
            ]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao alterar modo manutenção: ' . $e->getMessage()
    ]);
}



