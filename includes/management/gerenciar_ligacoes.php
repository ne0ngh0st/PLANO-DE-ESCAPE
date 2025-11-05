<?php
session_start();
require_once '../config/conexao.php';

// Log de debug
error_log("DEBUG LIGACOES - Action: " . ($_POST['action'] ?? $_GET['action'] ?? 'N/A'));
error_log("DEBUG LIGACOES - POST data: " . print_r($_POST, true));

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    error_log("DEBUG LIGACOES - Usuário não autenticado");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

error_log("DEBUG LIGACOES - Usuário: " . json_encode($usuario));
error_log("DEBUG LIGACOES - Action: " . $action);

try {
    switch ($action) {
        case 'iniciar_ligacao':
            error_log("DEBUG LIGACOES - Iniciando ligação");
            
            $cliente_id = $_POST['cliente_id'] ?? '';
            $telefone = $_POST['telefone'] ?? '';
            $tipo_contato = $_POST['tipo_contato'] ?? 'telefonica';
            
            // Validar tipo de contato
            $tipos_validos = ['telefonica', 'presencial', 'whatsapp', 'email'];
            if (!in_array($tipo_contato, $tipos_validos)) {
                $tipo_contato = 'telefonica';
            }
            
            error_log("DEBUG LIGACOES - Cliente ID: " . $cliente_id);
            error_log("DEBUG LIGACOES - Telefone: " . $telefone);
            error_log("DEBUG LIGACOES - Tipo Contato: " . $tipo_contato);
            error_log("DEBUG LIGACOES - Usuario ID: " . ($usuario['id'] ?? 'N/A'));
            
            if (empty($cliente_id)) {
                throw new Exception('ID do cliente é obrigatório');
            }
            
            // Inserir nova ligação
            $sql = "INSERT INTO LIGACOES (cliente_id, telefone, tipo_contato, usuario_id, data_ligacao) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            
            // Usar o ID correto do usuário (pode ser 'id' ou 'ID')
            $usuario_id = $usuario['id'] ?? $usuario['ID'] ?? 1;
            error_log("DEBUG LIGACOES - Usando usuario_id: " . $usuario_id);
            
            $stmt->execute([$cliente_id, $telefone, $tipo_contato, $usuario_id]);
            
            $ligacao_id = $pdo->lastInsertId();
            error_log("DEBUG LIGACOES - Ligação criada com ID: " . $ligacao_id);
            
            // Buscar perguntas do roteiro
            $sql_perguntas = "SELECT * FROM PERGUNTAS_LIGACAO ORDER BY ordem";
            $stmt_perguntas = $pdo->query($sql_perguntas);
            $perguntas = $stmt_perguntas->fetchAll(PDO::FETCH_ASSOC);
            
            // Processar lógica condicional das perguntas
            foreach ($perguntas as &$pergunta) {
                if ($pergunta['condicional']) {
                    $condicional = json_decode($pergunta['condicional'], true);
                    $pergunta['condicional'] = $condicional;
                }
                if ($pergunta['campos_condicionais']) {
                    $campos_condicionais = json_decode($pergunta['campos_condicionais'], true);
                    $pergunta['campos_condicionais'] = $campos_condicionais;
                }
            }
            
            error_log("DEBUG LIGACOES - Perguntas encontradas: " . count($perguntas));
            
            $response = [
                'success' => true,
                'ligacao_id' => $ligacao_id,
                'perguntas' => $perguntas
            ];
            
            error_log("DEBUG LIGACOES - Response: " . json_encode($response));
            echo json_encode($response);
            break;
            
        case 'salvar_resposta':
            error_log("DEBUG LIGACOES - Salvando resposta");
            $ligacao_id = $_POST['ligacao_id'] ?? '';
            $pergunta_id = $_POST['pergunta_id'] ?? '';
            $resposta = $_POST['resposta'] ?? '';
            $campos_adicionais = $_POST['campos_adicionais'] ?? null;
            error_log("DEBUG LIGACOES - Ligação ID: $ligacao_id, Pergunta ID: $pergunta_id, Resposta: $resposta");
            
            if (empty($ligacao_id) || empty($pergunta_id)) {
                throw new Exception('Dados obrigatórios não fornecidos');
            }
            
            // Verificar se já existe resposta para esta pergunta nesta ligação
            $sql_check = "SELECT id FROM RESPOSTAS_LIGACAO WHERE ligacao_id = ? AND pergunta_id = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$ligacao_id, $pergunta_id]);
            $existe = $stmt_check->fetch();
            
            if ($existe) {
                // Atualizar resposta existente
                $sql = "UPDATE RESPOSTAS_LIGACAO SET resposta = ?, campos_adicionais = ? WHERE ligacao_id = ? AND pergunta_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$resposta, $campos_adicionais ? json_encode($campos_adicionais) : null, $ligacao_id, $pergunta_id]);
            } else {
                // Inserir nova resposta
                $sql = "INSERT INTO RESPOSTAS_LIGACAO (ligacao_id, pergunta_id, resposta, campos_adicionais) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ligacao_id, $pergunta_id, $resposta, $campos_adicionais ? json_encode($campos_adicionais) : null]);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'finalizar_ligacao':
            error_log("DEBUG LIGACOES - Iniciando finalização da ligação");
            $ligacao_id = $_POST['ligacao_id'] ?? '';
            $ligacao_cancelada = $_POST['ligacao_cancelada'] ?? '0';
            error_log("DEBUG LIGACOES - Ligação ID: " . $ligacao_id);
            error_log("DEBUG LIGACOES - Ligação cancelada: " . $ligacao_cancelada);
            
            if (empty($ligacao_id)) {
                throw new Exception('ID da ligação é obrigatório');
            }
            
            // Buscar a resposta da primeira pergunta para determinar o fluxo
            $sql_primeira = "SELECT resposta FROM RESPOSTAS_LIGACAO WHERE ligacao_id = ? AND pergunta_id = 1";
            $stmt_primeira = $pdo->prepare($sql_primeira);
            $stmt_primeira->execute([$ligacao_id]);
            $primeira_resposta = $stmt_primeira->fetch(PDO::FETCH_COLUMN);
            error_log("DEBUG LIGACOES - Primeira resposta: " . ($primeira_resposta ?: 'NÃO ENCONTRADA'));
            
            if (!$primeira_resposta) {
                throw new Exception('Você precisa responder a primeira pergunta antes de finalizar');
            }
            
            // Determinar quais perguntas são obrigatórias baseado no fluxo
            if ($primeira_resposta === 'Sim') {
                // Fluxo "Sim" - perguntas 1-9 são obrigatórias
                $perguntas_obrigatorias = [1, 2, 3, 4, 5, 6, 7, 8, 9];
                error_log("DEBUG LIGACOES - Fluxo 'Sim' - perguntas obrigatórias: " . implode(',', $perguntas_obrigatorias));
            } else {
                // Fluxo "Não" - apenas perguntas 1 e 10 são obrigatórias
                $perguntas_obrigatorias = [1, 10];
                error_log("DEBUG LIGACOES - Fluxo 'Não' - perguntas obrigatórias: " . implode(',', $perguntas_obrigatorias));
            }
            
            // Verificar se todas as perguntas obrigatórias do fluxo foram respondidas
            $sql_check = "
                SELECT COUNT(*) as total_obrigatorias,
                       (SELECT COUNT(*) FROM RESPOSTAS_LIGACAO WHERE ligacao_id = ? AND pergunta_id IN (" . implode(',', $perguntas_obrigatorias) . ")) as total_respondidas
                FROM PERGUNTAS_LIGACAO 
                WHERE id IN (" . implode(',', $perguntas_obrigatorias) . ")
            ";
            error_log("DEBUG LIGACOES - SQL Check: " . $sql_check);
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$ligacao_id]);
            $resultado = $stmt_check->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG LIGACOES - Resultado: total_obrigatorias=" . $resultado['total_obrigatorias'] . ", total_respondidas=" . $resultado['total_respondidas']);
            
            if ($resultado['total_respondidas'] < $resultado['total_obrigatorias']) {
                $faltam = $resultado['total_obrigatorias'] - $resultado['total_respondidas'];
                throw new Exception("Você precisa responder mais {$faltam} pergunta(s) obrigatória(s) antes de finalizar a ligação");
            }
            
            // Buscar todas as respostas da ligação para criar o resumo
            $sql_respostas = "
                SELECT r.pergunta_id, r.resposta, r.campos_adicionais, p.pergunta, p.ordem
                FROM RESPOSTAS_LIGACAO r
                JOIN PERGUNTAS_LIGACAO p ON r.pergunta_id = p.id
                WHERE r.ligacao_id = ?
                ORDER BY p.ordem
            ";
            $stmt_respostas = $pdo->prepare($sql_respostas);
            $stmt_respostas->execute([$ligacao_id]);
            $todas_respostas = $stmt_respostas->fetchAll(PDO::FETCH_ASSOC);
            
            // Criar resumo das seleções
            $selecoes_resumo = [];
            foreach ($todas_respostas as $resposta) {
                $selecoes_resumo[] = [
                    'pergunta_id' => $resposta['pergunta_id'],
                    'pergunta' => $resposta['pergunta'],
                    'resposta' => $resposta['resposta'],
                    'campos_adicionais' => $resposta['campos_adicionais'] ? json_decode($resposta['campos_adicionais'], true) : null
                ];
            }
            
            // Atualizar o status da ligação para 'finalizada' e salvar o resumo das seleções
            $sql_update = "UPDATE LIGACOES SET status = 'finalizada', selecoes_resumo = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([json_encode($selecoes_resumo), $ligacao_id]);
            
            // Criar agendamentos automaticamente caso existam datas informadas no roteiro
            try {
                // Buscar dados do cliente desta ligação
                $sql_ligacao = "SELECT cliente_id FROM LIGACOES WHERE id = ?";
                $stmt_ligacao = $pdo->prepare($sql_ligacao);
                $stmt_ligacao->execute([$ligacao_id]);
                $cliente_id_ligacao = $stmt_ligacao->fetchColumn();
                
                $cliente_cnpj_ag = $cliente_id_ligacao;
                $cliente_nome_ag = 'Cliente não encontrado';
                
                // Tentar derivar a raiz do CNPJ (8 dígitos) quando o identificador for um CNPJ
                $digits = preg_replace('/[^0-9]/', '', (string)$cliente_id_ligacao);
                $raiz_cnpj = strlen($digits) >= 8 ? substr($digits, 0, 8) : '';
                if ($raiz_cnpj !== '') {
                    $cliente_cnpj_ag = $raiz_cnpj;
                    $sql_cliente = "SELECT cliente FROM ultimo_faturamento WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ? LIMIT 1";
                    $stmt_cliente = $pdo->prepare($sql_cliente);
                    $stmt_cliente->execute([$raiz_cnpj]);
                    $nome = $stmt_cliente->fetchColumn();
                    if ($nome) {
                        $cliente_nome_ag = $nome;
                    }
                }
                
                // Função auxiliar para mapear período em horário
                $mapearPeriodoParaHora = function($periodo) {
                    $periodo = trim((string)$periodo);
                    if (preg_match('/^\d{2}:\d{2}$/', $periodo)) {
                        return $periodo . ':00';
                    }
                    switch (mb_strtolower($periodo, 'UTF-8')) {
                        case 'manhã':
                        case 'manha':
                            return '09:00:00';
                        case 'tarde':
                            return '14:00:00';
                        case 'noite':
                            return '19:00:00';
                        default:
                            return '09:00:00';
                    }
                };
                
                // Coletar possíveis agendamentos a partir das respostas
                $agendamentos_para_criar = [];
                foreach ($selecoes_resumo as $sel) {
                    $campos = $sel['campos_adicionais'] ?? null;
                    if (!$campos || !is_array($campos)) {
                        continue;
                    }
                    
                    // 1) Situação da negociação => data_agendamento/periodo_agendamento (ou data_retorno/horario_retorno)
                    if (!empty($campos['data_agendamento'])) {
                        $data = $campos['data_agendamento'];
                        $hora = $mapearPeriodoParaHora($campos['periodo_agendamento'] ?? '09:00');
                        $agendamentos_para_criar[] = [
                            'datetime' => $data . ' ' . $hora,
                            'observacao' => 'Agendado retorno/reunião via ligação'
                        ];
                    }
                    if (!empty($campos['data_retorno'])) {
                        $data = $campos['data_retorno'];
                        $horaValor = $campos['horario_retorno'] ?? '09:00';
                        $hora = $mapearPeriodoParaHora($horaValor);
                        $agendamentos_para_criar[] = [
                            'datetime' => $data . ' ' . $hora,
                            'observacao' => 'Retorno agendado via ligação'
                        ];
                    }
                    
                    // 2) Próximo passo definido => data_recontato/periodo_recontato
                    if (!empty($campos['data_recontato'])) {
                        $data = $campos['data_recontato'];
                        $hora = $mapearPeriodoParaHora($campos['periodo_recontato'] ?? '09:00');
                        $agendamentos_para_criar[] = [
                            'datetime' => $data . ' ' . $hora,
                            'observacao' => 'Recontato agendado via ligação'
                        ];
                    }
                    
                    // 3) Disponibilidade futura (quando_disponivel)
                    if (!empty($campos['quando_disponivel'])) {
                        $data = $campos['quando_disponivel'];
                        $hora = '09:00:00';
                        $agendamentos_para_criar[] = [
                            'datetime' => $data . ' ' . $hora,
                            'observacao' => 'Contato quando disponível (via ligação)'
                        ];
                    }
                }
                
                // Inserir agendamentos evitando duplicidades exatas
                if (!empty($agendamentos_para_criar)) {
                    $sql_check_dup = "SELECT id FROM agendamentos_ligacoes WHERE usuario_id = ? AND cliente_cnpj = ? AND data_agendamento = ? LIMIT 1";
                    $stmt_check_dup = $pdo->prepare($sql_check_dup);
                    
                    $sql_insert_ag = "INSERT INTO agendamentos_ligacoes (usuario_id, cliente_cnpj, cliente_nome, data_agendamento, observacao, status, created_at) VALUES (?, ?, ?, ?, ?, 'agendado', NOW())";
                    $stmt_insert_ag = $pdo->prepare($sql_insert_ag);
                    
                    foreach ($agendamentos_para_criar as $ag) {
                        $data_ag = $ag['datetime'];
                        $obs = $ag['observacao'];
                        
                        $stmt_check_dup->execute([
                            $usuario['id'] ?? ($usuario['ID'] ?? 0),
                            $cliente_cnpj_ag,
                            $data_ag
                        ]);
                        $ja_existe = $stmt_check_dup->fetchColumn();
                        if ($ja_existe) {
                            continue;
                        }
                        
                        $stmt_insert_ag->execute([
                            $usuario['id'] ?? ($usuario['ID'] ?? 0),
                            $cliente_cnpj_ag,
                            $cliente_nome_ag,
                            $data_ag,
                            $obs
                        ]);
                    }
                }
                
                error_log("DEBUG LIGACOES - Agendamentos gerados automaticamente: " . count($agendamentos_para_criar));
            } catch (Exception $e_auto) {
                error_log("DEBUG LIGACOES - Erro ao gerar agendamentos automáticos: " . $e_auto->getMessage());
            }
            
            error_log("DEBUG LIGACOES - Finalização bem-sucedida - Status atualizado para 'finalizada' e seleções salvas");
            echo json_encode(['success' => true]);
            break;
            
        case 'buscar_ligacoes_cliente':
            $cliente_id = $_GET['cliente_id'] ?? '';
            
            if (empty($cliente_id)) {
                throw new Exception('ID do cliente é obrigatório');
            }
            
            $sql = "
                SELECT l.*, 
                       COUNT(r.id) as total_respostas,
                       (SELECT COUNT(*) FROM PERGUNTAS_LIGACAO WHERE obrigatoria = TRUE) as total_obrigatorias
                FROM LIGACOES l
                LEFT JOIN RESPOSTAS_LIGACAO r ON l.id = r.ligacao_id
                WHERE l.cliente_id = ?
                GROUP BY l.id
                ORDER BY l.data_ligacao DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cliente_id]);
            $ligacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'ligacoes' => $ligacoes]);
            break;
            
        case 'buscar_respostas_ligacao':
            $ligacao_id = $_GET['ligacao_id'] ?? $_POST['ligacao_id'] ?? '';
            
            if (empty($ligacao_id)) {
                throw new Exception('ID da ligação é obrigatório');
            }
            
            $sql = "
                SELECT r.*, p.pergunta, p.tipo, p.opcoes, p.ordem
                FROM RESPOSTAS_LIGACAO r
                JOIN PERGUNTAS_LIGACAO p ON r.pergunta_id = p.id
                WHERE r.ligacao_id = ?
                ORDER BY p.ordem
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ligacao_id]);
            $respostas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'respostas' => $respostas]);
            break;
            
        case 'cancelar_ligacao':
            error_log("DEBUG LIGACOES - Cancelando ligação");
            $ligacao_id = $_POST['ligacao_id'] ?? '';
            $motivo_cancelamento = $_POST['motivo_cancelamento'] ?? 'Cancelamento pelo usuário';
            
            error_log("DEBUG LIGACOES - Ligação ID: " . $ligacao_id);
            error_log("DEBUG LIGACOES - Motivo: " . $motivo_cancelamento);
            
            if (empty($ligacao_id)) {
                throw new Exception('ID da ligação é obrigatório');
            }
            
            // Verificar se a ligação existe e está ativa
            $sql_check = "SELECT id, status FROM LIGACOES WHERE id = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$ligacao_id]);
            $ligacao = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$ligacao) {
                throw new Exception('Ligação não encontrada');
            }
            
            if ($ligacao['status'] !== 'ativa') {
                throw new Exception('Apenas ligações ativas podem ser canceladas');
            }
            
            // Atualizar o status da ligação para 'cancelada'
            $sql_update = "UPDATE LIGACOES SET status = 'cancelada' WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$ligacao_id]);
            
            // Adicionar uma resposta automática indicando o cancelamento
            $sql_resposta = "
                INSERT INTO RESPOSTAS_LIGACAO (ligacao_id, pergunta_id, resposta, campos_adicionais) 
                VALUES (?, 999, ?, ?)
            ";
            $stmt_resposta = $pdo->prepare($sql_resposta);
            $stmt_resposta->execute([
                $ligacao_id,
                'Ligação cancelada',
                json_encode([
                    'cancelamento_automatico' => true,
                    'data_cancelamento' => date('Y-m-d H:i:s'),
                    'motivo_cancelamento' => $motivo_cancelamento,
                    'tipo_cancelamento' => 'usuario'
                ])
            ]);
            
            error_log("DEBUG LIGACOES - Cancelamento bem-sucedido - Status atualizado para 'cancelada'");
            echo json_encode(['success' => true, 'message' => 'Ligação cancelada com sucesso!']);
            break;
            
        case 'buscar_selecoes_ligacao':
            $ligacao_id = $_GET['ligacao_id'] ?? '';
            
            if (empty($ligacao_id)) {
                throw new Exception('ID da ligação é obrigatório');
            }
            
            // Buscar detalhes da ligação
            $sql_ligacao = "SELECT l.*, u.NOME_COMPLETO as usuario_nome,
                           COALESCE(uf.CLIENTE, l.cliente_id) as cliente_nome,
                           COALESCE(uf.CNPJ, l.cliente_id) as cliente_identificador
                           FROM LIGACOES l
                           LEFT JOIN USUARIOS u ON l.usuario_id = u.ID
                           LEFT JOIN (
                               SELECT DISTINCT CNPJ, CLIENTE
                               FROM ultimo_faturamento
                               WHERE CNPJ IS NOT NULL AND CNPJ != ''
                           ) uf ON l.cliente_id = uf.CNPJ
                           WHERE l.id = ?";
            $stmt_ligacao = $pdo->prepare($sql_ligacao);
            $stmt_ligacao->execute([$ligacao_id]);
            $ligacao = $stmt_ligacao->fetch(PDO::FETCH_ASSOC);
            
            if (!$ligacao) {
                throw new Exception('Ligação não encontrada');
            }
            
            // Buscar respostas da ligação
            $sql_respostas = "SELECT r.*, p.pergunta, p.obrigatoria, p.tipo
                             FROM RESPOSTAS_LIGACAO r
                             INNER JOIN PERGUNTAS_LIGACAO p ON r.pergunta_id = p.id
                             WHERE r.ligacao_id = ?
                             ORDER BY p.ordem";
            $stmt_respostas = $pdo->prepare($sql_respostas);
            $stmt_respostas->execute([$ligacao_id]);
            $respostas = $stmt_respostas->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatar as respostas
            $selecoes = [];
            foreach ($respostas as $resposta) {
                $campos_adicionais = [];
                if (!empty($resposta['campos_adicionais'])) {
                    $campos_adicionais = json_decode($resposta['campos_adicionais'], true) ?: [];
                }
                
                $selecoes[] = [
                    'pergunta' => $resposta['pergunta'],
                    'resposta' => $resposta['resposta'],
                    'tipo' => $resposta['tipo'],
                    'obrigatoria' => (bool)$resposta['obrigatoria'],
                    'campos_adicionais' => $campos_adicionais
                ];
            }
            
            echo json_encode([
                'success' => true, 
                'selecoes' => $selecoes,
                'ligacao' => $ligacao
            ]);
            break;
            
        case 'retomar_ligacao':
            error_log("DEBUG LIGACOES - Retomando ligação");
            $ligacao_id = $_POST['ligacao_id'] ?? '';
            
            error_log("DEBUG LIGACOES - Ligação ID: " . $ligacao_id);
            
            if (empty($ligacao_id)) {
                throw new Exception('ID da ligação é obrigatório');
            }
            
            // Verificar se a ligação existe e está cancelada
            $sql_check = "SELECT id, status FROM LIGACOES WHERE id = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$ligacao_id]);
            $ligacao = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$ligacao) {
                throw new Exception('Ligação não encontrada');
            }
            
            if ($ligacao['status'] !== 'cancelada') {
                throw new Exception('Apenas ligações canceladas podem ser retomadas');
            }
            
            // Atualizar o status da ligação para 'ativa'
            $sql_update = "UPDATE LIGACOES SET status = 'ativa' WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$ligacao_id]);
            
            // Remover a resposta de cancelamento automático
            $sql_delete = "DELETE FROM RESPOSTAS_LIGACAO WHERE ligacao_id = ? AND pergunta_id = 999";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute([$ligacao_id]);
            
            error_log("DEBUG LIGACOES - Retomada bem-sucedida - Status atualizado para 'ativa'");
            echo json_encode(['success' => true, 'message' => 'Ligação retomada com sucesso!']);
            break;
            
        case 'buscar_ligacao_cancelada':
            error_log("DEBUG LIGACOES - Buscando ligação cancelada");
            $cnpj_cliente = $_POST['cnpj_cliente'] ?? '';
            
            error_log("DEBUG LIGACOES - CNPJ Cliente: " . $cnpj_cliente);
            
            if (empty($cnpj_cliente)) {
                throw new Exception('CNPJ do cliente é obrigatório');
            }
            
            // Buscar ligação cancelada mais recente para este cliente
            $sql = "
                SELECT l.*, r.campos_adicionais
                FROM LIGACOES l
                LEFT JOIN RESPOSTAS_LIGACAO r ON l.id = r.ligacao_id AND r.pergunta_id = 999
                WHERE l.cliente_id = ? AND l.status = 'cancelada'
                ORDER BY l.data_ligacao DESC
                LIMIT 1
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cnpj_cliente]);
            $ligacao = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ligacao) {
                throw new Exception('Nenhuma ligação cancelada encontrada para este cliente');
            }
            
            error_log("DEBUG LIGACOES - Ligação cancelada encontrada: " . $ligacao['id']);
            echo json_encode(['success' => true, 'ligacao' => $ligacao]);
            break;
            
        case 'excluir_ligacao':
            error_log("DEBUG LIGACOES - Excluindo ligação (exclusão lógica)");
            
            // Verificar se o usuário é admin
            if (strtolower(trim($usuario['perfil'])) !== 'admin') {
                throw new Exception('Apenas administradores podem excluir ligações');
            }
            
            $ligacao_id = $_POST['ligacao_id'] ?? '';
            
            error_log("DEBUG LIGACOES - Ligação ID: " . $ligacao_id);
            
            if (empty($ligacao_id)) {
                throw new Exception('ID da ligação é obrigatório');
            }
            
            // Verificar se a ligação existe e não está já excluída
            $sql_check = "SELECT id, cliente_id, usuario_id, status FROM LIGACOES WHERE id = ? AND status != 'excluida'";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$ligacao_id]);
            $ligacao = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$ligacao) {
                throw new Exception('Ligação não encontrada ou já excluída');
            }
            
            try {
                // Marcar a ligação como excluída (exclusão lógica)
                $sql_update_ligacao = "UPDATE LIGACOES SET status = 'excluida', data_exclusao = NOW() WHERE id = ?";
                $stmt_update_ligacao = $pdo->prepare($sql_update_ligacao);
                $stmt_update_ligacao->execute([$ligacao_id]);
                
                error_log("DEBUG LIGACOES - Ligação marcada como excluída: " . $ligacao_id);
                echo json_encode(['success' => true, 'message' => 'Ligação excluída com sucesso!']);
                
            } catch (Exception $e) {
                throw new Exception('Erro ao excluir ligação: ' . $e->getMessage());
            }
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 