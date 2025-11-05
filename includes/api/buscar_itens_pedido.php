<?php
// Arquivo para processar requisições AJAX de itens do pedido
session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo 'Não autorizado';
    exit;
}

require_once __DIR__ . '/../config/conexao.php';

// Verificar se é uma requisição POST com os parâmetros necessários
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['acao']) || $_POST['acao'] !== 'buscar_itens_pedido') {
    http_response_code(400);
    echo 'Requisição inválida';
    exit;
}

$numero_pedido = $_POST['numero_pedido'] ?? '';
$cnpj_pedido = $_POST['cnpj'] ?? '';

if (empty($numero_pedido) || empty($cnpj_pedido)) {
    echo '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Parâmetros inválidos.</div>';
    exit;
}

// Função para extrair a raiz do CNPJ (primeiros 8 dígitos)
function extrairRaizCNPJ($cnpj) {
    if (empty($cnpj)) return '';
    
    // Remover caracteres não numéricos
    $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Retornar os primeiros 8 dígitos (raiz do CNPJ)
    return substr($cnpj_limpo, 0, 8);
}

// Função para converter valor brasileiro para decimal
function converterValorBrasileiro($valor) {
    if (empty($valor)) return 0;
    
    // Se já é um número, usar diretamente
    if (is_numeric($valor)) {
        return floatval($valor);
    }
    
    // Se tem vírgula e ponto, é formato brasileiro (67.485,58)
    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        $valor_limpo = str_replace('.', '', $valor);
        $valor_limpo = str_replace(',', '.', $valor_limpo);
        return floatval($valor_limpo);
    }
    
    // Se só tem vírgula, substituir por ponto
    if (strpos($valor, ',') !== false) {
        $valor_limpo = str_replace(',', '.', $valor);
        return floatval($valor_limpo);
    }
    
    return floatval($valor);
}

$raiz_cnpj_pedido = extrairRaizCNPJ($cnpj_pedido);

// Buscar itens específicos do pedido
$itens_sql = "SELECT 
                COD_PROD,
                DES_PROD,
                QUANT,
                VLR_UNIT,
                VLR_TOTAL as valor_item,
                NTA_FISCAL
              FROM FATURAMENTO 
              WHERE PEDIDO = ? 
                AND SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?
              ORDER BY NTA_FISCAL, COD_PROD, DES_PROD";

$stmt = $pdo->prepare($itens_sql);
$stmt->execute([$numero_pedido, $raiz_cnpj_pedido]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($itens)) {
    echo '<div style="overflow-x: auto;">';
    echo '<table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">';
    echo '<thead>';
    echo '<tr style="background: #e9ecef;">';
    echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">NOTA FISCAL</th>';
    echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">CÓDIGO</th>';
    echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">PRODUTO</th>';
    echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: center; width: 80px;">QUANT</th>';
    echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: right; width: 100px;">VLR_UNIT</th>';
    echo '<th style="padding: 8px; border: 1px solid #dee2e6; text-align: right; width: 100px;">VLR_TOTAL</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($itens as $item) {
        $valor_unit_formatado = converterValorBrasileiro($item['VLR_UNIT']);
        $valor_total_formatado = converterValorBrasileiro($item['valor_item']);
        
        echo '<tr style="border-bottom: 1px solid #dee2e6;">';
        echo '<td style="padding: 8px; border: 1px solid #dee2e6; font-weight: 600; color: #1a237e;">' . htmlspecialchars($item['NTA_FISCAL'] ?? '') . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #dee2e6; font-weight: 600; color: #007bff;">' . htmlspecialchars($item['COD_PROD'] ?? '') . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($item['DES_PROD'] ?? '') . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #dee2e6; text-align: center;">' . htmlspecialchars($item['QUANT'] ?? '') . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #dee2e6; text-align: right;">R$ ' . number_format($valor_unit_formatado, 2, ',', '.') . '</td>';
        echo '<td style="padding: 8px; border: 1px solid #dee2e6; text-align: right; font-weight: 600; color: #28a745;">R$ ' . number_format($valor_total_formatado, 2, ',', '.') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo '<div style="text-align: center; padding: 20px; color: #666;"><i class="fas fa-info-circle"></i> Nenhum item encontrado para este pedido.</div>';
}
?>
