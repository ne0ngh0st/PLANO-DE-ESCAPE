<?php
session_start();
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Incluir conexão com banco de dados
require_once '../config/conexao.php';

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_script_questions':
            getScriptQuestions($pdo);
            break;
            
        case 'salvar_resposta':
            salvarResposta($pdo);
            break;
            
        case 'listar':
            listarPerguntas($pdo);
            break;
            
        case 'adicionar':
            adicionarPergunta($pdo);
            break;
            
        case 'editar':
            editarPergunta($pdo);
            break;
            
        case 'excluir':
            excluirPergunta($pdo);
            break;
            
        case 'buscar':
            buscarPergunta($pdo);
            break;
            
        case 'atualizar_ordem':
            atualizarOrdem($pdo);
            break;
            
        case 'duplicar':
            duplicarPergunta($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}

function getScriptQuestions($pdo) {
    // Script de perguntas pré-definido conforme especificação do usuário
    $script = [
        [
            'id' => 1,
            'pergunta' => 'Conseguiu contato?',
            'tipo' => 'radio',
            'opcoes' => ['Sim', 'Não'],
            'obrigatoria' => true,
            'ordem' => 1
        ],
        [
            'id' => 2,
            'pergunta' => 'Perfil do contato atendido:',
            'tipo' => 'radio',
            'opcoes' => [
                'Decisor (proprietário / gestor)',
                'Influenciador (compra mas não decide)',
                'Funcionário sem poder de decisão',
                'Outro'
            ],
            'obrigatoria' => true,
            'ordem' => 2,
            'condicional' => ['pergunta_anterior' => 1, 'resposta_anterior' => 'Sim'],
            'campos_condicionais' => [
                'Funcionário sem poder de decisão' => [
                    ['tipo' => 'text', 'label' => 'Quem decide as compras?', 'nome' => 'quem_decide'],
                    ['tipo' => 'date', 'label' => 'Quando ele estará disponível?', 'nome' => 'quando_disponivel']
                ]
            ]
        ],
        [
            'id' => 3,
            'pergunta' => 'Grau de interesse demonstrado:',
            'tipo' => 'radio',
            'opcoes' => [
                'Alto — quer avançar agora',
                'Médio — pediu mais informações',
                'Baixo — não demonstrou interesse',
                'Nenhum — não quer continuar'
            ],
            'obrigatoria' => true,
            'ordem' => 3,
            'condicional' => ['pergunta_anterior' => 1, 'resposta_anterior' => 'Sim']
        ],
        [
            'id' => 4,
            'pergunta' => 'Situação da negociação:',
            'tipo' => 'radio',
            'opcoes' => [
                'Agendado retorno / reunião',
                'Enviar proposta',
                'Negociação em andamento',
                'Venda fechada',
                'Encerrado (perdido)'
            ],
            'obrigatoria' => true,
            'ordem' => 4,
            'condicional' => ['pergunta_anterior' => 1, 'resposta_anterior' => 'Sim'],
            'campos_condicionais' => [
                'Agendado retorno / reunião' => [
                    ['tipo' => 'date', 'label' => 'Data', 'nome' => 'data_agendamento'],
                    ['tipo' => 'select', 'label' => 'Período', 'nome' => 'periodo_agendamento', 'opcoes' => ['Manhã', 'Tarde', 'Noite']]
                ]
            ]
        ],
        [
            'id' => 5,
            'pergunta' => 'Produto/serviço de interesse:',
            'tipo' => 'radio',
            'opcoes' => ['Bobina', 'Etiqueta', 'Termoticket', 'A4', 'Outros'],
            'obrigatoria' => true,
            'ordem' => 5,
            'condicional' => ['pergunta_anterior' => 1, 'resposta_anterior' => 'Sim']
        ],
        [
            'id' => 6,
            'pergunta' => 'Motivo da compra ou interesse:',
            'tipo' => 'radio',
            'opcoes' => ['Preço', 'Qualidade', 'Urgência', 'Indicação', 'Outro'],
            'obrigatoria' => true,
            'ordem' => 6,
            'condicional' => ['pergunta_anterior' => 1, 'resposta_anterior' => 'Sim']
        ],

        [
            'id' => 7,
            'pergunta' => 'Próximo passo definido:',
            'tipo' => 'radio',
            'opcoes' => [
                'Aguardando retorno do cliente',
                'Recontato agendado',
                'Envio de material / proposta',
                'Visita presencial',
                'Encerrar contato'
            ],
            'obrigatoria' => true,
            'ordem' => 7,
            'condicional' => ['pergunta_anterior' => 1, 'resposta_anterior' => 'Sim'],
            'campos_condicionais' => [
                'Recontato agendado' => [
                    ['tipo' => 'date', 'label' => 'Data', 'nome' => 'data_recontato'],
                    ['tipo' => 'select', 'label' => 'Período', 'nome' => 'periodo_recontato', 'opcoes' => ['Manhã', 'Tarde', 'Noite']]
                ]
            ]
        ],
        [
            'id' => 8,
            'pergunta' => 'Observação final:',
            'tipo' => 'radio',
            'opcoes' => [
                'Cliente receptivo',
                'Cliente frio',
                'Cliente agressivo',
                'Cliente confuso',
                'Outro',
                'Não'
            ],
            'obrigatoria' => true,
            'ordem' => 8,
            'condicional' => ['pergunta_anterior' => 1, 'resposta_anterior' => 'Sim'],
            'campos_condicionais' => [
                'Outro' => [
                    ['tipo' => 'text', 'label' => 'Especificar', 'nome' => 'observacao_outro']
                ]
            ]
        ],
        [
            'id' => 9,
            'pergunta' => 'Motivo (NÃO conta como uma das 5-8 perguntas):',
            'tipo' => 'radio',
            'opcoes' => ['Só chama', 'Número errado', 'Caixa postal', 'Não atende', 'Outro'],
            'obrigatoria' => true,
            'ordem' => 9,
            'condicional' => ['pergunta_anterior' => 1, 'resposta_anterior' => 'Não'],
            'campos_condicionais' => [
                'Outro' => [
                    ['tipo' => 'text', 'label' => 'Especificar', 'nome' => 'motivo_outro']
                ]
            ]
        ]
    ];
    
    echo json_encode(['success' => true, 'script' => $script]);
}

function salvarResposta($pdo) {
    $cliente_id = $_POST['cliente_id'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $respostas = $_POST['respostas'] ?? [];
    $usuario_id = $_SESSION['usuario']['id'] ?? '';
    
    if (empty($cliente_id) || empty($respostas)) {
        echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não fornecidos']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Inserir registro principal da ligação
        $sql = "INSERT INTO LIGACOES (cliente_id, telefone, usuario_id, data_ligacao) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cliente_id, $telefone, $usuario_id]);
        $ligacao_id = $pdo->lastInsertId();
        
        // Inserir respostas
        foreach ($respostas as $pergunta_id => $resposta) {
            if (is_array($resposta)) {
                $resposta_principal = $resposta['resposta'] ?? '';
                $campos_adicionais = json_encode($resposta['campos_adicionais'] ?? []);
            } else {
                $resposta_principal = $resposta;
                $campos_adicionais = '{}';
            }
            
            $sql = "INSERT INTO RESPOSTAS_LIGACAO (ligacao_id, pergunta_id, resposta, campos_adicionais) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ligacao_id, $pergunta_id, $resposta_principal, $campos_adicionais]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Respostas salvas com sucesso']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
    }
}

function listarPerguntas($pdo) {
    try {
        $sql = "SELECT * FROM PERGUNTAS_LIGACAO ORDER BY ordem ASC";
        $stmt = $pdo->query($sql);
        $perguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log para debug
        error_log("Perguntas encontradas: " . count($perguntas));
        
        echo json_encode(['success' => true, 'perguntas' => $perguntas, 'count' => count($perguntas)]);
    } catch (Exception $e) {
        error_log("Erro ao listar perguntas: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao carregar perguntas: ' . $e->getMessage()]);
    }
}

function adicionarPergunta($pdo) {
    $pergunta = $_POST['pergunta'] ?? '';
    $tipo = $_POST['tipo'] ?? 'texto';
    $ordem = $_POST['ordem'] ?? 1;
    $obrigatoria = isset($_POST['obrigatoria']) ? 1 : 0;
    $opcoes = $_POST['opcoes'] ?? '';
    
    if (empty($pergunta)) {
        echo json_encode(['success' => false, 'message' => 'Pergunta é obrigatória']);
        return;
    }
    
    // Processar opções se fornecidas
    $opcoes_json = '[]';
    if (!empty($opcoes)) {
        $opcoes_array = json_decode($opcoes, true);
        if (is_array($opcoes_array) && !empty($opcoes_array)) {
            $opcoes_json = $opcoes;
        } else {
            error_log("Erro ao processar opções na adição: " . $opcoes);
        }
    }
    
    $sql = "INSERT INTO PERGUNTAS_LIGACAO (pergunta, tipo, ordem, obrigatoria, opcoes, data_criacao) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pergunta, $tipo, $ordem, $obrigatoria, $opcoes_json]);
    
    echo json_encode(['success' => true, 'message' => 'Pergunta adicionada com sucesso']);
}

function editarPergunta($pdo) {
    $id = $_POST['id'] ?? '';
    $pergunta = $_POST['pergunta'] ?? '';
    $tipo = $_POST['tipo'] ?? 'texto';
    $ordem = $_POST['ordem'] ?? 1;
    $obrigatoria = isset($_POST['obrigatoria']) ? 1 : 0;
    $opcoes = $_POST['opcoes'] ?? '';
    $condicional = $_POST['condicional'] ?? '';
    
    if (empty($id) || empty($pergunta)) {
        echo json_encode(['success' => false, 'message' => 'ID e pergunta são obrigatórios']);
        return;
    }
    
    // Processar opções se fornecidas
    $opcoes_json = '[]';
    if (!empty($opcoes)) {
        $opcoes_array = json_decode($opcoes, true);
        if (is_array($opcoes_array) && !empty($opcoes_array)) {
            $opcoes_json = $opcoes;
        } else {
            error_log("Erro ao processar opções na edição: " . $opcoes);
        }
    }
    
    // Processar condicional
    $condicional_json = '';
    if (!empty($condicional)) {
        $condicional_array = json_decode($condicional, true);
        if (is_array($condicional_array)) {
            $condicional_json = $condicional;
        }
    }
    
    $sql = "UPDATE PERGUNTAS_LIGACAO SET pergunta = ?, tipo = ?, ordem = ?, obrigatoria = ?, opcoes = ?, condicional = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pergunta, $tipo, $ordem, $obrigatoria, $opcoes_json, $condicional_json, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Pergunta atualizada com sucesso']);
}

function excluirPergunta($pdo) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID é obrigatório']);
        return;
    }
    
    $sql = "DELETE FROM PERGUNTAS_LIGACAO WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Pergunta excluída com sucesso']);
}

function buscarPergunta($pdo) {
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID é obrigatório']);
        return;
    }
    
    $sql = "SELECT * FROM PERGUNTAS_LIGACAO WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $pergunta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pergunta) {
        echo json_encode(['success' => false, 'message' => 'Pergunta não encontrada']);
        return;
    }
    
    echo json_encode(['success' => true, 'pergunta' => $pergunta]);
}

function atualizarOrdem($pdo) {
    $id = $_POST['id'] ?? '';
    $ordem = $_POST['ordem'] ?? 1;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID é obrigatório']);
        return;
    }
    
    $sql = "UPDATE PERGUNTAS_LIGACAO SET ordem = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ordem, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Ordem atualizada com sucesso']);
}

function duplicarPergunta($pdo) {
    $id = $_POST['id'] ?? '';
    $pergunta = $_POST['pergunta'] ?? '';
    $tipo = $_POST['tipo'] ?? 'texto';
    $ordem = $_POST['ordem'] ?? 1;
    $obrigatoria = isset($_POST['obrigatoria']) ? 1 : 0;
    $opcoes = $_POST['opcoes'] ?? '';
    $condicional = $_POST['condicional'] ?? '';
    
    if (empty($id) || empty($pergunta)) {
        echo json_encode(['success' => false, 'message' => 'ID e pergunta são obrigatórios']);
        return;
    }
    
    // Processar opções se fornecidas
    $opcoes_json = '[]';
    if (!empty($opcoes)) {
        $opcoes_array = json_decode($opcoes, true);
        if (is_array($opcoes_array) && !empty($opcoes_array)) {
            $opcoes_json = $opcoes;
        }
    }
    
    // Processar condicional
    $condicional_json = '';
    if (!empty($condicional)) {
        $condicional_array = json_decode($condicional, true);
        if (is_array($condicional_array)) {
            $condicional_json = $condicional;
        }
    }
    
    $sql = "INSERT INTO PERGUNTAS_LIGACAO (pergunta, tipo, ordem, obrigatoria, opcoes, condicional, data_criacao) VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pergunta, $tipo, $ordem, $obrigatoria, $opcoes_json, $condicional_json]);
    
    echo json_encode(['success' => true, 'message' => 'Pergunta duplicada com sucesso']);
}
?>
