<?php
/**
 * Script para popular automaticamente as tabelas de controle de notificações
 * Este arquivo pode ser executado via cron job para manter as tabelas atualizadas
 * 
 * Uso via cron: 0 2 * * * php /caminho/para/includes/popular_notificacoes.php
 */

// Configurações
$log_file = __DIR__ . '/../logs/popular_notificacoes.log';
$max_days = 30; // Manter apenas últimos 30 dias

// Função para log
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    
    if (file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) === false) {
        error_log("Erro ao escrever no log: $log_file");
    }
    
    // Também exibe no console se executado via linha de comando
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

try {
    logMessage("Iniciando população das tabelas de notificações...");
    
    // Conectar ao banco
    require_once 'conexao.php';
    
    // 1. Popular tabela de agendamentos
    logMessage("Populando tabela de agendamentos...");
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO agendamentos_notificacoes 
        (agendamento_id, usuario_id, cliente_nome, data_agendamento, status, data_agendamento_dia)
        SELECT 
            id,
            usuario_id,
            cliente_nome,
            data_agendamento,
            status,
            DATE(data_agendamento) as data_agendamento_dia
        FROM agendamentos_ligacoes 
        WHERE data_agendamento >= CURDATE() - INTERVAL 7 DAY
    ");
    $stmt->execute();
    $agendamentos_inseridos = $stmt->rowCount();
    logMessage("Agendamentos inseridos: $agendamentos_inseridos");
    
    // 2. Popular tabela de observações
    logMessage("Populando tabela de observações...");
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO observacoes_notificacoes 
        (observacao_id, tipo, entidade_id, usuario_id, usuario_nome, parent_id, data_criacao, data_criacao_dia)
        SELECT 
            id,
            tipo,
            identificador,
            usuario_id,
            usuario_nome,
            parent_id,
            data_criacao,
            DATE(data_criacao) as data_criacao_dia
        FROM observacoes 
        WHERE data_criacao >= CURDATE() - INTERVAL 7 DAY
    ");
    $stmt->execute();
    $observacoes_inseridas = $stmt->rowCount();
    logMessage("Observações inseridas: $observacoes_inseridas");
    
    // 3. Popular tabela de auditoria de status (se existir campos necessários)
    logMessage("Verificando se é possível popular auditoria de status...");
    
    // Verificar se a tabela leads tem os campos necessários
    $stmt = $pdo->query("SHOW COLUMNS FROM leads LIKE 'status_anterior'");
    $has_status_fields = $stmt->rowCount() > 0;
    
    if ($has_status_fields) {
        logMessage("Populando auditoria de status para leads...");
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO auditoria_mudancas_status 
            (tipo_entidade, entidade_id, usuario_id, nome_entidade, status_anterior, status_novo, data_mudanca, data_entidade)
            SELECT 
                'lead' as tipo_entidade,
                id as entidade_id,
                usuario_id,
                nome as nome_entidade,
                COALESCE(status_anterior, status) as status_anterior,
                status as status_novo,
                COALESCE(data_mudanca, NOW()) as data_mudanca,
                CURDATE() as data_entidade
            FROM leads 
            WHERE usuario_id IS NOT NULL 
            AND (status_anterior IS NOT NULL OR status IS NOT NULL)
        ");
        $stmt->execute();
        $leads_status_inseridos = $stmt->rowCount();
        logMessage("Leads status inseridos: $leads_status_inseridos");
        
        // Verificar se a tabela carteira tem os campos necessários
        $stmt = $pdo->query("SHOW COLUMNS FROM carteira LIKE 'status_anterior'");
        $has_carteira_status_fields = $stmt->rowCount() > 0;
        
        if ($has_carteira_status_fields) {
            logMessage("Populando auditoria de status para carteira...");
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO auditoria_mudancas_status 
                (tipo_entidade, entidade_id, usuario_id, nome_entidade, status_anterior, status_novo, data_mudanca, data_entidade)
                SELECT 
                    'cliente' as tipo_entidade,
                    id as entidade_id,
                    usuario_id,
                    nome as nome_entidade,
                    COALESCE(status_anterior, status) as status_anterior,
                    status as status_novo,
                    COALESCE(data_mudanca, NOW()) as data_mudanca,
                    CURDATE() as data_entidade
                FROM carteira 
                WHERE usuario_id IS NOT NULL 
                AND (status_anterior IS NOT NULL OR status IS NOT NULL)
            ");
            $stmt->execute();
            $carteira_status_inseridos = $stmt->rowCount();
            logMessage("Carteira status inseridos: $carteira_status_inseridos");
        } else {
            logMessage("Tabela carteira não tem campos de status - pulando...");
        }
    } else {
        logMessage("Tabela leads não tem campos de status - pulando auditoria...");
    }
    
    // 4. Limpar registros antigos
    logMessage("Limpando registros antigos...");
    
    $stmt = $pdo->prepare("DELETE FROM agendamentos_notificacoes WHERE data_agendamento < CURDATE() - INTERVAL ? DAY");
    $stmt->execute([$max_days]);
    $agendamentos_removidos = $stmt->rowCount();
    
    $stmt = $pdo->prepare("DELETE FROM observacoes_notificacoes WHERE data_criacao < CURDATE() - INTERVAL ? DAY");
    $stmt->execute([$max_days]);
    $observacoes_removidas = $stmt->rowCount();
    
    $stmt = $pdo->prepare("DELETE FROM auditoria_mudancas_status WHERE data_mudanca < CURDATE() - INTERVAL ? DAY");
    $stmt->execute([$max_days]);
    $status_removidos = $stmt->rowCount();
    
    logMessage("Registros antigos removidos - Agendamentos: $agendamentos_removidos, Observações: $observacoes_removidas, Status: $status_removidos");
    
    // 5. Estatísticas finais
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM agendamentos_notificacoes");
    $total_agendamentos = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM observacoes_notificacoes");
    $total_observacoes = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM auditoria_mudancas_status");
    $total_status = $stmt->fetch()['total'];
    
    logMessage("Estatísticas finais - Agendamentos: $total_agendamentos, Observações: $total_observacoes, Status: $total_status");
    logMessage("Processo concluído com sucesso!");
    
} catch (Exception $e) {
    $error_msg = "ERRO: " . $e->getMessage();
    logMessage($error_msg);
    
    if (php_sapi_name() === 'cli') {
        exit(1);
    }
}

// Se executado via web, mostrar resultado
if (php_sapi_name() !== 'cli') {
    echo "<h2>População de Notificações Concluída</h2>";
    echo "<p>Verifique o arquivo de log para detalhes: <code>$log_file</code></p>";
    echo "<p>Este script pode ser executado via cron job para manter as tabelas atualizadas.</p>";
}
?>


