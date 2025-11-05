<?php
// Arquivo proxy - redireciona para o arquivo real em management/
// CRÍTICO: Este arquivo deve retornar APENAS JSON, nunca HTML ou redirect

// Limpar qualquer output buffer
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Iniciar novo buffer para capturar qualquer output inesperado
ob_start();

// Incluir o arquivo real
require_once __DIR__ . '/management/gerenciar_observacoes.php';

// Se chegou aqui sem exit, pegar o output
$output = ob_get_clean();

// Verificar se o output é JSON válido
$is_json = false;
if (!empty($output)) {
    $trimmed = trim($output);
    if (($trimmed[0] === '{' || $trimmed[0] === '[') && json_decode($trimmed) !== null) {
        $is_json = true;
    }
}

// Se não for JSON válido (pode ser HTML de login ou erro), retornar erro JSON
if (!$is_json) {
    // Limpar qualquer header que possa ter sido enviado
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8', true, 500);
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    // Se o output contém HTML de login, significa que a sessão expirou
    if (strpos($output, '<!DOCTYPE html') !== false || strpos($output, 'Login - Painel BI') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Sessão expirada. Faça login novamente.',
            'code' => 'SESSION_EXPIRED'
        ]);
    } else {
        // Outro tipo de erro
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno do servidor. Resposta inválida.',
            'code' => 'INVALID_RESPONSE',
            'debug' => substr($output, 0, 200) // Apenas primeiros 200 chars para debug
        ]);
    }
    exit;
}

// Output é JSON válido, apenas retornar
echo $output;
exit;
?>
