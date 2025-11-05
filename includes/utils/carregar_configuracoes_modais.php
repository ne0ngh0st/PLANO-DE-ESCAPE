<?php
require_once __DIR__ . '/../config/conexao.php';

/**
 * Carrega configurações dos modais de ligação
 * @return array Array com todas as configurações
 */
function carregarConfiguracoesModais($pdo) {
    $configuracoes = [
        'modal' => [],
        'exclusao' => []
    ];
    
    try {
        // Verificar se a tabela existe
        $sql = "SHOW TABLES LIKE 'CONFIGURACOES_MODAIS'";
        $stmt = $pdo->query($sql);
        
        if ($stmt->rowCount() > 0) {
            // Carregar configurações do banco
            $sql = "SELECT tipo, chave, valor FROM CONFIGURACOES_MODAIS WHERE ativo = 1 ORDER BY tipo, ordem";
            $stmt = $pdo->query($sql);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($resultados as $row) {
                $configuracoes[$row['tipo']][$row['chave']] = $row['valor'];
            }
        }
    } catch (Exception $e) {
        // Se houver erro, usar configurações padrão
        error_log("Erro ao carregar configurações dos modais: " . $e->getMessage());
    }
    
    // Configurações padrão caso não existam no banco
    $configuracoes['modal'] = array_merge([
        'titulo_ligacao' => 'Roteiro de Ligação',
        'texto_confirmacao' => 'Tem certeza que deseja remover este item da sua carteira?',
        'texto_observacoes' => 'Digite sua observação aqui...',
        'texto_agendamento' => 'Agendar Ligação'
    ], $configuracoes['modal']);
    
    // Motivos de exclusão padrão
    if (empty($configuracoes['exclusao'])) {
        $motivosPadrao = [
            'Cliente Inativo',
            'Falta de Pagamento', 
            'Mudança de Gestão',
            'Falta de Contato',
            'Migração para Concorrência',
            'Falta de Interesse',
            'Problemas Técnicos',
            'Outros'
        ];
        
        foreach ($motivosPadrao as $index => $motivo) {
            $configuracoes['exclusao']['motivo_' . $index] = $motivo;
        }
    }
    
    return $configuracoes;
}

/**
 * Carrega perguntas do roteiro de ligação
 * @return array Array com as perguntas
 */
function carregarPerguntasRoteiro($pdo) {
    try {
        // Verificar se a tabela existe
        $sql = "SHOW TABLES LIKE 'PERGUNTAS_LIGACAO'";
        $stmt = $pdo->query($sql);
        
        if ($stmt->rowCount() > 0) {
            $sql = "SELECT * FROM PERGUNTAS_LIGACAO ORDER BY ordem ASC";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Erro ao carregar perguntas do roteiro: " . $e->getMessage());
    }
    
    // Retornar perguntas padrão se não existir no banco
    return [
        [
            'id' => 1,
            'pergunta' => 'Conseguiu contato?',
            'tipo' => 'radio',
            'opcoes' => json_encode(['Sim', 'Não']),
            'obrigatoria' => 1,
            'ordem' => 1
        ],
        [
            'id' => 2,
            'pergunta' => 'Perfil do contato atendido:',
            'tipo' => 'radio',
            'opcoes' => json_encode([
                'Decisor (proprietário / gestor)',
                'Influenciador (compra mas não decide)',
                'Funcionário sem poder de decisão',
                'Outro'
            ]),
            'obrigatoria' => 1,
            'ordem' => 2
        ],
        [
            'id' => 3,
            'pergunta' => 'Grau de interesse demonstrado:',
            'tipo' => 'radio',
            'opcoes' => json_encode([
                'Alto — quer avançar agora',
                'Médio — pediu mais informações',
                'Baixo — não demonstrou interesse',
                'Nenhum — não quer continuar'
            ]),
            'obrigatoria' => 1,
            'ordem' => 3
        ]
    ];
}

/**
 * Gera HTML para motivos de exclusão
 * @param array $motivos Array com os motivos
 * @param string $id ID do select
 * @param string $classe Classe CSS adicional
 * @return string HTML do select
 */
function gerarSelectMotivos($motivos, $id = 'motivoExclusao', $classe = 'motivo-select') {
    $html = "<select id='{$id}' class='{$classe}' required>";
    $html .= "<option value=''>Selecione um motivo</option>";
    
    foreach ($motivos as $motivo) {
        $valor = strtolower(str_replace([' ', '/'], ['_', '_'], $motivo));
        $html .= "<option value='{$valor}'>{$motivo}</option>";
    }
    
    $html .= "</select>";
    return $html;
}

/**
 * Gera HTML para perguntas do roteiro
 * @param array $perguntas Array com as perguntas
 * @return string HTML das perguntas
 */
function gerarHTMLPerguntas($perguntas) {
    $html = '';
    
    foreach ($perguntas as $pergunta) {
        $obrigatoria = $pergunta['obrigatoria'] ? 'obrigatoria' : '';
        $html .= "<div class='pergunta-item {$obrigatoria}' data-pergunta-id='{$pergunta['id']}'>";
        $html .= "<div class='pergunta-header'>";
        $html .= "<div class='pergunta-texto'>{$pergunta['pergunta']}</div>";
        $html .= "</div>";
        $html .= "<div class='pergunta-opcoes'>";
        
        if ($pergunta['tipo'] === 'radio') {
            $opcoes = json_decode($pergunta['opcoes'], true);
            foreach ($opcoes as $opcao) {
                $html .= "<div class='opcao-item' onclick='selecionarOpcao({$pergunta['id']}, \"{$opcao}\", this)'>";
                $html .= "<input type='radio' name='pergunta_{$pergunta['id']}' value='{$opcao}'>";
                $html .= "<label>{$opcao}</label>";
                $html .= "</div>";
            }
        } elseif ($pergunta['tipo'] === 'text') {
            $html .= "<textarea class='pergunta-texto-livre' placeholder='Digite sua resposta...' onchange='salvarResposta({$pergunta['id']}, this.value)'></textarea>";
        } elseif ($pergunta['tipo'] === 'select') {
            $opcoes = json_decode($pergunta['opcoes'], true);
            $html .= "<select class='pergunta-select' onchange='salvarResposta({$pergunta['id']}, this.value)'>";
            $html .= "<option value=''>Selecione uma opção</option>";
            foreach ($opcoes as $opcao) {
                $html .= "<option value='{$opcao}'>{$opcao}</option>";
            }
            $html .= "</select>";
        }
        
        $html .= "</div>";
        $html .= "</div>";
    }
    
    return $html;
}

// Se chamado diretamente, retornar configurações em JSON
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json');
    
    try {
        $configuracoes = carregarConfiguracoesModais($pdo);
        $perguntas = carregarPerguntasRoteiro($pdo);
        
        echo json_encode([
            'success' => true,
            'configuracoes' => $configuracoes,
            'perguntas' => $perguntas
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao carregar configurações: ' . $e->getMessage()
        ]);
    }
}
?>
