<?php
// Script para executar a coleta de métricas via web (pode ser chamado por cron job)
// Exemplo de uso: php executar_coleta_metricas.php ou via web

// Verificar se está sendo executado via linha de comando ou web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Se executado via web, verificar autenticação
    session_start();
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        die('Acesso negado');
    }
    
    $usuario = $_SESSION['usuario'];
    $perfil_permitido = in_array(strtolower(trim($usuario['perfil'])), ['admin', 'diretor']);
    if (!$perfil_permitido) {
        http_response_code(403);
        die('Permissão negada');
    }
}

// Incluir o script de coleta
require_once __DIR__ . '/coletar_metricas_volatilidade.php';

if ($is_cli) {
    echo "Coleta de métricas executada via linha de comando.\n";
} else {
    echo "Coleta de métricas executada via web.\n";
}
?>


