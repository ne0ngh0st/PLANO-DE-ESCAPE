<?php
/**
 * Script para importar/atualizar valores de consumo dos contratos
 * Baseado na planilha Excel fornecida
 */

require_once '../config/config.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Importar Valores de Consumo</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #1a237e; border-bottom: 3px solid #1a237e; padding-bottom: 10px; }
        .success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 10px 0; border-radius: 4px; color: #2e7d32; }
        .error { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 10px 0; border-radius: 4px; color: #c62828; }
        .info { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 10px 0; border-radius: 4px; color: #1565c0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1a237e; color: white; }
        .btn { display: inline-block; padding: 12px 24px; background: #1a237e; color: white; text-decoration: none; border-radius: 6px; margin: 5px; border: none; cursor: pointer; }
        .btn:hover { background: #283593; }
        .btn-success { background: #4caf50; }
        .btn-success:hover { background: #45a049; }
        textarea { width: 100%; min-height: 200px; padding: 10px; font-family: monospace; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>📥 Importar Valores de Consumo dos Contratos</h1>";

// Verificar se os campos existem
try {
    $sql_check = "SHOW COLUMNS FROM contratos LIKE 'valor_consumido'";
    $stmt = $pdo->prepare($sql_check);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo "<div class='error'>";
        echo "<h2>⚠️ Campos Necessários Não Encontrados</h2>";
        echo "<p>Você precisa adicionar os campos primeiro!</p>";
        echo "<a href='adicionar_campos_contratos.php' class='btn'>Adicionar Campos Agora</a>";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='error'>Erro: " . $e->getMessage() . "</div>";
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar'])) {
    echo "<div class='info'><strong>🔄 Processando atualização...</strong></div>";
    
    // Dados de exemplo baseados na planilha
    $dados_atualizacao = [
        ['sigla' => 'ALESP', 'gerenciador' => 'TJSP', 'valor_consumido' => 923042.86],
        ['sigla' => 'TCESP', 'gerenciador' => 'TJSP', 'valor_consumido' => 335277.56],
        ['sigla' => 'TCJSP', 'gerenciador' => 'TJSP', 'valor_consumido' => 1460.70],
        ['sigla' => 'TJSP', 'gerenciador' => 'TJSP', 'valor_consumido' => 4159510.00],
        ['sigla' => 'BANESTES', 'gerenciador' => 'BANESTES', 'valor_consumido' => 23800000.00],
        ['sigla' => 'BANESTES -CBB', 'gerenciador' => 'BANESTES', 'valor_consumido' => 71960100.00],
    ];
    
    $atualizados = 0;
    $erros = 0;
    
    foreach ($dados_atualizacao as $item) {
        try {
            // Atualizar usando SIGLA E GERENCIADOR (ambos obrigatórios)
            $sql = "UPDATE contratos 
                    SET valor_consumido = ?,
                        saldo_contrato = valor_global - ?,
                        percentual_consumo = CASE 
                            WHEN valor_global > 0 THEN (? / valor_global * 100)
                            ELSE 0 
                        END
                    WHERE sigla = ? 
                    AND gerenciador = ?
                    AND status = 'Vigente'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $item['valor_consumido'],
                $item['valor_consumido'],
                $item['valor_consumido'],
                $item['sigla'],
                $item['gerenciador']
            ]);
            
            if ($stmt->rowCount() > 0) {
                $atualizados++;
                echo "<div class='success'>✅ Atualizado: {$item['sigla']} / {$item['gerenciador']}</div>";
            }
            
        } catch (PDOException $e) {
            $erros++;
            echo "<div class='error'>❌ Erro ao atualizar {$item['sigla']}: " . $e->getMessage() . "</div>";
        }
    }
    
    echo "<hr>";
    echo "<div class='info'>";
    echo "<h3>📊 Resumo</h3>";
    echo "<p><strong>Registros atualizados:</strong> $atualizados</p>";
    echo "<p><strong>Erros:</strong> $erros</p>";
    echo "</div>";
    
    echo "<a href='../../pages/contratos.php' class='btn btn-success'>Ver Página de Contratos Atualizada</a>";
    
} else {
    // Mostrar formulário
    echo "<div class='info'>";
    echo "<h2>📋 Instruções</h2>";
    echo "<p>Este script vai atualizar os valores de consumo baseado nos dados da planilha.</p>";
    echo "<p><strong>Os seguintes contratos serão atualizados:</strong></p>";
    echo "</div>";
    
    echo "<table>";
    echo "<tr><th>SIGLA</th><th>Gerenciador</th><th>Valor Consumido</th><th>Status</th></tr>";
    
    // Buscar contratos atuais
    try {
        $sql = "SELECT id, sigla, gerenciador, razao_social, valor_global, valor_consumido, status 
                FROM contratos 
                WHERE status = 'Vigente' AND gerenciador IS NOT NULL 
                ORDER BY gerenciador";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($contratos as $c) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($c['sigla'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($c['gerenciador']) . "</td>";
            echo "<td>R$ " . number_format($c['valor_global'] ?? 0, 2, ',', '.') . "</td>";
            echo "<td>" . ($c['valor_consumido'] > 0 ? 
                "<span style='color: #4caf50;'>✓ Com dados</span>" : 
                "<span style='color: #ff9800;'>⚠ Sem dados</span>") . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>Erro: " . $e->getMessage() . "</div>";
    }
    
    echo "<div class='info'>";
    echo "<h3>⚙️ Opções de Importação</h3>";
    echo "<p><strong>Opção 1:</strong> Atualizar com dados de exemplo (valores que você me mostrou)</p>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='atualizar' class='btn btn-success'>Atualizar com Dados de Exemplo</button>";
    echo "</form>";
    
    echo "<hr>";
    
    echo "<p><strong>Opção 2:</strong> Importar do Excel manualmente via SQL</p>";
    echo "<p>Se você tem muitos contratos, o ideal é exportar a planilha Excel para CSV e importar via phpMyAdmin ou SQL.</p>";
    
    echo "<h4>Script SQL de Exemplo:</h4>";
    echo "<textarea readonly>";
    echo "-- IMPORTANTE: Usar SIGLA + GERENCIADOR para identificar contrato único\n\n";
    echo "-- Exemplo TJSP (4 contratos diferentes):\n";
    echo "UPDATE contratos SET valor_consumido = 923042.86 WHERE sigla = 'ALESP' AND gerenciador = 'TJSP';\n";
    echo "UPDATE contratos SET valor_consumido = 335277.56 WHERE sigla = 'TCESP' AND gerenciador = 'TJSP';\n";
    echo "UPDATE contratos SET valor_consumido = 1460.70 WHERE sigla = 'TCJSP' AND gerenciador = 'TJSP';\n";
    echo "UPDATE contratos SET valor_consumido = 4159510.00 WHERE sigla = 'TJSP' AND gerenciador = 'TJSP';\n\n";
    echo "-- Exemplo BANESTES:\n";
    echo "UPDATE contratos SET valor_consumido = 23800000.00 WHERE sigla = 'BANESTES' AND gerenciador = 'BANESTES';\n";
    echo "UPDATE contratos SET valor_consumido = 71960100.00 WHERE sigla = 'BANESTES -CBB' AND gerenciador = 'BANESTES';\n\n";
    echo "-- Depois calcular saldo e percentual:\n";
    echo "UPDATE contratos \n";
    echo "SET saldo_contrato = valor_global - valor_consumido,\n";
    echo "    percentual_consumo = CASE WHEN valor_global > 0 THEN (valor_consumido / valor_global * 100) ELSE 0 END\n";
    echo "WHERE status = 'Vigente';";
    echo "</textarea>";
    
    echo "</div>";
}

echo "</div></body></html>";
?>

