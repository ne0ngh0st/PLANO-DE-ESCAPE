<?php
session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/conexao.php';

try {
    if (!isset($_SESSION['usuario'])) {
        http_response_code(200);
        echo '<div class="no-data"><i class="fas fa-info-circle"></i> Sessão expirada. Recarregue a página.</div>';
        exit;
    }

    // Disponibilizar $usuario para o include
    $usuario = $_SESSION['usuario'];

    // Forçar metas expandidas por padrão no AJAX
    $_GET['show_metas'] = '1';

    ob_start();
    include __DIR__ . '/metas_detalhes.php';
    $html = ob_get_clean();

    echo $html;
} catch (Throwable $e) {
    http_response_code(200);
    echo '<div class="no-data"><i class="fas fa-info-circle"></i> Erro ao carregar metas.</div>';
}


