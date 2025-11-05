<?php
// Script para coletar métricas diárias de volatilidade
require_once 'conexao.php';

// Verificar se já foi executado hoje
$hoje = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM metricas_volatilidade_diaria WHERE data_metrica = ?");
$stmt->execute([$hoje]);
$ja_executado = $stmt->fetchColumn() > 0;

// Se executado via AJAX (coleta manual) ou com parâmetro force, permitir re-execução
$is_manual = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') || 
             (isset($_GET['force']) && $_GET['force'] === '1');

if ($ja_executado && !$is_manual) {
    echo "Métricas já coletadas para hoje ($hoje).\n";
    exit;
}

// Se for execução manual e já existe registro, deletar o anterior
if ($ja_executado && $is_manual) {
    $stmt = $pdo->prepare("DELETE FROM metricas_volatilidade_diaria WHERE data_metrica = ?");
    $stmt->execute([$hoje]);
    echo "Registro anterior removido para permitir nova coleta.\n";
}

try {
    // Coletar métricas do dia atual
    $metricas = [];
    
    // Obter hora atual para coleta justa
    $hora_atual = date('H:i:s');
    
    // 1. Total de Clientes (baseado na tabela de clientes)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total
        FROM ultimo_faturamento uf
    ");
    $stmt->execute();
    $metricas['total_clientes'] = $stmt->fetchColumn();
    
    // 2. Contatos WhatsApp DO DIA ATÉ AGORA (baseado na tabela LIGACOES, excluindo ligações excluídas)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM LIGACOES 
        WHERE tipo_contato = 'whatsapp' 
        AND status != 'excluida'
        AND DATE(data_ligacao) = ?
        AND TIME(data_ligacao) <= ?
    ");
    $stmt->execute([$hoje, $hora_atual]);
    $metricas['contatos_whatsapp_hoje'] = $stmt->fetchColumn();
    
    // 2b. Total WhatsApp acumulado (para referência)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM LIGACOES 
        WHERE tipo_contato = 'whatsapp' 
        AND status != 'excluida'
    ");
    $stmt->execute();
    $metricas['contatos_whatsapp_total'] = $stmt->fetchColumn();
    
    // 3. Contatos Presencial DO DIA ATÉ AGORA (baseado na tabela LIGACOES, excluindo ligações excluídas)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM LIGACOES 
        WHERE tipo_contato = 'presencial' 
        AND status != 'excluida'
        AND DATE(data_ligacao) = ?
        AND TIME(data_ligacao) <= ?
    ");
    $stmt->execute([$hoje, $hora_atual]);
    $metricas['contatos_presencial_hoje'] = $stmt->fetchColumn();
    
    // 3b. Total Presencial acumulado (para referência)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM LIGACOES 
        WHERE tipo_contato = 'presencial' 
        AND status != 'excluida'
    ");
    $stmt->execute();
    $metricas['contatos_presencial_total'] = $stmt->fetchColumn();
    
    // 4. Contatos Ligação/Telefônica DO DIA ATÉ AGORA (baseado na tabela LIGACOES, excluindo ligações excluídas)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM LIGACOES 
        WHERE tipo_contato = 'telefonica' 
        AND status != 'excluida'
        AND DATE(data_ligacao) = ?
        AND TIME(data_ligacao) <= ?
    ");
    $stmt->execute([$hoje, $hora_atual]);
    $metricas['contatos_ligacao_hoje'] = $stmt->fetchColumn();
    
    // 4b. Total Ligação acumulado (para referência)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM LIGACOES 
        WHERE tipo_contato = 'telefonica' 
        AND status != 'excluida'
    ");
    $stmt->execute();
    $metricas['contatos_ligacao_total'] = $stmt->fetchColumn();
    
    // 5. Contatos Email DO DIA ATÉ AGORA (baseado na tabela LIGACOES, excluindo ligações excluídas)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM LIGACOES 
        WHERE tipo_contato = 'email' 
        AND status != 'excluida'
        AND DATE(data_ligacao) = ?
        AND TIME(data_ligacao) <= ?
    ");
    $stmt->execute([$hoje, $hora_atual]);
    $metricas['contatos_email_hoje'] = $stmt->fetchColumn();
    
    // 5b. Total Email acumulado (para referência)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM LIGACOES 
        WHERE tipo_contato = 'email' 
        AND status != 'excluida'
    ");
    $stmt->execute();
    $metricas['contatos_email_total'] = $stmt->fetchColumn();
    
    // 6. Clientes Ativos (última compra nos últimos 290 dias)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total
        FROM ultimo_faturamento uf
        WHERE DATEDIFF(CURDATE(), COALESCE(STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y'), STR_TO_DATE('1900-01-01','%Y-%m-%d'))) <= 290
    ");
    $stmt->execute();
    $metricas['clientes_ativos'] = $stmt->fetchColumn();
    
    // 7. Clientes Inativos (última compra há mais de 365 dias ou nunca compraram)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total
        FROM ultimo_faturamento uf
        WHERE (uf.DT_FAT IS NULL OR uf.DT_FAT = '' OR DATEDIFF(CURDATE(), STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) > 365)
    ");
    $stmt->execute();
    $metricas['clientes_inativos'] = $stmt->fetchColumn();
    
    // 8. Clientes Inativando (última compra entre 290 e 365 dias)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total
        FROM ultimo_faturamento uf
        WHERE uf.DT_FAT IS NOT NULL 
        AND uf.DT_FAT != '' 
        AND DATEDIFF(CURDATE(), STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) > 290 
        AND DATEDIFF(CURDATE(), STR_TO_DATE(uf.DT_FAT, '%d/%m/%Y')) <= 365
    ");
    $stmt->execute();
    $metricas['clientes_inativando'] = $stmt->fetchColumn();
    
    // 9. Vendedores Ativos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM USUARIOS 
        WHERE ATIVO = 1 
        AND PERFIL IN ('vendedor', 'representante', 'supervisor', 'diretor', 'admin')
    ");
    $stmt->execute();
    $metricas['vendedores_ativos'] = $stmt->fetchColumn();
    
    // 10. Leads Novos (baseado em observações de leads)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM observacoes 
        WHERE DATE(data_criacao) = ? 
        AND (observacao LIKE '%lead%' OR observacao LIKE '%novo cliente%' OR observacao LIKE '%prospect%')
    ");
    $stmt->execute([$hoje]);
    $metricas['leads_novos'] = $stmt->fetchColumn();
    
    // 10. Agendamentos (baseado na tabela agendamentos_leads se existir, senão observações)
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM agendamentos_leads 
            WHERE DATE(data_agendamento) = ?
        ");
        $stmt->execute([$hoje]);
        $metricas['agendamentos'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Se tabela não existir, usar observações
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM observacoes 
            WHERE DATE(data_criacao) = ? 
            AND (observacao LIKE '%agendamento%' OR observacao LIKE '%agendar%' OR observacao LIKE '%reunião%')
        ");
        $stmt->execute([$hoje]);
        $metricas['agendamentos'] = $stmt->fetchColumn();
    }
    
    // 11. Ligações Realizadas (todas as ligações do dia)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM ligacoes 
        WHERE DATE(data_ligacao) = ?
    ");
    $stmt->execute([$hoje]);
    $metricas['ligacoes_realizadas'] = $stmt->fetchColumn();
    
    // 12. Observações Criadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM observacoes 
        WHERE DATE(data_criacao) = ?
    ");
    $stmt->execute([$hoje]);
    $metricas['observacoes_criadas'] = $stmt->fetchColumn();
    
    // 13. Sugestões Criadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM sugestoes 
        WHERE DATE(data_criacao) = ?
    ");
    $stmt->execute([$hoje]);
    $metricas['sugestoes_criadas'] = $stmt->fetchColumn();
    
    
    
    // Inserir métricas no banco
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
            total_sugestoes_criadas
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $hoje,
        $metricas['contatos_whatsapp_hoje'],
        $metricas['contatos_presencial_hoje'],
        $metricas['contatos_ligacao_hoje'],
        $metricas['contatos_email_hoje'],
        $metricas['contatos_whatsapp_total'],
        $metricas['contatos_presencial_total'],
        $metricas['contatos_ligacao_total'],
        $metricas['contatos_email_total'],
        $metricas['total_clientes'],
        $metricas['clientes_ativos'],
        $metricas['clientes_inativos'],
        $metricas['clientes_inativando'],
        $metricas['vendedores_ativos'],
        $metricas['leads_novos'],
        $metricas['agendamentos'],
        $metricas['ligacoes_realizadas'],
        $metricas['observacoes_criadas'],
        $metricas['sugestoes_criadas']
    ]);
    
    // Só mostrar mensagem detalhada se não for execução via AJAX
    if (!$is_manual) {
        echo "Métricas coletadas com sucesso para $hoje:\n";
        foreach ($metricas as $chave => $valor) {
            echo "- $chave: $valor\n";
        }
    }
    
    // Detectar transições de status dos clientes
    require_once 'detectar_transicoes_status.php';
    detectarTransicoesStatus($pdo, $hoje);
    
    // Verificar se há mudanças significativas e criar alertas
    verificarMudancasSignificativas($pdo, $hoje, $metricas);
    
} catch (PDOException $e) {
    echo "Erro ao coletar métricas: " . $e->getMessage() . "\n";
    error_log("Erro ao coletar métricas de volatilidade: " . $e->getMessage());
}

function verificarMudancasSignificativas($pdo, $data_atual, $metricas_atuais) {
    try {
        // Buscar métricas do dia anterior
        $data_anterior = date('Y-m-d', strtotime('-1 day'));
        $stmt = $pdo->prepare("SELECT * FROM metricas_volatilidade_diaria WHERE data_metrica = ?");
        $stmt->execute([$data_anterior]);
        $metricas_anteriores = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$metricas_anteriores) {
            echo "Não há dados do dia anterior para comparação.\n";
            return;
        }
        
        // Obter hora atual para comparação justa
        $hora_atual = date('H:i:s');
        
        // Definir limites para alertas
        $limites = [
            'contatos_whatsapp' => 20, // 20% de variação
            'contatos_presencial' => 30,
            'contatos_ligacao' => 25,
            'clientes_ativos' => 5,
            'clientes_inativos' => 10
        ];
        
        $tipos_metricas = [
            'contatos_whatsapp' => 'contatos_whatsapp_hoje',
            'contatos_presencial' => 'contatos_presencial_hoje',
            'contatos_ligacao' => 'contatos_ligacao_hoje',
            'clientes_ativos' => 'total_clientes_ativos',
            'clientes_inativos' => 'total_clientes_inativos'
        ];
        
        foreach ($tipos_metricas as $tipo => $campo) {
            $valor_atual = $metricas_atuais[$tipo . '_hoje'] ?? $metricas_atuais[$tipo];
            
            // Para contatos, buscar dados do dia anterior até a mesma hora
            if (in_array($tipo, ['contatos_whatsapp', 'contatos_presencial', 'contatos_ligacao'])) {
                $tipo_contato = str_replace('contatos_', '', $tipo);
                if ($tipo_contato === 'ligacao') $tipo_contato = 'telefonica';
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM LIGACOES 
                    WHERE tipo_contato = ? 
                    AND status != 'excluida'
                    AND DATE(data_ligacao) = ?
                    AND TIME(data_ligacao) <= ?
                ");
                $stmt->execute([$tipo_contato, $data_anterior, $hora_atual]);
                $valor_anterior = $stmt->fetchColumn();
            } else {
                // Para outros tipos, usar dados da tabela de métricas
                $valor_anterior = $metricas_anteriores[$campo];
            }
            
            if ($valor_anterior > 0) {
                $percentual_mudanca = (($valor_atual - $valor_anterior) / $valor_anterior) * 100;
            } else {
                $percentual_mudanca = $valor_atual > 0 ? 100 : 0;
            }
            
            $limite = $limites[$tipo];
            
            if (abs($percentual_mudanca) >= $limite) {
                $severidade = 'baixa';
                if (abs($percentual_mudanca) >= $limite * 2) {
                    $severidade = 'media';
                }
                if (abs($percentual_mudanca) >= $limite * 3) {
                    $severidade = 'alta';
                }
                if (abs($percentual_mudanca) >= $limite * 4) {
                    $severidade = 'critica';
                }
                
                $descricao = "Mudança de " . number_format($percentual_mudanca, 1) . 
                           "% em " . str_replace('_', ' ', $tipo) . 
                           " (de " . number_format($valor_anterior, 0) . 
                           " para " . number_format($valor_atual, 0) . ")";
                
                // Inserir alerta
                $stmt = $pdo->prepare("
                    INSERT INTO alertas_volatilidade (
                        data_metrica, tipo_alerta, valor_anterior, valor_atual, 
                        percentual_mudanca, severidade, descricao
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $data_atual,
                    $tipo,
                    $valor_anterior,
                    $valor_atual,
                    $percentual_mudanca,
                    $severidade,
                    $descricao
                ]);
                
                echo "Alerta criado: $descricao (Severidade: $severidade)\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "Erro ao verificar mudanças significativas: " . $e->getMessage() . "\n";
        error_log("Erro ao verificar mudanças significativas: " . $e->getMessage());
    }
    
    // 6. Detectar transições de status dos clientes
    echo "\n=== DETECTANDO TRANSIÇÕES DE STATUS ===\n";
    try {
        require_once __DIR__ . '/detectar_transicoes_status.php';
        $data_hoje = date('Y-m-d');
        $transicoes = detectarTransicoesStatus($pdo, $data_hoje);
        
        if (!empty($transicoes)) {
            echo "✅ Transições de status processadas automaticamente!\n";
            echo "📊 Total de transições: " . count($transicoes) . "\n";
        } else {
            echo "ℹ️  Nenhuma transição de status detectada para hoje.\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Erro ao detectar transições de status: " . $e->getMessage() . "\n";
        error_log("Erro ao detectar transições de status: " . $e->getMessage());
    }
}
?>
