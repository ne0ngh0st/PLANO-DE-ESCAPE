-- Script simples para adicionar colunas de aprovação na tabela ORCAMENTOS
-- Execute este script no PHPMyAdmin ou via linha de comando MySQL

-- Adicionar coluna para token de aprovação
ALTER TABLE ORCAMENTOS 
ADD COLUMN token_aprovacao VARCHAR(255) UNIQUE 
AFTER usuario_criador;

-- Adicionar coluna para data de aprovação do cliente
ALTER TABLE ORCAMENTOS 
ADD COLUMN data_aprovacao_cliente TIMESTAMP NULL 
AFTER token_aprovacao;

-- Adicionar colunas de segurança
ALTER TABLE ORCAMENTOS 
ADD COLUMN token_expires_at TIMESTAMP NULL 
AFTER data_aprovacao_cliente;

ALTER TABLE ORCAMENTOS 
ADD COLUMN token_used_at TIMESTAMP NULL 
AFTER token_expires_at;

ALTER TABLE ORCAMENTOS 
ADD COLUMN token_ip_address VARCHAR(45) NULL 
AFTER token_used_at;

ALTER TABLE ORCAMENTOS 
ADD COLUMN token_attempts INT DEFAULT 0 
AFTER token_ip_address;

-- Verificar se as colunas foram adicionadas
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ORCAMENTOS' 
  AND COLUMN_NAME IN ('token_aprovacao', 'data_aprovacao_cliente');
