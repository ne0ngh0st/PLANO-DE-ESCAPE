-- ===============================================
-- Script para atualizar a tabela ORCAMENTOS
-- ===============================================
-- Alterando o campo forma_pagamento de ENUM para VARCHAR 
-- para aceitar todas as condições de pagamento da TABLE 74

-- 1. Alterar o tipo da coluna forma_pagamento
ALTER TABLE ORCAMENTOS 
MODIFY COLUMN forma_pagamento VARCHAR(255) NULL;

-- 2. Verificar a estrutura atualizada
DESCRIBE ORCAMENTOS;

-- 3. Verificar se a TABLE 74 existe
SHOW TABLES LIKE 'TABLE 74';

-- 4. Ver a estrutura da TABLE 74
DESCRIBE `TABLE 74`;

-- 5. Mostrar todas as condições de pagamento disponíveis na TABLE 74
-- (Substitua 'NOME_DA_COLUNA' pelo nome real da coluna que contém as descrições)
SELECT * FROM `TABLE 74` 
ORDER BY 1 ASC;

-- 6. Contar total de condições disponíveis
SELECT COUNT(*) as total_condicoes FROM `TABLE 74`;

-- ===============================================
-- NOTAS IMPORTANTES:
-- ===============================================
-- - As condições de pagamento são carregadas dinamicamente da TABLE 74
-- - Para adicionar novas condições, basta inserir registros na TABLE 74
-- - O sistema detecta automaticamente qual coluna usar na TABLE 74
-- - Possíveis nomes de coluna: DESCRICAO, NOME, CONDICAO, FORMA_PAGAMENTO, etc.

