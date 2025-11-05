<?php
session_start();
require_once '../config/conexao.php';

// Verificar autenticação
if (!isset($_SESSION['usuario']) || !in_array(strtolower($_SESSION['usuario']['perfil']), ['admin', 'diretor', 'ecommerce'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json');

try {
    // Obter parâmetros
    $dataInicial = $_GET['dataInicial'] ?? null;
    $dataFinal = $_GET['dataFinal'] ?? null;
    $produto = $_GET['produto'] ?? null;
    
    // Construir query base
    $sql = "SELECT * FROM ECOMERCE WHERE 1=1";
    $params = [];
    
    // Aplicar filtros
    if ($dataInicial && $dataFinal) {
        // Converter datas do formato brasileiro para comparação
        $sql .= " AND STR_TO_DATE(DATA, '%d/%m/%Y') BETWEEN STR_TO_DATE(:dataInicial, '%Y-%m-%d') AND STR_TO_DATE(:dataFinal, '%Y-%m-%d')";
        $params['dataInicial'] = $dataInicial;
        $params['dataFinal'] = $dataFinal;
    }
    
    if ($produto) {
        $sql .= " AND DESCRICAO LIKE :produto";
        $params['produto'] = '%' . $produto . '%';
    }
    
    $sql .= " ORDER BY STR_TO_DATE(DATA, '%d/%m/%Y') DESC";
    
    // Executar query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar dados
    $totalVendas = 0;
    $totalValorProdutos = 0;
    $totalFretes = 0;
    $totalDespesas = 0;
    $totalValorTotal = 0;
    
    $produtosAgrupados = [];
    $vendasPorData = [];
    
    foreach ($vendas as &$venda) {
        // Converter valores de string para float
        $valorProdutos = floatval(str_replace(',', '.', str_replace('.', '', $venda['VALOR_PRODUTOS'])));
        $freteProporcional = floatval(str_replace(',', '.', str_replace('.', '', $venda['FRETE_PROPORCIONAL'])));
        $outrasDespesas = floatval(str_replace(',', '.', str_replace('.', '', $venda['OUTRAS_DESPESAS'])));
        $valorTotal = floatval(str_replace(',', '.', str_replace('.', '', $venda['VALOR_TOTAL'])));
        
        // Adicionar valores formatados ao array
        $venda['valor_produtos_num'] = $valorProdutos;
        $venda['frete_num'] = $freteProporcional;
        $venda['despesas_num'] = $outrasDespesas;
        $venda['valor_total_num'] = $valorTotal;
        
        // Somar totais
        $totalVendas++;
        $totalValorProdutos += $valorProdutos;
        $totalFretes += $freteProporcional;
        $totalDespesas += $outrasDespesas;
        $totalValorTotal += $valorTotal;
        
        // Agrupar por produto
        $descricao = $venda['DESCRICAO'];
        if (!isset($produtosAgrupados[$descricao])) {
            $produtosAgrupados[$descricao] = [
                'descricao' => $descricao,
                'quantidade' => 0,
                'valor_produtos' => 0,
                'frete_total' => 0,
                'despesas_total' => 0,
                'valor_total' => 0
            ];
        }
        
        $produtosAgrupados[$descricao]['quantidade']++;
        $produtosAgrupados[$descricao]['valor_produtos'] += $valorProdutos;
        $produtosAgrupados[$descricao]['frete_total'] += $freteProporcional;
        $produtosAgrupados[$descricao]['despesas_total'] += $outrasDespesas;
        $produtosAgrupados[$descricao]['valor_total'] += $valorTotal;
        
        // Agrupar por data para gráfico
        $data = $venda['DATA'];
        if (!isset($vendasPorData[$data])) {
            $vendasPorData[$data] = [
                'data' => $data,
                'valor_produtos' => 0,
                'frete' => 0,
                'despesas' => 0,
                'valor_total' => 0,
                'quantidade' => 0
            ];
        }
        
        $vendasPorData[$data]['valor_produtos'] += $valorProdutos;
        $vendasPorData[$data]['frete'] += $freteProporcional;
        $vendasPorData[$data]['despesas'] += $outrasDespesas;
        $vendasPorData[$data]['valor_total'] += $valorTotal;
        $vendasPorData[$data]['quantidade']++;
    }
    
    // Calcular estatísticas de frete
    $pedidosComFrete = 0;
    $pedidosSemFrete = 0;
    $maiorFrete = 0;
    $produtoMaiorFrete = '';
    
    foreach ($vendas as $venda) {
        if ($venda['frete_num'] > 0) {
            $pedidosComFrete++;
            if ($venda['frete_num'] > $maiorFrete) {
                $maiorFrete = $venda['frete_num'];
                $produtoMaiorFrete = $venda['DESCRICAO'];
            }
        } else {
            $pedidosSemFrete++;
        }
    }
    
    $ticketMedioFrete = $pedidosComFrete > 0 ? $totalFretes / $pedidosComFrete : 0;
    
    // Ordenar produtos por valor total
    usort($produtosAgrupados, function($a, $b) {
        return $b['valor_total'] <=> $a['valor_total'];
    });
    
    // Ordenar vendas por data
    uksort($vendasPorData, function($a, $b) {
        $dateA = DateTime::createFromFormat('d/m/Y', $a);
        $dateB = DateTime::createFromFormat('d/m/Y', $b);
        return $dateA <=> $dateB;
    });
    
    // Preparar resposta
    $response = [
        'success' => true,
        'resumo' => [
            'total_vendas' => $totalVendas,
            'total_valor_produtos' => $totalValorProdutos,
            'total_fretes' => $totalFretes,
            'total_despesas' => $totalDespesas,
            'total_valor_total' => $totalValorTotal,
            'percentual_frete' => $totalValorTotal > 0 ? ($totalFretes / $totalValorTotal) * 100 : 0,
            'percentual_despesas' => $totalValorTotal > 0 ? ($totalDespesas / $totalValorTotal) * 100 : 0,
        ],
        'frete' => [
            'pedidos_com_frete' => $pedidosComFrete,
            'pedidos_sem_frete' => $pedidosSemFrete,
            'percentual_com_frete' => $totalVendas > 0 ? ($pedidosComFrete / $totalVendas) * 100 : 0,
            'percentual_sem_frete' => $totalVendas > 0 ? ($pedidosSemFrete / $totalVendas) * 100 : 0,
            'ticket_medio_frete' => $ticketMedioFrete,
            'maior_frete' => $maiorFrete,
            'produto_maior_frete' => $produtoMaiorFrete
        ],
        'produtos' => array_values($produtosAgrupados),
        'vendas_por_data' => array_values($vendasPorData),
        'vendas_detalhadas' => $vendas
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Erro em buscar_ecommerce.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados do e-commerce',
        'message' => $e->getMessage()
    ]);
}
?>


