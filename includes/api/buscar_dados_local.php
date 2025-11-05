<?php
/**
 * API para buscar dados do BANCO LOCAL (sincronizados do Bling)
 * MUITO MAIS RÁPIDO que buscar direto da API
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexao.php';

// Criar conexão MySQLi se não existir
if (!isset($conn)) {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['erro' => 'Erro de conexão com banco de dados']);
        exit;
    }
    $conn->set_charset("utf8mb4");
}

// Verificar autenticação
if (!isset($_SESSION['usuario']) || !in_array(strtolower($_SESSION['usuario']['perfil']), ['admin', 'diretor', 'ecommerce'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

try {
    // Obter filtros
    $dataInicial = $_GET['data_inicial'] ?? date('Y-m-01');
    $dataFinal = $_GET['data_final'] ?? date('Y-m-d');
    $filtroProduto = $_GET['produto'] ?? null;
    
    // Verificar se tem dados sincronizados
    $checkSync = $conn->query("SELECT COUNT(*) as total FROM bling_pedidos WHERE data BETWEEN '$dataInicial' AND '$dataFinal'");
    $totalPedidos = $checkSync->fetch_assoc()['total'];
    
    if ($totalPedidos == 0) {
        echo json_encode([
            'erro' => 'Nenhum dado sincronizado',
            'mensagem' => 'Execute a sincronização primeiro',
            'requer_sincronizacao' => true
        ]);
        exit;
    }
    
    // Buscar última sincronização
    $ultimaSync = $conn->query("
        SELECT iniciado_em, finalizado_em, total_pedidos_processados 
        FROM bling_sincronizacao_log 
        WHERE status = 'concluido' 
        ORDER BY id DESC 
        LIMIT 1
    ")->fetch_assoc();
    
    // Query base
    $whereClause = "WHERE p.data BETWEEN ? AND ? AND p.situacao_valor NOT IN (9, 12)";
    $params = [$dataInicial, $dataFinal];
    $types = "ss";
    
    if ($filtroProduto) {
        $whereClause .= " AND i.descricao LIKE ?";
        $params[] = "%$filtroProduto%";
        $types .= "s";
    }
    
    // Buscar vendas
    $stmt = $conn->prepare("
        SELECT 
            p.id as pedido_id,
            p.data,
            p.total as pedido_total,
            p.total_produtos,
            p.frete,
            p.outras_despesas,
            p.custo_frete,
            i.codigo,
            i.descricao,
            i.quantidade,
            i.valor_unitario,
            i.valor_total as valor_produto,
            i.frete_proporcional,
            i.despesas_proporcionais,
            i.valor_total_com_extras as valor_total
        FROM bling_pedidos p
        INNER JOIN bling_itens i ON p.id = i.pedido_id
        $whereClause
        ORDER BY p.data DESC, p.id DESC
    ");
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Processar dados
    $totalVendas = 0;
    $valorProdutos = 0;
    $totalFretes = 0;
    $totalDespesas = 0;
    $quantidadeVendas = 0;
    
    $produtosAgrupados = [];
    $vendasDetalhadas = [];
    $pedidosComFrete = 0;
    $pedidosSemFrete = 0;
    $maiorFrete = 0;
    $produtoMaiorFrete = '-';
    $faturamentoPorPeriodo = [];
    $pedidosContados = [];
    
    while ($row = $result->fetch_assoc()) {
        $data = $row['data'];
        $descricao = $row['descricao'];
        $valorProduto = floatval($row['valor_produto']);
        $frete = floatval($row['frete_proporcional']);
        $despesas = floatval($row['despesas_proporcionais']);
        $valorTotal = floatval($row['valor_total']);
        $quantidade = floatval($row['quantidade']);
        $pedidoId = $row['pedido_id'];
        
        // Contabilizar por pedido único
        if (!isset($pedidosContados[$pedidoId])) {
            $pedidosContados[$pedidoId] = true;
            $quantidadeVendas++;
            
            $fretePedido = floatval($row['custo_frete'] > 0 ? $row['custo_frete'] : $row['frete']);
            if ($fretePedido > 0) {
                $pedidosComFrete++;
                if ($fretePedido > $maiorFrete) {
                    $maiorFrete = $fretePedido;
                }
            } else {
                $pedidosSemFrete++;
            }
            
            // Faturamento por período
            $mesAno = date('Y-m', strtotime($data));
            if (!isset($faturamentoPorPeriodo[$mesAno])) {
                $faturamentoPorPeriodo[$mesAno] = 0;
            }
            $faturamentoPorPeriodo[$mesAno] += floatval($row['pedido_total']);
        }
        
        // Totais
        $valorProdutos += $valorProduto;
        $totalFretes += $frete;
        $totalDespesas += $despesas;
        $totalVendas += $valorTotal;
        
        // Agrupar por produto
        if (!isset($produtosAgrupados[$descricao])) {
            $produtosAgrupados[$descricao] = [
                'descricao' => $descricao,
                'codigo' => $row['codigo'],
                'quantidade_vendas' => 0,
                'valor_produtos' => 0,
                'frete_total' => 0,
                'outras_despesas' => 0,
                'valor_total' => 0
            ];
        }
        
        $produtosAgrupados[$descricao]['quantidade_vendas']++;
        $produtosAgrupados[$descricao]['valor_produtos'] += $valorProduto;
        $produtosAgrupados[$descricao]['frete_total'] += $frete;
        $produtosAgrupados[$descricao]['outras_despesas'] += $despesas;
        $produtosAgrupados[$descricao]['valor_total'] += $valorTotal;
        
        // Vendas detalhadas
        $vendasDetalhadas[] = [
            'data' => $data,
            'produto' => $descricao,
            'valor_produto' => $valorProduto,
            'frete' => $frete,
            'outras_despesas' => $despesas,
            'valor_total' => $valorTotal
        ];
    }
    
    // Calcular percentuais
    $percentualFrete = $totalVendas > 0 ? ($totalFretes / $totalVendas) * 100 : 0;
    $percentualDespesas = $totalVendas > 0 ? ($totalDespesas / $totalVendas) * 100 : 0;
    $totalPedidosUnicos = $pedidosComFrete + $pedidosSemFrete;
    $percentualPedidosComFrete = $totalPedidosUnicos > 0 ? ($pedidosComFrete / $totalPedidosUnicos) * 100 : 0;
    $percentualPedidosSemFrete = $totalPedidosUnicos > 0 ? ($pedidosSemFrete / $totalPedidosUnicos) * 100 : 0;
    $ticketMedioFrete = $pedidosComFrete > 0 ? ($totalFretes / $pedidosComFrete) : 0;
    
    // Ordenar produtos por valor total
    usort($produtosAgrupados, function($a, $b) {
        return $b['valor_total'] <=> $a['valor_total'];
    });
    
    // Adicionar percentual de faturamento
    foreach ($produtosAgrupados as &$produto) {
        $produto['percentual_faturamento'] = $totalVendas > 0 ? ($produto['valor_total'] / $totalVendas) * 100 : 0;
    }
    
    // Top 10 produtos
    $top10Produtos = array_slice($produtosAgrupados, 0, 10);
    
    // Resposta
    echo json_encode([
        'resumo' => [
            'total_vendas' => $totalVendas,
            'valor_produtos' => $valorProdutos,
            'total_fretes' => $totalFretes,
            'total_despesas' => $totalDespesas,
            'quantidade_vendas' => $quantidadeVendas,
            'percentual_frete' => $percentualFrete,
            'percentual_despesas' => $percentualDespesas
        ],
        'analise_fretes' => [
            'pedidos_com_frete' => $pedidosComFrete,
            'pedidos_sem_frete' => $pedidosSemFrete,
            'percentual_pedidos_com_frete' => $percentualPedidosComFrete,
            'percentual_pedidos_sem_frete' => $percentualPedidosSemFrete,
            'ticket_medio_frete' => $ticketMedioFrete,
            'maior_frete' => $maiorFrete,
            'produto_maior_frete' => $produtoMaiorFrete
        ],
        'produtos' => $produtosAgrupados,
        'top10_produtos' => $top10Produtos,
        'vendas_detalhadas' => $vendasDetalhadas,
        'faturamento_periodo' => $faturamentoPorPeriodo,
        'info_sincronizacao' => [
            'ultima_sincronizacao' => $ultimaSync['finalizado_em'] ?? null,
            'pedidos_sincronizados' => $ultimaSync['total_pedidos_processados'] ?? 0,
            'fonte' => 'banco_local'
        ],
        'data_atualizacao' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro ao buscar dados',
        'mensagem' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

