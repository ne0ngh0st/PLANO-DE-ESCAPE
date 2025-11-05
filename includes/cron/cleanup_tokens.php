<?php
/**
 * Script para limpeza automática de tokens expirados
 * Execute este script via cron job diariamente
 */

require_once '../config.php';
require_once '../security/TokenSecurity.php';

// Log de execução
$logFile = __DIR__ . '/../logs/cron_' . date('Y-m-d') . '.log';
$logEntry = date('Y-m-d H:i:s') . " - Iniciando limpeza de tokens expirados\n";

try {
    // Inicializar classe de segurança
    $tokenSecurity = new TokenSecurity($pdo);
    
    // Executar limpeza
    $result = $tokenSecurity->cleanupExpiredTokens();
    
    if ($result) {
        $logEntry .= date('Y-m-d H:i:s') . " - Limpeza de tokens concluída com sucesso\n";
        
        // Obter estatísticas
        $stats = $tokenSecurity->getSecurityStats();
        $logEntry .= date('Y-m-d H:i:s') . " - Estatísticas: " . json_encode($stats) . "\n";
        
    } else {
        $logEntry .= date('Y-m-d H:i:s') . " - Erro na limpeza de tokens\n";
    }
    
} catch (Exception $e) {
    $logEntry .= date('Y-m-d H:i:s') . " - Erro: " . $e->getMessage() . "\n";
}

// Salvar log
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Se executado via CLI, mostrar resultado
if (php_sapi_name() === 'cli') {
    echo $logEntry;
}
?>




