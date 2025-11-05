-- Script SQL simples para criar a tabela clientes_ligados SEM foreign key
-- Execute este script diretamente no MySQL se o script PHP não funcionar

CREATE TABLE IF NOT EXISTS clientes_ligados (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Se quiser adicionar a foreign key depois (opcional), execute:
-- ALTER TABLE clientes_ligados 
-- ADD CONSTRAINT fk_clientes_ligados_usuario 
-- FOREIGN KEY (usuario_id) REFERENCES USUARIOS(ID) ON DELETE CASCADE;




