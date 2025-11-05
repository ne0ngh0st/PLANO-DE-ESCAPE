<?php
/**
 * Script para importar dados do Excel (CSV) para atualizar valores de consumo
 * 
 * INSTRUÇÕES:
 * 1. Salve sua planilha Excel como CSV (separado por vírgula ou ponto-e-vírgula)
 * 2. Faça upload do arquivo CSV
 * 3. O script vai atualizar os valores automaticamente
 */

require_once '../config/config.php';

// Configuração
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '256M');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Importar Excel - Contratos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #1a237e; border-bottom: 3px solid #1a237e; padding-bottom: 10px; margin-bottom: 20px; }
        .success { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 15px; margin: 10px 0; border-radius: 4px; color: #2e7d32; }
        .error { background: #ffebee; border-left: 4px solid #f44336; padding: 15px; margin: 10px 0; border-radius: 4px; color: #c62828; }
        .warning { background: #fff3e0; border-left: 4px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 4px; color: #e65100; }
        .info { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 10px 0; border-radius: 4px; color: #1565c0; }
        .upload-area { border: 2px dashed #1a237e; border-radius: 8px; padding: 40px; text-align: center; margin: 20px 0; background: #f8f9fa; }
        .upload-area:hover { background: #e3f2fd; }
        input[type="file"] { display: none; }
        .btn { display: inline-block; padding: 12px 24px; background: #1a237e; color: white; border-radius: 6px; cursor: pointer; border: none; font-size: 14px; font-weight: 500; text-decoration: none; }
        .btn:hover { background: #283593; }
        .btn-success { background: #4caf50; }
        .btn-success:hover { background: #45a049; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
        th { background: #1a237e; color: white; position: sticky; top: 0; }
        .progress { width: 100%; height: 30px; background: #f0f0f0; border-radius: 15px; overflow: hidden; margin: 10px 0; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #4caf50, #66bb6a); transition: width 0.3s ease; text-align: center; line-height: 30px; color: white; font-weight: 600; }
        .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #2196f3; }
        .step h3 { color: #1a237e; margin-bottom: 10px; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📥 Importar Dados do Excel - Contratos</h1>

<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "<div class='info'><strong>📂 Processando arquivo:</strong> " . htmlspecialchars($file['name']) . "</div>";
        
        $csv_path = $file['tmp_name'];
        
        // Detectar delimitador
        $first_line = fgets(fopen($csv_path, 'r'));
        $delimiter = (strpos($first_line, ';') !== false) ? ';' : ',';
        
        echo "<div class='info'><strong>🔍 Delimitador detectado:</strong> " . ($delimiter === ';' ? 'Ponto-e-vírgula (;)' : 'Vírgula (,)') . "</div>";
        
        $handle = fopen($csv_path, 'r');
        $header = fgetcsv($handle, 0, $delimiter);
        
        // Mapear colunas (ajustar conforme necessário)
        $col_map = [];
        foreach ($header as $index => $col_name) {
            $col_lower = strtolower(trim($col_name));
            if (stripos($col_lower, 'sigla') !== false) $col_map['sigla'] = $index;
            if (stripos($col_lower, 'gerenciador') !== false) $col_map['gerenciador'] = $index;
            if (stripos($col_lower, 'cnpj') !== false) $col_map['cnpj'] = $index;
            if (stripos($col_lower, 'valor') !== false && stripos($col_lower, 'consumid') !== false) $col_map['valor_consumido'] = $index;
            if (stripos($col_lower, 'saldo') !== false) $col_map['saldo'] = $index;
            if (stripos($col_lower, 'consumo') !== false && stripos($col_lower, '%') !== false) $col_map['percentual'] = $index;
        }
        
        echo "<div class='info'><strong>📋 Colunas identificadas:</strong><br>";
        echo "Sigla: " . (isset($col_map['sigla']) ? "✓ Coluna " . $col_map['sigla'] : "❌ Não encontrada") . "<br>";
        echo "Gerenciador: " . (isset($col_map['gerenciador']) ? "✓ Coluna " . $col_map['gerenciador'] : "❌ Não encontrada") . "<br>";
        echo "Valor Consumido: " . (isset($col_map['valor_consumido']) ? "✓ Coluna " . $col_map['valor_consumido'] : "❌ Não encontrada") . "<br>";
        echo "</div>";
        
        $total_linhas = 0;
        $atualizados = 0;
        $erros = 0;
        $erros_detalhes = [];
        
        echo "<div class='progress'><div class='progress-bar' id='progressBar' style='width: 0%;'>0%</div></div>";
        echo "<div id='status'>Processando...</div>";
        echo "<table><thead><tr><th>Linha</th><th>Sigla</th><th>Gerenciador</th><th>Valor Consumido</th><th>Status</th></tr></thead><tbody id='resultados'>";
        
        ob_flush();
        flush();
        
        while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            $total_linhas++;
            
            $sigla = isset($col_map['sigla']) ? trim($data[$col_map['sigla']]) : '';
            $gerenciador = isset($col_map['gerenciador']) ? trim($data[$col_map['gerenciador']]) : '';
            $valor_consumido_str = isset($col_map['valor_consumido']) ? trim($data[$col_map['valor_consumido']]) : '0';
            
            // Limpar valor (remover R$, pontos de milhares, trocar vírgula por ponto)
            $valor_consumido = preg_replace('/[^0-9,.-]/', '', $valor_consumido_str);
            $valor_consumido = str_replace('.', '', $valor_consumido); // Remove separador de milhares
            $valor_consumido = str_replace(',', '.', $valor_consumido); // Vírgula vira ponto decimal
            $valor_consumido = floatval($valor_consumido);
            
            if (empty($sigla) && empty($gerenciador)) continue;
            
            try {
                // Atualizar usando SIGLA E GERENCIADOR para identificar único
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
                    $valor_consumido,
                    $valor_consumido,
                    $valor_consumido,
                    $sigla,
                    $gerenciador
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $atualizados++;
                    echo "<tr style='background: #e8f5e9;'><td>$total_linhas</td><td>$sigla</td><td>$gerenciador</td><td>R$ " . number_format($valor_consumido, 2, ',', '.') . "</td><td>✅ Atualizado</td></tr>";
                } else {
                    echo "<tr style='background: #fff3e0;'><td>$total_linhas</td><td>$sigla</td><td>$gerenciador</td><td>R$ " . number_format($valor_consumido, 2, ',', '.') . "</td><td>⚠️ Não encontrado</td></tr>";
                }
                
            } catch (PDOException $e) {
                $erros++;
                $erros_detalhes[] = "Linha $total_linhas: " . $e->getMessage();
                echo "<tr style='background: #ffebee;'><td>$total_linhas</td><td>$sigla</td><td>$gerenciador</td><td>-</td><td>❌ Erro</td></tr>";
            }
            
            // Atualizar progresso
            if ($total_linhas % 10 == 0) {
                echo "<script>document.getElementById('progressBar').style.width = '" . min(100, $total_linhas) . "%'; document.getElementById('progressBar').textContent = '$total_linhas linhas';</script>";
                ob_flush();
                flush();
            }
        }
        
        echo "</tbody></table>";
        
        fclose($handle);
        
        echo "<div class='success'>";
        echo "<h3>✅ Importação Concluída!</h3>";
        echo "<p><strong>Total de linhas processadas:</strong> $total_linhas</p>";
        echo "<p><strong>Registros atualizados:</strong> $atualizados</p>";
        echo "<p><strong>Erros:</strong> $erros</p>";
        echo "</div>";
        
        if (count($erros_detalhes) > 0) {
            echo "<div class='error'>";
            echo "<h3>❌ Erros Detalhados:</h3><ul>";
            foreach ($erros_detalhes as $erro) {
                echo "<li>$erro</li>";
            }
            echo "</ul></div>";
        }
        
        echo "<a href='../../pages/contratos.php' class='btn btn-success' style='margin-top: 20px;'>Ver Página de Contratos Atualizada</a>";
        
    } else {
        echo "<div class='error'>❌ Erro no upload: " . $file['error'] . "</div>";
    }
    
} else {
    // Mostrar formulário de upload
    ?>
    
    <div class="info">
        <h2>📋 Instruções</h2>
        <ol style="line-height: 2; margin-left: 20px;">
            <li>Abra sua planilha Excel</li>
            <li>Salve como <strong>CSV (separado por vírgulas)</strong> ou <strong>CSV UTF-8</strong></li>
            <li>Faça o upload do arquivo CSV aqui</li>
            <li>O script vai identificar automaticamente as colunas e atualizar os valores</li>
        </ol>
    </div>
    
    <div class="step">
        <h3>Passo 1: Salvar como CSV</h3>
        <p>No Excel: <strong>Arquivo → Salvar Como → CSV UTF-8 (delimitado por vírgulas)</strong></p>
    </div>
    
    <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <div class="upload-area" onclick="document.getElementById('fileInput').click()">
            <i style="font-size: 48px; color: #1a237e;">📄</i>
            <h3>Clique aqui para selecionar o arquivo CSV</h3>
            <p style="color: #666; margin-top: 10px;">Formatos aceitos: .csv</p>
            <p id="fileName" style="margin-top: 10px; font-weight: 600; color: #4caf50;"></p>
        </div>
        <input type="file" id="fileInput" name="csv_file" accept=".csv" required onchange="updateFileName(this)">
        <button type="submit" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 16px; margin-top: 20px;">
            📥 Importar Dados
        </button>
    </form>
    
    <script>
    function updateFileName(input) {
        if (input.files && input.files[0]) {
            document.getElementById('fileName').textContent = '✓ ' + input.files[0].name;
        }
    }
    </script>
    
    <hr style="margin: 30px 0;">
    
    <div class="warning">
        <h3>⚠️ Importante</h3>
        <p>Certifique-se de que sua planilha CSV contém as colunas:</p>
        <ul style="margin-left: 20px; line-height: 2;">
            <li><strong>SIGLA</strong> ou <strong>sigla</strong></li>
            <li><strong>GERENCIADOR</strong> ou <strong>gerenciador</strong></li>
            <li><strong>VALOR CONSUMIDO</strong> ou similar</li>
        </ul>
        <p style="margin-top: 10px;">O script vai tentar identificar as colunas automaticamente.</p>
    </div>
    
    <?php
}
?>

</div>
</body>
</html>

