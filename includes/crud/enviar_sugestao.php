<?php
session_start();
require_once __DIR__ . '/conexao.php';

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Qualquer usuário autenticado pode enviar sugestões

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se a tabela existe, se não, criar
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'sugestoes'");
    if ($stmt->rowCount() === 0) {
        $sql_criar_tabela = "
        CREATE TABLE sugestoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            usuario_nome VARCHAR(255) NOT NULL,
            categoria VARCHAR(50) NOT NULL,
            sugestao TEXT NOT NULL,
            status ENUM('pendente', 'em_analise', 'aprovada', 'rejeitada', 'implementada') DEFAULT 'pendente',
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resposta_admin TEXT,
            admin_respondeu_id INT,
            INDEX idx_usuario_id (usuario_id),
            INDEX idx_status (status),
            INDEX idx_data_criacao (data_criacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql_criar_tabela);
        error_log("Tabela sugestoes criada com sucesso");
    }
} catch (Exception $e) {
    error_log("Erro ao criar tabela sugestoes: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao configurar banco de dados. Tente novamente.']);
    exit;
}

// Validar dados recebidos
$categoria = trim($_POST['categoria'] ?? '');
$sugestao = trim($_POST['sugestao'] ?? '');

// Validações
if (empty($categoria)) {
    echo json_encode(['success' => false, 'message' => 'Por favor, selecione uma categoria']);
    exit;
}



if (empty($sugestao) || strlen($sugestao) < 10) {
    echo json_encode(['success' => false, 'message' => 'A sugestão deve ter pelo menos 10 caracteres']);
    exit;
}

// Validar categoria
$categorias_validas = ['interface', 'funcionalidade', 'relatorio', 'performance', 'outro'];
if (!in_array($categoria, $categorias_validas)) {
    echo json_encode(['success' => false, 'message' => 'Categoria inválida']);
    exit;
}



try {
    // Inserir sugestão no banco
    $sql = "INSERT INTO sugestoes (usuario_id, usuario_nome, categoria, sugestao) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([
        $_SESSION['usuario']['id'],
        $_SESSION['usuario']['nome'],
        $categoria,
        $sugestao
    ]);
    
    if ($resultado) {
        // Log da sugestão
        error_log("Nova sugestão enviada por " . $_SESSION['usuario']['nome'] . " (ID: " . $_SESSION['usuario']['id'] . ") - Categoria: $categoria");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Sugestão enviada com sucesso! Obrigado por contribuir para melhorar nossa plataforma.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar sugestão. Tente novamente.']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao salvar sugestão: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor. Tente novamente mais tarde.']);
}
?>
