<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'message' => 'Teste OK!',
    'timestamp' => date('Y-m-d H:i:s')
]);


