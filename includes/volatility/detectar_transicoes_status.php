<?php
// Script para detectar e registrar transições de status dos clientes
require_once 'conexao.php';

function detectarTransicoesStatus($pdo, $data_analise = null) {
    if (!$data_analise) {
        $data_analise = date('Y-m-d');
    }
    
    try {
        // 1. Criar snapshot do status atual dos clientes
        criarSnapshotStatus($pdo, $data_analise);
        
        // 2. Encontrar o snapshot mais recente anterior à data de análise
        $data_anterior = buscarSnapshotAnterior($pdo, $data_analise);
        
        if (!$data_anterior) {
            echo "Nenhum snapshot anterior encontrado para comparar com $data_analise\n";
            return [];
        }
        
        echo "Comparando $data_anterior com $data_analise...\n";
        
        // 3. Detectar transições comparando com o snapshot anterior
        $transicoes = compararStatusClientes($pdo, $data_anterior, $data_analise);
        
        // 4. Registrar transições encontradas
        if (!empty($transicoes)) {
            registrarTransicoes($pdo, $transicoes, $data_analise);
            echo "✅ Transições detectadas para $data_analise: " . count($transicoes) . "\n";
        } else {
            echo "Nenhuma transição de status encontrada para $data_analise\n";
        }
        
        return $transicoes;
        
    } catch (Exception $e) {
        echo "Erro ao detectar transições: " . $e->getMessage() . "\n";
        error_log("Erro ao detectar transições de status: " . $e->getMessage());
        return [];
    }
}

function criarSnapshotStatus($pdo, $data_snapshot) {
    // Verificar se já existe snapshot para esta data
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM snapshot_status_clientes WHERE data_snapshot = ?");
    $stmt->execute([$data_snapshot]);
    if ($stmt->fetchColumn() > 0) {
        echo "Snapshot já existe para $data_snapshot\n";
        return;
    }
    
    // Buscar todos os clientes e seus status atuais (apenas um registro por CNPJ)
    $stmt = $pdo->prepare("
        SELECT 
            SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as cnpj_limpo,
            uf.CNPJ as cnpj_original,
            uf.DT_FAT as data_ultima_compra,
            uf.VLR_TOTAL as valor_ultima_compra,
            CASE 
                WHEN uf.DT_FAT IS NULL OR uf.DT_FAT = '' THEN 'inativo'
                WHEN DATEDIFF(CURDATE(), STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) <= 290 THEN 'ativo'
                WHEN DATEDIFF(CURDATE(), STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) > 290 
                     AND DATEDIFF(CURDATE(), STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) <= 365 THEN 'inativando'
                ELSE 'inativo'
            END as status_cliente,
            CASE 
                WHEN uf.DT_FAT IS NULL OR uf.DT_FAT = '' THEN NULL
                ELSE DATEDIFF(CURDATE(), STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y'))
            END as dias_sem_comprar
        FROM ultimo_faturamento uf
        WHERE uf.CNPJ IS NOT NULL AND uf.CNPJ != ''
        GROUP BY SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)
    ");
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inserir snapshot
    $stmt = $pdo->prepare("
        INSERT INTO snapshot_status_clientes 
        (data_snapshot, cnpj_cliente, status_cliente, data_ultima_compra, dias_sem_comprar, valor_ultima_compra)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($clientes as $cliente) {
        $data_compra = null;
        if ($cliente['data_ultima_compra'] && $cliente['data_ultima_compra'] != '') {
            try {
                $dateTime = DateTime::createFromFormat('d/m/Y', $cliente['data_ultima_compra']);
                if ($dateTime !== false) {
                    $data_compra = $dateTime->format('Y-m-d');
                } else {
                    $data_compra = null;
                }
            } catch (Exception $e) {
                // Se não conseguir converter a data, deixar como null
                $data_compra = null;
            }
        }
        
        $stmt->execute([
            $data_snapshot,
            $cliente['cnpj_limpo'],
            $cliente['status_cliente'],
            $data_compra,
            $cliente['dias_sem_comprar'],
            $cliente['valor_ultima_compra']
        ]);
    }
    
    echo "Snapshot criado para $data_snapshot com " . count($clientes) . " clientes\n";
}

function compararStatusClientes($pdo, $data_anterior, $data_atual) {
    $transicoes = [];
    
    // Buscar clientes que mudaram de status
    $stmt = $pdo->prepare("
        SELECT 
            a.cnpj_cliente,
            a.status_cliente as status_anterior,
            b.status_cliente as status_novo,
            b.data_ultima_compra,
            b.dias_sem_comprar,
            b.valor_ultima_compra
        FROM snapshot_status_clientes a
        INNER JOIN snapshot_status_clientes b ON a.cnpj_cliente = b.cnpj_cliente
        WHERE a.data_snapshot = ? 
        AND b.data_snapshot = ?
        AND a.status_cliente != b.status_cliente
        ORDER BY a.cnpj_cliente
    ");
    $stmt->execute([$data_anterior, $data_atual]);
    $mudancas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($mudancas as $mudanca) {
        $transicoes[] = [
            'cnpj_cliente' => $mudanca['cnpj_cliente'],
            'status_anterior' => $mudanca['status_anterior'],
            'status_novo' => $mudanca['status_novo'],
            'data_ultima_compra' => $mudanca['data_ultima_compra'],
            'dias_sem_comprar' => $mudanca['dias_sem_comprar'],
            'valor_ultima_compra' => $mudanca['valor_ultima_compra']
        ];
    }
    
    return $transicoes;
}

function buscarSnapshotAnterior($pdo, $data_analise) {
    // Buscar o snapshot mais recente anterior à data de análise
    $stmt = $pdo->prepare("
        SELECT data_snapshot 
        FROM snapshot_status_clientes 
        WHERE data_snapshot < ? 
        ORDER BY data_snapshot DESC 
        LIMIT 1
    ");
    $stmt->execute([$data_analise]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $resultado ? $resultado['data_snapshot'] : null;
}

function registrarTransicoes($pdo, $transicoes, $data_transicao) {
    // Limpar transições existentes para esta data (evitar duplicatas)
    $stmt_delete = $pdo->prepare("DELETE FROM transicoes_status_clientes WHERE data_transicao = ?");
    $stmt_delete->execute([$data_transicao]);
    
    $stmt = $pdo->prepare("
        INSERT INTO transicoes_status_clientes 
        (cnpj_cliente, status_anterior, status_novo, data_transicao, data_ultima_compra, dias_sem_comprar, valor_ultima_compra)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $inseridos = 0;
    $erros = 0;
    
    foreach ($transicoes as $transicao) {
        try {
            $stmt->execute([
                $transicao['cnpj_cliente'],
                $transicao['status_anterior'],
                $transicao['status_novo'],
                $data_transicao,
                $transicao['data_ultima_compra'],
                $transicao['dias_sem_comprar'],
                $transicao['valor_ultima_compra']
            ]);
            $inseridos++;
        } catch (Exception $e) {
            $erros++;
            error_log("Erro ao inserir transição {$transicao['cnpj_cliente']}: " . $e->getMessage());
        }
    }
    
    echo "📊 Transições registradas: $inseridos inseridas, $erros erros\n";
}

// Se executado diretamente, processar transições para hoje
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $data_processar = $_GET['data'] ?? date('Y-m-d');
    $transicoes = detectarTransicoesStatus($pdo, $data_processar);
    
    echo "\n=== RESUMO DAS TRANSIÇÕES ===\n";
    $resumo = [];
    foreach ($transicoes as $transicao) {
        $chave = $transicao['status_anterior'] . ' -> ' . $transicao['status_novo'];
        $resumo[$chave] = ($resumo[$chave] ?? 0) + 1;
    }
    
    foreach ($resumo as $tipo => $quantidade) {
        echo "- $tipo: $quantidade clientes\n";
    }
}
?>
