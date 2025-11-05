<?php
// Script para criar a tabela de métricas de volatilidade diária
require_once 'conexao.php';

try {
    // Criar tabela para métricas diárias de volatilidade
    $sql = "CREATE TABLE IF NOT EXISTS metricas_volatilidade_diaria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_metrica DATE NOT NULL,
        -- Contatos do dia (para volatilidade)
        contatos_whatsapp_hoje INT DEFAULT 0,
        contatos_presencial_hoje INT DEFAULT 0,
        contatos_ligacao_hoje INT DEFAULT 0,
        contatos_email_hoje INT DEFAULT 0,
        -- Totais acumulados (para referência)
        total_contatos_whatsapp INT DEFAULT 0,
        total_contatos_presencial INT DEFAULT 0,
        total_contatos_ligacao INT DEFAULT 0,
        total_contatos_email INT DEFAULT 0,
        -- Outras métricas
        total_clientes INT DEFAULT 0,
        total_clientes_ativos INT DEFAULT 0,
        total_clientes_inativos INT DEFAULT 0,
        total_clientes_inativando INT DEFAULT 0,
        total_vendedores_ativos INT DEFAULT 0,
        total_leads_novos INT DEFAULT 0,
        total_agendamentos INT DEFAULT 0,
        total_ligacoes_realizadas INT DEFAULT 0,
        total_observacoes_criadas INT DEFAULT 0,
        total_sugestoes_criadas INT DEFAULT 0,
        faturamento_dia DECIMAL(15,2) DEFAULT 0.00,
        meta_dia DECIMAL(15,2) DEFAULT 0.00,
        percentual_atingimento DECIMAL(5,2) DEFAULT 0.00,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_data (data_metrica),
        INDEX idx_data_metrica (data_metrica)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "Tabela 'metricas_volatilidade_diaria' criada com sucesso!\n";
    
    // Criar tabela para histórico de mudanças significativas
    $sql2 = "CREATE TABLE IF NOT EXISTS alertas_volatilidade (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_metrica DATE NOT NULL,
        tipo_alerta ENUM('contatos_whatsapp', 'contatos_presencial', 'contatos_ligacao', 'contatos_email', 'clientes_ativos', 'clientes_inativos', 'faturamento', 'atingimento_meta') NOT NULL,
        valor_anterior DECIMAL(15,2) DEFAULT 0.00,
        valor_atual DECIMAL(15,2) DEFAULT 0.00,
        percentual_mudanca DECIMAL(5,2) DEFAULT 0.00,
        severidade ENUM('baixa', 'media', 'alta', 'critica') DEFAULT 'baixa',
        descricao TEXT,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_data_tipo (data_metrica, tipo_alerta),
        INDEX idx_severidade (severidade)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql2);
    echo "Tabela 'alertas_volatilidade' criada com sucesso!\n";
    
    // Inserir dados de exemplo para os últimos 7 dias (se não existirem)
    $hoje = date('Y-m-d');
    for ($i = 6; $i >= 0; $i--) {
        $data = date('Y-m-d', strtotime("-$i days"));
        
        // Verificar se já existe dados para esta data
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM metricas_volatilidade_diaria WHERE data_metrica = ?");
        $stmt->execute([$data]);
        $existe = $stmt->fetchColumn();
        
        if (!$existe) {
            // Inserir dados de exemplo
            $stmt = $pdo->prepare("
                INSERT INTO metricas_volatilidade_diaria (
                    data_metrica, 
                    contatos_whatsapp_hoje,
                    contatos_presencial_hoje,
                    contatos_ligacao_hoje,
                    contatos_email_hoje,
                    total_contatos_whatsapp,
                    total_contatos_presencial,
                    total_contatos_ligacao,
                    total_contatos_email,
                    total_clientes,
                    total_clientes_ativos,
                    total_clientes_inativos,
                    total_clientes_inativando,
                    total_vendedores_ativos,
                    total_leads_novos,
                    total_agendamentos,
                    total_ligacoes_realizadas,
                    total_observacoes_criadas,
                    total_sugestoes_criadas,
                    faturamento_dia,
                    meta_dia,
                    percentual_atingimento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Gerar dados aleatórios para exemplo
            $contatos_whatsapp_hoje = rand(15, 45);
            $contatos_presencial_hoje = rand(5, 20);
            $contatos_ligacao_hoje = rand(20, 60);
            $contatos_email_hoje = rand(8, 25);
            
            // Totais acumulados (simulando crescimento ao longo do tempo)
            $total_whatsapp = 1000 + ($i * 50) + $contatos_whatsapp_hoje;
            $total_presencial = 500 + ($i * 25) + $contatos_presencial_hoje;
            $total_ligacao = 2000 + ($i * 100) + $contatos_ligacao_hoje;
            $total_email = 300 + ($i * 15) + $contatos_email_hoje;
            
            $total_clientes = rand(1200, 1500);
            $clientes_ativos = rand(800, 1200);
            $clientes_inativos = rand(200, 400);
            $clientes_inativando = rand(50, 150);
            $vendedores_ativos = rand(8, 15);
            $leads_novos = rand(10, 30);
            $agendamentos = rand(5, 25);
            $ligacoes_realizadas = rand(30, 80);
            $observacoes = rand(5, 20);
            $sugestoes = rand(2, 10);
            $faturamento = rand(50000, 150000);
            $meta = 100000;
            $percentual = ($faturamento / $meta) * 100;
            
            $stmt->execute([
                $data,
                $contatos_whatsapp_hoje,
                $contatos_presencial_hoje,
                $contatos_ligacao_hoje,
                $contatos_email_hoje,
                $total_whatsapp,
                $total_presencial,
                $total_ligacao,
                $total_email,
                $total_clientes,
                $clientes_ativos,
                $clientes_inativos,
                $clientes_inativando,
                $vendedores_ativos,
                $leads_novos,
                $agendamentos,
                $ligacoes_realizadas,
                $observacoes,
                $sugestoes,
                $faturamento,
                $meta,
                $percentual
            ]);
        }
    }
    
    echo "Dados de exemplo inseridos para os últimos 7 dias!\n";
    echo "Estrutura criada com sucesso! Agora você pode acessar o dashboard de volatilidade.\n";
    
} catch (PDOException $e) {
    echo "Erro ao criar estrutura: " . $e->getMessage() . "\n";
}
?>
