<?php
/**
 * Página para iniciar processo de autorização OAuth com Bling
 */

session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/BlingAPI.php';

// Verificar se é admin ou diretor
if (!isset($_SESSION['usuario']) || !in_array(strtolower($_SESSION['usuario']['perfil']), ['admin', 'diretor'])) {
    header('Location: /Site/home');
    exit;
}

$usuario = $_SESSION['usuario'];
$mensagemErro = $_SESSION['mensagem_erro'] ?? null;
unset($_SESSION['mensagem_erro']);

$bling = new BlingAPI();
$isAutenticado = $bling->isAuthenticated();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorização Bling - Autopel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/Site/assets/css/estilo.css">
    <style>
        .auth-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .auth-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .auth-header p {
            color: #666;
        }
        .bling-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #0066cc, #0099ff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 20px 0;
        }
        .status-badge.connected {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.disconnected {
            background: #f8d7da;
            color: #721c24;
        }
        .auth-steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .auth-steps h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .auth-steps ol {
            margin-left: 20px;
            color: #666;
        }
        .auth-steps ol li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .btn-auth {
            display: block;
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0066cc, #0099ff);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,102,204,0.3);
        }
        .btn-auth i {
            margin-right: 10px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }
        .back-link:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="bling-logo">
                <i class="fas fa-link"></i>
            </div>
            <h1>Integração com Bling ERP</h1>
            <p>Configure a conexão com sua conta do Bling</p>
            
            <?php if ($isAutenticado): ?>
                <span class="status-badge connected">
                    <i class="fas fa-check-circle"></i> Conectado
                </span>
            <?php else: ?>
                <span class="status-badge disconnected">
                    <i class="fas fa-times-circle"></i> Não conectado
                </span>
            <?php endif; ?>
        </div>
        
        <?php if ($mensagemErro): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($mensagemErro); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty(BLING_CLIENT_ID) || empty(BLING_CLIENT_SECRET)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle"></i> 
                <strong>Configuração incompleta!</strong><br>
                Configure as credenciais do Bling no arquivo <code>includes/config/bling_config.php</code>
            </div>
        <?php endif; ?>
        
        <div class="auth-steps">
            <h3><i class="fas fa-list-ol"></i> Como autorizar:</h3>
            <ol>
                <li>Clique no botão abaixo para ser redirecionado ao Bling</li>
                <li>Faça login na sua conta do Bling (se necessário)</li>
                <li>Autorize o acesso do Autopel aos dados</li>
                <li>Você será redirecionado de volta automaticamente</li>
            </ol>
        </div>
        
        <?php if (!empty(BLING_CLIENT_ID) && !empty(BLING_CLIENT_SECRET)): ?>
            <?php if ($isAutenticado): ?>
                <a href="/Site/gestao-ecommerce.php" class="btn-auth" style="background: #28a745;">
                    <i class="fas fa-chart-line"></i> Ir para Gestão E-commerce
                </a>
                <button onclick="renovarAutorizacao()" class="btn-auth" style="background: #ffc107; margin-top: 10px;">
                    <i class="fas fa-sync-alt"></i> Renovar Autorização
                </button>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($bling->getAuthorizationUrl()); ?>" class="btn-auth">
                    <i class="fas fa-shield-alt"></i> Autorizar Acesso ao Bling
                </a>
            <?php endif; ?>
        <?php endif; ?>
        
        <a href="/Site/gestao-ecommerce.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Voltar para Gestão E-commerce
        </a>
    </div>
    
    <script>
        function renovarAutorizacao() {
            if (confirm('Deseja realmente renovar a autorização? Você será redirecionado ao Bling.')) {
                window.location.href = '<?php echo htmlspecialchars($bling->getAuthorizationUrl()); ?>';
            }
        }
    </script>
</body>
</html>


