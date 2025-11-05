<?php
/**
 * Script para adicionar campos de consumo na tabela CONTRATOS
 * Execute este arquivo UMA VEZ para adicionar as colunas necessárias
 */

require_once '../config/config.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Adicionar Campos - Contratos</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #1a237e; border-bottom: 3px solid #1a237e; padding-bottom: 10px; }
        .success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 10px 0; border-radius: 4px; color: #2e7d32; }
        .error { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 10px 0; border-radius: 4px; color: #c62828; }
        .warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 4px; color: #e65100; }
        .info { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 10px 0; border-radius: 4px; color: #1565c0; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 12px 24px; background: #1a237e; color: white; text-decoration: none; border-radius: 6px; margin-top: 15px; }
        .btn:hover { background: #283593; }
        ul { line-height: 2; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Adicionar Campos de Consumo na Tabela Contratos</h1>";

$campos_adicionados = [];
$campos_existentes = [];
$erros = [];

try {
    // 1. Verificar estrutura atual
    echo "<div class='info'><strong>📋 Verificando estrutura atual...</strong></div>";
    
    $sql_describe = "DESCRIBE contratos";
    $stmt = $pdo->prepare($sql_describe);
    $stmt->execute();
    $estrutura_atual = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $colunas_existentes = array_column($estrutura_atual, 'Field');
    
    // 2. Definir campos que precisam ser adicionados
    $campos_necessarios = [
        'valor_consumido' => [
            'sql' => "ALTER TABLE contratos ADD COLUMN valor_consumido DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Valor já consumido/utilizado do contrato'",
            'descricao' => 'Valor já consumido/utilizado'
        ],
        'saldo_contrato' => [
            'sql' => "ALTER TABLE contratos ADD COLUMN saldo_contrato DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Saldo disponível no contrato'",
            'descricao' => 'Saldo disponível'
        ],
        'percentual_consumo' => [
            'sql' => "ALTER TABLE contratos ADD COLUMN percentual_consumo DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentual consumido do valor global'",
            'descricao' => 'Percentual de consumo (%)'
        ]
    ];
    
    // 3. Adicionar campos que não existem
    foreach ($campos_necessarios as $campo => $config) {
        if (in_array($campo, $colunas_existentes)) {
            $campos_existentes[] = $campo;
            echo "<div class='warning'>⚠️ Campo <strong>{$campo}</strong> já existe</div>";
        } else {
            try {
                $pdo->exec($config['sql']);
                $campos_adicionados[] = $campo;
                echo "<div class='success'>✅ Campo <strong>{$campo}</strong> adicionado com sucesso! ({$config['descricao']})</div>";
            } catch (PDOException $e) {
                $erros[] = "Erro ao adicionar {$campo}: " . $e->getMessage();
                echo "<div class='error'>❌ Erro ao adicionar <strong>{$campo}</strong>: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    // 4. Criar/Atualizar índices
    echo "<div class='info'><strong>📊 Criando índices...</strong></div>";
    
    try {
        $pdo->exec("CREATE INDEX idx_valor_consumido ON contratos(valor_consumido)");
        echo "<div class='success'>✅ Índice criado para valor_consumido</div>";
    } catch (PDOException $e) {
        echo "<div class='warning'>⚠️ Índice já existe ou erro: " . $e->getMessage() . "</div>";
    }
    
    // 5. Calcular valores para registros existentes (se saldo e percentual foram adicionados)
    if (in_array('saldo_contrato', $campos_adicionados) || in_array('percentual_consumo', $campos_adicionados)) {
        echo "<div class='info'><strong>🔄 Calculando valores iniciais...</strong></div>";
        
        $sql_update = "UPDATE contratos 
                      SET saldo_contrato = valor_global - valor_consumido,
                          percentual_consumo = CASE 
                              WHEN valor_global > 0 THEN (valor_consumido / valor_global * 100)
                              ELSE 0 
                          END
                      WHERE valor_global IS NOT NULL";
        
        $pdo->exec($sql_update);
        echo "<div class='success'>✅ Valores calculados e atualizados!</div>";
    }
    
    // 6. Resumo
    echo "<hr style='margin: 30px 0;'>";
    echo "<h2>📊 Resumo da Operação</h2>";
    
    if (count($campos_adicionados) > 0) {
        echo "<div class='success'>";
        echo "<strong>✅ Campos Adicionados:</strong><ul>";
        foreach ($campos_adicionados as $campo) {
            echo "<li>{$campo}</li>";
        }
        echo "</ul></div>";
    }
    
    if (count($campos_existentes) > 0) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Campos que já existiam:</strong><ul>";
        foreach ($campos_existentes as $campo) {
            echo "<li>{$campo}</li>";
        }
        echo "</ul></div>";
    }
    
    if (count($erros) > 0) {
        echo "<div class='error'>";
        echo "<strong>❌ Erros encontrados:</strong><ul>";
        foreach ($erros as $erro) {
            echo "<li>{$erro}</li>";
        }
        echo "</ul></div>";
    }
    
    // 7. Próximos passos
    echo "<hr style='margin: 30px 0;'>";
    echo "<h2>🚀 Próximos Passos</h2>";
    echo "<div class='info'>";
    echo "<p><strong>1. Importar dados do Excel</strong></p>";
    echo "<p>Agora você pode atualizar os valores de consumo no banco com os dados da planilha.</p>";
    echo "<p><strong>2. Testar a página de contratos</strong></p>";
    echo "<p>Acesse a página para ver os valores corretos de consumo.</p>";
    echo "</div>";
    
    echo "<div style='margin-top: 30px; display: flex; gap: 10px;'>";
    echo "<a href='../../pages/contratos.php' class='btn'>Ver Página de Contratos</a>";
    echo "<a href='../../ver_estrutura_contratos.php' class='btn'>Ver Estrutura Atualizada</a>";
    echo "<a href='../../debug_contratos.php' class='btn'>Executar Debug</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Erro Fatal</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
?>

