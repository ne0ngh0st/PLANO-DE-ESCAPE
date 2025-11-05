<?php
session_start();

// Definir header JSON no início para evitar qualquer output HTML
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$response = ['success' => false, 'message' => ''];

try {
    // Incluir conexão local automática com tratamento de erro
    if (!file_exists('conexao_local_auto.php')) {
        throw new Exception('Arquivo de conexão local automática não encontrado');
    }
    
    require_once 'conexao_local_auto.php';
    
    // Verificar se a conexão foi estabelecida
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Erro na conexão com o banco de dados local - nenhuma configuração funcionou');
    }
    
    // Testar a conexão
    $pdo->query("SELECT 1");
    
    // Garantir suporte a respostas: adicionar coluna parent_id se não existir
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM observacoes LIKE 'parent_id'");
        $hasParent = $colCheck && $colCheck->fetch();
        if (!$hasParent) {
            $pdo->exec("ALTER TABLE observacoes ADD COLUMN parent_id INT NULL DEFAULT NULL, ADD INDEX idx_observacoes_parent_id (parent_id)");
        }
    } catch (Exception $e) {
        // Ignorar erro de migração silenciosamente para não quebrar a API
    }
    
    // Garantir tabela de arquivamento de observações excluídas
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS observacoes_excluidas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                observacao_id INT NOT NULL,
                tipo VARCHAR(50) NOT NULL,
                identificador VARCHAR(100) NOT NULL,
                observacao TEXT NOT NULL,
                usuario_id INT NULL,
                usuario_nome VARCHAR(255) NULL,
                parent_id INT NULL,
                data_criacao DATETIME NULL,
                data_exclusao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                usuario_exclusao INT NULL,
                motivo_exclusao VARCHAR(255) NULL,
                INDEX idx_obs_excl_tipo_identificador (tipo, identificador),
                INDEX idx_obs_excl_parent_id (parent_id),
                INDEX idx_obs_excl_observacao_id (observacao_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
    } catch (Exception $e) {
        // Ignorar falha de criação
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro de conexão local: ' . $e->getMessage()
    ]);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'adicionar':
            $tipo = $_POST['tipo'] ?? '';
            $identificador = $_POST['identificador'] ?? '';
            $observacao = trim($_POST['observacao'] ?? '');
            
            if (empty($tipo) || empty($identificador) || empty($observacao)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            if (!in_array($tipo, ['cliente', 'lead'])) {
                throw new Exception('Tipo inválido');
            }
            
            // Usar campos mais genéricos para compatibilidade
            $usuario_id = $usuario['id'] ?? $usuario['codigo'] ?? 1;
            $usuario_nome = $usuario['nome'] ?? $usuario['usuario'] ?? 'Usuário';
            
            $sql = "INSERT INTO observacoes (tipo, identificador, observacao, usuario_id, usuario_nome) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tipo, $identificador, $observacao, $usuario_id, $usuario_nome]);
            
            $response = [
                'success' => true, 
                'message' => 'Observação adicionada com sucesso',
                'id' => $pdo->lastInsertId()
            ];
            break;
            
        case 'listar':
            $tipo = $_POST['tipo'] ?? '';
            $identificador = $_POST['identificador'] ?? '';
            $incluir_respostas = isset($_POST['incluir_respostas']) ? (bool)$_POST['incluir_respostas'] : false;
            
            if (empty($tipo) || empty($identificador)) {
                throw new Exception('Tipo e identificador são obrigatórios');
            }
            
            if ($incluir_respostas) {
                $sqlPais = "SELECT o.*, DATE_FORMAT(o.data_criacao, '%d/%m/%Y %H:%i') as data_formatada 
                            FROM observacoes o 
                            WHERE o.tipo = ? AND o.identificador = ? AND (o.parent_id IS NULL OR o.parent_id = 0)
                              AND NOT EXISTS (
                                  SELECT 1 FROM observacoes_excluidas ox WHERE ox.observacao_id = o.id
                              )
                            ORDER BY o.data_criacao DESC";
                $stmtPais = $pdo->prepare($sqlPais);
                $stmtPais->execute([$tipo, $identificador]);
                $pais = $stmtPais->fetchAll();

                $idsPais = array_column($pais, 'id');
                $respostasPorPai = [];
                if (!empty($idsPais)) {
                    $placeholders = implode(',', array_fill(0, count($idsPais), '?'));
                    $sqlFilhos = "SELECT o.*, DATE_FORMAT(o.data_criacao, '%d/%m/%Y %H:%i') as data_formatada 
                                  FROM observacoes o 
                                  WHERE o.parent_id IN ($placeholders)
                                    AND NOT EXISTS (
                                        SELECT 1 FROM observacoes_excluidas ox WHERE ox.observacao_id = o.id
                                    )
                                  ORDER BY o.data_criacao ASC";
                    $stmtFilhos = $pdo->prepare($sqlFilhos);
                    $stmtFilhos->execute($idsPais);
                    $filhos = $stmtFilhos->fetchAll();
                    foreach ($filhos as $f) {
                        $pid = $f['parent_id'];
                        if (!isset($respostasPorPai[$pid])) $respostasPorPai[$pid] = [];
                        $respostasPorPai[$pid][] = $f;
                    }
                }

                $observacoes = [];
                foreach ($pais as $p) {
                    $p['respostas'] = $respostasPorPai[$p['id']] ?? [];
                    $observacoes[] = $p;
                }
            } else {
                $sql = "SELECT o.*, DATE_FORMAT(o.data_criacao, '%d/%m/%Y %H:%i') as data_formatada 
                        FROM observacoes o 
                        WHERE o.tipo = ? AND o.identificador = ? 
                          AND NOT EXISTS (
                              SELECT 1 FROM observacoes_excluidas ox WHERE ox.observacao_id = o.id
                          )
                        ORDER BY o.data_criacao DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$tipo, $identificador]);
                $observacoes = $stmt->fetchAll();
            }
            
            $response = [
                'success' => true,
                'observacoes' => $observacoes
            ];
            break;
        
        case 'responder':
            $parent_id = $_POST['parent_id'] ?? ($_POST['id_pai'] ?? '');
            $observacao = trim($_POST['observacao'] ?? '');
            
            if (empty($parent_id) || empty($observacao)) {
                throw new Exception('ID do comentário e texto da resposta são obrigatórios');
            }
            
            $stmtPai = $pdo->prepare("SELECT id, tipo, identificador FROM observacoes WHERE id = ?");
            $stmtPai->execute([$parent_id]);
            $pai = $stmtPai->fetch();
            if (!$pai) {
                throw new Exception('Comentário original não encontrado');
            }
            
            $usuario_id = $usuario['id'] ?? $usuario['codigo'] ?? 1;
            $usuario_nome = $usuario['nome'] ?? $usuario['usuario'] ?? 'Usuário';
            
            $sql = "INSERT INTO observacoes (tipo, identificador, observacao, usuario_id, usuario_nome, parent_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$pai['tipo'], $pai['identificador'], $observacao, $usuario_id, $usuario_nome, $parent_id]);
            
            $response = [
                'success' => true,
                'message' => 'Resposta adicionada com sucesso',
                'id' => $pdo->lastInsertId()
            ];
            break;
            
        case 'excluir':
            $id = $_POST['id'] ?? '';
            $motivo_exclusao = trim($_POST['motivo_exclusao'] ?? '');
            
            if (empty($id)) {
                throw new Exception('ID da observação é obrigatório');
            }
            if ($motivo_exclusao === '') {
                throw new Exception('Motivo da exclusão é obrigatório');
            }
            
            // Verificar se o usuário pode excluir (própria observação ou admin)
            $sql = "SELECT id, usuario_id, parent_id FROM observacoes WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $obs = $stmt->fetch();
            
            if (!$obs) {
                throw new Exception('Observação não encontrada');
            }
            
            $usuario_id = $usuario['id'] ?? $usuario['codigo'] ?? 1;
            if (($obs['usuario_id'] ?? null) != $usuario_id && ($usuario['perfil'] ?? '') !== 'admin') {
                throw new Exception('Você só pode excluir suas próprias observações');
            }
            
            // Montar lista de IDs a remover (pai + filhos se aplicável)
            $idsParaRemover = [$id];
            if (empty($obs['parent_id'])) {
                $stmtFilhos = $pdo->prepare("SELECT id FROM observacoes WHERE parent_id = ?");
                $stmtFilhos->execute([$id]);
                $filhos = $stmtFilhos->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($filhos)) {
                    $idsParaRemover = array_merge($idsParaRemover, $filhos);
                }
            }
            
            // Iniciar transação: arquivar e (se possível) excluir
            $pdo->beginTransaction();
            try {
                // Arquivar observações
                $placeholders = implode(',', array_fill(0, count($idsParaRemover), '?'));
                $params = array_merge([
                    $usuario_id,
                    $motivo_exclusao
                ], $idsParaRemover);
                
                $sqlArchive = "INSERT INTO observacoes_excluidas (
                                    observacao_id, tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao, usuario_exclusao, motivo_exclusao
                                )
                                SELECT id, tipo, identificador, observacao, usuario_id, usuario_nome, parent_id, data_criacao, ?, ?
                                FROM observacoes
                                WHERE id IN ($placeholders)";
                $stmtA = $pdo->prepare($sqlArchive);
                $stmtA->execute($params);
                
                // Tentar excluir da tabela principal (pode falhar por permissão)
                try {
                    $stmtD = $pdo->prepare("DELETE FROM observacoes WHERE id IN ($placeholders)");
                    $stmtD->execute($idsParaRemover);
                    $pdo->commit();
                    $response = [
                        'success' => true,
                        'message' => 'Observação movida para lixeira com sucesso'
                    ];
                } catch (PDOException $e) {
                    $errorCode = $e->getCode();
                    $errorMsg = $e->getMessage();
                    $permissionDenied = strpos($errorMsg, 'DELETE command denied') !== false || $errorCode === '42000';
                    if ($permissionDenied) {
                        $pdo->commit();
                        $response = [
                            'success' => true,
                            'message' => 'Exclusão registrada sem remover da origem (sem permissão de DELETE). A observação não aparecerá mais nas listagens.'
                        ];
                    } else {
                        throw $e;
                    }
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'editar':
            $id = $_POST['id'] ?? '';
            $observacao = trim($_POST['observacao'] ?? '');
            
            if (empty($id) || empty($observacao)) {
                throw new Exception('ID e observação são obrigatórios');
            }
            
            // Verificar se o usuário pode editar (própria observação ou admin)
            $sql = "SELECT usuario_id FROM observacoes WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $obs = $stmt->fetch();
            
            if (!$obs) {
                throw new Exception('Observação não encontrada');
            }
            
            $usuario_id = $usuario['id'] ?? $usuario['codigo'] ?? 1;
            if ($obs['usuario_id'] != $usuario_id && $usuario['perfil'] !== 'admin') {
                throw new Exception('Você só pode editar suas próprias observações');
            }
            
            $sql = "UPDATE observacoes SET observacao = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$observacao, $id]);
            
            $response = [
                'success' => true,
                'message' => 'Observação atualizada com sucesso'
            ];
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
?>
