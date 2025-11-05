<?php
require_once 'includes/conexao.php';

// Verificar se o usuário está logado
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$mensagem = '';
$tipo_mensagem = '';

// Buscar dados do vendedor
    $stmt = $pdo->prepare('SELECT * FROM USUARIOS WHERE ID = ?');
$stmt->execute([$id]);
$vendedor = $stmt->fetch();

if (!$vendedor) {
    header('Location: vendedores.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cod_vendedor = trim($_POST['cod_vendedor'] ?? '');
    $uf = trim($_POST['uf'] ?? '');
    $regiao = trim($_POST['regiao'] ?? '');
    $gerente = trim($_POST['gerente'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $perfil = $_POST['perfil'] ?? 'vendedor';
    
    // Validações
    if (empty($nome) || empty($email)) {
        $mensagem = 'Nome e email são obrigatórios.';
        $tipo_mensagem = 'erro';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Email inválido.';
        $tipo_mensagem = 'erro';
    } else {
        // Verificar se o email já existe (exceto para o usuário atual)
        $stmt = $pdo->prepare('SELECT ID FROM USUARIOS WHERE EMAIL = ? AND ID != ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $mensagem = 'Este email já está sendo usado por outro usuário.';
            $tipo_mensagem = 'erro';
        } else {
            // Atualizar vendedor
            $stmt = $pdo->prepare('
                UPDATE USUARIOS 
                SET nome = ?, email = ?, cod_vendedor = ?, UF = ?, regiao = ?, gerente = ?, telefone = ?, cargo = ?, perfil = ?
                WHERE id = ?
            ');
            
            if ($stmt->execute([$nome, $email, $cod_vendedor, $uf, $regiao, $gerente, $telefone, $cargo, $perfil, $id])) {
                $mensagem = 'Vendedor atualizado com sucesso!';
                $tipo_mensagem = 'sucesso';
                
                // Atualizar dados na variável
                $vendedor['nome'] = $nome;
                $vendedor['email'] = $email;
                $vendedor['cod_vendedor'] = $cod_vendedor;
                $vendedor['UF'] = $uf;
                $vendedor['regiao'] = $regiao;
                $vendedor['gerente'] = $gerente;
                $vendedor['telefone'] = $telefone;
                $vendedor['cargo'] = $cargo;
                $vendedor['perfil'] = $perfil;
            } else {
                $mensagem = 'Erro ao atualizar vendedor.';
                $tipo_mensagem = 'erro';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Vendedor - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/img/logo_site.png" type="image/png">
    <link rel="shortcut icon" href="../assets/img/logo_site.png" type="image/png">
    <link rel="apple-touch-icon" href="../assets/img/logo_site.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/estilo.css?v=<?php echo time(); ?>">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 32px;
        }
        .form-title {
            color: #1a237e;
            margin-bottom: 24px;
            text-align: center;
            font-size: 1.5rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            font-size: 1rem;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover { background: #e0a800; }
        .mensagem {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .mensagem.sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .mensagem.erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-card {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .info-card h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: 500;
            color: #555;
        }
        .info-value {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-user-edit"></i> Editar Vendedor</h1>
        
        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <h2 class="form-title">Informações do Vendedor</h2>
            
            <!-- Informações do Sistema -->
            <div class="info-card">
                <h4><i class="fas fa-info-circle"></i> Informações do Sistema</h4>
                <div class="info-item">
                    <span class="info-label">ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($vendedor['id']); ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label">Data de Criação:</span>
                    <span class="info-value">
                        <?php echo $vendedor['created_at'] ? date('d/m/Y H:i', strtotime($vendedor['created_at'])) : 'N/A'; ?>
                    </span>
                </div>
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome *</label>
                        <input type="text" name="nome" id="nome" value="<?php echo htmlspecialchars($vendedor['nome']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($vendedor['email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="cod_vendedor">Código do Vendedor</label>
                        <input type="text" name="cod_vendedor" id="cod_vendedor" value="<?php echo htmlspecialchars($vendedor['cod_vendedor']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="uf">UF</label>
                        <input type="text" name="uf" id="uf" value="<?php echo htmlspecialchars($vendedor['UF']); ?>" maxlength="2" placeholder="SP">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="regiao">Região</label>
                        <input type="text" name="regiao" id="regiao" value="<?php echo htmlspecialchars($vendedor['regiao']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gerente">Gerente</label>
                        <input type="text" name="gerente" id="gerente" value="<?php echo htmlspecialchars($vendedor['gerente']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="text" name="telefone" id="telefone" value="<?php echo htmlspecialchars($vendedor['telefone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="cargo">Cargo</label>
                        <input type="text" name="cargo" id="cargo" value="<?php echo htmlspecialchars($vendedor['cargo']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="perfil">Perfil</label>
                    <select name="perfil" id="perfil">
                        <option value="vendedor" <?php echo $vendedor['perfil'] === 'vendedor' ? 'selected' : ''; ?>>Vendedor</option>
                        <option value="gerente" <?php echo $vendedor['perfil'] === 'gerente' ? 'selected' : ''; ?>>Gerente</option>
                        <option value="admin" <?php echo $vendedor['perfil'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                    </select>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                    <a href="vendedores.php" class="btn btn-warning">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 