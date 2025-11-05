<?php
/**
 * Script para verificar se a tabela ORCAMENTOS foi atualizada corretamente
 */

require_once '../config.php';

echo "<h2>Verificação da Tabela ORCAMENTOS</h2>\n";
echo "<pre>\n";

try {
    // Verificar se a tabela existe
    $sql_check = "SHOW TABLES LIKE 'ORCAMENTOS'";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute();
    $table_exists = $stmt_check->fetch();
    
    if (!$table_exists) {
        echo "❌ Tabela ORCAMENTOS não encontrada!\n";
        exit;
    }
    
    echo "✅ Tabela ORCAMENTOS encontrada.\n\n";
    
    // Verificar colunas necessárias
    $required_columns = ['token_aprovacao', 'data_aprovacao_cliente'];
    $missing_columns = [];
    
    foreach ($required_columns as $column) {
        $sql_check_col = "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'ORCAMENTOS' 
                            AND COLUMN_NAME = ?";
        $stmt_check_col = $pdo->prepare($sql_check_col);
        $stmt_check_col->execute([$column]);
        $col_exists = $stmt_check_col->fetch()['count'];
        
        if ($col_exists > 0) {
            echo "✅ Coluna '{$column}' encontrada.\n";
        } else {
            echo "❌ Coluna '{$column}' NÃO encontrada.\n";
            $missing_columns[] = $column;
        }
    }
    
    echo "\n";
    
    if (empty($missing_columns)) {
        echo "🎉 Todas as colunas necessárias estão presentes!\n";
        echo "✅ O sistema de aprovação de orçamentos está pronto para uso.\n\n";
        
        // Mostrar estrutura da tabela
        echo "📋 Estrutura da tabela ORCAMENTOS:\n";
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
        
        // Estatísticas
        $sql_count = "SELECT COUNT(*) as total FROM ORCAMENTOS";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute();
        $total_orcamentos = $stmt_count->fetch()['total'];
        
        echo "\n📊 Estatísticas:\n";
        echo "   - Total de orçamentos: {$total_orcamentos}\n";
        
        if ($total_orcamentos > 0) {
            // Verificar quantos têm token
            $sql_tokens = "SELECT COUNT(*) as count FROM ORCAMENTOS WHERE token_aprovacao IS NOT NULL";
            $stmt_tokens = $pdo->prepare($sql_tokens);
            $stmt_tokens->execute();
            $com_tokens = $stmt_tokens->fetch()['count'];
            
            // Verificar quantos foram aprovados pelo cliente
            $sql_aprovados = "SELECT COUNT(*) as count FROM ORCAMENTOS WHERE data_aprovacao_cliente IS NOT NULL";
            $stmt_aprovados = $pdo->prepare($sql_aprovados);
            $stmt_aprovados->execute();
            $aprovados_cliente = $stmt_aprovados->fetch()['count'];
            
            echo "   - Com token de aprovação: {$com_tokens}\n";
            echo "   - Aprovados pelo cliente: {$aprovados_cliente}\n";
        }
        
    } else {
        echo "⚠️  Colunas faltando: " . implode(', ', $missing_columns) . "\n";
        echo "Execute o script de atualização para adicionar as colunas necessárias.\n";
        echo "Arquivo: includes/database/atualizar_orcamentos.php\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erro ao verificar a tabela: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
echo "<p><a href='../pages/orcamentos.php'>← Voltar para Orçamentos</a></p>\n";
?>




