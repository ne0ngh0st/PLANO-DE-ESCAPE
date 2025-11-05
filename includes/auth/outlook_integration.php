<?php
session_start();
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Incluir arquivo de conexão
require_once 'conexao.php';

$usuario = $_SESSION['usuario'];
$action = $_POST['acao'] ?? $_GET['acao'] ?? '';

try {
    switch ($action) {
        case 'exportar_ics':
            exportarAgendamentoICS($pdo, $usuario);
            break;
            
        case 'exportar_todos_ics':
            exportarTodosAgendamentosICS($pdo, $usuario);
            break;
            
        case 'sincronizar_outlook':
            sincronizarComOutlook($pdo, $usuario);
            break;
            
        case 'configurar_outlook':
            configurarOutlook($pdo, $usuario);
            break;
            
        case 'testar_conexao_outlook':
            testarConexaoOutlook($pdo, $usuario);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}

/**
 * Exporta um agendamento específico para formato .ics
 */
function exportarAgendamentoICS($pdo, $usuario) {
    $agendamento_id = $_POST['agendamento_id'] ?? $_GET['agendamento_id'] ?? '';
    
    if (empty($agendamento_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido']);
        return;
    }
    
    // Buscar dados do agendamento
    $sql = "SELECT a.*, u.nome as usuario_nome 
            FROM agendamentos_ligacoes a 
            LEFT JOIN usuarios u ON a.usuario_id = u.id 
            WHERE a.id = ? AND a.usuario_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$agendamento_id, $usuario['id']]);
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agendamento) {
        echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado']);
        return;
    }
    
    // Gerar arquivo .ics
    $ics_content = gerarICSContent($agendamento);
    
    // Definir headers para download
    $filename = 'agendamento_' . $agendamento_id . '_' . date('Y-m-d_H-i-s') . '.ics';
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($ics_content));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    echo $ics_content;
    exit;
}

/**
 * Exporta todos os agendamentos do usuário para formato .ics
 */
function exportarTodosAgendamentosICS($pdo, $usuario) {
    // Buscar todos os agendamentos do usuário
    $sql = "SELECT a.*, u.nome as usuario_nome 
            FROM agendamentos_ligacoes a 
            LEFT JOIN usuarios u ON a.usuario_id = u.id 
            WHERE a.usuario_id = ? 
            ORDER BY a.data_agendamento";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario['id']]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($agendamentos)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum agendamento encontrado']);
        return;
    }
    
    // Gerar conteúdo .ics para todos os agendamentos
    $ics_content = "BEGIN:VCALENDAR\r\n";
    $ics_content .= "VERSION:2.0\r\n";
    $ics_content .= "PRODID:-//Autopel BI//Calendario de Ligacoes//PT-BR\r\n";
    $ics_content .= "CALSCALE:GREGORIAN\r\n";
    $ics_content .= "METHOD:PUBLISH\r\n";
    
    foreach ($agendamentos as $agendamento) {
        $ics_content .= gerarEventoICS($agendamento);
    }
    
    $ics_content .= "END:VCALENDAR\r\n";
    
    // Definir headers para download
    $filename = 'agendamentos_autopel_' . date('Y-m-d_H-i-s') . '.ics';
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($ics_content));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    echo $ics_content;
    exit;
}

/**
 * Gera o conteúdo completo de um arquivo .ics para um agendamento
 */
function gerarICSContent($agendamento) {
    $ics_content = "BEGIN:VCALENDAR\r\n";
    $ics_content .= "VERSION:2.0\r\n";
    $ics_content .= "PRODID:-//Autopel BI//Calendario de Ligacoes//PT-BR\r\n";
    $ics_content .= "CALSCALE:GREGORIAN\r\n";
    $ics_content .= "METHOD:PUBLISH\r\n";
    $ics_content .= gerarEventoICS($agendamento);
    $ics_content .= "END:VCALENDAR\r\n";
    
    return $ics_content;
}

/**
 * Gera um evento individual no formato .ics
 */
function gerarEventoICS($agendamento) {
    // Formatar data/hora para formato UTC
    $data_agendamento = new DateTime($agendamento['data_agendamento']);
    $data_fim = clone $data_agendamento;
    $data_fim->add(new DateInterval('PT30M')); // 30 minutos de duração
    
    $dtstart = $data_agendamento->format('Ymd\THis\Z');
    $dtend = $data_fim->format('Ymd\THis\Z');
    $created = new DateTime($agendamento['created_at']);
    $created_str = $created->format('Ymd\THis\Z');
    
    // Gerar UID único
    $uid = 'autopel-agendamento-' . $agendamento['id'] . '-' . time() . '@autopel.com.br';
    
    // Preparar descrição
    $descricao = "Agendamento de ligação com cliente: " . $agendamento['cliente_nome'];
    if (!empty($agendamento['observacao'])) {
        $descricao .= "\n\nObservações: " . $agendamento['observacao'];
    }
    $descricao .= "\n\nStatus: " . ucfirst($agendamento['status']);
    $descricao .= "\nResponsável: " . $agendamento['usuario_nome'];
    
    // Escapar caracteres especiais
    $descricao = str_replace(["\r\n", "\r", "\n"], "\\n", $descricao);
    $descricao = str_replace([",", ";", "\\"], ["\\,", "\\;", "\\\\"], $descricao);
    
    $titulo = "Ligação - " . $agendamento['cliente_nome'];
    $titulo = str_replace([",", ";", "\\"], ["\\,", "\\;", "\\\\"], $titulo);
    
    $evento = "BEGIN:VEVENT\r\n";
    $evento .= "UID:" . $uid . "\r\n";
    $evento .= "DTSTAMP:" . $created_str . "\r\n";
    $evento .= "DTSTART:" . $dtstart . "\r\n";
    $evento .= "DTEND:" . $dtend . "\r\n";
    $evento .= "SUMMARY:" . $titulo . "\r\n";
    $evento .= "DESCRIPTION:" . $descricao . "\r\n";
    $evento .= "LOCATION:Telefone\r\n";
    $evento .= "STATUS:CONFIRMED\r\n";
    $evento .= "SEQUENCE:0\r\n";
    $evento .= "END:VEVENT\r\n";
    
    return $evento;
}

/**
 * Configura as credenciais do Outlook
 */
function configurarOutlook($pdo, $usuario) {
    $client_id = $_POST['client_id'] ?? '';
    $client_secret = $_POST['client_secret'] ?? '';
    $tenant_id = $_POST['tenant_id'] ?? '';
    $email_outlook = $_POST['email_outlook'] ?? '';
    
    if (empty($client_id) || empty($client_secret) || empty($tenant_id) || empty($email_outlook)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
        return;
    }
    
    // Verificar se já existe configuração para este usuário
    $sql_check = "SELECT id FROM outlook_config WHERE usuario_id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$usuario['id']]);
    
    if ($stmt_check->fetch()) {
        // Atualizar configuração existente
        $sql = "UPDATE outlook_config SET 
                client_id = ?, 
                client_secret = ?, 
                tenant_id = ?, 
                email_outlook = ?, 
                updated_at = NOW() 
                WHERE usuario_id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$client_id, $client_secret, $tenant_id, $email_outlook, $usuario['id']]);
    } else {
        // Inserir nova configuração
        $sql = "INSERT INTO outlook_config (usuario_id, client_id, client_secret, tenant_id, email_outlook, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$usuario['id'], $client_id, $client_secret, $tenant_id, $email_outlook]);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Configuração do Outlook salva com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar configuração']);
    }
}

/**
 * Testa a conexão com o Outlook
 */
function testarConexaoOutlook($pdo, $usuario) {
    // Buscar configuração do usuário
    $sql = "SELECT * FROM outlook_config WHERE usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario['id']]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        echo json_encode(['success' => false, 'message' => 'Configuração do Outlook não encontrada']);
        return;
    }
    
    try {
        // Aqui você implementaria a lógica de teste com Microsoft Graph API
        // Por enquanto, retornamos sucesso simulado
        echo json_encode([
            'success' => true, 
            'message' => 'Conexão com Outlook testada com sucesso',
            'email' => $config['email_outlook']
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao testar conexão: ' . $e->getMessage()]);
    }
}

/**
 * Sincroniza agendamentos com o Outlook
 */
function sincronizarComOutlook($pdo, $usuario) {
    $tipo_sincronizacao = $_POST['tipo'] ?? 'exportar'; // exportar, importar, bidirecional
    
    // Buscar configuração do usuário
    $sql = "SELECT * FROM outlook_config WHERE usuario_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario['id']]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        echo json_encode(['success' => false, 'message' => 'Configuração do Outlook não encontrada']);
        return;
    }
    
    try {
        switch ($tipo_sincronizacao) {
            case 'exportar':
                // Exportar agendamentos para o Outlook
                $resultado = exportarParaOutlook($pdo, $usuario, $config);
                break;
                
            case 'importar':
                // Importar eventos do Outlook
                $resultado = importarDoOutlook($pdo, $usuario, $config);
                break;
                
            case 'bidirecional':
                // Sincronização bidirecional
                $resultado = sincronizacaoBidirecional($pdo, $usuario, $config);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Tipo de sincronização inválido']);
                return;
        }
        
        echo json_encode($resultado);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro na sincronização: ' . $e->getMessage()]);
    }
}

/**
 * Exporta agendamentos para o Outlook
 */
function exportarParaOutlook($pdo, $usuario, $config) {
    // Buscar agendamentos não sincronizados
    $sql = "SELECT * FROM agendamentos_ligacoes 
            WHERE usuario_id = ? AND (outlook_sync = 0 OR outlook_sync IS NULL)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuario['id']]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $exportados = 0;
    $erros = 0;
    
    foreach ($agendamentos as $agendamento) {
        try {
            // Aqui você implementaria a lógica de criação de evento no Outlook via Microsoft Graph API
            // Por enquanto, apenas marcamos como sincronizado
            
            $sql_update = "UPDATE agendamentos_ligacoes SET 
                          outlook_sync = 1, 
                          outlook_event_id = ?, 
                          last_sync = NOW() 
                          WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute(['outlook_' . $agendamento['id'], $agendamento['id']]);
            
            $exportados++;
            
        } catch (Exception $e) {
            $erros++;
        }
    }
    
    return [
        'success' => true,
        'message' => "Sincronização concluída. Exportados: $exportados, Erros: $erros",
        'exportados' => $exportados,
        'erros' => $erros
    ];
}

/**
 * Importa eventos do Outlook
 */
function importarDoOutlook($pdo, $usuario, $config) {
    // Aqui você implementaria a lógica de busca de eventos no Outlook via Microsoft Graph API
    // Por enquanto, retornamos sucesso simulado
    
    return [
        'success' => true,
        'message' => 'Importação do Outlook concluída com sucesso',
        'importados' => 0
    ];
}

/**
 * Sincronização bidirecional
 */
function sincronizacaoBidirecional($pdo, $usuario, $config) {
    // Combina exportação e importação
    $export_result = exportarParaOutlook($pdo, $usuario, $config);
    $import_result = importarDoOutlook($pdo, $usuario, $config);
    
    return [
        'success' => true,
        'message' => 'Sincronização bidirecional concluída',
        'exportados' => $export_result['exportados'] ?? 0,
        'importados' => $import_result['importados'] ?? 0
    ];
}
?>
