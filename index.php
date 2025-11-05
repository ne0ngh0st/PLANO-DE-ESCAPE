<?php
// ===== VERIFICAÇÃO DE MODO MANUTENÇÃO =====
// NOTA: A verificação de manutenção acontece APÓS o login bem-sucedido
// Usuários podem ver a página de login normalmente, mas serão redirecionados
// após login se não forem admin (exceto desenvolvedor com bypass)

// Função para encontrar o arquivo conexao.php
function encontrarConexao() {
    $caminhos = [
        __DIR__ . '/includes/config/conexao.php',
        dirname(__FILE__) . '/includes/config/conexao.php',
        'includes/config/conexao.php',
        './includes/config/conexao.php',
        '../includes/config/conexao.php',
        '../../includes/config/conexao.php'
    ];
    
    foreach ($caminhos as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return false;
}

// Tentar incluir o arquivo de conexão
$caminho_conexao = encontrarConexao();
if ($caminho_conexao) {
    require_once $caminho_conexao;
} else {
    // Se não conseguir encontrar, mostrar debug detalhado
    echo "<h2>Erro: Arquivo conexao.php não encontrado</h2>";
    echo "<p><strong>Diretório atual:</strong> " . getcwd() . "</p>";
    echo "<p><strong>Arquivo atual:</strong> " . __FILE__ . "</p>";
    echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</p>";
    
    echo "<h3>Caminhos testados:</h3>";
    $caminhos_teste = [
        __DIR__ . '/includes/config/conexao.php',
        dirname(__FILE__) . '/includes/config/conexao.php',
        'includes/config/conexao.php',
        './includes/config/conexao.php',
        '../includes/config/conexao.php',
        '../../includes/config/conexao.php'
    ];
    
    foreach ($caminhos_teste as $path) {
        echo "- <code>$path</code>: " . (file_exists($path) ? "✓ Existe" : "✗ Não existe") . "<br>";
    }
    
    echo "<h3>Estrutura de diretórios:</h3>";
    echo "Conteúdo do diretório atual:<br>";
    $arquivos = scandir('.');
    foreach ($arquivos as $arquivo) {
        if ($arquivo != '.' && $arquivo != '..') {
            echo "- $arquivo<br>";
        }
    }
    
    if (is_dir('includes')) {
        echo "<br>Conteúdo do diretório includes:<br>";
        $arquivos_includes = scandir('includes');
        foreach ($arquivos_includes as $arquivo) {
            if ($arquivo != '.' && $arquivo != '..') {
                echo "- $arquivo<br>";
            }
        }
    }
    
    die();
}

// Carregar config.php (que gerencia sessão automaticamente)
require_once __DIR__ . '/includes/config/config.php';

// Debug temporário
if (isset($_GET['debug'])) {
    echo "<h2>Debug do Index</h2>";
    echo "<p>Sessão:</p>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    echo "<p>Cookies:</p>";
    echo "<pre>";
    print_r($_COOKIE);
    echo "</pre>";
    echo "<p><a href='index.php'>Limpar Debug</a></p>";
    exit;
}

// Verificar se já existe um cookie de "manter logado"
if (!isset($_SESSION['usuario']) && isset($_COOKIE['manter_logado'])) {
    $token = $_COOKIE['manter_logado'];
    
    // Buscar o token no banco de dados (apenas tokens válidos)
    $stmt = $pdo->prepare('SELECT u.* FROM USUARIOS u 
                          INNER JOIN login_tokens lt ON u.ID = lt.usuario_id 
                          WHERE lt.token = ? AND lt.expira > NOW() AND lt.ativo = 1 AND u.ATIVO = 1');
    $stmt->execute([$token]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        // Atualizar último login
        $stmt_update_login = $pdo->prepare('UPDATE USUARIOS SET ULTIMO_LOGIN = NOW() WHERE ID = ?');
        $stmt_update_login->execute([$usuario['ID']]);
        
        // Login automático bem-sucedido
        $_SESSION['usuario'] = [
            'id' => $usuario['ID'],
            'email' => $usuario['EMAIL'],
            'nome' => $usuario['NOME_EXIBICAO'] ?: $usuario['NOME_COMPLETO'],
            'perfil' => $usuario['PERFIL'],
            'cod_vendedor' => $usuario['COD_VENDEDOR'],
            'sidebar_color' => $usuario['SIDEBAR_COLOR'] ?: '#1a237e'
        ];
        
        // Renovar o token
        $novo_token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare('UPDATE login_tokens SET token = ?, expira = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE token = ? AND ativo = 1');
        $stmt->execute([$novo_token, $token]);
        
        // Detectar HTTPS e caminho base para cookie
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                   (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $cookiePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
        setcookie('manter_logado', $novo_token, time() + (30 * 24 * 60 * 60), $cookiePath, '', $isHttps, true);
        
        // Verificar modo manutenção APÓS login (exceto admin e desenvolvedor)
        $manutencao_flag = __DIR__ . '/includes/config/manutencao.flag';
        $modo_manutencao = file_exists($manutencao_flag);
        $perfil_usuario = strtolower(trim($usuario['PERFIL'] ?? ''));
        $is_admin = ($perfil_usuario === 'admin');
        
        // Verificar bypass de desenvolvedor
        $dev_bypass = false;
        if (isset($_COOKIE['dev_bypass']) && $_COOKIE['dev_bypass'] === 'autopel_dev_2024') {
            $dev_bypass = true;
        }
        
        // Se em manutenção e não é admin e não é dev, redirecionar para manutenção
        if ($modo_manutencao && !$is_admin && !$dev_bypass) {
            $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
            header("Location: " . rtrim($basePath, '/') . "/index_manutencao.php");
            exit;
        }
        
        // Após login automático, delegar roteamento por perfil ao home.php
        $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
        header("Location: " . rtrim($basePath, '/') . "/home");
        exit;
    } else {
        // Token inválido, remover cookie
        $cookiePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
        setcookie('manter_logado', '', time() - 3600, $cookiePath);
    }
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $manter_logado = isset($_POST['manter_logado']);
    
    // Debug: Log dos dados recebidos
    error_log("Login attempt - Email: " . $email . ", Manter logado: " . ($manter_logado ? 'true' : 'false'));
    
    $stmt = $pdo->prepare('SELECT * FROM USUARIOS WHERE EMAIL = ? AND ATIVO = 1');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario && password_verify($senha, $usuario['PASSWORD_HASH'])) {
        // Atualizar último login
        $stmt_update_login = $pdo->prepare('UPDATE USUARIOS SET ULTIMO_LOGIN = NOW() WHERE ID = ?');
        $stmt_update_login->execute([$usuario['ID']]);
        
        // Login OK
        $_SESSION['usuario'] = [
            'id' => $usuario['ID'],
            'email' => $usuario['EMAIL'],
            'nome' => $usuario['NOME_EXIBICAO'] ?: $usuario['NOME_COMPLETO'],
            'perfil' => $usuario['PERFIL'],
            'cod_vendedor' => $usuario['COD_VENDEDOR'],
            'sidebar_color' => $usuario['SIDEBAR_COLOR'] ?: '#1a237e'
        ];
        
        // Se "manter logado" foi marcado, criar token
        if ($manter_logado) {
            error_log("Criando token para manter logado - Usuario ID: " . $usuario['ID']);
            $token = bin2hex(random_bytes(32));
            
            // Desativar tokens antigos do usuário (em vez de deletar)
            $stmt = $pdo->prepare('UPDATE login_tokens SET ativo = 0 WHERE usuario_id = ?');
            $stmt->execute([$usuario['ID']]);
            
            // Inserir novo token
            $stmt = $pdo->prepare('INSERT INTO login_tokens (usuario_id, token, expira, ativo) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), 1)');
            $stmt->execute([$usuario['ID'], $token]);
            
            // Criar cookie seguro
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                       (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                       (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $cookiePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
            setcookie('manter_logado', $token, time() + (30 * 24 * 60 * 60), $cookiePath, '', $isHttps, true);
            error_log("Token criado e cookie definido para usuario ID: " . $usuario['ID']);
        } else {
            error_log("Manter logado não foi marcado para usuario ID: " . $usuario['ID']);
        }
        
        // Verificar modo manutenção APÓS login (exceto admin e desenvolvedor)
        $manutencao_flag = __DIR__ . '/includes/config/manutencao.flag';
        $modo_manutencao = file_exists($manutencao_flag);
        $perfil_usuario = strtolower(trim($usuario['PERFIL'] ?? ''));
        $is_admin = ($perfil_usuario === 'admin');
        
        // Verificar bypass de desenvolvedor
        $dev_bypass = false;
        if (isset($_COOKIE['dev_bypass']) && $_COOKIE['dev_bypass'] === 'autopel_dev_2024') {
            $dev_bypass = true;
        }
        
        // Se em manutenção e não é admin e não é dev, redirecionar para manutenção
        if ($modo_manutencao && !$is_admin && !$dev_bypass) {
            $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/Site/');
            header("Location: " . rtrim($basePath, '/') . "/index_manutencao.php");
            exit;
        }
        
        // Após login, delegar roteamento por perfil ao home.php
        $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
        header("Location: " . rtrim($basePath, '/') . "/home");
        exit;
    } else {
        $erro = 'E-mail ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Painel BI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>">
    <style>
        /* Prevenção de flickering */
        body {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        body.loaded {
            opacity: 1;
        }
    </style>
            <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
</head>
<body>
    <div class="login-page">
        <div class="logo-header">
                <img src="<?php echo base_url('assets/img/LOGO AUTOPEL VETOR-01.png'); ?>" alt="Autopel" class="main-logo">
        </div>
        
        <div class="login-content">
            <div class="login-card">
                <h2 class="login-title">Inteligência Comercial Autopel</h2>
                <form method="POST" id="loginForm" class="login-form">
    <input type="email" id="email" name="email" placeholder="Seu e-mail" required>
    <div style="position:relative;">
        <input type="password" id="senha" name="senha" placeholder="Sua senha" required style="padding-right:40px;">
    </div>
    <label style="display:flex;align-items:center;gap:8px;font-size:0.98rem;margin-top:2px;margin-bottom:8px;">
        <input type="checkbox" name="manter_logado" id="manter_logado" style="width:16px;height:16px;"> Manter logado
    </label>
    <button type="submit" class="login-button">Entrar</button>
                </form>
                <?php if (!empty($erro)): ?>
                <p id="erro" class="error-message"><?php echo htmlspecialchars($erro); ?></p>
                <?php endif; ?>
            </div>
            

        
  
    </div>
    
    <script>
        // Adiciona classe loaded quando a página estiver pronta
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');
            
            // Debug: Verificar se o checkbox está funcionando
            const manterLogadoCheckbox = document.getElementById('manter_logado');
            if (manterLogadoCheckbox) {
                console.log('Checkbox "manter logado" encontrado');
                manterLogadoCheckbox.addEventListener('change', function() {
                    console.log('Checkbox "manter logado" alterado:', this.checked);
                });
            } else {
                console.error('Checkbox "manter logado" não encontrado');
            }
            
            // Debug: Verificar se o formulário está funcionando
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    console.log('Formulário enviado');
                    console.log('Manter logado:', manterLogadoCheckbox ? manterLogadoCheckbox.checked : 'checkbox não encontrado');
                });
            }
        });
    </script>
</body>
</html>