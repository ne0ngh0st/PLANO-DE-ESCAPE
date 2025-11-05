<?php
/**
 * Script para criar a tabela LEADS_MANUAIS
 * Esta tabela armazena leads cadastradas manualmente pelos usuários
 * Separada da BASE_LEADS que é sobrescrita diariamente
 */

require_once 'conexao.php';

try {
    // Criar tabela LEADS_MANUAIS
    $sql = "
    CREATE TABLE IF NOT EXISTS LEADS_MANUAIS (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        razao_social VARCHAR(255),
        nome_fantasia VARCHAR(255),
        email VARCHAR(255),
        telefone VARCHAR(20),
        endereco VARCHAR(255),
        complemento VARCHAR(255),
        cep VARCHAR(10),
        bairro VARCHAR(100),
        municipio VARCHAR(100),
        estado VARCHAR(2),
        cnpj VARCHAR(18),
        inscricao_estadual VARCHAR(20),
        segmento_atuacao VARCHAR(50),
        valor_estimado DECIMAL(10,2),
        observacoes TEXT,
        codigo_vendedor INT,
        usuario_cadastrou VARCHAR(100) NOT NULL,
        data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        data_ultima_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        status ENUM('ativo', 'inativo', 'convertido', 'excluido') DEFAULT 'ativo',
        data_conversao DATETIME NULL,
        observacoes_conversao TEXT,
        
        INDEX idx_email (email),
        INDEX idx_telefone (telefone),
        INDEX idx_cnpj (cnpj),
        INDEX idx_codigo_vendedor (codigo_vendedor),
        INDEX idx_usuario_cadastrou (usuario_cadastrou),
        INDEX idx_data_cadastro (data_cadastro),
        INDEX idx_status (status),
        INDEX idx_estado (estado),
        INDEX idx_segmento (segmento_atuacao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    
    echo "Tabela LEADS_MANUAIS criada com sucesso!";
    
} catch (PDOException $e) {
    error_log("Erro ao criar tabela LEADS_MANUAIS: " . $e->getMessage());
    echo "Erro ao criar tabela: " . $e->getMessage();
}
?>
