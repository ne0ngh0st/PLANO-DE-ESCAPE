<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

try {
    if (isset($_SESSION['carteira_filtros'])) {
        unset($_SESSION['carteira_filtros']);
    }
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Falha ao limpar filtros']);
}
exit;

<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

try {
    unset($_SESSION['carteira_filtros']);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


