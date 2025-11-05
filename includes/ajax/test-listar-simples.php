<?php
session_start();

// Simular usuário admin para teste
if (!isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = [
        'id' => 1,
        'nome' => 'Teste',
        'perfil' => 'admin'
    ];
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'message' => 'Teste simples funcionando!',
    'session_exists' => isset($_SESSION['usuario']),
    'is_admin' => isset($_SESSION['usuario']) && strtolower($_SESSION['usuario']['perfil']) === 'admin',
    'pasta' => __DIR__ . '/../../.XLSX',
    'pasta_exists' => file_exists(__DIR__ . '/../../.XLSX')
]);


