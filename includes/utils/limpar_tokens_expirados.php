<?php
/**
 * Script para desativar tokens de login expirados (versão sem DELETE)
 * Execute este script via cron job diariamente
 * Exemplo: 0 2 * * * php /caminho/para/limpar_tokens_expirados.php
 */

require_once 'conexao.php';

try {
    // Desativar tokens expirados (em vez de deletar)
    $stmt = $pdo->prepare('UPDATE login_tokens SET ativo = 0 WHERE expira < NOW() AND ativo = 1');
    $resultado = $stmt->execute();
    
    $linhas_afetadas = $stmt->rowCount();
    
    // Log do resultado
    $data = date('Y-m-d H:i:s');
    echo "[$data] Tokens expirados desativados: $linhas_afetadas\n";
    
    // Salvar log em arquivo (opcional)
    file_put_contents('logs/limpeza_tokens.log', "[$data] Tokens desativados: $linhas_afetadas\n", FILE_APPEND);
    
} catch (PDOException $e) {
    echo "Erro ao desativar tokens: " . $e->getMessage() . "\n";
    file_put_contents('logs/erro_limpeza_tokens.log', "[" . date('Y-m-d H:i:s') . "] Erro: " . $e->getMessage() . "\n", FILE_APPEND);
}
?> 