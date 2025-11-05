<?php
/**
 * Script para criar a tabela de leaderboard do Snake Game
 * Armazena as pontuações dos usuários no jogo da cobrinha
 */

// Verificar se foi chamado via require (silencioso) ou diretamente (com output)
$silent_mode = !empty($argv) || (isset($_SERVER['REQUEST_METHOD']) && !empty($_SERVER['REQUEST_URI']));

require_once __DIR__ . '/../config/conexao.php';

try {
    // Verificar estrutura da tabela USUARIOS
    $check_usuarios = "SHOW TABLES LIKE 'USUARIOS'";
    $stmt_check = $pdo->query($check_usuarios);
    
    if ($stmt_check->rowCount() == 0) {
        if (!$silent_mode) {
            echo "AVISO: Tabela USUARIOS não encontrada. Criando tabela sem foreign key.\n";
        }
        $with_fk = false;
    } else {
        // Verificar estrutura da coluna ID na tabela USUARIOS
        $check_id = "SHOW COLUMNS FROM USUARIOS WHERE Field IN ('id', 'ID')";
        $stmt_id = $pdo->query($check_id);
        $coluna_id = $stmt_id->fetch(PDO::FETCH_ASSOC);
        
        if ($coluna_id) {
            $with_fk = true;
            $id_column = $coluna_id['Field'];
            $id_type = $coluna_id['Type'];
            
            if (stripos($id_type, 'unsigned') !== false) {
                $usuario_id_type = 'INT UNSIGNED';
            } else {
                $usuario_id_type = 'INT';
            }
        } else {
            if (!$silent_mode) {
                echo "AVISO: Coluna ID não encontrada na tabela USUARIOS. Criando tabela sem foreign key.\n";
            }
            $with_fk = false;
            $usuario_id_type = 'INT';
        }
    }
    
    // Criar tabela
    $sql = "CREATE TABLE IF NOT EXISTS snake_leaderboard (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id {$usuario_id_type} NOT NULL,
        usuario_nome VARCHAR(255) NOT NULL,
        pontuacao INT NOT NULL,
        data_pontuacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_pontuacao (pontuacao DESC),
        INDEX idx_data_pontuacao (data_pontuacao DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    if (!$silent_mode) {
        echo "Tabela 'snake_leaderboard' criada com sucesso!\n";
    }
    
    // Adicionar foreign key se possível
    if ($with_fk && isset($id_column)) {
        try {
            // Verificar se a foreign key já existe
            $check_fk = "SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'snake_leaderboard' 
                        AND CONSTRAINT_NAME = 'fk_snake_leaderboard_usuario'";
            $stmt_fk = $pdo->query($check_fk);
            
            if ($stmt_fk->rowCount() > 0) {
                $pdo->exec("ALTER TABLE snake_leaderboard DROP FOREIGN KEY fk_snake_leaderboard_usuario");
            }
            
            // Adicionar foreign key
            $fk_sql = "ALTER TABLE snake_leaderboard 
                      ADD CONSTRAINT fk_snake_leaderboard_usuario 
                      FOREIGN KEY (usuario_id) REFERENCES USUARIOS({$id_column}) ON DELETE CASCADE";
            $pdo->exec($fk_sql);
            if (!$silent_mode) {
                echo "Foreign key adicionada com sucesso!\n";
            }
        } catch (PDOException $e) {
            if (!$silent_mode) {
                echo "AVISO: Não foi possível criar foreign key (isso não impede o funcionamento): " . $e->getMessage() . "\n";
            }
        }
    }
    
} catch (PDOException $e) {
    if (!$silent_mode) {
        echo "Erro ao criar tabela: " . $e->getMessage() . "\n";
        echo "\nTentando criar sem foreign key...\n";
    }
    
    try {
        // Tentar criar sem foreign key como fallback
        $sql_fallback = "CREATE TABLE IF NOT EXISTS snake_leaderboard (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            usuario_nome VARCHAR(255) NOT NULL,
            pontuacao INT NOT NULL,
            data_pontuacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario_id (usuario_id),
            INDEX idx_pontuacao (pontuacao DESC),
            INDEX idx_data_pontuacao (data_pontuacao DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql_fallback);
        if (!$silent_mode) {
            echo "Tabela 'snake_leaderboard' criada com sucesso (sem foreign key)!\n";
        }
    } catch (PDOException $e2) {
        if (!$silent_mode) {
            echo "Erro ao criar tabela (fallback): " . $e2->getMessage() . "\n";
        }
        throw $e2; // Re-lançar para que o chamador saiba do erro
    }
}

