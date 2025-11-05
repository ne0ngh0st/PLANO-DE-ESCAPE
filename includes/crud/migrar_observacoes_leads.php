<?php
/**
 * Script de Migração de Observações de Leads
 * 
 * Este script migra as observações existentes que usam apenas email
 * para a nova chave composta (email|cep|telefone)
 */

session_start();
require_once 'conexao.php';

// Verificar se o usuário arquivo é admin
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['perfil'] !== 'admin') {
    die('Acesso negado. Apenas administradores podem executar este script.');
}

echo "<h2>Migração de Observações de Leads</h2>";
echo "<p>Iniciando migração...</p>";

try {
    // Buscar todas as observações de leads que usam apenas email
    $sql = "SELECT * FROM observacoes WHERE tipo = 'lead' AND identificador NOT LIKE '%|%'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $observacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Encontradas " . count($observacoes) . " observações para migrar.</p>";
    
    $migradas = 0;
    $erros = 0;
    
    foreach ($observacoes as $obs) {
        $email_original = $obs['identificador'];
        
        // Buscar leads com este email para obter CEP e telefone
        $sql_leads = "SELECT DISTINCT Email, CEP, TelefonePrincipalFINAL FROM BASE_LEADS WHERE Email = ?";
        $stmt_leads = $pdo->prepare($sql_leads);
        $stmt_leads->execute([$email_original]);
        $leads = $stmt_leads->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($leads)) {
            echo "<p style='color: orange;'>⚠️ Nenhum lead encontrado para email: $email_original</p>";
            continue;
        }
        
        // Para cada lead com este email, criar uma observação com a nova chave
        foreach ($leads as $lead) {
            $email = trim($lead['Email'] ?? '');
            $cep = preg_replace('/\D/', '', $lead['CEP'] ?? '');
            $telefone = preg_replace('/\D/', '', $lead['TelefonePrincipalFINAL'] ?? '');
            
            if (!empty($email) && $email !== 'N/A' && $email !== '-') {
                $nova_chave = $email . '|' . $cep . '|' . $telefone;
                
                // Verificar se já existe uma observação com esta nova chave
                $sql_check = "SELECT COUNT(*) FROM observacoes WHERE tipo = 'lead' AND identificador = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$nova_chave]);
                $existe = $stmt_check->fetchColumn();
                
                if ($existe == 0) {
                    // Criar nova observação com a nova chave
                    $sql_insert = "INSERT INTO observacoes (tipo, identificador, observacao, usuario_id, usuario_nome, data_criacao) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $stmt_insert->execute([
                        'lead',
                        $nova_chave,
                        $obs['observacao'],
                        $obs['usuario_id'],
                        $obs['usuario_nome'],
                        $obs['data_criacao']
                    ]);
                    
                    $migradas++;
                    echo "<p style='color: green;'>✅ Migrada: $email_original → $nova_chave</p>";
                } else {
                    echo "<p style='color: blue;'>ℹ️ Já existe: $nova_chave</p>";
                }
            }
        }
        
        // Marcar a observação original como migrada (opcional - pode ser removida depois)
        $sql_update = "UPDATE observacoes SET identificador = CONCAT(identificador, '_MIGRADA') WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$obs['id']]);
    }
    
    echo "<h3>Migração Concluída</h3>";
    echo "<p style='color: green;'><strong>Observações migradas: $migradas</strong></p>";
    echo "<p style='color: red;'><strong>Erros: $erros</strong></p>";
    
    // Limpar observações migradas (opcional)
    $sql_cleanup = "DELETE FROM observacoes WHERE identificador LIKE '%_MIGRADA'";
    $stmt_cleanup = $pdo->prepare($sql_cleanup);
    $stmt_cleanup->execute();
    $removidas = $stmt_cleanup->rowCount();
    
    echo "<p style='color: blue;'><strong>Observações antigas removidas: $removidas</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Erro durante a migração:</strong> " . $e->getMessage() . "</p>";
}

echo "<p><a href='../leads.php'>← Voltar para Leads</a></p>";
?>


