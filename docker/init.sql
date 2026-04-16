-- ============================================
-- SEED DATABASE - BI Autopel (Dados Fictícios)
-- Para uso em homelab de cybersecurity
-- ============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS autopel01 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE autopel01;

-- ============================================
-- TABELA: USUARIOS
-- ============================================
CREATE TABLE IF NOT EXISTS USUARIOS (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    NOME_COMPLETO VARCHAR(255) NOT NULL,
    NOME_EXIBICAO VARCHAR(100),
    EMAIL VARCHAR(255) NOT NULL UNIQUE,
    PASSWORD_HASH VARCHAR(255) NOT NULL,
    PERFIL ENUM('admin', 'diretor', 'supervisor', 'vendedor', 'representante', 'ecommerce') DEFAULT 'vendedor',
    COD_VENDEDOR VARCHAR(50),
    COD_SUPER VARCHAR(50),
    SIDEBAR_COLOR VARCHAR(20) DEFAULT '#1a237e',
    FOTO_PERFIL VARCHAR(255),
    ATIVO TINYINT(1) DEFAULT 1,
    ULTIMO_LOGIN DATETIME,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (EMAIL),
    INDEX idx_perfil (PERFIL),
    INDEX idx_cod_vendedor (COD_VENDEDOR),
    INDEX idx_cod_super (COD_SUPER)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Senhas: todas são "admin123" (bcrypt hash)
INSERT INTO USUARIOS (NOME_COMPLETO, NOME_EXIBICAO, EMAIL, PASSWORD_HASH, PERFIL, COD_VENDEDOR, COD_SUPER, ATIVO) VALUES
('Administrador Sistema', 'Admin', 'admin@autopel.com', '$2y$10$YMjGiXVJFDkhKRr6Nm.SLeEzm4q3wSRxVBGFsNjX4JqV8m8Hm2J6y', 'admin', 'ADM001', NULL, 1),
('Carlos Mendes', 'Carlos', 'carlos@autopel.com', '$2y$10$YMjGiXVJFDkhKRr6Nm.SLeEzm4q3wSRxVBGFsNjX4JqV8m8Hm2J6y', 'diretor', 'DIR001', NULL, 1),
('Fernanda Oliveira', 'Fernanda', 'fernanda@autopel.com', '$2y$10$YMjGiXVJFDkhKRr6Nm.SLeEzm4q3wSRxVBGFsNjX4JqV8m8Hm2J6y', 'supervisor', 'SUP001', NULL, 1),
('Ricardo Almeida', 'Ricardo', 'ricardo@autopel.com', '$2y$10$YMjGiXVJFDkhKRr6Nm.SLeEzm4q3wSRxVBGFsNjX4JqV8m8Hm2J6y', 'vendedor', 'VEN001', 'SUP001', 1),
('Juliana Costa', 'Juliana', 'juliana@autopel.com', '$2y$10$YMjGiXVJFDkhKRr6Nm.SLeEzm4q3wSRxVBGFsNjX4JqV8m8Hm2J6y', 'vendedor', 'VEN002', 'SUP001', 1),
('Marcos Silva', 'Marcos', 'marcos@autopel.com', '$2y$10$YMjGiXVJFDkhKRr6Nm.SLeEzm4q3wSRxVBGFsNjX4JqV8m8Hm2J6y', 'representante', 'REP001', 'SUP001', 1),
('Ana Paula Santos', 'Ana Paula', 'anapaula@autopel.com', '$2y$10$YMjGiXVJFDkhKRr6Nm.SLeEzm4q3wSRxVBGFsNjX4JqV8m8Hm2J6y', 'ecommerce', 'ECO001', NULL, 1);

-- ============================================
-- TABELA: login_tokens
-- ============================================
CREATE TABLE IF NOT EXISTS login_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expira DATETIME NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_usuario (usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES USUARIOS(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: ultimo_faturamento (carteira de clientes)
-- ============================================
CREATE TABLE IF NOT EXISTS ultimo_faturamento (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    CNPJ VARCHAR(20),
    cliente VARCHAR(255),
    COD_VENDEDOR VARCHAR(50),
    EMISSAO VARCHAR(20),
    VALOR DECIMAL(15,2),
    ESTADO VARCHAR(2),
    CIDADE VARCHAR(100),
    Descricao1 VARCHAR(255),
    NOME_CONTATO VARCHAR(255),
    TELEFONE VARCHAR(50),
    EMAIL VARCHAR(255),
    INDEX idx_cnpj (CNPJ),
    INDEX idx_cod_vendedor (COD_VENDEDOR),
    INDEX idx_estado (ESTADO)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ultimo_faturamento (CNPJ, cliente, COD_VENDEDOR, EMISSAO, VALOR, ESTADO, CIDADE, Descricao1, NOME_CONTATO, TELEFONE, EMAIL) VALUES
('12.345.678/0001-90', 'Papelaria Central Ltda', 'VEN001', '15/03/2026', 4500.00, 'SP', 'São Paulo', 'Papelaria', 'João Batista', '(11) 99999-1111', 'joao@papcentral.com'),
('23.456.789/0001-01', 'Gráfica Express ME', 'VEN001', '10/03/2026', 8200.50, 'SP', 'Campinas', 'Gráfica', 'Maria Lima', '(19) 98888-2222', 'maria@graficaexpress.com'),
('34.567.890/0001-12', 'Distribuidora Norte Papel', 'VEN001', '01/02/2026', 15300.00, 'PR', 'Curitiba', 'Distribuidor', 'Pedro Souza', '(41) 97777-3333', 'pedro@nortepapel.com'),
('45.678.901/0001-23', 'Office Supplies SA', 'VEN002', '20/03/2026', 22100.00, 'RJ', 'Rio de Janeiro', 'Escritório', 'Ana Torres', '(21) 96666-4444', 'ana@officesupplies.com'),
('56.789.012/0001-34', 'Embalagens Rápidas Ltda', 'VEN002', '05/03/2026', 6750.00, 'MG', 'Belo Horizonte', 'Embalagens', 'Carlos Neto', '(31) 95555-5555', 'carlos@embrapi.com'),
('67.890.123/0001-45', 'Copiadora Digital ME', 'REP001', '12/03/2026', 3200.00, 'SC', 'Florianópolis', 'Reprografia', 'Lucia Martins', '(48) 94444-6666', 'lucia@copiadigital.com'),
('78.901.234/0001-56', 'Atacado Papel Sul', 'REP001', '28/02/2026', 45000.00, 'RS', 'Porto Alegre', 'Atacado', 'Roberto Dias', '(51) 93333-7777', 'roberto@papelsul.com'),
('89.012.345/0001-67', 'Escola Municipal Progresso', 'VEN001', '18/01/2026', 1200.00, 'SP', 'Santos', 'Educação', 'Marcia Ribeiro', '(13) 92222-8888', 'marcia@escolaprogresso.edu'),
('90.123.456/0001-78', 'Tech Print Ltda', 'VEN002', '22/03/2026', 18500.00, 'BA', 'Salvador', 'Tecnologia', 'Fernando Gomes', '(71) 91111-9999', 'fernando@techprint.com'),
('01.234.567/0001-89', 'Livraria PageTurner', 'REP001', '14/03/2026', 5600.00, 'CE', 'Fortaleza', 'Livraria', 'Renata Lopes', '(85) 90000-1234', 'renata@pageturner.com'),
('11.222.333/0001-44', 'Supermercado Bom Preço', 'VEN001', '25/03/2026', 2800.00, 'SP', 'Ribeirão Preto', 'Varejo', 'Thiago Barros', '(16) 98765-4321', 'thiago@bompreco.com'),
('22.333.444/0001-55', 'Construtora Horizonte', 'VEN002', '08/03/2026', 9400.00, 'GO', 'Goiânia', 'Construção', 'Patrícia Freitas', '(62) 91234-5678', 'patricia@horizonte.com');

-- ============================================
-- TABELA: FATURAMENTO (histórico)
-- ============================================
CREATE TABLE IF NOT EXISTS FATURAMENTO (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    CNPJ VARCHAR(20),
    cliente VARCHAR(255),
    COD_VENDEDOR VARCHAR(50),
    EMISSAO VARCHAR(20),
    VALOR DECIMAL(15,2),
    NOTA VARCHAR(50),
    INDEX idx_cnpj (CNPJ),
    INDEX idx_cod_vendedor (COD_VENDEDOR)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO FATURAMENTO (CNPJ, cliente, COD_VENDEDOR, EMISSAO, VALOR, NOTA) VALUES
('12.345.678/0001-90', 'Papelaria Central Ltda', 'VEN001', '15/03/2026', 4500.00, 'NF-001234'),
('12.345.678/0001-90', 'Papelaria Central Ltda', 'VEN001', '10/01/2026', 3800.00, 'NF-001100'),
('23.456.789/0001-01', 'Gráfica Express ME', 'VEN001', '10/03/2026', 8200.50, 'NF-001235'),
('34.567.890/0001-12', 'Distribuidora Norte Papel', 'VEN001', '01/02/2026', 15300.00, 'NF-001200'),
('45.678.901/0001-23', 'Office Supplies SA', 'VEN002', '20/03/2026', 22100.00, 'NF-001236'),
('56.789.012/0001-34', 'Embalagens Rápidas Ltda', 'VEN002', '05/03/2026', 6750.00, 'NF-001220'),
('67.890.123/0001-45', 'Copiadora Digital ME', 'REP001', '12/03/2026', 3200.00, 'NF-001237'),
('78.901.234/0001-56', 'Atacado Papel Sul', 'REP001', '28/02/2026', 45000.00, 'NF-001210'),
('89.012.345/0001-67', 'Escola Municipal Progresso', 'VEN001', '18/01/2026', 1200.00, 'NF-001150'),
('90.123.456/0001-78', 'Tech Print Ltda', 'VEN002', '22/03/2026', 18500.00, 'NF-001238');

-- ============================================
-- TABELA: PEDIDOS_EM_ABERTO
-- ============================================
CREATE TABLE IF NOT EXISTS PEDIDOS_EM_ABERTO (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    NUM_PEDIDO VARCHAR(50),
    CNPJ VARCHAR(20),
    CLIENTE VARCHAR(255),
    COD_VENDEDOR VARCHAR(50),
    DT_PEDIDO VARCHAR(20),
    DT_FATURAMENTO VARCHAR(20),
    DT_ENTREGA VARCHAR(20),
    VLR_TOTAL DECIMAL(15,2),
    STATUS VARCHAR(50),
    INDEX idx_cod_vendedor (COD_VENDEDOR),
    INDEX idx_status (STATUS)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO PEDIDOS_EM_ABERTO (NUM_PEDIDO, CNPJ, CLIENTE, COD_VENDEDOR, DT_PEDIDO, DT_FATURAMENTO, DT_ENTREGA, VLR_TOTAL, STATUS) VALUES
('PED-2026-001', '12.345.678/0001-90', 'Papelaria Central Ltda', 'VEN001', '25/03/2026', '28/03/2026', '02/04/2026', 5600.00, 'Em Separação'),
('PED-2026-002', '45.678.901/0001-23', 'Office Supplies SA', 'VEN002', '26/03/2026', NULL, '05/04/2026', 12300.00, 'Aguardando Aprovação'),
('PED-2026-003', '78.901.234/0001-56', 'Atacado Papel Sul', 'REP001', '20/03/2026', '22/03/2026', '30/03/2026', 38000.00, 'Faturado'),
('PED-2026-004', '23.456.789/0001-01', 'Gráfica Express ME', 'VEN001', '27/03/2026', NULL, '10/04/2026', 9500.00, 'Pendente'),
('PED-2026-005', '56.789.012/0001-34', 'Embalagens Rápidas Ltda', 'VEN002', '24/03/2026', '26/03/2026', '01/04/2026', 4200.00, 'Em Transporte');

-- ============================================
-- TABELA: BASE_LEADS
-- ============================================
CREATE TABLE IF NOT EXISTS BASE_LEADS (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    RAZAO_SOCIAL VARCHAR(255),
    NOME_FANTASIA VARCHAR(255),
    CNPJ VARCHAR(20),
    Email VARCHAR(255),
    Telefone VARCHAR(50),
    CIDADE VARCHAR(100),
    UF VARCHAR(2),
    SEGMENTO VARCHAR(100),
    COD_VENDEDOR VARCHAR(50),
    MARCAOPROSPECT VARCHAR(50) DEFAULT 'PROSPECT',
    STATUS VARCHAR(50) DEFAULT 'Novo',
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cnpj (CNPJ),
    INDEX idx_cod_vendedor (COD_VENDEDOR),
    INDEX idx_status (STATUS)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO BASE_LEADS (RAZAO_SOCIAL, NOME_FANTASIA, CNPJ, Email, Telefone, CIDADE, UF, SEGMENTO, COD_VENDEDOR, MARCAOPROSPECT, STATUS) VALUES
('Mega Impressões Ltda', 'Mega Print', '99.888.777/0001-66', 'contato@megaprint.com', '(11) 3456-7890', 'São Paulo', 'SP', 'Gráfica', 'VEN001', 'PROSPECT', 'Novo'),
('Embalart Embalagens', 'Embalart', '88.777.666/0001-55', 'vendas@embalart.com', '(21) 2345-6789', 'Niterói', 'RJ', 'Embalagens', 'VEN002', 'PROSPECT', 'Em Contato'),
('Copycenter Express', 'CopyCenter', '77.666.555/0001-44', 'admin@copycenter.com', '(31) 1234-5678', 'BH', 'MG', 'Reprografia', 'REP001', 'SAI PROSPECT', 'Convertido'),
('Papel & Cia SA', 'Papel e Cia', '66.555.444/0001-33', 'compras@papelecia.com', '(41) 9876-5432', 'Curitiba', 'PR', 'Distribuidor', 'VEN001', 'PROSPECT', 'Novo'),
('Escritório Total ME', 'EscrTotal', '55.444.333/0001-22', 'contato@escrtotal.com', '(51) 8765-4321', 'POA', 'RS', 'Escritório', 'VEN002', 'PROSPECT', 'Sem Interesse');

-- ============================================
-- TABELA: LEADS_MANUAIS
-- ============================================
CREATE TABLE IF NOT EXISTS LEADS_MANUAIS (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    RAZAO_SOCIAL VARCHAR(255),
    NOME_FANTASIA VARCHAR(255),
    CNPJ VARCHAR(20),
    EMAIL VARCHAR(255),
    TELEFONE VARCHAR(50),
    CIDADE VARCHAR(100),
    UF VARCHAR(2),
    SEGMENTO VARCHAR(100),
    OBSERVACAO TEXT,
    COD_VENDEDOR VARCHAR(50),
    STATUS VARCHAR(50) DEFAULT 'Novo',
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cod_vendedor (COD_VENDEDOR)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: LICITACAO
-- ============================================
CREATE TABLE IF NOT EXISTS LICITACAO (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    ORGAO VARCHAR(255),
    NUMERO_LICITACAO VARCHAR(100),
    MODALIDADE VARCHAR(100),
    OBJETO TEXT,
    DATA_ABERTURA DATE,
    DATA_ENCERRAMENTO DATE,
    VALOR_ESTIMADO DECIMAL(15,2),
    STATUS VARCHAR(50) DEFAULT 'Vigente',
    GERENCIADOR VARCHAR(150),
    OBSERVACOES TEXT,
    COD_VENDEDOR VARCHAR(50),
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (STATUS),
    INDEX idx_gerenciador (GERENCIADOR),
    INDEX idx_cod_vendedor (COD_VENDEDOR)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO LICITACAO (ORGAO, NUMERO_LICITACAO, MODALIDADE, OBJETO, DATA_ABERTURA, DATA_ENCERRAMENTO, VALOR_ESTIMADO, STATUS, GERENCIADOR, COD_VENDEDOR) VALUES
('Prefeitura Municipal de Fictícia', 'PE-2026/001', 'Pregão Eletrônico', 'Aquisição de material de escritório e papelaria', '2026-01-15', '2026-12-31', 250000.00, 'Vigente', 'Governo Municipal', 'VEN001'),
('Tribunal Regional Fictício', 'PE-2026/015', 'Pregão Eletrônico', 'Fornecimento de papel A4 e suprimentos', '2026-02-01', '2027-01-31', 180000.00, 'Vigente', 'Poder Judiciário', 'VEN002'),
('Universidade Federal Exemplo', 'CC-2026/003', 'Concorrência', 'Material de consumo para laboratórios', '2025-06-01', '2026-05-31', 500000.00, 'Encerrado', 'Educação Federal', 'REP001');

-- ============================================
-- TABELA: GERENCIADOR
-- ============================================
CREATE TABLE IF NOT EXISTS GERENCIADOR (
    ID_GERENCIADOR INT AUTO_INCREMENT PRIMARY KEY,
    NOME VARCHAR(150) NOT NULL,
    ATIVO TINYINT(1) DEFAULT 1,
    COD_VENDEDOR_CRIACAO VARCHAR(50),
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nome (NOME),
    INDEX idx_ativo (ATIVO)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO GERENCIADOR (NOME, ATIVO, COD_VENDEDOR_CRIACAO) VALUES
('Governo Municipal', 1, 'ADM001'),
('Poder Judiciário', 1, 'ADM001'),
('Educação Federal', 1, 'ADM001');

-- ============================================
-- TABELA: contratos
-- ============================================
CREATE TABLE IF NOT EXISTS contratos (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    GERENCIADOR VARCHAR(100) NOT NULL,
    DESCRICAO TEXT,
    VALOR_CONTRATADO DECIMAL(15,2) DEFAULT 0.00,
    VALOR_CONSUMIDO DECIMAL(15,2) DEFAULT 0.00,
    DATA_INICIO DATE,
    DATA_FIM DATE,
    STATUS VARCHAR(50) DEFAULT 'Ativo',
    ATIVO TINYINT(1) DEFAULT 1,
    razao_social VARCHAR(255),
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gerenciador (GERENCIADOR),
    INDEX idx_ativo (ATIVO)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO contratos (GERENCIADOR, DESCRICAO, VALOR_CONTRATADO, VALOR_CONSUMIDO, DATA_INICIO, DATA_FIM, STATUS, razao_social) VALUES
('Governo Municipal', 'Contrato de fornecimento de papel e material de escritório', 150000.00, 45000.00, '2026-01-01', '2026-12-31', 'Ativo', 'Prefeitura Municipal de Fictícia'),
('Poder Judiciário', 'Fornecimento de suprimentos de impressão', 80000.00, 22000.00, '2026-02-01', '2027-01-31', 'Ativo', 'Tribunal Regional Fictício'),
('Educação Federal', 'Material de consumo laboratorial', 300000.00, 280000.00, '2025-06-01', '2026-05-31', 'Encerrado', 'Universidade Federal Exemplo');

-- ============================================
-- TABELA: ORCAMENTOS
-- ============================================
CREATE TABLE IF NOT EXISTS ORCAMENTOS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_nome VARCHAR(255) NOT NULL,
    cliente_cnpj VARCHAR(18),
    cliente_email VARCHAR(255),
    cliente_telefone VARCHAR(20),
    tipo_produto_servico ENUM('produto', 'servico') DEFAULT 'produto',
    produto_servico TEXT NOT NULL,
    descricao TEXT,
    valor_total DECIMAL(10,2) NOT NULL,
    status ENUM('pendente', 'aprovado', 'rejeitado', 'cancelado') DEFAULT 'pendente',
    forma_pagamento ENUM('a_vista', '28_ddl') DEFAULT 'a_vista',
    tipo_faturamento VARCHAR(255),
    itens_orcamento JSON,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_validade DATE,
    codigo_vendedor VARCHAR(50) NOT NULL,
    observacoes TEXT,
    usuario_criador VARCHAR(100),
    token_aprovacao VARCHAR(255) UNIQUE,
    data_aprovacao_cliente TIMESTAMP NULL,
    motivo_recusa TEXT,
    token_expires_at DATETIME,
    token_attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ORCAMENTOS (cliente_nome, cliente_cnpj, cliente_email, cliente_telefone, produto_servico, descricao, valor_total, status, forma_pagamento, codigo_vendedor, usuario_criador, data_validade) VALUES
('Papelaria Central Ltda', '12.345.678/0001-90', 'joao@papcentral.com', '(11) 99999-1111', 'Resma A4 75g (cx 10un)', 'Papel sulfite A4, 75g, caixa com 10 resmas', 2500.00, 'pendente', 'a_vista', 'VEN001', 'ricardo@autopel.com', '2026-04-30'),
('Office Supplies SA', '45.678.901/0001-23', 'ana@officesupplies.com', '(21) 96666-4444', 'Kit material escritório completo', 'Inclui grampeador, furador, clips, post-it, canetas', 8900.00, 'aprovado', '28_ddl', 'VEN002', 'juliana@autopel.com', '2026-04-15');

-- ============================================
-- TABELA: observacoes
-- ============================================
CREATE TABLE IF NOT EXISTS observacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cnpj VARCHAR(20),
    raiz_cnpj VARCHAR(8),
    usuario_id INT,
    usuario_nome VARCHAR(255),
    texto TEXT,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_raiz_cnpj (raiz_cnpj),
    INDEX idx_usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO observacoes (cnpj, raiz_cnpj, usuario_id, usuario_nome, texto) VALUES
('12.345.678/0001-90', '12345678', 4, 'Ricardo Almeida', 'Cliente solicitou desconto para compra acima de 50 caixas. Verificar com gerência.'),
('45.678.901/0001-23', '45678901', 5, 'Juliana Costa', 'Preferem entrega às terças e quintas. Portaria fecha 17h.'),
('78.901.234/0001-56', '78901234', 6, 'Marcos Silva', 'Pagamento sempre via boleto 28DDL. Não aceitar cheque.');

-- ============================================
-- TABELA: clientes_excluidos
-- ============================================
CREATE TABLE IF NOT EXISTS clientes_excluidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cnpj VARCHAR(20),
    raiz_cnpj VARCHAR(8),
    cliente VARCHAR(255),
    motivo TEXT,
    usuario_id INT,
    usuario_nome VARCHAR(255),
    no_lixao TINYINT(1) DEFAULT 0,
    oculto TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_raiz_cnpj (raiz_cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: clientes_restaurados
-- ============================================
CREATE TABLE IF NOT EXISTS clientes_restaurados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cnpj VARCHAR(20),
    raiz_cnpj VARCHAR(8),
    cliente VARCHAR(255),
    usuario_id INT,
    usuario_nome VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: clientes_ligados
-- ============================================
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
    INDEX idx_raiz_cnpj (raiz_cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: LIGACOES
-- ============================================
CREATE TABLE IF NOT EXISTS LIGACOES (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cnpj VARCHAR(20),
    raiz_cnpj VARCHAR(8),
    cliente_nome VARCHAR(255),
    data_ligacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    duracao_minutos INT,
    resultado VARCHAR(100),
    observacao TEXT,
    INDEX idx_usuario (usuario_id),
    INDEX idx_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: PERGUNTAS_LIGACAO
-- ============================================
CREATE TABLE IF NOT EXISTS PERGUNTAS_LIGACAO (
    id INT AUTO_INCREMENT PRIMARY KEY,
    texto VARCHAR(500) NOT NULL,
    obrigatoria TINYINT(1) DEFAULT 0,
    ativa TINYINT(1) DEFAULT 1,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO PERGUNTAS_LIGACAO (texto, obrigatoria, ativa, ordem) VALUES
('O cliente demonstrou interesse em novos produtos?', 1, 1, 1),
('Há previsão de compra nos próximos 30 dias?', 1, 1, 2),
('O cliente mencionou concorrentes?', 0, 1, 3),
('Existe pendência financeira a resolver?', 0, 1, 4);

-- ============================================
-- TABELA: RESPOSTAS_LIGACAO
-- ============================================
CREATE TABLE IF NOT EXISTS RESPOSTAS_LIGACAO (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ligacao_id INT NOT NULL,
    pergunta_id INT NOT NULL,
    resposta TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ligacao (ligacao_id),
    INDEX idx_pergunta (pergunta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: sugestoes
-- ============================================
CREATE TABLE IF NOT EXISTS sugestoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    usuario_nome VARCHAR(255),
    titulo VARCHAR(255),
    descricao TEXT,
    categoria VARCHAR(50),
    status VARCHAR(50) DEFAULT 'pendente',
    visivel TINYINT(1) DEFAULT 1,
    resposta_admin TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: observacoes_excluidas
-- ============================================
CREATE TABLE IF NOT EXISTS observacoes_excluidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    observacao_id INT,
    cnpj VARCHAR(20),
    usuario_id INT,
    usuario_nome VARCHAR(255),
    texto TEXT,
    excluido_por INT,
    excluido_nome VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: agendamentos_leads
-- ============================================
CREATE TABLE IF NOT EXISTS agendamentos_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    lead_email VARCHAR(255),
    lead_identificador VARCHAR(255),
    lead_nome VARCHAR(255),
    lead_telefone VARCHAR(50),
    data_agendamento DATE,
    observacao TEXT,
    status ENUM('agendado', 'realizado', 'cancelado') DEFAULT 'agendado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_data (data_agendamento),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: notificacoes_lidas
-- ============================================
CREATE TABLE IF NOT EXISTS notificacoes_lidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    notificacao_id VARCHAR(100) NOT NULL,
    lida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_notif (usuario_id, notificacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: notificacoes_personalizadas
-- ============================================
CREATE TABLE IF NOT EXISTS notificacoes_personalizadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255),
    mensagem TEXT,
    tipo VARCHAR(50),
    destinatario_id INT,
    criado_por INT,
    lida TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: CONFIGURACOES_MODAIS
-- ============================================
CREATE TABLE IF NOT EXISTS CONFIGURACOES_MODAIS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    modal_id VARCHAR(100) NOT NULL,
    configuracao JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_modal (usuario_id, modal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: snake_leaderboard
-- ============================================
CREATE TABLE IF NOT EXISTS snake_leaderboard (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    usuario_nome VARCHAR(255) NOT NULL,
    pontuacao INT NOT NULL,
    data_pontuacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_pontuacao (pontuacao DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: security_logs
-- ============================================
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(100),
    orcamento_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: LEADS_EDICOES_ARQUIVO
-- ============================================
CREATE TABLE IF NOT EXISTS LEADS_EDICOES_ARQUIVO (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT,
    campo VARCHAR(100),
    valor_original TEXT,
    valor_novo TEXT,
    usuario_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: leads_restaurados
-- ============================================
CREATE TABLE IF NOT EXISTS leads_restaurados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT,
    usuario_id INT,
    usuario_nome VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: clientes_para_cadastro
-- ============================================
CREATE TABLE IF NOT EXISTS clientes_para_cadastro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(255),
    cnpj VARCHAR(20),
    email VARCHAR(255),
    telefone VARCHAR(50),
    cidade VARCHAR(100),
    uf VARCHAR(2),
    usuario_id INT,
    status VARCHAR(50) DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelas Bling (sync)
-- ============================================
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
    INDEX idx_situacao (situacao_valor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: metricas_volatilidade_diaria
-- ============================================
CREATE TABLE IF NOT EXISTS metricas_volatilidade_diaria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_referencia DATE NOT NULL,
    cod_vendedor VARCHAR(50),
    total_clientes INT DEFAULT 0,
    clientes_ativos INT DEFAULT 0,
    clientes_inativos INT DEFAULT 0,
    faturamento_total DECIMAL(15,2) DEFAULT 0,
    ticket_medio DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_data (data_referencia),
    INDEX idx_vendedor (cod_vendedor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: alertas_volatilidade
-- ============================================
CREATE TABLE IF NOT EXISTS alertas_volatilidade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50),
    mensagem TEXT,
    cod_vendedor VARCHAR(50),
    cnpj VARCHAR(20),
    severidade ENUM('baixa', 'media', 'alta', 'critica') DEFAULT 'media',
    lido TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_vendedor (cod_vendedor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: alertas_volatilidade_lidos
-- ============================================
CREATE TABLE IF NOT EXISTS alertas_volatilidade_lidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    lido_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_alerta_user (alerta_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VIEW: vw_bling_vendas
-- ============================================
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
WHERE p.situacao_valor NOT IN (9, 12);

-- ============================================
-- FIM DO SEED
-- ============================================
