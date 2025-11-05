<?php
/**
 * Script para criar a tabela CONTRATOS
 * Execute este arquivo uma vez para criar a estrutura da tabela
 */

require_once '../config/config.php';

try {
    // Verificar se a tabela já existe
    $sql_check = "SHOW TABLES LIKE 'contratos'";
    $stmt = $pdo->prepare($sql_check);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ A tabela 'contratos' já existe.<br>";
    } else {
        // Criar tabela
        $sql_create = "CREATE TABLE IF NOT EXISTS `contratos` (
            `ID` INT(11) NOT NULL AUTO_INCREMENT,
            `GERENCIADOR` VARCHAR(100) NOT NULL COMMENT 'Nome do gerenciador do contrato',
            `DESCRICAO` TEXT DEFAULT NULL COMMENT 'Descrição do contrato',
            `VALOR_CONTRATADO` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor total contratado',
            `VALOR_CONSUMIDO` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor já consumido',
            `DATA_INICIO` DATE DEFAULT NULL COMMENT 'Data de início do contrato',
            `DATA_FIM` DATE DEFAULT NULL COMMENT 'Data de término do contrato',
            `ATIVO` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo, 0 = Inativo',
            `CREATED_AT` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `UPDATED_AT` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`ID`),
            INDEX `idx_gerenciador` (`GERENCIADOR`),
            INDEX `idx_ativo` (`ATIVO`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contratos e consumo por gerenciador';";
        
        $pdo->exec($sql_create);
        echo "✅ Tabela 'contratos' criada com sucesso!<br>";
    }
    
    // Verificar se há dados
    $sql_count = "SELECT COUNT(*) as total FROM contratos";
    $stmt = $pdo->prepare($sql_count);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<br>📊 Total de contratos cadastrados: " . $result['total'] . "<br>";
    
    // Se não houver dados, inserir exemplos (opcional - comentado por padrão)
    if ($result['total'] == 0) {
        echo "<br>💡 A tabela está vazia. Descomente o código abaixo para inserir dados de exemplo.<br>";
        
        /*
        // DESCOMENTAR PARA INSERIR DADOS DE EXEMPLO
        $sql_insert = "INSERT INTO contratos 
            (GERENCIADOR, DESCRICAO, VALOR_CONTRATADO, VALOR_CONSUMIDO, DATA_INICIO, DATA_FIM, ATIVO) 
        VALUES 
            ('João Silva', 'Contrato de Manutenção Anual', 150000.00, 45000.00, '2025-01-01', '2025-12-31', 1),
            ('João Silva', 'Contrato de Licenças de Software', 80000.00, 25000.00, '2025-01-01', '2025-12-31', 1),
            ('Maria Santos', 'Contrato de Consultoria', 200000.00, 180000.00, '2025-01-01', '2025-12-31', 1),
            ('Pedro Costa', 'Contrato de Suporte Técnico', 120000.00, 30000.00, '2025-01-01', '2025-12-31', 1),
            ('Ana Paula', 'Contrato de Desenvolvimento', 300000.00, 95000.00, '2025-01-01', '2025-12-31', 1),
            ('Carlos Mendes', 'Contrato de Treinamento', 90000.00, 60000.00, '2025-01-01', '2025-12-31', 1)
        ";
        
        $pdo->exec($sql_insert);
        echo "✅ Dados de exemplo inseridos com sucesso!<br>";
        */
    }
    
    echo "<br>✅ Setup concluído!<br>";
    echo "<br><a href='../../pages/contratos.php' style='display: inline-block; padding: 10px 20px; background: #1a237e; color: white; text-decoration: none; border-radius: 5px;'>Ir para Contratos</a>";
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    echo "<br>Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Tabela Contratos</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #1a237e;
            border-bottom: 3px solid #1a237e;
            padding-bottom: 10px;
        }
        
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Setup da Tabela Contratos</h1>
        
        <div style="margin-top: 30px; padding: 20px; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 5px;">
            <h3 style="margin-top: 0; color: #2e7d32;">📋 Estrutura da Tabela</h3>
            <ul style="line-height: 1.8;">
                <li><strong>ID:</strong> Identificador único (auto-increment)</li>
                <li><strong>GERENCIADOR:</strong> Nome do gerenciador responsável</li>
                <li><strong>DESCRICAO:</strong> Descrição detalhada do contrato</li>
                <li><strong>VALOR_CONTRATADO:</strong> Valor total contratado</li>
                <li><strong>VALOR_CONSUMIDO:</strong> Valor já consumido</li>
                <li><strong>DATA_INICIO:</strong> Data de início do contrato</li>
                <li><strong>DATA_FIM:</strong> Data de término do contrato</li>
                <li><strong>ATIVO:</strong> Status (1 = Ativo, 0 = Inativo)</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px; padding: 20px; background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 5px;">
            <h3 style="margin-top: 0; color: #e65100;">⚠️ Importante</h3>
            <p>Para inserir dados de exemplo, edite este arquivo e descomente o bloco de INSERT.</p>
            <p>Ou insira dados manualmente através do phpMyAdmin ou da interface de administração.</p>
        </div>
    </div>
</body>
</html>

