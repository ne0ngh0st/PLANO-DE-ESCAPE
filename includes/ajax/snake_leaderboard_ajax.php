<?php
/**
 * Endpoint AJAX para o leaderboard do Snake Game
 * Ações: salvar (save), buscar (get)
 */

// Limpar qualquer output anterior
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Iniciar sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar se o usuário está logado
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
        exit;
    }
    
    require_once __DIR__ . '/../config/conexao.php';
    
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro de conexão com o banco de dados']);
        exit;
    }
    
    $usuario_id = $_SESSION['usuario']['id'];
    $usuario_nome = $_SESSION['usuario']['nome'] ?? 'Usuário';
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'save':
            // Salvar pontuação
            $pontuacao = intval($_POST['pontuacao'] ?? 0);
            
            if ($pontuacao <= 0) {
                echo json_encode(['success' => false, 'message' => 'Pontuação inválida']);
                exit;
            }
            
            try {
                // Verificar se a tabela existe, se não, criar
                $check_table = "SHOW TABLES LIKE 'snake_leaderboard'";
                $stmt_check = $pdo->query($check_table);
                
                if ($stmt_check->rowCount() == 0) {
                    // Executar script de criação da tabela
                    require_once __DIR__ . '/../database/criar_tabela_snake_leaderboard.php';
                }
                
                // Inserir pontuação
                $stmt = $pdo->prepare('INSERT INTO snake_leaderboard (usuario_id, usuario_nome, pontuacao) VALUES (?, ?, ?)');
                $stmt->execute([$usuario_id, $usuario_nome, $pontuacao]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pontuação salva com sucesso!',
                    'pontuacao' => $pontuacao
                ]);
            } catch (PDOException $e) {
                // Se a tabela não existe, tentar criar
                if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Table") !== false) {
                    require_once __DIR__ . '/../database/criar_tabela_snake_leaderboard.php';
                    // Tentar inserir novamente
                    $stmt = $pdo->prepare('INSERT INTO snake_leaderboard (usuario_id, usuario_nome, pontuacao) VALUES (?, ?, ?)');
                    $stmt->execute([$usuario_id, $usuario_nome, $pontuacao]);
                    echo json_encode(['success' => true, 'message' => 'Pontuação salva com sucesso!', 'pontuacao' => $pontuacao]);
                } else {
                    throw $e;
                }
            }
            break;
            
        case 'get':
            // Buscar top pontuações
            $limit = intval($_GET['limit'] ?? 10);
            $limit = min(max($limit, 1), 50); // Entre 1 e 50
            
            try {
                // Verificar se a tabela existe
                $check_table = "SHOW TABLES LIKE 'snake_leaderboard'";
                $stmt_check = $pdo->query($check_table);
                
                if ($stmt_check->rowCount() == 0) {
                    // Tentar criar a tabela
                    try {
                        require_once __DIR__ . '/../database/criar_tabela_snake_leaderboard.php';
                    } catch (Exception $e) {
                        // Ignorar erro se não conseguir criar
                    }
                    echo json_encode(['success' => true, 'leaderboard' => []]);
                    exit;
                }
                
                // Buscar top pontuações - apenas uma entrada por usuário (melhor pontuação)
                // LIMIT não aceita placeholder, então vamos usar diretamente (já validado)
                $limit = intval($limit);
                $stmt = $pdo->prepare('
                    SELECT usuario_id, usuario_nome, MAX(pontuacao) as pontuacao, MIN(data_pontuacao) as data_pontuacao
                    FROM snake_leaderboard
                    GROUP BY usuario_id, usuario_nome
                    ORDER BY pontuacao DESC, data_pontuacao ASC
                    LIMIT ' . $limit
                );
                $stmt->execute();
                $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Buscar melhor pontuação do usuário atual
                $stmt_user = $pdo->prepare('
                    SELECT MAX(pontuacao) as melhor_pontuacao
                    FROM snake_leaderboard
                    WHERE usuario_id = ?
                ');
                $stmt_user->execute([$usuario_id]);
                $minha_pontuacao = $stmt_user->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'leaderboard' => $leaderboard ?: [],
                    'minha_pontuacao' => intval($minha_pontuacao['melhor_pontuacao'] ?? 0)
                ]);
            } catch (PDOException $e) {
                // Se a tabela não existe, tentar criar
                if (strpos($e->getMessage(), "doesn't exist") !== false || 
                    strpos($e->getMessage(), "Table") !== false ||
                    strpos($e->getMessage(), "Unknown table") !== false) {
                    try {
                        require_once __DIR__ . '/../database/criar_tabela_snake_leaderboard.php';
                        // Tentar buscar novamente
                        // LIMIT não aceita placeholder, então vamos usar diretamente (já validado)
                        $limit = intval($limit);
                        $stmt = $pdo->prepare('
                            SELECT usuario_id, usuario_nome, MAX(pontuacao) as pontuacao, MIN(data_pontuacao) as data_pontuacao
                            FROM snake_leaderboard
                            GROUP BY usuario_id, usuario_nome
                            ORDER BY pontuacao DESC, data_pontuacao ASC
                            LIMIT ' . $limit
                        );
                        $stmt->execute();
                        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $stmt_user = $pdo->prepare('
                            SELECT MAX(pontuacao) as melhor_pontuacao
                            FROM snake_leaderboard
                            WHERE usuario_id = ?
                        ');
                        $stmt_user->execute([$usuario_id]);
                        $minha_pontuacao = $stmt_user->fetch(PDO::FETCH_ASSOC);
                        
                        echo json_encode([
                            'success' => true,
                            'leaderboard' => $leaderboard ?: [],
                            'minha_pontuacao' => intval($minha_pontuacao['melhor_pontuacao'] ?? 0)
                        ]);
                    } catch (Exception $e2) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Erro ao criar/buscar leaderboard: ' . $e2->getMessage()
                        ]);
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erro ao buscar leaderboard: ' . $e->getMessage()
                    ]);
                }
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Ação não reconhecida: ' . $action,
                'available_actions' => ['save', 'get']
            ]);
    }
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}

