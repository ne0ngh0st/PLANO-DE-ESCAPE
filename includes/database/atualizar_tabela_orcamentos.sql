-- Script para adicionar colunas de aprovação do cliente na tabela ORCAMENTOS
-- Execute este script se a tabela ORCAMENTOS já existir

-- Verificar se as colunas já existem antes de adicionar
SET @sql = '';

-- Adicionar coluna token_aprovacao se não existir
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ORCAMENTOS' 
  AND COLUMN_NAME = 'token_aprovacao';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ORCAMENTOS ADD COLUMN token_aprovacao VARCHAR(255) UNIQUE AFTER usuario_criador;', 
    'SELECT "Coluna token_aprovacao já existe" as mensagem;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar coluna data_aprovacao_cliente se não existir
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ORCAMENTOS' 
  AND COLUMN_NAME = 'data_aprovacao_cliente';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ORCAMENTOS ADD COLUMN data_aprovacao_cliente TIMESTAMP NULL AFTER token_aprovacao;', 
    'SELECT "Coluna data_aprovacao_cliente já existe" as mensagem;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se as colunas foram adicionadas com sucesso
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ORCAMENTOS' 
  AND COLUMN_NAME IN ('token_aprovacao', 'data_aprovacao_cliente')
ORDER BY ORDINAL_POSITION;

-- Mostrar estrutura completa da tabela
DESCRIBE ORCAMENTOS;




