<?php
// Iniciar sessão apenas se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Usar a TABLE 74 que contém todas as condições de pagamento
    
    // Primeiro, verificar se a TABLE 74 existe
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'TABLE 74'");
    $table_exists = $stmt_check->fetch();
    
    if (!$table_exists) {
        throw new Exception('Tabela TABLE 74 não encontrada');
    }
    
    // Descobrir a estrutura da TABLE 74
    $stmt_desc = $pdo->query("DESCRIBE `TABLE 74`");
    $colunas = $stmt_desc->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar coluna de descrição/nome da condição de pagamento
    // Ignorar colunas técnicas como CODIGO, ID, LISTAGEM, BROWSER
    $colunas_ignorar = ['CODIGO', 'ID', 'LISTAGEM', 'BROWSER', 'CREATED_AT', 'UPDATED_AT'];
    
    $coluna_descricao = null;
    $possiveis_nomes = [
        'DESCRICAO', 'NOME', 'CONDICAO', 'FORMA_PAGAMENTO', 'FORMA_PAG', 
        'COND_PAG', 'COND_PAGAMENTO', 'TIPO_PAGAMENTO', 'DESCRIPTION', 'NAME',
        'DESC_CONDICAO', 'DESC_PAGAMENTO'
    ];
    
    // Buscar por nomes exatos
    foreach ($possiveis_nomes as $possivel) {
        if (in_array($possivel, array_column($colunas, 'Field'))) {
            $coluna_descricao = $possivel;
            break;
        }
    }
    
    // Se não encontrou exato, buscar por similaridade (excluindo colunas técnicas)
    if (!$coluna_descricao) {
        foreach ($colunas as $coluna) {
            $nome = strtoupper($coluna['Field']);
            
            // Ignorar colunas técnicas
            $ignorar = false;
            foreach ($colunas_ignorar as $col_ignorar) {
                if ($nome === $col_ignorar || strpos($nome, $col_ignorar) !== false) {
                    $ignorar = true;
                    break;
                }
            }
            
            if ($ignorar) continue;
            
            // Buscar colunas que parecem conter descrições
            if (strpos($nome, 'DESC') !== false || 
                strpos($nome, 'NOME') !== false || 
                strpos($nome, 'COND') !== false ||
                strpos($nome, 'PAG') !== false) {
                $coluna_descricao = $coluna['Field'];
                break;
            }
        }
    }
    
    // Se ainda não encontrou, usar a primeira coluna que não seja técnica
    if (!$coluna_descricao) {
        foreach ($colunas as $coluna) {
            $nome = strtoupper($coluna['Field']);
            
            // Ignorar colunas técnicas
            $ignorar = false;
            foreach ($colunas_ignorar as $col_ignorar) {
                if ($nome === $col_ignorar || strpos($nome, $col_ignorar) !== false) {
                    $ignorar = true;
                    break;
                }
            }
            
            if (!$ignorar) {
                $coluna_descricao = $coluna['Field'];
                break;
            }
        }
    }
    
    if (!$coluna_descricao) {
        throw new Exception('Não foi possível identificar a coluna de descrição na TABLE 74');
    }
    
    // Buscar todas as condições de pagamento da TABLE 74
    // Usar COL 2 que contém as descrições legíveis
    $sql = "SELECT 
                `COL 2` as forma_pagamento
            FROM `TABLE 74`
            WHERE `COL 2` IS NOT NULL 
              AND `COL 2` != ''
              AND `COL 2` != 'Descricao'
              AND `COL 1` NOT IN ('Listagem do Browse', 'Codigo')
              AND `COL 1` IS NOT NULL
              AND `COL 1` != ''
            ORDER BY `COL 1` ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $formas_pagamento = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($formas_pagamento)) {
        throw new Exception('Nenhuma condição de pagamento encontrada na TABLE 74');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formas_pagamento,
        'fonte' => 'TABLE 74',
        'coluna_usada' => 'COL 2 (Descrição)',
        'total' => count($formas_pagamento)
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar formas de pagamento: " . $e->getMessage());
    // Retornar formas padrão em caso de erro
    echo json_encode([
        'success' => true,
        'data' => [
            ['forma_pagamento' => 'À Vista'],
            ['forma_pagamento' => '28 DDL'],
            ['forma_pagamento' => '30 dias'],
            ['forma_pagamento' => '30/60 dias'],
            ['forma_pagamento' => '30/60/90 dias']
        ],
        'fonte' => 'fallback',
        'error' => $e->getMessage()
    ]);
}

