<?php
/**
 * GESTÃO DE CONTRATOS - CRUD AJAX
 * Endpoint para adicionar, editar e deletar contratos
 */

require_once '../config.php';
session_start();

// Verificar se usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Definir resposta como JSON
header('Content-Type: application/json');

try {
    // ADICIONAR NOVO CONTRATO
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['acao'])) {
        
        // Validar campos obrigatórios
        $campos_obrigatorios = [
            'razao_social',
            'cnpj',
            'gerenciador',
            'tipo_licitacao',
            'numero_pregao',
            'valor_global',
            'data_inicio_vigencia',
            'data_termino_vigencia'
        ];
        
        foreach ($campos_obrigatorios as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode([
                    'success' => false,
                    'message' => "O campo " . ucfirst(str_replace('_', ' ', $campo)) . " é obrigatório"
                ]);
                exit;
            }
        }
        
        // Limpar CNPJ (remover pontos, barras e traços)
        $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj']);
        
        // Preparar dados
        $razao_social = trim($_POST['razao_social']);
        $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
        $sigla = trim($_POST['sigla'] ?? '');
        $gerenciador = trim($_POST['gerenciador']);
        $responsavel_interno = trim($_POST['responsavel_interno'] ?? '');
        $tipo_licitacao = trim($_POST['tipo_licitacao']);
        $numero_pregao = trim($_POST['numero_pregao']);
        $termo_contrato = trim($_POST['termo_contrato'] ?? '');
        $valor_global = floatval($_POST['valor_global']);
        $valor_consumido = floatval($_POST['valor_consumido'] ?? 0);
        $data_inicio = $_POST['data_inicio_vigencia'];
        $data_termino = $_POST['data_termino_vigencia'];
        $usuario_id = $_SESSION['usuario']['id'];
        
        // Calcular saldo e percentual
        $saldo = $valor_global - $valor_consumido;
        $percentual = $valor_global > 0 ? ($valor_consumido / $valor_global) * 100 : 0;
        
        // Inserir no banco
        $sql = "INSERT INTO contratos (
                    cnpj,
                    razao_social,
                    nome_fantasia,
                    sigla,
                    gerenciador,
                    responsavel_interno,
                    tipo_licitacao,
                    numero_pregao,
                    termo_contrato,
                    valor_global,
                    valor_consumido,
                    saldo_contrato,
                    percentual_consumo,
                    data_inicio_vigencia,
                    data_termino_vigencia,
                    status,
                    usuario_cadastro_id,
                    data_cadastro
                ) VALUES (
                    :cnpj,
                    :razao_social,
                    :nome_fantasia,
                    :sigla,
                    :gerenciador,
                    :responsavel_interno,
                    :tipo_licitacao,
                    :numero_pregao,
                    :termo_contrato,
                    :valor_global,
                    :valor_consumido,
                    :saldo_contrato,
                    :percentual_consumo,
                    :data_inicio,
                    :data_termino,
                    'Vigente',
                    :usuario_id,
                    NOW()
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'cnpj' => $cnpj,
            'razao_social' => $razao_social,
            'nome_fantasia' => $nome_fantasia,
            'sigla' => $sigla,
            'gerenciador' => $gerenciador,
            'responsavel_interno' => $responsavel_interno,
            'tipo_licitacao' => $tipo_licitacao,
            'numero_pregao' => $numero_pregao,
            'termo_contrato' => $termo_contrato,
            'valor_global' => $valor_global,
            'valor_consumido' => $valor_consumido,
            'saldo_contrato' => $saldo,
            'percentual_consumo' => $percentual,
            'data_inicio' => $data_inicio,
            'data_termino' => $data_termino,
            'usuario_id' => $usuario_id
        ]);
        
        $contrato_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Contrato adicionado com sucesso!',
            'contrato_id' => $contrato_id
        ]);
        exit;
    }
    
    // EDITAR CONTRATO
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['acao'] === 'editar') {
        
        if (empty($_POST['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID do contrato não informado']);
            exit;
        }
        
        $id = intval($_POST['id']);
        $valor_consumido = floatval($_POST['valor_consumido'] ?? 0);
        
        // Buscar valor global do contrato
        $sql_get = "SELECT valor_global FROM contratos WHERE id = :id";
        $stmt_get = $pdo->prepare($sql_get);
        $stmt_get->execute(['id' => $id]);
        $contrato = $stmt_get->fetch(PDO::FETCH_ASSOC);
        
        if (!$contrato) {
            echo json_encode(['success' => false, 'message' => 'Contrato não encontrado']);
            exit;
        }
        
        $valor_global = floatval($contrato['valor_global']);
        $saldo = $valor_global - $valor_consumido;
        $percentual = $valor_global > 0 ? ($valor_consumido / $valor_global) * 100 : 0;
        
        // Atualizar
        $sql = "UPDATE contratos 
                SET valor_consumido = :valor_consumido,
                    saldo_contrato = :saldo,
                    percentual_consumo = :percentual,
                    data_atualizacao = NOW()
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'valor_consumido' => $valor_consumido,
            'saldo' => $saldo,
            'percentual' => $percentual,
            'id' => $id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contrato atualizado com sucesso!'
        ]);
        exit;
    }
    
    // DELETAR CONTRATO
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['acao'] === 'deletar') {
        
        if (empty($_POST['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID do contrato não informado']);
            exit;
        }
        
        $id = intval($_POST['id']);
        
        // Ao invés de deletar, marcar como encerrado
        $sql = "UPDATE contratos 
                SET status = 'Encerrado',
                    data_atualizacao = NOW()
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contrato encerrado com sucesso!'
        ]);
        exit;
    }
    
    // Método não suportado
    echo json_encode([
        'success' => false,
        'message' => 'Método não suportado'
    ]);
    
} catch (PDOException $e) {
    error_log("Erro no CRUD de contratos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar solicitação: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Erro geral no CRUD de contratos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro inesperado: ' . $e->getMessage()
    ]);
}
?>

