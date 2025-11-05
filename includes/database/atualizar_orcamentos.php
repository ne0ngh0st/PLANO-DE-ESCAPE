<?php
/**
 * Script para atualizar a tabela ORCAMENTOS com as novas colunas
 * Execute este arquivo uma vez para adicionar as colunas necessárias
 */

require_once '../config.php';

echo "<h2>Atualização da Tabela ORCAMENTOS</h2>\n";
echo "<pre>\n";

try {
    // Verificar se a tabela ORCAMENTOS existe
    $sql_check = "SHOW TABLES LIKE 'ORCAMENTOS'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute();
    $table_exists = $stmt_check->fetch();
    
    if (!$table_exists) {
        echo "❌ Tabela ORCAMENTOS não encontrada!\n";
        echo "Execute primeiro o script de criação da tabela.\n";
        exit;
    }
    
    echo "✅ Tabela ORCAMENTOS encontrada.\n\n";
    
    // Verificar se a coluna token_aprovacao já existe
    $sql_check_col = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                      WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'ORCAMENTOS' 
                        AND COLUMN_NAME = 'token_aprovacao'";
    $stmt_check_col = $pdo->prepare($sql_check_col);
    $stmt_check_col->execute();
    $col_exists = $stmt_check_col->fetch()['count'];
    
    if ($col_exists == 0) {
        echo "📝 Adicionando coluna 'token_aprovacao'...\n";
        $sql_add_token = "ALTER TABLE ORCAMENTOS ADD COLUMN token_aprovacao VARCHAR(255) UNIQUE AFTER usuario_criador";
        $pdo->exec($sql_add_token);
        echo "✅ Coluna 'token_aprovacao' adicionada com sucesso!\n\n";
    } else {
        echo "ℹ️  Coluna 'token_aprovacao' já existe.\n\n";
    }
    
    // Verificar se a coluna data_aprovacao_cliente já existe
    $sql_check_col2 = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                       WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'ORCAMENTOS' 
                         AND COLUMN_NAME = 'data_aprovacao_cliente'";
    $stmt_check_col2 = $pdo->prepare($sql_check_col2);
    $stmt_check_col2->execute();
    $col_exists2 = $stmt_check_col2->fetch()['count'];
    
    if ($col_exists2 == 0) {
        echo "📝 Adicionando coluna 'data_aprovacao_cliente'...\n";
        $sql_add_date = "ALTER TABLE ORCAMENTOS ADD COLUMN data_aprovacao_cliente TIMESTAMP NULL AFTER token_aprovacao";
        $pdo->exec($sql_add_date);
        echo "✅ Coluna 'data_aprovacao_cliente' adicionada com sucesso!\n\n";
    } else {
        echo "ℹ️  Coluna 'data_aprovacao_cliente' já existe.\n\n";
    }
    
    // Mostrar estrutura atual da tabela
    echo "📋 Estrutura atual da tabela ORCAMENTOS:\n";
    echo str_repeat("-", 80) . "\n";
    
    $sql_describe = "DESCRIBE ORCAMENTOS";
    $stmt_describe = $pdo->prepare($sql_describe);
    $stmt_describe->execute();
    $columns = $stmt_describe->fetchAll(PDO::FETCH_ASSOC);
    
    printf("%-20s %-15s %-8s %-8s %-10s %-15s\n", 
           "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($columns as $column) {
        printf("%-20s %-15s %-8s %-8s %-10s %-15s\n",
               $column['Field'],
               $column['Type'],
               $column['Null'],
               $column['Key'],
               $column['Default'] ?? 'NULL',
               $column['Extra']);
    }
    
    echo "\n✅ Atualização concluída com sucesso!\n";
    echo "🎉 A tabela ORCAMENTOS agora suporta aprovação de clientes.\n";
    
    // Verificar se há orçamentos existentes
    $sql_count = "SELECT COUNT(*) as total FROM ORCAMENTOS";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute();
    $total_orcamentos = $stmt_count->fetch()['total'];
    
    echo "\n📊 Estatísticas:\n";
    echo "   - Total de orçamentos: {$total_orcamentos}\n";
    
    if ($total_orcamentos > 0) {
        echo "   - Orçamentos existentes não serão afetados\n";
        echo "   - Novos orçamentos poderão usar o sistema de aprovação\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro ao atualizar a tabela: " . $e->getMessage() . "\n";
    echo "Verifique as permissões do banco de dados.\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
echo "<p><a href='../pages/orcamentos.php'>← Voltar para Orçamentos</a></p>\n";
?>




