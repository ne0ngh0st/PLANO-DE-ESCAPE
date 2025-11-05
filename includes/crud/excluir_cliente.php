<?php
session_start();

// Ativar exibição de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Aceitar múltiplos formatos de campos vindos do frontend
$cnpj_cliente = $_POST['cnpj'] ?? ($_POST['cliente_id'] ?? ($_POST['id'] ?? ''));
$motivo_exclusao = $_POST['motivo_exclusao'] ?? ($_POST['motivo'] ?? '');
$observacao_exclusao = $_POST['observacao_exclusao'] ?? '';

// Verificar obrigatórios
if (empty($cnpj_cliente)) {
    echo json_encode(['success' => false, 'message' => 'CNPJ do cliente não fornecido']);
    exit;
}
if (empty($motivo_exclusao)) {
    echo json_encode(['success' => false, 'message' => 'Motivo da exclusão é obrigatório']);
    exit;
}
// Normalizar CNPJ recebido e calcular raiz (8 primeiros dígitos)
$cnpj_limpo = preg_replace('/\D/', '', $cnpj_cliente);
$raiz_cnpj = substr($cnpj_limpo, 0, 8);

// Incluir arquivo de conexão
require_once 'conexao.php';

try {
    // Verificar se o cliente existe (aceitar CNPJ com/sem máscara e também por RAIZ)
    $stmt = $pdo->prepare("SELECT CLIENTE as cliente, CNPJ as cnpj
                           FROM ultimo_faturamento
                           WHERE REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', '') = ?
                              OR SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?
                           LIMIT 1");
    $stmt->execute([$cnpj_limpo, $raiz_cnpj]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit;
    }
    
    // Iniciar transação para garantir consistência
    $pdo->beginTransaction();
    
    try {
        // 1. Copiar todos os registros do cliente para a tabela de excluídos
        $insert_sql = "
            INSERT INTO clientes_excluidos (
                cnpj, cliente, nome_fantasia, estado, endereco, 
                cod_vendedor, nome_vendedor, total_pedidos, valor_total, 
                ultima_compra, primeira_compra, data_exclusao, usuario_exclusao, motivo_exclusao, observacao_exclusao
            )
            SELECT 
                CNPJ as cnpj,
                CLIENTE as cliente,
                NOME_FANTASIA as nome_fantasia,
                ESTADO as estado,
                ENDERECO as endereco,
                COD_VENDEDOR as cod_vendedor,
                NOME_VENDEDOR as nome_vendedor,
                COUNT(*) as total_pedidos,
                SUM(VLR_TOTAL) as valor_total,
                MAX(DT_FAT) as ultima_compra,
                MIN(DT_FAT) as primeira_compra,
                NOW() as data_exclusao,
                ? as usuario_exclusao,
                ? as motivo_exclusao,
                ? as observacao_exclusao
            FROM ultimo_faturamento 
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', '') = ?
               OR SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?
            GROUP BY CNPJ, COD_VENDEDOR
        ";
        
        $stmt = $pdo->prepare($insert_sql);
        $insert_result = $stmt->execute([$_SESSION['usuario']['id'] ?? 1, $motivo_exclusao, $observacao_exclusao, $cnpj_limpo, $raiz_cnpj]);
        
        if (!$insert_result) {
            throw new Exception('Erro ao inserir na tabela de excluídos: ' . implode(', ', $stmt->errorInfo()));
        }
        
        // 2. Excluir todos os registros do cliente da tabela principal
        try {
            $delete_sql = "DELETE FROM ultimo_faturamento 
                           WHERE REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', '') = ?
                              OR SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?";
            $stmt = $pdo->prepare($delete_sql);
            $delete_result = $stmt->execute([$cnpj_limpo, $raiz_cnpj]);

            if ($delete_result) {
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Cliente movido para exclusão com sucesso']);
            } else {
                // Se falhar sem exceção, tratar como erro genérico
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir cliente da tabela principal']);
            }
        } catch (PDOException $e) {
            // Fallback: sem permissão para DELETE -> manter inserção (exclusão lógica)
            $errorCode = $e->getCode();
            $errorMsg = $e->getMessage();
            $errorInfo = $stmt->errorInfo();

            $permissionDenied = strpos($errorMsg, 'DELETE command denied') !== false || $errorCode === '42000' || (isset($errorInfo[1]) && (int)$errorInfo[1] === 1142);
            if ($permissionDenied) {
                // Concluir a transação mantendo o registro em clientes_excluidos
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Exclusão registrada sem remover da origem (sem permissão de DELETE). O cliente não aparecerá mais nas listagens.'
                ]);
            } else {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir cliente: ' . $errorMsg]);
            }
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro na transação: ' . $e->getMessage()]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro geral: ' . $e->getMessage()]);
}
?>