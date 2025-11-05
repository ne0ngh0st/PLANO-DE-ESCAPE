<?php
// Script para atualizar a tabela de métricas de volatilidade diária
// Adiciona os novos campos para separar contatos diários dos totais acumulados
require_once 'conexao.php';

try {
    echo "Iniciando atualização da tabela de métricas de volatilidade...\n";
    
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'metricas_volatilidade_diaria'");
    if ($stmt->rowCount() == 0) {
        echo "Tabela não existe. Execute primeiro o script criar_tabela_volatilidade.php\n";
        exit;
    }
    
    // Verificar quais campos já existem
    $stmt = $pdo->query("DESCRIBE metricas_volatilidade_diaria");
    $campos_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $novos_campos = [
        'contatos_whatsapp_hoje' => 'INT DEFAULT 0',
        'contatos_presencial_hoje' => 'INT DEFAULT 0', 
        'contatos_ligacao_hoje' => 'INT DEFAULT 0',
        'contatos_email_hoje' => 'INT DEFAULT 0',
        'total_clientes' => 'INT DEFAULT 0'
    ];
    
    // Adicionar novos campos se não existirem
    foreach ($novos_campos as $campo => $definicao) {
        if (!in_array($campo, $campos_existentes)) {
            $sql = "ALTER TABLE metricas_volatilidade_diaria ADD COLUMN $campo $definicao";
            $pdo->exec($sql);
            echo "Campo '$campo' adicionado com sucesso!\n";
        } else {
            echo "Campo '$campo' já existe.\n";
        }
    }
    
    // Atualizar dados existentes para popular os novos campos
    echo "Atualizando dados existentes...\n";
    
    // Buscar registros que precisam ser atualizados
    $stmt = $pdo->query("
        SELECT id, data_metrica, total_contatos_whatsapp, total_contatos_presencial, 
               total_contatos_ligacao, total_contatos_email
        FROM metricas_volatilidade_diaria 
        WHERE contatos_whatsapp_hoje = 0 OR contatos_whatsapp_hoje IS NULL
        ORDER BY data_metrica DESC
        LIMIT 10
    ");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($registros as $registro) {
        $data = $registro['data_metrica'];
        
        // Buscar contatos reais do dia
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN tipo_contato = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp_hoje,
                SUM(CASE WHEN tipo_contato = 'presencial' THEN 1 ELSE 0 END) as presencial_hoje,
                SUM(CASE WHEN tipo_contato = 'telefonica' THEN 1 ELSE 0 END) as ligacao_hoje,
                SUM(CASE WHEN tipo_contato = 'email' THEN 1 ELSE 0 END) as email_hoje
            FROM LIGACOES 
            WHERE DATE(data_ligacao) = ? AND status != 'excluida'
        ");
        $stmt->execute([$data]);
        $contatos_dia = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Atualizar o registro
        $stmt = $pdo->prepare("
            UPDATE metricas_volatilidade_diaria 
            SET 
                contatos_whatsapp_hoje = ?,
                contatos_presencial_hoje = ?,
                contatos_ligacao_hoje = ?,
                contatos_email_hoje = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $contatos_dia['whatsapp_hoje'] ?? 0,
            $contatos_dia['presencial_hoje'] ?? 0,
            $contatos_dia['ligacao_hoje'] ?? 0,
            $contatos_dia['email_hoje'] ?? 0,
            $registro['id']
        ]);
        
        echo "Registro de $data atualizado: WhatsApp={$contatos_dia['whatsapp_hoje']}, Presencial={$contatos_dia['presencial_hoje']}, Ligação={$contatos_dia['ligacao_hoje']}, Email={$contatos_dia['email_hoje']}\n";
    }
    
    echo "Atualização concluída com sucesso!\n";
    echo "Agora a volatilidade será calculada corretamente comparando contatos do dia anterior vs hoje.\n";
    
} catch (PDOException $e) {
    echo "Erro ao atualizar tabela: " . $e->getMessage() . "\n";
    error_log("Erro ao atualizar tabela de volatilidade: " . $e->getMessage());
}
?>
