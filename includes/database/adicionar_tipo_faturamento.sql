-- Script para adicionar coluna tipo_faturamento na tabela ORCAMENTOS
-- Execute este script no seu banco de dados MySQL

-- Verificar se a coluna já existe antes de adicionar
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'ORCAMENTOS' 
       AND COLUMN_NAME = 'tipo_faturamento') = 0,
    'ALTER TABLE ORCAMENTOS ADD COLUMN tipo_faturamento VARCHAR(255) AFTER forma_pagamento',
    'SELECT "Coluna tipo_faturamento já existe na tabela ORCAMENTOS" as resultado'
));

-- Executar o comando SQL
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se a coluna foi adicionada com sucesso
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ORCAMENTOS' 
  AND COLUMN_NAME = 'tipo_faturamento';

