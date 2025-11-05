<?php
/**
 * Script para criar a tabela de marcação de clientes ligados
 * Esta tabela armazena quais clientes foram marcados como "já ligados" pelos usuários
 */

require_once __DIR__ . '/../config/conexao.php';

try {
    // Primeiro, verificar a estrutura da tabela USUARIOS
    $check_usuarios = "SHOW TABLES LIKE 'USUARIOS'";
    $stmt_check = $pdo->query($check_usuarios);
    
    if ($stmt_check->rowCount() == 0) {
        echo "AVISO: Tabela USUARIOS não encontrada. Criando tabela sem foreign key.\n";
        $with_fk = false;
    } else {
        // Verificar estrutura da coluna ID na tabela USUARIOS
        $check_id = "SHOW COLUMNS FROM USUARIOS WHERE Field IN ('id', 'ID')";
        $stmt_id = $pdo->query($check_id);
        $coluna_id = $stmt_id->fetch(PDO::FETCH_ASSOC);
        
        if ($coluna_id) {
            $with_fk = true;
            $id_column = $coluna_id['Field']; // Pode ser 'id' ou 'ID'
            $id_type = $coluna_id['Type']; // Tipo da coluna (INT, INT UNSIGNED, etc)
            
            // Ajustar tipo de usuario_id para corresponder ao tipo em USUARIOS
            if (stripos($id_type, 'unsigned') !== false) {
                $usuario_id_type = 'INT UNSIGNED';
            } else {
                $usuario_id_type = 'INT';
            }
        } else {
            echo "AVISO: Coluna ID não encontrada na tabela USUARIOS. Criando tabela sem foreign key.\n";
            $with_fk = false;
            $usuario_id_type = 'INT';
        }
    }
    
    // Criar tabela sem foreign key primeiro
    $sql = "CREATE TABLE IF NOT EXISTS clientes_ligados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id {$usuario_id_type} NOT NULL,
        raiz_cnpj VARCHAR(8) NOT NULL,
        cnpj_completo VARCHAR(20) NOT NULL,
        cliente_nome VARCHAR(255) NOT NULL,
        data_marcacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_usuario_cliente (usuario_id, raiz_cnpj),
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_raiz_cnpj (raiz_cnpj),
        INDEX idx_data_marcacao (data_marcacao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "Tabela 'clientes_ligados' criada com sucesso!\n";
    
    // Se a foreign key deve ser criada, tentar adicioná-la
    if ($with_fk && isset($id_column)) {
        try {
            // Verificar se a foreign key já existe antes de criar
            $check_fk = "SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'clientes_ligados' 
                        AND CONSTRAINT_NAME = 'fk_clientes_ligados_usuario'";
            $stmt_fk = $pdo->query($check_fk);
            
            if ($stmt_fk->rowCount() > 0) {
                // Remover foreign key existente
                $pdo->exec("ALTER TABLE clientes_ligados DROP FOREIGN KEY fk_clientes_ligados_usuario");
            }
            
            // Adicionar foreign key
            $fk_sql = "ALTER TABLE clientes_ligados 
                      ADD CONSTRAINT fk_clientes_ligados_usuario 
                      FOREIGN KEY (usuario_id) REFERENCES USUARIOS({$id_column}) ON DELETE CASCADE";
            $pdo->exec($fk_sql);
            echo "Foreign key adicionada com sucesso!\n";
        } catch (PDOException $e) {
            echo "AVISO: Não foi possível criar foreign key (isso não impede o funcionamento): " . $e->getMessage() . "\n";
            echo "A tabela foi criada sem foreign key. A funcionalidade ainda funcionará normalmente.\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Erro ao criar tabela: " . $e->getMessage() . "\n";
    echo "\nTentando criar sem foreign key...\n";
    
    try {
        // Tentar criar sem foreign key como fallback
        $sql_fallback = "CREATE TABLE IF NOT EXISTS clientes_ligados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            raiz_cnpj VARCHAR(8) NOT NULL,
            cnpj_completo VARCHAR(20) NOT NULL,
            cliente_nome VARCHAR(255) NOT NULL,
            data_marcacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_usuario_cliente (usuario_id, raiz_cnpj),
            INDEX idx_usuario_id (usuario_id),
            INDEX idx_raiz_cnpj (raiz_cnpj),
            INDEX idx_data_marcacao (data_marcacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql_fallback);
        echo "Tabela 'clientes_ligados' criada com sucesso (sem foreign key)!\n";
    } catch (PDOException $e2) {
        echo "Erro ao criar tabela (fallback): " . $e2->getMessage() . "\n";
    }
}

