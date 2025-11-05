<?php
// Inicializar sessão e conexão usando o mesmo padrão das outras páginas
require_once __DIR__ . '/../config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];

// Verificar permissões - Todos os usuários podem editar clientes
$perfil_permitido = in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'supervisor', 'admin', 'vendedor', 'representante']);

if (!$perfil_permitido) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Você não tem permissão para editar clientes.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['cnpj'])) {
        // Buscar dados do cliente para pré-preenchimento do modal
        $cnpj = $_GET['cnpj'];
        
        // Verificar se a coluna NOME_CONTATO existe
        $check_column = "SHOW COLUMNS FROM ultimo_faturamento LIKE 'NOME_CONTATO'";
        $stmt_check = $pdo->query($check_column);
        $nome_contato_exists = $stmt_check->rowCount() > 0;
        
        // Buscar informações atuais do cliente
        $sql = "SELECT 
                    CNPJ as cnpj,
                    CLIENTE as cliente,
                    NOME_FANTASIA as nome_fantasia,
                    ESTADO as estado,
                    ENDERECO as endereco,
                    TELEFONE as telefone,
                    EMailNFe as email,
                    Descricao1 as segmento";
        
        if ($nome_contato_exists) {
            $sql .= ", NOME_CONTATO as nome_contato";
        } else {
            $sql .= ", '' as nome_contato";
        }
        
        $sql .= " FROM ultimo_faturamento WHERE CNPJ = ? LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cnpj]);
        $cliente_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente_info) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
            exit;
        }
        
        // Verificar se existe edição na tabela CLIENTES_EDICOES
        $sql_edicao = "SELECT * FROM CLIENTES_EDICOES WHERE cnpj_original = ? AND ativo = 1 LIMIT 1";
        $stmt_edicao = $pdo->prepare($sql_edicao);
        $stmt_edicao->execute([$cnpj]);
        $edicao = $stmt_edicao->fetch(PDO::FETCH_ASSOC);
        
        // Aplicar edições se existirem
        if ($edicao) {
            $cliente_info['cliente'] = $edicao['cliente_editado'] ?: $cliente_info['cliente'];
            $cliente_info['nome_fantasia'] = $edicao['nome_fantasia_editado'] ?: $cliente_info['nome_fantasia'];
            $cliente_info['estado'] = $edicao['estado_editado'] ?: $cliente_info['estado'];
            $cliente_info['endereco'] = $edicao['endereco_editado'] ?: $cliente_info['endereco'];
            $cliente_info['telefone'] = $edicao['telefone_editado'] ?: $cliente_info['telefone'];
            $cliente_info['email'] = $edicao['email_editado'] ?: $cliente_info['email'];
            $cliente_info['segmento'] = $edicao['segmento_editado'] ?: $cliente_info['segmento'];
            $cliente_info['nome_contato'] = $edicao['nome_contato_editado'] ?: $cliente_info['nome_contato'];
        }
        
        // Buscar segmentos disponíveis
        $sql_segmentos = "SELECT DISTINCT Descricao1 FROM ultimo_faturamento WHERE Descricao1 IS NOT NULL AND Descricao1 != '' ORDER BY Descricao1";
        $stmt_segmentos = $pdo->query($sql_segmentos);
        $segmentos_disponiveis = $stmt_segmentos->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'cliente' => $cliente_info,
            'segmentos' => $segmentos_disponiveis
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Processar edição do cliente
        $cnpj_original = trim($_POST['cnpj_original'] ?? '');
        $novo_endereco = trim($_POST['endereco'] ?? '');
        $novo_estado = trim($_POST['estado'] ?? '');
        $novo_nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
        $novo_cliente = trim($_POST['cliente'] ?? '');
        $novo_segmento = trim($_POST['segmento'] ?? '');
        $novo_telefone = trim($_POST['telefone'] ?? '');
        $novo_email = trim($_POST['email'] ?? '');
        $novo_nome_contato = trim($_POST['nome_contato'] ?? '');
        
        // Validar dados obrigatórios
        if (empty($cnpj_original) || empty($novo_endereco) || empty($novo_estado) || empty($novo_nome_fantasia) || empty($novo_cliente)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Todos os campos obrigatórios devem ser preenchidos.']);
            exit;
        }
        
        // Verificar se a coluna NOME_CONTATO existe, se não existir, criar
        if (!empty($novo_nome_contato)) {
            $check_column = "SHOW COLUMNS FROM ultimo_faturamento LIKE 'NOME_CONTATO'";
            $stmt_check = $pdo->query($check_column);
            if ($stmt_check->rowCount() == 0) {
                // Coluna não existe, criar
                $create_column = "ALTER TABLE ultimo_faturamento ADD COLUMN NOME_CONTATO VARCHAR(255)";
                $pdo->exec($create_column);
            }
        }
        
        // Verificar se já existe uma edição para este CNPJ
        $sql_verificar_edicao = "SELECT COUNT(*) as total FROM CLIENTES_EDICOES WHERE cnpj_original = ? AND ativo = 1";
        $stmt_verificar_edicao = $pdo->prepare($sql_verificar_edicao);
        $stmt_verificar_edicao->execute([$cnpj_original]);
        $edicao_existe = $stmt_verificar_edicao->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        
        if ($edicao_existe) {
            // Atualizar edição existente
            $sql_atualizar_edicao = "UPDATE CLIENTES_EDICOES SET 
                                     endereco_editado = ?, 
                                     estado_editado = ?, 
                                     nome_fantasia_editado = ?, 
                                     cliente_editado = ?, 
                                     segmento_editado = ?, 
                                     telefone_editado = ?, 
                                     email_editado = ?, 
                                     nome_contato_editado = ?, 
                                     usuario_editor = ?,
                                     data_edicao = NOW()
                                     WHERE cnpj_original = ? AND ativo = 1";
            
            $stmt_edicao = $pdo->prepare($sql_atualizar_edicao);
            $resultado = $stmt_edicao->execute([
                $novo_endereco,
                $novo_estado, 
                $novo_nome_fantasia,
                $novo_cliente,
                $novo_segmento,
                $novo_telefone,
                $novo_email,
                $novo_nome_contato,
                $usuario['email'],
                $cnpj_original
            ]);
        } else {
            // Inserir nova edição
            $sql_inserir_edicao = "INSERT INTO CLIENTES_EDICOES 
                                   (cnpj_original, endereco_editado, estado_editado, nome_fantasia_editado, 
                                    cliente_editado, segmento_editado, telefone_editado, email_editado, 
                                    nome_contato_editado, usuario_editor, ativo, data_edicao) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
            
            $stmt_edicao = $pdo->prepare($sql_inserir_edicao);
            $resultado = $stmt_edicao->execute([
                $cnpj_original,
                $novo_endereco,
                $novo_estado, 
                $novo_nome_fantasia,
                $novo_cliente,
                $novo_segmento,
                $novo_telefone,
                $novo_email,
                $novo_nome_contato,
                $usuario['email']
            ]);
        }
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Cliente atualizado com sucesso!']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar as informações.']);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
    
} catch (PDOException $e) {
    error_log("Erro na edição de cliente: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
