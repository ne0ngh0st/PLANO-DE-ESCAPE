<?php
// Arquivo de teste para verificar se o acesso AJAX está funcionando
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Acesso AJAX funcionando corretamente!',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['HTTP_HOST'] ?? 'localhost'
]);
?>

