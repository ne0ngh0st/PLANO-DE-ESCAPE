<?php
// PREVENIR QUALQUER OUTPUT ANTES DO JSON
ob_start();

// Configurar headers para JSON ANTES DE QUALQUER COISA
header('Content-Type: application/json; charset=utf-8');

// Desabilitar exibição de erros para não quebrar o JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // IMPORTANTE: Usar config.php que já tem toda configuração de sessão correta
    // Isso garante que a sessão seja configurada da mesma forma que o resto do sistema
    require_once __DIR__ . '/../config/config.php';
    
    // LIMPAR BUFFER ANTES DE VERIFICAR SESSÃO
    ob_clean();
    
    // Verificar se o usuário está logado
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
        // SEMPRE retornar JSON, nunca HTML
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Sessão expirada. Por favor, faça login novamente.', 
            'session_expired' => true
        ]);
        exit;
    }
    
    // Verificar se a conexão foi estabelecida (config.php já inclui conexao.php)
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Erro ao conectar ao banco de dados');
    }
} catch (Exception $e) {
    // Limpar qualquer output antes de retornar erro
    ob_clean();
    error_log("Erro na configuração: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['success' => false, 'message' => 'Erro de configuração: ' . $e->getMessage()]);
    exit;
}

// Limpar buffer final antes de processar
ob_end_clean();

$codigo_produto = $_GET['codigo'] ?? '';

if (empty($codigo_produto)) {
    echo json_encode(['success' => false, 'message' => 'Código do produto não fornecido']);
    exit;
}

try {
    // Colunas corretas conforme informado: COD_PROD e DES_PROD
    // Buscar produto usando as colunas corretas
    $sql = "SELECT DISTINCT 
                COD_PROD as codigo_produto,
                DES_PROD as nome_produto
            FROM FATURAMENTO 
            WHERE COD_PROD = :codigo_produto
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['codigo_produto' => $codigo_produto]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($produto) {
        echo json_encode([
            'success' => true,
            'data' => [
                'codigo' => $produto['codigo_produto'],
                'nome' => $produto['nome_produto'],
                'descricao' => $produto['nome_produto'] // DES_PROD contém o nome do produto
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar produto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar produto: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erro ao buscar produto: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

