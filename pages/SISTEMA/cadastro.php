<?php
define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/includes/config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

// Calcular caminho web base (ex.: /Site) a partir do DOCUMENT_ROOT
$__doc_root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
$__root_path_norm = str_replace('\\', '/', ROOT_PATH);
$__doc_root_norm = str_replace('\\', '/', $__doc_root);
$WEB_BASE = rtrim(str_replace($__doc_root_norm, '', $__root_path_norm), '/');
if ($WEB_BASE === '') { $WEB_BASE = ''; }


if (!isset($_SESSION['usuario'])) {
	$basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
	header('Location: ' . $basePath);
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once ROOT_PATH . '/includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);

// Verificar permissões - usuários com perfil licitação não podem acessar cadastro
$perfilUsuario = strtolower($usuario['perfil'] ?? '');
if ($perfilUsuario === 'licitação') {
	$basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
	header('Location: ' . $basePath . 'contratos');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Função para extrair a raiz do CNPJ (primeiros 8 dígitos)
function extrairRaizCNPJ($cnpj) {
    if (empty($cnpj)) return '';
    $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);
    return substr($cnpj_limpo, 0, 8);
}

// Função para verificar se CNPJ raiz já existe
function verificarCNPJRazExistente($pdo, $cnpj_faturamento) {
    if (empty($cnpj_faturamento)) return false;
    
    $raiz_cnpj = extrairRaizCNPJ($cnpj_faturamento);
    if (strlen($raiz_cnpj) < 8) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ultimo_faturamento 
                               WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = ?");
        $stmt->execute([$raiz_cnpj]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado['total'] > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar CNPJ raiz: " . $e->getMessage());
        return false;
    }
}

// Processar formulário de cadastro de lead manual
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo_cadastro']) && $_POST['tipo_cadastro'] === 'lead') {
    $nome = trim($_POST['nome'] ?? '');
    $razao_social = trim($_POST['razao_social'] ?? '');
    $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $cnpj = trim($_POST['cnpj'] ?? '');
    $inscricao_estadual = trim($_POST['inscricao_estadual'] ?? '');
    $segmento_atuacao = $_POST['segmento_atuacao'] ?? '';
    $valor_estimado = trim($_POST['valor_estimado'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações básicas - campos obrigatórios
    $campos_obrigatorios = [
        'Razão Social' => $razao_social,
        'Telefone' => $telefone,
        'E-mail' => $email,
        'Endereço' => $endereco
    ];
    
    $campos_vazios = [];
    foreach ($campos_obrigatorios as $campo => $valor) {
        if (empty($valor)) {
            $campos_vazios[] = $campo;
        }
    }
    
    if (!empty($campos_vazios)) {
        $mensagem = 'Os seguintes campos são obrigatórios: ' . implode(', ', $campos_vazios);
        $tipo_mensagem = 'erro';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'E-mail inválido.';
        $tipo_mensagem = 'erro';
    } else {
        try {
            // Verificar se a tabela existe, se não, criar
            $stmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS LEADS_MANUAIS (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome VARCHAR(255) NOT NULL,
                    razao_social VARCHAR(255),
                    nome_fantasia VARCHAR(255),
                    email VARCHAR(255),
                    telefone VARCHAR(20),
                    endereco VARCHAR(255),
                    complemento VARCHAR(255),
                    cep VARCHAR(10),
                    bairro VARCHAR(100),
                    municipio VARCHAR(100),
                    estado VARCHAR(2),
                    cnpj VARCHAR(18),
                    inscricao_estadual VARCHAR(20),
                    segmento_atuacao VARCHAR(50),
                    valor_estimado DECIMAL(10,2),
                    observacoes TEXT,
                    codigo_vendedor INT,
                    usuario_cadastrou VARCHAR(100) NOT NULL,
                    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    data_ultima_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    status ENUM('ativo', 'inativo', 'convertido', 'excluido') DEFAULT 'ativo',
                    data_conversao DATETIME NULL,
                    observacoes_conversao TEXT,
                    
                    INDEX idx_email (email),
                    INDEX idx_telefone (telefone),
                    INDEX idx_cnpj (cnpj),
                    INDEX idx_codigo_vendedor (codigo_vendedor),
                    INDEX idx_usuario_cadastrou (usuario_cadastrou),
                    INDEX idx_data_cadastro (data_cadastro),
                    INDEX idx_status (status),
                    INDEX idx_estado (estado),
                    INDEX idx_segmento (segmento_atuacao)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $stmt->execute();
            
            // Buscar código do vendedor do usuário
            $codigo_vendedor = null;
            if (isset($usuario['cod_vendedor'])) {
                $codigo_vendedor = $usuario['cod_vendedor'];
            } else {
                $stmt_cod = $pdo->prepare("SELECT COD_VENDEDOR FROM USUARIOS WHERE EMAIL = ?");
                $stmt_cod->execute([$usuario['email']]);
                $codigo_vendedor = $stmt_cod->fetch(PDO::FETCH_COLUMN);
            }
            
            // Inserir o novo lead
            $stmt = $pdo->prepare("
                INSERT INTO LEADS_MANUAIS (
                    nome, razao_social, nome_fantasia, email, telefone,
                    endereco, cnpj, codigo_vendedor, usuario_cadastrou
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $resultado = $stmt->execute([
                $razao_social, $razao_social, $nome_fantasia, $email, $telefone,
                $endereco, $cnpj, $codigo_vendedor, $usuario['nome']
            ]);
            
            if ($resultado) {
                $lead_id = $pdo->lastInsertId();
                $mensagem = "Lead cadastrado com sucesso! ID: {$lead_id}";
                $tipo_mensagem = 'sucesso';
                
                // Limpar formulário após sucesso
                $_POST = array();
            } else {
                $mensagem = 'Erro ao cadastrar lead. Tente novamente.';
                $tipo_mensagem = 'erro';
            }
            
        } catch (PDOException $e) {
            error_log("Erro ao cadastrar lead manual: " . $e->getMessage());
            $mensagem = 'Erro interno do sistema. Tente novamente mais tarde.';
            $tipo_mensagem = 'erro';
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'POST' && (!isset($_POST['tipo_cadastro']) || $_POST['tipo_cadastro'] !== 'lead')) {
    // Processar formulário de cadastro de cliente
    $cnpj_faturamento = trim($_POST['cnpj_faturamento'] ?? '');
    $razao_social = trim($_POST['razao_social'] ?? '');
    $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $inscricao_estadual = trim($_POST['inscricao_estadual'] ?? '');
    $segmento_atuacao = $_POST['segmento_atuacao'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    // Validações básicas - campos obrigatórios
    $campos_obrigatorios = [
        'CNPJ de Faturamento' => $cnpj_faturamento,
        'Telefone' => $telefone,
        'E-mail' => $email,
        'Inscrição Estadual' => $inscricao_estadual,
        'Segmento de Atuação' => $segmento_atuacao,
        'Endereço' => $endereco,
        'CEP' => $cep,
        'Bairro' => $bairro,
        'Município' => $municipio,
        'Estado' => $estado
    ];
    
    $campos_vazios = [];
    foreach ($campos_obrigatorios as $campo => $valor) {
        if (empty($valor)) {
            $campos_vazios[] = $campo;
        }
    }
    
    if (!empty($campos_vazios)) {
        $mensagem = 'Os seguintes campos são obrigatórios: ' . implode(', ', $campos_vazios);
        $tipo_mensagem = 'erro';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'E-mail inválido.';
        $tipo_mensagem = 'erro';
    } else {
        // Verificar se o CNPJ raiz já existe na carteira
        $cnpj_existente = verificarCNPJRazExistente($pdo, $cnpj_faturamento);
        
        if ($cnpj_existente) {
            $mensagem = 'Este CNPJ já está cadastrado na carteira de clientes. Não é possível cadastrar clientes duplicados.';
            $tipo_mensagem = 'erro';
        } else {
            // Preparar dados para envio por e-mail
            $dados_cliente = [
                'cnpj_faturamento' => $cnpj_faturamento,
                'vendedor_autopel' => $usuario['nome'],
                'cod_vendedor' => $usuario['cod_vendedor'] ?? '',
                'razao_social' => $razao_social,
                'nome_fantasia' => $nome_fantasia,
                'endereco' => $endereco,
                'complemento' => $complemento,
                'cep' => $cep,
                'bairro' => $bairro,
                'municipio' => $municipio,
                'estado' => $estado,
                'telefone' => $telefone,
                'email' => $email,
                'inscricao_estadual' => $inscricao_estadual,
                'segmento_atuacao' => $segmento_atuacao,
                'observacoes' => $observacoes,
                'data_cadastro' => date('d/m/Y H:i:s'),
                'usuario_cadastrou' => $usuario['nome']
            ];
            
            // Gerar botão mailto
            $resultado_email = enviarEmailCadastro($pdo, $dados_cliente);
            
            $mensagem = 'E-Mail criado com sucesso! Escolha uma das opções abaixo para enviar o e-mail.';
            $tipo_mensagem = 'sucesso';
            
            // Salvar o botão mailto na sessão para exibir na página
            $_SESSION['botao_email'] = $resultado_email;
            
            // Limpar formulário após sucesso
            $_POST = array();
        }
    }
}

// Endpoint AJAX para verificar CNPJ
if (isset($_GET['ajax']) && $_GET['ajax'] === 'verificar_cnpj') {
    header('Content-Type: application/json');
    
    $cnpj = trim($_GET['cnpj'] ?? '');
    $existe = false;
    $mensagem_erro = '';
    
    if (!empty($cnpj)) {
        $existe = verificarCNPJRazExistente($pdo, $cnpj);
        if ($existe) {
            $mensagem_erro = 'Este CNPJ já está cadastrado na carteira de clientes. Favor entrar com contato com cadastro@autopel.com caso seja uma filial';
        }
    }
    
    echo json_encode([
        'existe' => $existe,
        'mensagem' => $mensagem_erro
    ]);
    exit;
}

// ===== SOLUÇÃO MAILTO PARA AUTOPEL =====

// Função para gerar link mailto
function gerarMailtoCadastro($dados) {
    $para = 'cadastro.cliente@autopel.com';
    $assunto = 'Novo Cliente Cadastrado - Sistema de Gestão Comercial Autopel';
    
    // Corpo do e-mail com formatação melhorada
    $corpo = "Olá!\n\n";
    $corpo .= "Um novo cliente foi cadastrado no sistema e precisa ser processado.\n\n";
    $corpo .= "ID INTERNO: {$dados['cliente_id']}\n";
    $corpo .= "DADOS DO CLIENTE\n";
    $corpo .= "================\n\n";
    $corpo .= "CNPJ de Faturamento: {$dados['cnpj_faturamento']}\n";
    $corpo .= "Vendedor Autopel: {$dados['vendedor_autopel']}\n";
    if (!empty($dados['cod_vendedor'])) {
        $corpo .= "Código do Vendedor: {$dados['cod_vendedor']}\n";
    }
    $corpo .= "\n";
    
    $corpo .= "DADOS EMPRESARIAIS\n";
    $corpo .= "==================\n";
    $corpo .= "Razão Social: " . ($dados['razao_social'] ?: 'Não informado') . "\n";
    $corpo .= "Nome Fantasia: " . ($dados['nome_fantasia'] ?: 'Não informado') . "\n";
    $corpo .= "Inscrição Estadual: {$dados['inscricao_estadual']}\n";
    $corpo .= "Segmento de Atuação: {$dados['segmento_atuacao']}\n\n";
    
    $corpo .= "ENDEREÇO\n";
    $corpo .= "========\n";
    $corpo .= "Endereço: {$dados['endereco']}\n";
    $corpo .= "Complemento: " . ($dados['complemento'] ?: 'Não informado') . "\n";
    $corpo .= "CEP: {$dados['cep']}\n";
    $corpo .= "Bairro: {$dados['bairro']}\n";
    $corpo .= "Município: {$dados['municipio']}\n";
    $corpo .= "Estado: {$dados['estado']}\n\n";
    
    $corpo .= "CONTATO\n";
    $corpo .= "=======\n";
    $corpo .= "Telefone: {$dados['telefone']}\n";
    $corpo .= "E-mail: {$dados['email']}\n\n";
    
    if (!empty($dados['observacoes'])) {
        $corpo .= "OBSERVAÇÕES\n";
        $corpo .= "===========\n";
        $corpo .= "{$dados['observacoes']}\n\n";
    }
    
    $corpo .= "INFORMAÇÕES DO CADASTRO\n";
    $corpo .= "=======================\n";
    $corpo .= "Data/Hora: {$dados['data_cadastro']}\n";
    $corpo .= "Cadastrado por: {$dados['usuario_cadastrou']}\n\n";
    
    $corpo .= "Atenciosamente,\n";
    $corpo .= "Sistema de Gestão Comercial Autopel\n";
    $corpo .= "© 2025 Autopel - Todos os direitos reservados";
    
    // Codificar para URL (removendo espaços extras e quebras de linha)
    $corpo_limpo = str_replace(["\n\n\n", "\n\n"], "\n", $corpo);
    $assunto_encoded = rawurlencode($assunto);
    // Usar rawurlencode para preservar espaços como %20 em vez de +
    $corpo_encoded = rawurlencode($corpo_limpo);
    
    // Gerar link mailto
    $mailto_link = "mailto:{$para}?subject={$assunto_encoded}&body={$corpo_encoded}";
    
    return $mailto_link;
}

// Função para gerar link do Outlook Web
function gerarLinkOutlookWeb($dados) {
    $para = 'cadastro.cliente@autopel.com';
    $assunto = 'Novo Cliente Cadastrado - Sistema de Gestão Comercial Autopel';
    
    // Corpo do e-mail para Outlook Web
    $corpo = "Olá!\n\n";
    $corpo .= "Um novo cliente foi cadastrado no sistema e precisa ser processado.\n\n";
    $corpo .= "ID INTERNO: {$dados['cliente_id']}\n";
    $corpo .= "DADOS DO CLIENTE\n";
    $corpo .= "================\n\n";
    $corpo .= "CNPJ de Faturamento: {$dados['cnpj_faturamento']}\n";
    $corpo .= "Vendedor Autopel: {$dados['vendedor_autopel']}\n";
    if (!empty($dados['cod_vendedor'])) {
        $corpo .= "Código do Vendedor: {$dados['cod_vendedor']}\n";
    }
    $corpo .= "\n";
    
    $corpo .= "DADOS EMPRESARIAIS\n";
    $corpo .= "==================\n";
    $corpo .= "Razão Social: " . ($dados['razao_social'] ?: 'Não informado') . "\n";
    $corpo .= "Nome Fantasia: " . ($dados['nome_fantasia'] ?: 'Não informado') . "\n";
    $corpo .= "Inscrição Estadual: {$dados['inscricao_estadual']}\n";
    $corpo .= "Segmento de Atuação: {$dados['segmento_atuacao']}\n\n";
    
    $corpo .= "ENDEREÇO\n";
    $corpo .= "========\n";
    $corpo .= "Endereço: {$dados['endereco']}\n";
    $corpo .= "Complemento: " . ($dados['complemento'] ?: 'Não informado') . "\n";
    $corpo .= "CEP: {$dados['cep']}\n";
    $corpo .= "Bairro: {$dados['bairro']}\n";
    $corpo .= "Município: {$dados['municipio']}\n";
    $corpo .= "Estado: {$dados['estado']}\n\n";
    
    $corpo .= "CONTATO\n";
    $corpo .= "=======\n";
    $corpo .= "Telefone: {$dados['telefone']}\n";
    $corpo .= "E-mail: {$dados['email']}\n\n";
    
    if (!empty($dados['observacoes'])) {
        $corpo .= "OBSERVAÇÕES\n";
        $corpo .= "===========\n";
        $corpo .= "{$dados['observacoes']}\n\n";
    }
    
    $corpo .= "INFORMAÇÕES DO CADASTRO\n";
    $corpo .= "=======================\n";
    $corpo .= "Data/Hora: {$dados['data_cadastro']}\n";
    $corpo .= "Cadastrado por: {$dados['usuario_cadastrou']}\n\n";
    
    $corpo .= "Atenciosamente,\n";
    $corpo .= "Sistema de Gestão Comercial Autopel\n";
    $corpo .= "© 2025 Autopel - Todos os direitos reservados";
    
    // Codificar para URL do Outlook Web
    $assunto_encoded = rawurlencode($assunto);
    $corpo_encoded = rawurlencode($corpo);
    
    // Link para Outlook Web
    $outlook_link = "https://outlook.office.com/mail/deeplink/compose?to={$para}&subject={$assunto_encoded}&body={$corpo_encoded}";
    
    return $outlook_link;
}

// Função para gerar HTML do botão com múltiplas opções
function gerarBotaoMailto($dados) {
    $mailto_link = gerarMailtoCadastro($dados);
    $outlook_link = gerarLinkOutlookWeb($dados);
    
    // Corpo do e-mail para cópia manual
    $corpo_manual = "Olá!\n\n";
    $corpo_manual .= "Um novo cliente foi cadastrado no sistema e precisa ser processado.\n\n";
    $corpo_manual .= "ID INTERNO: {$dados['cliente_id']}\n";
    $corpo_manual .= "DADOS DO CLIENTE\n";
    $corpo_manual .= "================\n\n";
    $corpo_manual .= "CNPJ de Faturamento: {$dados['cnpj_faturamento']}\n";
    $corpo_manual .= "Vendedor Autopel: {$dados['vendedor_autopel']}\n";
    if (!empty($dados['cod_vendedor'])) {
        $corpo_manual .= "Código do Vendedor: {$dados['cod_vendedor']}\n";
    }
    $corpo_manual .= "\n";
    
    $corpo_manual .= "DADOS EMPRESARIAIS\n";
    $corpo_manual .= "==================\n";
    $corpo_manual .= "Razão Social: " . ($dados['razao_social'] ?: 'Não informado') . "\n";
    $corpo_manual .= "Nome Fantasia: " . ($dados['nome_fantasia'] ?: 'Não informado') . "\n";
    $corpo_manual .= "Inscrição Estadual: {$dados['inscricao_estadual']}\n";
    $corpo_manual .= "Segmento de Atuação: {$dados['segmento_atuacao']}\n\n";
    
    $corpo_manual .= "ENDEREÇO\n";
    $corpo_manual .= "========\n";
    $corpo_manual .= "Endereço: {$dados['endereco']}\n";
    $corpo_manual .= "Complemento: " . ($dados['complemento'] ?: 'Não informado') . "\n";
    $corpo_manual .= "CEP: {$dados['cep']}\n";
    $corpo_manual .= "Bairro: {$dados['bairro']}\n";
    $corpo_manual .= "Município: {$dados['municipio']}\n";
    $corpo_manual .= "Estado: {$dados['estado']}\n\n";
    
    $corpo_manual .= "CONTATO\n";
    $corpo_manual .= "=======\n";
    $corpo_manual .= "Telefone: {$dados['telefone']}\n";
    $corpo_manual .= "E-mail: {$dados['email']}\n\n";
    
    if (!empty($dados['observacoes'])) {
        $corpo_manual .= "OBSERVAÇÕES\n";
        $corpo_manual .= "===========\n";
        $corpo_manual .= "{$dados['observacoes']}\n\n";
    }
    
    $corpo_manual .= "INFORMAÇÕES DO CADASTRO\n";
    $corpo_manual .= "=======================\n";
    $corpo_manual .= "Data/Hora: {$dados['data_cadastro']}\n";
    $corpo_manual .= "Cadastrado por: {$dados['usuario_cadastrou']}\n\n";
    
    $corpo_manual .= "---\n";
    $corpo_manual .= "Este e-mail foi gerado automaticamente pelo Sistema de Gestão Comercial Autopel\n";
    $corpo_manual .= "© 2025 Autopel - Todos os direitos reservados";
    
    $html = "<div class='email-options-container'>\n";
    $html .= "<div class='email-options-box'>\n";
    $html .= "<h4 class='email-options-title'><i class='fas fa-envelope'></i> Opções para Enviar E-mail</h4>\n";
    $html .= "<p class='email-options-subtitle'>Escolha a opção que funciona melhor no seu ambiente:</p>\n";
    
    // Botão para Outlook Web
    $html .= "<div class='email-option'>\n";
    $html .= "<a href='{$outlook_link}' target='_blank' class='btn-outlook-web'>\n";
    $html .= "    <i class='fas fa-globe'></i> Abrir no Outlook Web\n";
    $html .= "</a>\n";
    $html .= "<span class='email-option-desc'>Recomendado para usuários do Outlook Web</span>\n";
    $html .= "</div>\n";
    
    // Botão mailto tradicional
    $html .= "<div class='email-option'>\n";
    $html .= "<a href='{$mailto_link}' class='btn-mailto'>\n";
    $html .= "    <i class='fas fa-envelope'></i> Abrir Cliente de E-mail\n";
    $html .= "</a>\n";
    $html .= "<span class='email-option-desc'>Para usuários com cliente de e-mail instalado</span>\n";
    $html .= "</div>\n";
    
    // Área de cópia manual
    $html .= "<div class='email-copy-section'>\n";
    $html .= "<h5 class='email-copy-title'><i class='fas fa-copy'></i> Copiar Conteúdo Manualmente</h5>\n";
    $html .= "<p class='email-copy-desc'>Se as opções acima não funcionarem, copie o conteúdo abaixo e cole no seu e-mail:</p>\n";
    $html .= "<div class='email-copy-container'>\n";
    $html .= "<textarea id='email-content' readonly class='email-copy-textarea'>{$corpo_manual}</textarea>\n";
    $html .= "<button onclick='copyToClipboard()' class='btn-copy-email'><i class='fas fa-copy'></i> Copiar</button>\n";
    $html .= "</div>\n";
    $html .= "<div class='email-copy-info'>\n";
    $html .= "<strong>Destinatário:</strong> cadastro.cliente@autopel.com<br>\n";
    $html .= "<strong>Assunto:</strong> Novo Cliente Cadastrado - Sistema de Gestão Comercial Autopel\n";
    $html .= "</div>\n";
    $html .= "</div>\n";
    
    $html .= "</div>\n";
    $html .= "</div>\n";
    
    return $html;
}

// Função para salvar backup
function salvarDadosBackup($dados) {
	$arquivo = ROOT_PATH . '/logs/cadastros_' . date('Y-m-d') . '.txt';
	$diretorio = dirname($arquivo);
    
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true);
    }
    
    $linha = date('Y-m-d H:i:s') . ' | ' . 
             $dados['cnpj_faturamento'] . ' | ' . 
             $dados['razao_social'] . ' | ' . 
             $dados['email'] . ' | ' . 
             $dados['vendedor_autopel'] . ' | MAILTO' . "\n";
    
    return file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
}

// Função para salvar cliente na tabela SQL
function salvarClienteSQL($pdo, $dados) {
    try {
        // Verificar se a tabela existe, se não, criar
        $stmt = $pdo->prepare("
            CREATE TABLE IF NOT EXISTS clientes_para_cadastro (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cnpj_faturamento VARCHAR(18) NOT NULL,
                vendedor_autopel VARCHAR(100) NOT NULL,
                razao_social VARCHAR(255),
                nome_fantasia VARCHAR(255),
                endereco VARCHAR(255) NOT NULL,
                complemento VARCHAR(255),
                cep VARCHAR(10) NOT NULL,
                bairro VARCHAR(100) NOT NULL,
                municipio VARCHAR(100) NOT NULL,
                estado VARCHAR(2) NOT NULL,
                telefone VARCHAR(20) NOT NULL,
                email VARCHAR(100) NOT NULL,
                inscricao_estadual VARCHAR(20) NOT NULL,
                segmento_atuacao VARCHAR(50) NOT NULL,
                observacoes TEXT,
                data_cadastro DATETIME NOT NULL,
                usuario_cadastrou VARCHAR(100) NOT NULL,
                status ENUM('pendente', 'processado', 'rejeitado') DEFAULT 'pendente',
                data_processamento DATETIME NULL,
                observacoes_processamento TEXT,
                INDEX idx_cnpj (cnpj_faturamento),
                INDEX idx_vendedor (vendedor_autopel),
                INDEX idx_status (status),
                INDEX idx_data_cadastro (data_cadastro)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $stmt->execute();
        
        // Inserir o novo cliente
        $stmt = $pdo->prepare("
            INSERT INTO clientes_para_cadastro (
                cnpj_faturamento, vendedor_autopel, razao_social, nome_fantasia,
                endereco, complemento, cep, bairro, municipio, estado,
                telefone, email, inscricao_estadual, segmento_atuacao, observacoes,
                data_cadastro, usuario_cadastrou
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?
            )
        ");
        
        $resultado = $stmt->execute([
            $dados['cnpj_faturamento'],
            $dados['vendedor_autopel'],
            $dados['razao_social'],
            $dados['nome_fantasia'],
            $dados['endereco'],
            $dados['complemento'],
            $dados['cep'],
            $dados['bairro'],
            $dados['municipio'],
            $dados['estado'],
            $dados['telefone'],
            $dados['email'],
            $dados['inscricao_estadual'],
            $dados['segmento_atuacao'],
            $dados['observacoes'],
            $dados['usuario_cadastrou']
        ]);
        
        if ($resultado) {
            $cliente_id = $pdo->lastInsertId();
            error_log("Cliente salvo no SQL - ID: {$cliente_id}, CNPJ: {$dados['cnpj_faturamento']}");
            return $cliente_id;
        } else {
            error_log("Erro ao salvar cliente no SQL - CNPJ: {$dados['cnpj_faturamento']}");
            return false;
        }
        
    } catch (PDOException $e) {
        error_log("Erro SQL ao salvar cliente: " . $e->getMessage());
        return false;
    }
}

// Função principal para substituir enviarEmailCadastro
function enviarEmailCadastro($pdo, $dados) {
    // Salvar backup dos dados
    $backup_salvo = salvarDadosBackup($dados);
    
    // Salvar dados no SQL
    $cliente_id = salvarClienteSQL($pdo, $dados);
    
    // Adicionar o ID do cliente aos dados para usar no e-mail
    $dados['cliente_id'] = $cliente_id ?: 'N/A';
    
    // Gerar e retornar o HTML do botão mailto
    $html_botao = gerarBotaoMailto($dados);
    
    // Registrar no log
    error_log("Cadastro realizado - Mailto gerado para: " . $dados['cnpj_faturamento'] . " (ID: {$cliente_id})");
    
    return $html_botao;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Clientes - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="secondary-color" content="<?php echo htmlspecialchars($usuario['secondary_color'] ?? '#ff8f00'); ?>">
	<link rel="icon" href="<?php echo $WEB_BASE; ?>/assets/img/logo_site.png" type="image/png">
	<link rel="shortcut icon" href="<?php echo $WEB_BASE; ?>/assets/img/logo_site.png" type="image/png">
	<link rel="apple-touch-icon" href="<?php echo $WEB_BASE; ?>/assets/img/logo_site.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
	<!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
	<link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>?v=<?php echo time(); ?>">
	<link rel="stylesheet" href="<?php echo base_url('assets/css/cadastro.css'); ?>?v=<?php echo time(); ?>">
</head>
<body>
    <div class="dashboard-layout">
		<!-- Incluir navbar -->
		<?php include ROOT_PATH . '/includes/components/navbar.php'; ?>
		
		<?php include ROOT_PATH . '/includes/components/nav_hamburguer.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                <!-- Navegação por Abas -->
                <div class="tabs-container">
                    <ul class="nav nav-tabs" id="cadastroTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="cliente-tab" data-bs-toggle="tab" data-bs-target="#cliente" type="button" role="tab" aria-controls="cliente" aria-selected="true">
                                <i class="fas fa-building"></i> Cadastro de Cliente
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="lead-tab" data-bs-toggle="tab" data-bs-target="#lead" type="button" role="tab" aria-controls="lead" aria-selected="false">
                                <i class="fas fa-user-plus"></i> Cadastro de Lead
                            </button>
                        </li>
                    </ul>
                </div>
                
                <?php if ($mensagem): ?>
                    <div class="mensagem <?php echo $tipo_mensagem; ?>">
                        <?php echo htmlspecialchars($mensagem); ?>
                    </div>
                    
                    <?php if (isset($_SESSION['botao_email']) && $tipo_mensagem === 'sucesso'): ?>
                        <?php echo $_SESSION['botao_email']; ?>
                        <?php unset($_SESSION['botao_email']); ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="tab-content" id="cadastroTabContent">
                    <!-- Aba Cliente -->
                    <div class="tab-pane fade show active" id="cliente" role="tabpanel" aria-labelledby="cliente-tab">
                        <div class="info-box">
                            <h4><i class="fas fa-info-circle"></i> Informações - Cadastro de Cliente</h4>
                            <p>Após preencher o formulário, você receberá múltiplas opções para enviar as informações por e-mail para <strong>cadastro.cliente@autopel.com</strong>:</p>
                            <ul class="info-list">
                                <li><strong>Outlook Web:</strong> Abre diretamente no navegador (recomendado)</li>
                                <li><strong>Cliente de E-mail:</strong> Para usuários com aplicativo de e-mail instalado</li>
                                <li><strong>Cópia Manual:</strong> Copie o conteúdo e cole no seu e-mail</li>
                            </ul>
                            <p><strong>Importante:</strong> Todos os clientes cadastrados são automaticamente salvos no banco de dados com um ID único para rastreamento.</p>
                        </div>
                
                <div class="cadastro-container">
                    <h3><i class="fas fa-user-plus"></i> Novo Cliente</h3>
                    <form method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="cnpj_faturamento">CNPJ de Faturamento <span class="required">*</span></label>
                                <input type="text" id="cnpj_faturamento" name="cnpj_faturamento" value="<?php echo htmlspecialchars($_POST['cnpj_faturamento'] ?? ''); ?>" required>
                            </div>
                            

                            
                            <div class="form-group">
                                <label for="razao_social">Razão Social</label>
                                <input type="text" id="razao_social" name="razao_social" value="<?php echo htmlspecialchars($_POST['razao_social'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="nome_fantasia">Nome Fantasia</label>
                                <input type="text" id="nome_fantasia" name="nome_fantasia" value="<?php echo htmlspecialchars($_POST['nome_fantasia'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="endereco">Endereço (rua/número) <span class="required">*</span></label>
                                <input type="text" id="endereco" name="endereco" value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="complemento">Complemento/Referência de Endereço</label>
                                <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($_POST['complemento'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="cep">CEP <span class="required">*</span></label>
                                <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bairro">Bairro <span class="required">*</span></label>
                                <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="municipio">Município <span class="required">*</span></label>
                                <input type="text" id="municipio" name="municipio" value="<?php echo htmlspecialchars($_POST['municipio'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="estado">Estado <span class="required">*</span></label>
                                <select id="estado" name="estado" required>
                                    <option value="">Selecione o estado</option>
                                    <option value="AC" <?php echo ($_POST['estado'] ?? '') === 'AC' ? 'selected' : ''; ?>>Acre</option>
                                    <option value="AL" <?php echo ($_POST['estado'] ?? '') === 'AL' ? 'selected' : ''; ?>>Alagoas</option>
                                    <option value="AP" <?php echo ($_POST['estado'] ?? '') === 'AP' ? 'selected' : ''; ?>>Amapá</option>
                                    <option value="AM" <?php echo ($_POST['estado'] ?? '') === 'AM' ? 'selected' : ''; ?>>Amazonas</option>
                                    <option value="BA" <?php echo ($_POST['estado'] ?? '') === 'BA' ? 'selected' : ''; ?>>Bahia</option>
                                    <option value="CE" <?php echo ($_POST['estado'] ?? '') === 'CE' ? 'selected' : ''; ?>>Ceará</option>
                                    <option value="DF" <?php echo ($_POST['estado'] ?? '') === 'DF' ? 'selected' : ''; ?>>Distrito Federal</option>
                                    <option value="ES" <?php echo ($_POST['estado'] ?? '') === 'ES' ? 'selected' : ''; ?>>Espírito Santo</option>
                                    <option value="GO" <?php echo ($_POST['estado'] ?? '') === 'GO' ? 'selected' : ''; ?>>Goiás</option>
                                    <option value="MA" <?php echo ($_POST['estado'] ?? '') === 'MA' ? 'selected' : ''; ?>>Maranhão</option>
                                    <option value="MT" <?php echo ($_POST['estado'] ?? '') === 'MT' ? 'selected' : ''; ?>>Mato Grosso</option>
                                    <option value="MS" <?php echo ($_POST['estado'] ?? '') === 'MS' ? 'selected' : ''; ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?php echo ($_POST['estado'] ?? '') === 'MG' ? 'selected' : ''; ?>>Minas Gerais</option>
                                    <option value="PA" <?php echo ($_POST['estado'] ?? '') === 'PA' ? 'selected' : ''; ?>>Pará</option>
                                    <option value="PB" <?php echo ($_POST['estado'] ?? '') === 'PB' ? 'selected' : ''; ?>>Paraíba</option>
                                    <option value="PR" <?php echo ($_POST['estado'] ?? '') === 'PR' ? 'selected' : ''; ?>>Paraná</option>
                                    <option value="PE" <?php echo ($_POST['estado'] ?? '') === 'PE' ? 'selected' : ''; ?>>Pernambuco</option>
                                    <option value="PI" <?php echo ($_POST['estado'] ?? '') === 'PI' ? 'selected' : ''; ?>>Piauí</option>
                                    <option value="RJ" <?php echo ($_POST['estado'] ?? '') === 'RJ' ? 'selected' : ''; ?>>Rio de Janeiro</option>
                                    <option value="RN" <?php echo ($_POST['estado'] ?? '') === 'RN' ? 'selected' : ''; ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?php echo ($_POST['estado'] ?? '') === 'RS' ? 'selected' : ''; ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?php echo ($_POST['estado'] ?? '') === 'RO' ? 'selected' : ''; ?>>Rondônia</option>
                                    <option value="RR" <?php echo ($_POST['estado'] ?? '') === 'RR' ? 'selected' : ''; ?>>Roraima</option>
                                    <option value="SC" <?php echo ($_POST['estado'] ?? '') === 'SC' ? 'selected' : ''; ?>>Santa Catarina</option>
                                    <option value="SP" <?php echo ($_POST['estado'] ?? '') === 'SP' ? 'selected' : ''; ?>>São Paulo</option>
                                    <option value="SE" <?php echo ($_POST['estado'] ?? '') === 'SE' ? 'selected' : ''; ?>>Sergipe</option>
                                    <option value="TO" <?php echo ($_POST['estado'] ?? '') === 'TO' ? 'selected' : ''; ?>>Tocantins</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="telefone">Telefone <span class="required">*</span></label>
                                <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">E-mail <span class="required">*</span></label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="inscricao_estadual">Inscrição Estadual <span class="required">*</span></label>
                                <input type="text" id="inscricao_estadual" name="inscricao_estadual" value="<?php echo htmlspecialchars($_POST['inscricao_estadual'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="segmento_atuacao">Segmento de Atuação do Cliente <span class="required">*</span></label>
                                <select id="segmento_atuacao" name="segmento_atuacao" required>
                                    <option value="">Selecione o segmento</option>
                                    <option value="Automotivo" <?php echo ($_POST['segmento_atuacao'] ?? '') === 'Automotivo' ? 'selected' : ''; ?>>Automotivo</option>
                                    <option value="Industrial" <?php echo ($_POST['segmento_atuacao'] ?? '') === 'Industrial' ? 'selected' : ''; ?>>Industrial</option>
                                    <option value="Comercial" <?php echo ($_POST['segmento_atuacao'] ?? '') === 'Comercial' ? 'selected' : ''; ?>>Comercial</option>
                                    <option value="Residencial" <?php echo ($_POST['segmento_atuacao'] ?? '') === 'Residencial' ? 'selected' : ''; ?>>Residencial</option>
                                    <option value="Agrícola" <?php echo ($_POST['segmento_atuacao'] ?? '') === 'Agrícola' ? 'selected' : ''; ?>>Agrícola</option>
                                    <option value="Outros" <?php echo ($_POST['segmento_atuacao'] ?? '') === 'Outros' ? 'selected' : ''; ?>>Outros</option>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="observacoes">Observações</label>
                                <textarea id="observacoes" name="observacoes" placeholder="Informações adicionais sobre o cliente..."><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-cadastrar">
                            <i class="fas fa-paper-plane"></i> Criar E-Mail
                        </button>
                    </form>
                </div>
                    </div>
                    
                    <!-- Aba Lead -->
                    <div class="tab-pane fade" id="lead" role="tabpanel" aria-labelledby="lead-tab">
                        <div class="info-box">
                            <h4><i class="fas fa-info-circle"></i> Informações - Cadastro de Lead</h4>
                            <p>Cadastre leads diretamente na sua carteira. Estes leads ficam disponíveis na aba "Leads Manuais" da página de leads.</p>
                            <ul class="info-list">
                                <li><strong>Armazenamento Seguro:</strong> Leads são salvos em tabela separada da BASE_LEADS</li>
                                <li><strong>Gestão Completa:</strong> Edite, exclua e gerencie seus leads cadastrados</li>
                                <li><strong>Integração:</strong> Leads aparecem automaticamente na sua carteira</li>
                            </ul>
                            <p><strong>Importante:</strong> Todos os leads cadastrados são automaticamente associados ao seu código de vendedor.</p>
                        </div>
                        
                        <div class="cadastro-container">
                            <h3><i class="fas fa-user-plus"></i> Novo Lead</h3>
                            <form method="POST" action="">
                                <input type="hidden" name="tipo_cadastro" value="lead">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="razao_social">Razão Social <span class="required">*</span></label>
                                        <input type="text" id="razao_social" name="razao_social" value="<?php echo htmlspecialchars($_POST['razao_social'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="nome_fantasia">Nome Fantasia</label>
                                        <input type="text" id="nome_fantasia" name="nome_fantasia" value="<?php echo htmlspecialchars($_POST['nome_fantasia'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cnpj">CNPJ</label>
                                        <input type="text" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($_POST['cnpj'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">E-mail <span class="required">*</span></label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="telefone">Telefone <span class="required">*</span></label>
                                        <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="endereco">Endereço <span class="required">*</span></label>
                                        <input type="text" id="endereco" name="endereco" value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn-cadastrar">
                                    <i class="fas fa-save"></i> Cadastrar Lead
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
            <footer class="dashboard-footer">
                <p>© 2025 Autopel - Todos os direitos reservados</p>
                <p class="system-info">Sistema BI v1.0</p>
            </footer>
        </div>
    </div>
    
    <!-- Incluir JavaScript -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo base_url('assets/js/dark-mode.js'); ?>?v=<?php echo time(); ?>"></script>
	<script src="<?php echo base_url('assets/js/cadastro.js'); ?>"></script>
    
</body>
</html>
