-- ============================================
-- TABELAS PARA SINCRONIZAÇÃO DO BLING
-- ============================================

-- Tabela de pedidos do Bling
CREATE TABLE IF NOT EXISTS bling_pedidos (
    id BIGINT PRIMARY KEY,
    numero INT,
    numero_loja VARCHAR(100),
    data DATE,
    data_saida DATE,
    data_prevista DATE,
    total_produtos DECIMAL(10,2),
    total DECIMAL(10,2),
    frete DECIMAL(10,2),
    outras_despesas DECIMAL(10,2),
    custo_frete DECIMAL(10,2),
    taxa_comissao DECIMAL(10,2),
    contato_id BIGINT,
    contato_nome VARCHAR(255),
    contato_documento VARCHAR(50),
    situacao_id INT,
    situacao_valor INT,
    loja_id BIGINT,
    observacoes TEXT,
    json_completo JSON,
    sincronizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_data (data),
    INDEX idx_sincronizado (sincronizado_em),
    INDEX idx_situacao (situacao_valor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de itens dos pedidos
CREATE TABLE IF NOT EXISTS bling_itens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pedido_id BIGINT NOT NULL,
    item_id BIGINT,
    codigo VARCHAR(100),
    descricao VARCHAR(500),
    unidade VARCHAR(10),
    quantidade DECIMAL(10,3),
    valor_unitario DECIMAL(10,2),
    valor_total DECIMAL(10,2),
    desconto DECIMAL(10,2),
    produto_id BIGINT,
    frete_proporcional DECIMAL(10,2),
    despesas_proporcionais DECIMAL(10,2),
    valor_total_com_extras DECIMAL(10,2),
    sincronizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES bling_pedidos(id) ON DELETE CASCADE,
    INDEX idx_pedido (pedido_id),
    INDEX idx_codigo (codigo),
    INDEX idx_descricao (descricao(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de controle de sincronização
CREATE TABLE IF NOT EXISTS bling_sincronizacao_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    iniciado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finalizado_em TIMESTAMP NULL,
    status ENUM('em_andamento', 'concluido', 'erro') DEFAULT 'em_andamento',
    total_pedidos_encontrados INT DEFAULT 0,
    total_pedidos_processados INT DEFAULT 0,
    total_itens_inseridos INT DEFAULT 0,
    data_inicial DATE,
    data_final DATE,
    mensagem TEXT,
    erro TEXT,
    usuario_id INT,
    INDEX idx_status (status),
    INDEX idx_iniciado (iniciado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View para facilitar consultas
CREATE OR REPLACE VIEW vw_bling_vendas AS
SELECT 
    p.id,
    p.numero,
    p.data,
    p.total,
    p.total_produtos,
    p.frete,
    p.outras_despesas,
    p.contato_nome,
    i.codigo,
    i.descricao as produto,
    i.quantidade,
    i.valor_unitario,
    i.valor_total as valor_produto,
    i.frete_proporcional,
    i.despesas_proporcionais,
    i.valor_total_com_extras
FROM bling_pedidos p
INNER JOIN bling_itens i ON p.id = i.pedido_id
WHERE p.situacao_valor NOT IN (9, 12); -- Excluir cancelados


