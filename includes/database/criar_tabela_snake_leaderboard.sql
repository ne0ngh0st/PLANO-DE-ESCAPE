-- Script SQL para criar a tabela de leaderboard do Snake Game
-- Armazena as pontuações dos usuários no jogo da cobrinha

CREATE TABLE IF NOT EXISTS snake_leaderboard (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    usuario_nome VARCHAR(255) NOT NULL,
    pontuacao INT NOT NULL,
    data_pontuacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_pontuacao (pontuacao DESC),
    INDEX idx_data_pontuacao (data_pontuacao DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar foreign key para USUARIOS (opcional - descomente se a tabela USUARIOS existir)
 ALTER TABLE snake_leaderboard 
ADD CONSTRAINT fk_snake_leaderboard_usuario 
 FOREIGN KEY (usuario_id) REFERENCES USUARIOS(ID) ON DELETE CASCADE;



