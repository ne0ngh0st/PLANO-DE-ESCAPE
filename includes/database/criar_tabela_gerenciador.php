<?php
/**
 * Script para criar a tabela GERENCIADOR e popular com valores distintos de LICITACAO.GERENCIADOR
 * Execute este arquivo uma vez para criar a estrutura e realizar o backfill inicial
 */

require_once '../config/config.php';

try {
    // Verificar se a tabela já existe
    $sqlCheck = "SHOW TABLES LIKE 'GERENCIADOR'";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute();

    if ($stmtCheck->rowCount() > 0) {
        echo "✅ A tabela 'GERENCIADOR' já existe.<br>";
    } else {
        // Criar tabela GERENCIADOR
        $sqlCreate = "CREATE TABLE IF NOT EXISTS `GERENCIADOR` (
            `ID_GERENCIADOR` INT(11) NOT NULL AUTO_INCREMENT,
            `NOME` VARCHAR(150) NOT NULL,
            `ATIVO` TINYINT(1) NOT NULL DEFAULT 1,
            `CREATED_AT` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `UPDATED_AT` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`ID_GERENCIADOR`),
            UNIQUE KEY `uk_nome` (`NOME`),
            KEY `idx_ativo` (`ATIVO`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro de gerenciadores de licitações';";

        $pdo->exec($sqlCreate);
        echo "✅ Tabela 'GERENCIADOR' criada com sucesso!<br>";
    }

    // Backfill: inserir gerenciadores únicos da tabela LICITACAO
    // Ignora nulos, vazios e espaços
    $sqlBackfill = "INSERT IGNORE INTO GERENCIADOR (NOME)
        SELECT DISTINCT TRIM(GERENCIADOR) AS NOME
        FROM LICITACAO
        WHERE GERENCIADOR IS NOT NULL
          AND TRIM(GERENCIADOR) <> ''";

    $linhasInseridas = $pdo->exec($sqlBackfill);
    if ($linhasInseridas === false) {
        echo "⚠️ Nenhum gerenciador inserido (possivelmente já estavam inseridos).<br>";
    } else {
        echo "✅ Backfill concluído. Gerenciadores inseridos: " . (int)$linhasInseridas . "<br>";
    }

    // Contagem final
    $stmtCount = $pdo->query("SELECT COUNT(*) AS total FROM GERENCIADOR");
    $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    echo "<br>📊 Total de gerenciadores cadastrados: " . (int)$total . "<br>";

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
    <title>Setup - Tabela GERENCIADOR</title>
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
        <h1>🗄️ Setup da Tabela GERENCIADOR</h1>

        <div style="margin-top: 30px; padding: 20px; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 5px;">
            <h3 style="margin-top: 0; color: #2e7d32;">📋 Estrutura da Tabela</h3>
            <ul style="line-height: 1.8;">
                <li><strong>ID_GERENCIADOR:</strong> Identificador único (auto-increment)</li>
                <li><strong>NOME:</strong> Nome do gerenciador (único)</li>
                <li><strong>ATIVO:</strong> Status (1 = Ativo, 0 = Inativo)</li>
            </ul>
        </div>

        <div style="margin-top: 20px; padding: 20px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 5px;">
            <h3 style="margin-top: 0; color: #1565c0;">ℹ️ Observação</h3>
            <p>Os registros foram populados a partir de valores distintos da coluna <code>LICITACAO.GERENCIADOR</code>, ignorando nulos e vazios.</p>
        </div>
    </div>
</body>
</html>


