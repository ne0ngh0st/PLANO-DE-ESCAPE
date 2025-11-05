<?php
// Desabilitar output buffer e garantir que headers sejam enviados primeiro
if (ob_get_level()) {
    ob_end_clean();
}

// Iniciar sessão SEM output antes
session_start();

// Definir header JSON ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');

// Verifica autenticação
if (!isset($_SESSION['usuario'])) {
	echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
	exit;
}

$usuario = $_SESSION['usuario'];
$perfil = strtolower(trim($usuario['perfil'] ?? ''));

// Permitir acesso para usuários autenticados (todos os perfis autorizados)
if (!in_array($perfil, ['admin', 'vendedor', 'supervisor', 'diretor', 'representante'])) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Acesso restrito a usuários autorizados']);
	exit;
}

// Incluir conexão com tratamento de erros
try {
	require_once __DIR__ . '/../config/conexao.php';
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Erro ao conectar com banco de dados']);
	exit;
}

// Garantir tabelas auxiliares para leitura e testes
try {
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS notificacoes_lidas (
			id INT AUTO_INCREMENT PRIMARY KEY,
			usuario_id INT NOT NULL,
			evento_chave VARCHAR(191) NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_usuario_evento (usuario_id, evento_chave)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
	);
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS notificacoes_personalizadas (
			id INT AUTO_INCREMENT PRIMARY KEY,
			usuario_id INT NOT NULL,
			titulo VARCHAR(255) NOT NULL,
			mensagem TEXT NOT NULL,
			url VARCHAR(255) DEFAULT NULL,
			data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_usuario_data (usuario_id, data_criacao)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
	);
} catch (Exception $e) {
	// Não bloquear o endpoint em caso de falha de migração leve
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$dias = intval($_GET['dias'] ?? 1); // Padrão: 1 dia (hoje)

try {
	switch ($acao) {
		case 'listar':
			listarNotificacoes($pdo, $usuario, $dias);
			break;
		case 'marcar_lida':
			marcarLida($pdo, $usuario);
			break;
		case 'marcar_todas':
			marcarTodas($pdo, $usuario);
			break;
		case 'criar_teste':
			// Apenas admins podem criar testes
			if ($perfil !== 'admin') {
				echo json_encode(['success' => false, 'message' => 'Apenas administradores podem gerar testes']);
				return;
			}
			criarTeste($pdo, $usuario);
			break;
		default:
			echo json_encode(['success' => false, 'message' => 'Ação inválida']);
	}
} catch (Exception $e) {
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function listarNotificacoes(PDO $pdo, array $usuario, int $dias = 1): void {
	// Usar o mesmo padrão das observações para compatibilidade
	$usuarioId = (int)($usuario['id'] ?? $usuario['codigo'] ?? 1);
	$perfil = strtolower(trim($usuario['perfil'] ?? ''));
	$isAdmin = ($perfil === 'admin');
	$items = [];
	


	// Carregar chaves já lidas
	$stmtLidas = $pdo->prepare("SELECT evento_chave FROM notificacoes_lidas WHERE usuario_id = ?");
	$stmtLidas->execute([$usuarioId]);
	$lidas = array_column($stmtLidas->fetchAll(PDO::FETCH_ASSOC), 'evento_chave');
	$lidasMapa = array_flip($lidas);

	// 1) Agendamentos - ajustar filtro baseado no contexto
	if ($dias == 1) {
		// Dropdown normal: apenas agendamentos de hoje
		if ($isAdmin) {
			$sqlAg = "SELECT id, cliente_nome, data_agendamento, status FROM agendamentos_ligacoes WHERE DATE(data_agendamento) = CURDATE() ORDER BY data_agendamento ASC";
			$stmtAg = $pdo->prepare($sqlAg);
			$stmtAg->execute();
		} else {
			$sqlAg = "SELECT id, cliente_nome, data_agendamento, status FROM agendamentos_ligacoes WHERE DATE(data_agendamento) = CURDATE() AND usuario_id = ? ORDER BY data_agendamento ASC";
			$stmtAg = $pdo->prepare($sqlAg);
			$stmtAg->execute([$usuarioId]);
		}
	} else {
		// Modal "Ver todas": agendamentos dos últimos X dias
		if ($isAdmin) {
			$sqlAg = "SELECT id, cliente_nome, data_agendamento, status FROM agendamentos_ligacoes WHERE DATE(data_agendamento) >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY data_agendamento DESC";
			$stmtAg = $pdo->prepare($sqlAg);
			$stmtAg->execute([$dias]);
		} else {
			$sqlAg = "SELECT id, cliente_nome, data_agendamento, status FROM agendamentos_ligacoes WHERE DATE(data_agendamento) >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND usuario_id = ? ORDER BY data_agendamento DESC";
			$stmtAg = $pdo->prepare($sqlAg);
			$stmtAg->execute([$dias, $usuarioId]);
		}
	}
	$agendamentos = $stmtAg->fetchAll(PDO::FETCH_ASSOC);

	foreach ($agendamentos as $row) {
		$chave = 'agendamento:' . $row['id'];
		if (isset($lidasMapa[$chave])) continue;
		
		$dataAgendamento = new DateTime($row['data_agendamento']);
		$hoje = new DateTime();
		$hora = $dataAgendamento->format('H:i');
		$data = $dataAgendamento->format('d/m/Y');
		
		// Determinar o título baseado na data
		if ($dataAgendamento->format('Y-m-d') === $hoje->format('Y-m-d')) {
			$titulo = 'Agendamento para hoje';
		} else {
			$titulo = 'Agendamento para ' . $data;
		}
		
		$items[] = [
			'key' => $chave,
			'type' => 'agendamento',
			'title' => $titulo,
			'message' => 'Cliente: ' . ($row['cliente_nome'] ?? '—') . ' às ' . $hora,
			'url' => 'carteira.php',
			'time' => $row['data_agendamento']
		];
	}

	// 2) Novas observações e respostas de HOJE
	if ($isAdmin) {
		// Admins veem todas as observações dos últimos X dias
		$sqlObs = "SELECT id, tipo, identificador, usuario_nome, data_criacao, parent_id, usuario_id
				FROM observacoes 
				WHERE DATE(data_criacao) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
				ORDER BY data_criacao DESC";
		$stmtObs = $pdo->prepare($sqlObs);
		$stmtObs->execute([$dias]);
	} else {
		// Outros usuários veem as próprias observações E respostas às suas observações dos últimos X dias
		$sqlObs = "SELECT o.id, o.tipo, o.identificador, o.usuario_nome, o.data_criacao, o.parent_id, o.usuario_id
				FROM observacoes o
				WHERE DATE(o.data_criacao) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
				AND (
					o.usuario_id = ? 
					OR (
						o.parent_id IS NOT NULL 
						AND EXISTS (
							SELECT 1 FROM observacoes pai 
							WHERE pai.id = o.parent_id 
							AND pai.usuario_id = ?
						)
					)
				)
				ORDER BY o.data_criacao DESC";
		$stmtObs = $pdo->prepare($sqlObs);
		$stmtObs->execute([$dias, $usuarioId, $usuarioId]);
	}
	$observacoes = $stmtObs->fetchAll(PDO::FETCH_ASSOC);
	
	// Debug: Log das observações encontradas
	error_log("DEBUG NOTIFICAÇÕES - Usuario: $usuarioId, Dias: $dias, Observações encontradas: " . count($observacoes));

	foreach ($observacoes as $row) {
		$tipoEvento = empty($row['parent_id']) ? 'observacao' : 'resposta';
		$chave = ($tipoEvento === 'observacao' ? 'obs:' : 'obs_resp:') . $row['id'];
		if (isset($lidasMapa[$chave])) continue;
		
		// Pular observações próprias - só mostrar respostas
		if ($tipoEvento === 'observacao' && $row['usuario_id'] == $usuarioId) {
			continue; // Não notificar observações próprias
		}
		
		$ref = strtoupper(substr($row['tipo'] . ':' . $row['identificador'], 0, 30));
		
		// Só processar respostas
		if ($tipoEvento === 'resposta') {
			// É uma resposta - verificar se é para o usuário atual
			if ($row['usuario_id'] == $usuarioId) {
				$titulo = 'Sua resposta foi enviada';
			} else {
				$titulo = 'Nova resposta em sua observação';
			}
			
			$items[] = [
				'key' => $chave,
				'type' => $tipoEvento,
				'title' => $titulo,
				'message' => ($row['usuario_nome'] ? $row['usuario_nome'] . ': ' : '') . $ref,
				'url' => 'carteira.php',
				'time' => $row['data_criacao']
			];
		}
	}

	// 3) Notificações personalizadas (testes) dos últimos X dias
	$stmtPers = $pdo->prepare("SELECT id, titulo, mensagem, url, data_criacao FROM notificacoes_personalizadas WHERE usuario_id = ? AND DATE(data_criacao) >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY data_criacao DESC");
	$stmtPers->execute([$usuarioId, $dias]);
	foreach ($stmtPers->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$chave = 'custom:' . $row['id'];
		if (isset($lidasMapa[$chave])) continue;
		$items[] = [
			'key' => $chave,
			'type' => 'custom',
			'title' => $row['titulo'],
			'message' => $row['mensagem'],
			'url' => $row['url'] ?: 'home.php',
			'time' => $row['data_criacao']
		];
	}

	// 4) Notificações específicas para vendedores (não-admin)
	if (!$isAdmin && $perfil === 'vendedor') {
		// Debug: Log para verificar se está entrando nesta seção
		error_log("DEBUG: Entrando na seção de notificações para vendedores - Usuario: $usuarioId, Perfil: $perfil");
		
		// Novos leads atribuídos ao vendedor (últimas 24h)
		$sqlLeads = "SELECT Email as id, Email as nome, Email as email, TelefonePrincipalFINAL as telefone, 'BASE_LEADS' as origem, DATAOBS as data_cadastro
				FROM BASE_LEADS 
				WHERE CodigoVendedor = ? AND DATAOBS >= DATE_SUB(NOW(), INTERVAL 1 DAY)
				ORDER BY DATAOBS DESC";
		$stmtLeads = $pdo->prepare($sqlLeads);
		$stmtLeads->execute([$usuarioId]);
		$novosLeads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);
		
		// Debug: Log do resultado da query
		error_log("DEBUG: Query novos leads retornou " . count($novosLeads) . " resultados para usuario $usuarioId");

		foreach ($novosLeads as $row) {
			$chave = 'novo_lead:' . $row['id'];
			if (isset($lidasMapa[$chave])) continue;
			
			$items[] = [
				'key' => $chave,
				'type' => 'lead',
				'title' => 'Novo lead atribuído',
				'message' => 'Lead: ' . ($row['nome'] ?: '—') . ' - ' . ($row['origem'] ?: 'Origem não informada'),
				'url' => 'leads.php',
				'time' => $row['data_cadastro']
			];
		}

		// Leads com status "quente" ou "proposta" (últimas 48h)
		$sqlLeadsQuentes = "SELECT Email as id, Email as nome, 'ativo' as status, DATAOBS as ultima_atividade
				FROM BASE_LEADS 
				WHERE CodigoVendedor = ? AND MARCAOPROSPECT = 'SAI PROSPECT'
				AND DATAOBS >= DATE_SUB(NOW(), INTERVAL 2 DAY)
				ORDER BY DATAOBS DESC";
		$stmtLeadsQuentes = $pdo->prepare($sqlLeadsQuentes);
		$stmtLeadsQuentes->execute([$usuarioId]);
		$leadsQuentes = $stmtLeadsQuentes->fetchAll(PDO::FETCH_ASSOC);
		
		// Debug: Log do resultado da query
		error_log("DEBUG: Query leads quentes retornou " . count($leadsQuentes) . " resultados para usuario $usuarioId");

		foreach ($leadsQuentes as $row) {
			$chave = 'lead_quente:' . $row['id'];
			if (isset($lidasMapa[$chave])) continue;
			
			$statusDisplay = ucfirst($row['status']);
			$items[] = [
				'key' => $chave,
				'type' => 'opportunity',
				'title' => 'Lead ' . $statusDisplay,
				'message' => 'Lead: ' . ($row['nome'] ?: '—') . ' - Status: ' . $statusDisplay,
				'url' => 'leads.php',
				'time' => $row['ultima_atividade']
			];
		}

		// Lembretes de follow-up (leads sem contato há mais de 3 dias)
		$sqlFollowUp = "SELECT Email as id, Email as nome, DATAOBS as ultimo_contato, 'ativo' as status
				FROM BASE_LEADS 
				WHERE CodigoVendedor = ? AND DATAOBS < DATE_SUB(NOW(), INTERVAL 3 DAY)
				AND MARCAOPROSPECT = 'SAI PROSPECT'
				ORDER BY DATAOBS ASC";
		$stmtFollowUp = $pdo->prepare($sqlFollowUp);
		$stmtFollowUp->execute([$usuarioId]);
		$followUps = $stmtFollowUp->fetchAll(PDO::FETCH_ASSOC);
		
		// Debug: Log do resultado da query
		error_log("DEBUG: Query follow-up retornou " . count($followUps) . " resultados para usuario $usuarioId");

		foreach ($followUps as $row) {
			$chave = 'follow_up:' . $row['id'];
			if (isset($lidasMapa[$chave])) continue;
			
			$diasSemContato = floor((time() - strtotime($row['ultimo_contato'])) / (60 * 60 * 24));
			$items[] = [
				'key' => $chave,
				'type' => 'reminder',
				'title' => 'Lembrete de Follow-up',
				'message' => 'Lead: ' . ($row['nome'] ?: '—') . ' - Sem contato há ' . $diasSemContato . ' dias',
				'url' => 'leads.php',
				'time' => $row['ultimo_contato']
			];
		}

		// Sistema de metas removido - não é necessário para notificações
		
		// Debug: Log final
		error_log("DEBUG: Seção de vendedores concluída - Total de itens adicionados: " . count($items));
	} else {
		// Debug: Log para verificar por que não está entrando na seção
		error_log("DEBUG: NÃO entrou na seção de vendedores - isAdmin: " . ($isAdmin ? 'true' : 'false') . ", perfil: '$perfil'");
	}


	// Ordenar por data (desc)
	usort($items, function($a, $b) {
		return strcmp($b['time'], $a['time']);
	});



	// Retornar resultado
	$resultado = [
		'success' => true,
		'count' => count($items),
		'items' => $items
	];
	
	// Debug: Log do resultado antes de codificar
	error_log("DEBUG JSON: Tentando codificar " . count($items) . " itens");
	
	$json = json_encode($resultado);
	if ($json === false) {
		error_log("ERRO JSON: " . json_last_error_msg());
		echo json_encode(['success' => false, 'message' => 'Erro ao gerar JSON: ' . json_last_error_msg()]);
	} else {
		echo $json;
	}
}

function marcarLida(PDO $pdo, array $usuario): void {
	$usuarioId = (int)($usuario['id'] ?? $usuario['codigo'] ?? 1);
	$chave = $_POST['evento_chave'] ?? $_GET['evento_chave'] ?? '';
	if ($chave === '') {
		echo json_encode(['success' => false, 'message' => 'Chave do evento ausente']);
		return;
	}
	try {
		$stmt = $pdo->prepare("INSERT IGNORE INTO notificacoes_lidas (usuario_id, evento_chave) VALUES (?, ?)");
		$stmt->execute([$usuarioId, $chave]);
		echo json_encode(['success' => true]);
	} catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
}

function marcarTodas(PDO $pdo, array $usuario): void {
	$usuarioId = (int)($usuario['id'] ?? $usuario['codigo'] ?? 1);
	$keys = $_POST['keys'] ?? [];
	if (!is_array($keys) || empty($keys)) {
		echo json_encode(['success' => false, 'message' => 'Nenhuma notificação informada']);
		return;
	}
	$pdo->beginTransaction();
	try {
		$stmt = $pdo->prepare("INSERT IGNORE INTO notificacoes_lidas (usuario_id, evento_chave) VALUES (?, ?)");
		foreach ($keys as $k) {
			$stmt->execute([$usuarioId, $k]);
		}
		$pdo->commit();
		echo json_encode(['success' => true]);
	} catch (Exception $e) {
		$pdo->rollBack();
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
}

function criarTeste(PDO $pdo, array $usuario): void {
	$usuarioId = (int)($usuario['id'] ?? $usuario['codigo'] ?? 1);
	$titulo = $_POST['titulo'] ?? 'Notificação de Teste';
	$mensagem = $_POST['mensagem'] ?? 'Esta é uma notificação gerada para testes.';
	$url = $_POST['url'] ?? null;
	$stmt = $pdo->prepare("INSERT INTO notificacoes_personalizadas (usuario_id, titulo, mensagem, url) VALUES (?, ?, ?, ?)");
	$ok = $stmt->execute([$usuarioId, $titulo, $mensagem, $url]);
	echo json_encode(['success' => (bool)$ok]);
}

?>


