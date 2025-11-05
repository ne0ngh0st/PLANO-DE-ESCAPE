<?php
/**
 * API para buscar dados do Bling e processar para a página de gestão e-commerce
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/BlingAPI.php';

// Verificar autenticação
if (!isset($_SESSION['usuario']) || !in_array(strtolower($_SESSION['usuario']['perfil']), ['admin', 'diretor', 'ecommerce'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado']);
    exit;
}

try {
    // Instanciar API do Bling
    $bling = new BlingAPI();
    
    // Verificar se está autenticado com o Bling
    if (!$bling->isAuthenticated()) {
        http_response_code(403);
        echo json_encode([
            'erro' => 'Integração não configurada',
            'mensagem' => 'É necessário autorizar o acesso ao Bling',
            'requer_autorizacao' => true
        ]);
        exit;
    }
    
    // Obter filtros
    $dataInicial = $_GET['data_inicial'] ?? date('Y-m-01'); // Primeiro dia do mês atual
    $dataFinal = $_GET['data_final'] ?? date('Y-m-d'); // Hoje
    $filtroProduto = $_GET['produto'] ?? null;
    $limite = intval($_GET['limite'] ?? 50); // Limite de pedidos a buscar detalhes (padrão 50)
    $usarCache = isset($_GET['sem_cache']) ? false : true;
    
    // Cache
    $cacheKey = md5($dataInicial . $dataFinal . $filtroProduto . $limite);
    $cacheFile = __DIR__ . '/../config/cache/dados_ecommerce_' . $cacheKey . '.json';
    $cacheDuration = 1800; // 30 minutos
    
    // Verificar se tem cache válido
    if ($usarCache && file_exists($cacheFile)) {
        $cacheAge = time() - filemtime($cacheFile);
        if ($cacheAge < $cacheDuration) {
            $dadosCache = json_decode(file_get_contents($cacheFile), true);
            if ($dadosCache) {
                $dadosCache['_cache'] = [
                    'usado' => true,
                    'idade_segundos' => $cacheAge,
                    'expira_em' => $cacheDuration - $cacheAge
                ];
                echo json_encode($dadosCache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
        }
    }
    
    // Buscar pedidos do Bling (resumo)
    $pedidosResumo = $bling->getAllPedidos($dataInicial, $dataFinal);
    
    // Buscar detalhes de cada pedido (com itens)
    $pedidosCompletos = [];
    $totalPedidos = count($pedidosResumo);
    $contador = 0;
    
    foreach ($pedidosResumo as $pedidoResumo) {
        $contador++;
        
        // Limitar quantidade de pedidos a buscar detalhes (evitar timeout)
        if ($contador > $limite) {
            break;
        }
        
        try {
            $pedidoDetalhado = $bling->getPedido($pedidoResumo['id']);
            if (isset($pedidoDetalhado['data'])) {
                $pedidosCompletos[] = $pedidoDetalhado['data'];
            }
            
            // Delay para não sobrecarregar a API
            usleep(200000); // 0.2 segundos
            
        } catch (Exception $e) {
            // Se falhar, usa o resumo mesmo
            $pedidosCompletos[] = $pedidoResumo;
        }
    }
    
    // Processar dados
    $resultado = processarDadosPedidos($pedidosCompletos, $filtroProduto);
    
    // Adicionar informações de processamento
    $resultado['info_processamento'] = [
        'total_pedidos_encontrados' => $totalPedidos,
        'pedidos_processados' => count($pedidosCompletos),
        'limite_aplicado' => $limite,
        'cache_habilitado' => $usarCache
    ];
    
    // Salvar cache
    if ($usarCache) {
        file_put_contents($cacheFile, json_encode($resultado, JSON_UNESCAPED_UNICODE));
    }
    
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Erro ao buscar dados',
        'mensagem' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Processa os dados dos pedidos do Bling
 */
function processarDadosPedidos($pedidos, $filtroProduto = null) {
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
    
    foreach ($pedidos as $pedido) {
        // Verificar se o pedido tem status válido (não cancelado)
        $situacao = $pedido['situacao']['valor'] ?? $pedido['situacao']['id'] ?? null;
        if (in_array($situacao, [9, 12])) { // 9 = Cancelado, 12 = Cancelado
            continue;
        }
        
        $dataPedido = $pedido['data'] ?? null;
        $itensPedido = $pedido['itens'] ?? [];
        
        // Se não tem itens, pular este pedido
        if (empty($itensPedido)) {
            continue;
        }
        
        // Valores do pedido
        $valorTotalPedido = floatval($pedido['total'] ?? 0);
        $valorTotalProdutos = floatval($pedido['totalProdutos'] ?? 0);
        $valorFrete = floatval($pedido['transporte']['frete'] ?? 0);
        $valorOutrasDespesas = floatval($pedido['outrasDespesas'] ?? 0);
        
        // Usar custo de frete das taxas se disponível
        if (isset($pedido['taxas']['custoFrete']) && floatval($pedido['taxas']['custoFrete']) > 0) {
            $valorFrete = floatval($pedido['taxas']['custoFrete']);
        }
        
        // Contabilizar frete
        if ($valorFrete > 0) {
            $pedidosComFrete++;
            if ($valorFrete > $maiorFrete) {
                $maiorFrete = $valorFrete;
            }
        } else {
            $pedidosSemFrete++;
        }
        
        // Processar itens do pedido
        foreach ($itensPedido as $item) {
            $descricaoProduto = $item['descricao'] ?? 'Produto sem nome';
            $codigoProduto = $item['codigo'] ?? '';
            $quantidade = floatval($item['quantidade'] ?? 0);
            $valorUnitario = floatval($item['valor'] ?? 0);
            $valorItem = $quantidade * $valorUnitario;
            
            // Aplicar filtro de produto se fornecido
            if ($filtroProduto && stripos($descricaoProduto, $filtroProduto) === false) {
                continue;
            }
            
            // Calcular proporção de frete e despesas para este item
            // (distribui proporcionalmente ao valor do item em relação ao total dos produtos)
            $valorBaseProporcao = $valorTotalProdutos > 0 ? $valorTotalProdutos : $valorTotalPedido;
            $proporcao = $valorBaseProporcao > 0 ? ($valorItem / $valorBaseProporcao) : 0;
            $freteItem = $valorFrete * $proporcao;
            $despesasItem = $valorOutrasDespesas * $proporcao;
            $totalItem = $valorItem + $freteItem + $despesasItem;
            
            // Agrupar por produto
            if (!isset($produtosAgrupados[$descricaoProduto])) {
                $produtosAgrupados[$descricaoProduto] = [
                    'descricao' => $descricaoProduto,
                    'codigo' => $codigoProduto,
                    'quantidade_vendas' => 0,
                    'valor_produtos' => 0,
                    'frete_total' => 0,
                    'outras_despesas' => 0,
                    'valor_total' => 0
                ];
            }
            
            $produtosAgrupados[$descricaoProduto]['quantidade_vendas']++;
            $produtosAgrupados[$descricaoProduto]['valor_produtos'] += $valorItem;
            $produtosAgrupados[$descricaoProduto]['frete_total'] += $freteItem;
            $produtosAgrupados[$descricaoProduto]['outras_despesas'] += $despesasItem;
            $produtosAgrupados[$descricaoProduto]['valor_total'] += $totalItem;
            
            // Vendas detalhadas
            $vendasDetalhadas[] = [
                'data' => $dataPedido,
                'produto' => $descricaoProduto,
                'valor_produto' => $valorItem,
                'frete' => $freteItem,
                'outras_despesas' => $despesasItem,
                'valor_total' => $totalItem
            ];
            
            // Totais gerais
            $valorProdutos += $valorItem;
            $totalFretes += $freteItem;
            $totalDespesas += $despesasItem;
            $totalVendas += $totalItem;
        }
        
        // Faturamento por período (por mês)
        if ($dataPedido) {
            $mesAno = date('Y-m', strtotime($dataPedido));
            if (!isset($faturamentoPorPeriodo[$mesAno])) {
                $faturamentoPorPeriodo[$mesAno] = 0;
            }
            $faturamentoPorPeriodo[$mesAno] += $valorTotalPedido;
        }
        
        $quantidadeVendas++;
    }
    
    // Calcular percentuais
    $percentualFrete = $totalVendas > 0 ? ($totalFretes / $totalVendas) * 100 : 0;
    $percentualDespesas = $totalVendas > 0 ? ($totalDespesas / $totalVendas) * 100 : 0;
    $totalPedidos = $pedidosComFrete + $pedidosSemFrete;
    $percentualPedidosComFrete = $totalPedidos > 0 ? ($pedidosComFrete / $totalPedidos) * 100 : 0;
    $percentualPedidosSemFrete = $totalPedidos > 0 ? ($pedidosSemFrete / $totalPedidos) * 100 : 0;
    $ticketMedioFrete = $pedidosComFrete > 0 ? ($totalFretes / $pedidosComFrete) : 0;
    
    // Ordenar produtos por valor total (decrescente)
    usort($produtosAgrupados, function($a, $b) {
        return $b['valor_total'] <=> $a['valor_total'];
    });
    
    // Adicionar percentual de faturamento para cada produto
    foreach ($produtosAgrupados as &$produto) {
        $produto['percentual_faturamento'] = $totalVendas > 0 ? ($produto['valor_total'] / $totalVendas) * 100 : 0;
    }
    
    // Top 10 produtos
    $top10Produtos = array_slice($produtosAgrupados, 0, 10);
    
    // Ordenar faturamento por período
    ksort($faturamentoPorPeriodo);
    
    // Ordenar vendas detalhadas por data (mais recente primeiro)
    usort($vendasDetalhadas, function($a, $b) {
        return strtotime($b['data']) <=> strtotime($a['data']);
    });
    
    return [
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
        'data_atualizacao' => date('Y-m-d H:i:s')
    ];
}

