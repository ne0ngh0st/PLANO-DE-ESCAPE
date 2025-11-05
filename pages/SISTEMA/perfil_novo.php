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

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

$usuario = $_SESSION['usuario'];
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = 'Meu Perfil';

$mensagem = '';
$erro = '';

// Verificar mensagens de sucesso via URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'sidebar_color':
            $mensagem = 'Cor da navbar atualizada com sucesso!';
            break;
        case 'secondary_color':
            $mensagem = 'Cor secundária atualizada com sucesso!';
            break;
        case 'photo_updated':
            $mensagem = 'Foto de perfil atualizada com sucesso!';
            break;
        case 'photo_removed':
            $mensagem = 'Foto de perfil removida com sucesso!';
            break;
    }
}

// Processar alteração de dados pessoais
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_dados'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    
    // Verificar se o email já existe (exceto para o usuário atual)
    $stmt = $pdo->prepare('SELECT ID FROM USUARIOS WHERE EMAIL = ? AND ID != ?');
    $stmt->execute([$email, $_SESSION['usuario']['id']]);
    
    if ($stmt->rowCount() > 0) {
        $erro = 'Este e-mail já está sendo usado por outro usuário.';
    } else {
        // Atualizar os dados
        $stmt = $pdo->prepare('UPDATE USUARIOS SET NOME_EXIBICAO = ?, EMAIL = ?, TELEFONE = ? WHERE ID = ?');
        $stmt->execute([$nome, $email, $telefone, $_SESSION['usuario']['id']]);
        
        // Atualizar a sessão
        $_SESSION['usuario']['nome'] = $nome;
        $_SESSION['usuario']['email'] = $email;
        $_SESSION['usuario']['telefone'] = $telefone;
        
        $mensagem = 'Dados atualizados com sucesso!';
    }
}

// Processar alteração de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_senha'])) {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    // Validações
    if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
        $erro = 'Todos os campos são obrigatórios.';
    } elseif ($nova_senha !== $confirmar_senha) {
        $erro = 'A nova senha e a confirmação não coincidem.';
    } elseif (strlen($nova_senha) < 6) {
        $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
    } else {
        try {
            // Verificar se a senha atual está correta
            $stmt = $pdo->prepare('SELECT PASSWORD_HASH FROM USUARIOS WHERE ID = ?');
            $stmt->execute([$_SESSION['usuario']['id']]);
            $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_db && password_verify($senha_atual, $usuario_db['PASSWORD_HASH'])) {
                // Atualizar a senha
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare('UPDATE USUARIOS SET PASSWORD_HASH = ? WHERE ID = ?');
                $resultado = $stmt->execute([$senha_hash, $_SESSION['usuario']['id']]);
                
                if ($resultado) {
                    $mensagem = 'Senha alterada com sucesso!';
                } else {
                    $erro = 'Erro ao atualizar senha no banco de dados.';
                }
            } else {
                $erro = 'Senha atual incorreta.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro de banco de dados: ' . $e->getMessage();
        }
    }
}

// Processar alteração de cor da navbar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_sidebar_color'])) {
    $sidebar_color = trim($_POST['sidebar_color']);
    
    // Validar cor hexadecimal
    if (preg_match('/^#[a-fA-F0-9]{6}$/', $sidebar_color)) {
        // Atualizar a cor da sidebar
        $stmt = $pdo->prepare('UPDATE USUARIOS SET SIDEBAR_COLOR = ? WHERE ID = ?');
        $resultado = $stmt->execute([$sidebar_color, $_SESSION['usuario']['id']]);
        
        if ($resultado) {
            // Atualizar a sessão
            $_SESSION['usuario']['sidebar_color'] = $sidebar_color;
            $mensagem = 'Cor da navbar atualizada com sucesso! A página será recarregada para aplicar as mudanças.';
            
            // Redirecionar para evitar reload infinito
            header('Location: ' . $_SERVER['PHP_SELF'] . '?success=sidebar_color');
            exit;
        } else {
            $erro = 'Erro ao atualizar cor da navbar no banco de dados.';
        }
    } else {
        $erro = 'Cor inválida. Por favor, selecione uma cor válida.';
    }
}

// Processar upload de foto de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_foto_perfil'])) {
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_perfil'];
        
        // Validar tipo de arquivo
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $erro = 'Tipo de arquivo não permitido. Use apenas JPG, PNG ou GIF.';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $erro = 'Arquivo muito grande. Tamanho máximo permitido: 5MB.';
        } else {
            // Criar pasta de perfis se não existir
            $perfis_dir = ROOT_PATH . '/assets/img/perfis/';
            if (!is_dir($perfis_dir)) {
                mkdir($perfis_dir, 0755, true);
            }
            
            // Gerar nome único para o arquivo
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'perfil_' . $_SESSION['usuario']['id'] . '_' . time() . '.' . $extension;
            $upload_path = $perfis_dir . $new_filename;
            
            
            // Validar e processar a imagem
            if (validateAndProcessImage($file['tmp_name'], $upload_path)) {
                // Remover foto anterior se existir
                $stmt = $pdo->prepare('SELECT FOTO_PERFIL FROM USUARIOS WHERE ID = ?');
                $stmt->execute([$_SESSION['usuario']['id']]);
                $old_photo = $stmt->fetchColumn();
                
                if ($old_photo && file_exists(ROOT_PATH . '/' . $old_photo)) {
                    unlink(ROOT_PATH . '/' . $old_photo);
                }
                
                // Caminho relativo para salvar no banco
                $relative_path = 'assets/img/perfis/' . $new_filename;
                
                // Atualizar no banco de dados
                $stmt = $pdo->prepare('UPDATE USUARIOS SET FOTO_PERFIL = ? WHERE ID = ?');
                $resultado = $stmt->execute([$relative_path, $_SESSION['usuario']['id']]);
                
                if ($resultado) {
                    // Atualizar a sessão
                    $_SESSION['usuario']['foto_perfil'] = $relative_path;
                    
                    $mensagem = 'Foto de perfil atualizada com sucesso! A página será recarregada para aplicar as mudanças.';
                    
                    // Redirecionar para evitar reload infinito
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?success=photo_updated');
                    exit;
                } else {
                    $erro = 'Erro ao salvar foto no banco de dados.';
                    unlink($upload_path); // Remove o arquivo se não salvou no banco
                }
            } else {
                $erro = 'Erro ao processar a imagem.';
            }
        }
    } else {
        $erro = 'Nenhuma foto foi selecionada ou ocorreu um erro no upload.';
    }
}

// Processar alteração de cor secundária
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_secondary_color'])) {
    $secondary_color = trim($_POST['secondary_color']);
    
    // Validar cor hexadecimal
    if (preg_match('/^#[a-fA-F0-9]{6}$/', $secondary_color)) {
        // Atualizar a cor secundária
        $stmt = $pdo->prepare('UPDATE USUARIOS SET SECONDARY_COLOR = ? WHERE ID = ?');
        $resultado = $stmt->execute([$secondary_color, $_SESSION['usuario']['id']]);
        
        if ($resultado) {
            // Atualizar a sessão
            $_SESSION['usuario']['secondary_color'] = $secondary_color;
            $mensagem = 'Cor secundária atualizada com sucesso! A página será recarregada para aplicar as mudanças.';
            
            // Redirecionar para evitar reload infinito
            header('Location: ' . $_SERVER['PHP_SELF'] . '?success=secondary_color');
            exit;
        } else {
            $erro = 'Erro ao atualizar cor secundária no banco de dados.';
        }
    } else {
        $erro = 'Cor inválida. Por favor, selecione uma cor válida.';
    }
}

// Processar remoção de foto de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_foto_perfil'])) {
    // Buscar foto atual
    $stmt = $pdo->prepare('SELECT FOTO_PERFIL FROM USUARIOS WHERE ID = ?');
    $stmt->execute([$_SESSION['usuario']['id']]);
    $current_photo = $stmt->fetchColumn();
    
    if ($current_photo && file_exists(ROOT_PATH . '/' . $current_photo)) {
        unlink(ROOT_PATH . '/' . $current_photo);
    }
    
    // Atualizar no banco de dados
    $stmt = $pdo->prepare('UPDATE USUARIOS SET FOTO_PERFIL = NULL WHERE ID = ?');
    $resultado = $stmt->execute([$_SESSION['usuario']['id']]);
    
    if ($resultado) {
        // Atualizar a sessão
        $_SESSION['usuario']['foto_perfil'] = null;
        $mensagem = 'Foto de perfil removida com sucesso! A página será recarregada para aplicar as mudanças.';
        
        // Redirecionar para evitar reload infinito
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=photo_removed');
        exit;
    } else {
        $erro = 'Erro ao remover foto do banco de dados.';
    }
}

// Função para validar e processar imagem (sem redimensionamento)
function validateAndProcessImage($source, $destination) {
    // Verificar se é uma imagem válida
    $image_info = getimagesize($source);
    if (!$image_info) {
        return false;
    }
    
    $mime_type = $image_info['mime'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    
    if (!in_array($mime_type, $allowed_types)) {
        return false;
    }
    
    // Simplesmente mover o arquivo (sem redimensionamento)
    return move_uploaded_file($source, $destination);
}

// Verificar se a coluna SECONDARY_COLOR existe, se não, criá-la
try {
    $pdo->exec("ALTER TABLE USUARIOS ADD COLUMN SECONDARY_COLOR VARCHAR(7) DEFAULT '#ff8f00'");
} catch (PDOException $e) {
    // Coluna já existe ou outro erro - ignorar
}

// Verificar se a coluna TELEFONE existe, se não, criá-la
try {
    $pdo->exec("ALTER TABLE USUARIOS ADD COLUMN TELEFONE VARCHAR(20) DEFAULT NULL");
} catch (PDOException $e) {
    // Coluna já existe ou outro erro - ignorar
}

// Buscar dados atuais do usuário
$stmt = $pdo->prepare('SELECT NOME_COMPLETO, NOME_EXIBICAO, EMAIL, TELEFONE, PERFIL, COD_VENDEDOR, SIDEBAR_COLOR, FOTO_PERFIL, SECONDARY_COLOR FROM USUARIOS WHERE ID = ?');
$stmt->execute([$_SESSION['usuario']['id']]);
$usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);

$nome_atual = $usuario_db['NOME_EXIBICAO'] ?: $usuario_db['NOME_COMPLETO'];
$email_atual = $usuario_db['EMAIL'];
$telefone_atual = $usuario_db['TELEFONE'];
$perfil_atual = $usuario_db['PERFIL'];
$cod_vendedor_atual = $usuario_db['COD_VENDEDOR'];
$sidebar_color_atual = $usuario_db['SIDEBAR_COLOR'] ?: '#1a237e';
$secondary_color_atual = $usuario_db['SECONDARY_COLOR'] ?: '#ff8f00';
$foto_perfil_atual = $usuario_db['FOTO_PERFIL'];

// Atualizar a sessão com os dados mais recentes do banco
$_SESSION['usuario']['nome'] = $nome_atual;
$_SESSION['usuario']['email'] = $email_atual;
$_SESSION['usuario']['telefone'] = $telefone_atual;
$_SESSION['usuario']['perfil'] = $perfil_atual;
$_SESSION['usuario']['sidebar_color'] = $sidebar_color_atual;
$_SESSION['usuario']['secondary_color'] = $secondary_color_atual;
$_SESSION['usuario']['foto_perfil'] = $foto_perfil_atual;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="secondary-color" content="<?php echo htmlspecialchars($secondary_color_atual); ?>">
	<!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
	<link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
	<link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
	<link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    <style>
        .perfil-container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .perfil-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            text-align: center;
        }

        .perfil-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .perfil-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .perfil-section {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .perfil-section-header {
            background: var(--light-color);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .perfil-section-header h3 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin: 0;
        }

        .perfil-section-content {
            padding: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-item {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .info-item strong {
            color: var(--primary-color);
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item span {
            color: var(--dark-color);
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--gray);
            font-size: 0.85rem;
        }

        .btn-perfil {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-perfil:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(26, 35, 126, 0.3);
        }

        .password-strength {
            margin-top: 0.5rem;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .password-strength.weak {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .password-strength.medium {
            background: #fff3e0;
            color: #ef6c00;
            border: 1px solid #ffcc02;
        }

        .password-strength.strong {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        /* Estilos para personalização da sidebar */
        .color-picker-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .color-picker {
            width: 60px;
            height: 40px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .color-picker:hover {
            border-color: var(--primary-color);
            transform: scale(1.05);
        }

        .color-preview {
            width: 60px;
            height: 40px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .color-input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
        }

        .color-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }

        .color-presets {
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .color-presets h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .preset-colors {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(50px, 1fr));
            gap: 0.75rem;
        }

        .preset-color {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            border: 3px solid transparent;
            position: relative;
        }

        .preset-color:hover {
            transform: scale(1.1);
            border-color: var(--white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .preset-color.selected {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--white), 0 0 0 4px var(--primary-color);
        }

        /* Estilos para foto de perfil */
        .foto-perfil-container {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .foto-perfil-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--gray);
            overflow: hidden;
        }

        .foto-perfil-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .foto-perfil-info {
            flex: 1;
        }

        .foto-perfil-info h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .foto-perfil-info p {
            color: var(--gray);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .foto-perfil-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-foto {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-foto-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-foto-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(26, 35, 126, 0.3);
        }

        .btn-foto-danger {
            background: #dc3545;
            color: white;
        }

        .btn-foto-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(220, 53, 69, 0.3);
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            overflow: hidden;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .file-input-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(26, 35, 126, 0.3);
        }

        .upload-progress {
            margin-top: 1rem;
            padding: 1rem;
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: var(--border-radius);
            display: none;
        }

        .upload-progress.show {
            display: block;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            width: 0%;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .perfil-container {
                padding: 1rem;
            }

            .perfil-header h1 {
                font-size: 2rem;
            }

            .perfil-header {
                height: 200px !important;
                padding: 1rem !important;
            }

            .perfil-header div[style*="color: white"] {
                padding: 1rem !important;
            }

            .perfil-section-content {
                padding: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .foto-perfil-container {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .foto-perfil-actions {
                justify-content: center;
            }
        }

        /* ===== ESTILOS PARA CLASSES ESPECÍFICAS PERFIL_NOVO.PHP ===== */
        .perfil-header {
            background: url('<?php echo base_url('assets/img/autopel.jpg'); ?>') center/cover no-repeat;
            position: relative;
            height: 250px;
            display: flex;
            align-items: center;
            padding: 2rem;
        }

        .perfil-header-content {
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
        }

        .perfil-title {
            color: white;
            margin-bottom: 0.5rem;
        }

        .perfil-subtitle {
            color: white;
            opacity: 0.9;
            margin: 0;
        }

        .admin-tools-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: var(--border-radius);
        }

        .admin-tools-title {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .admin-tools-desc {
            color: var(--gray);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .admin-tools-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .admin-tools-info {
            color: var(--gray);
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        /* Estilos para controle de manutenção */
        #toggle-manutencao-btn {
            transition: all 0.3s ease;
        }

        #toggle-manutencao-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(26, 35, 126, 0.3);
        }

        #toggle-manutencao-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        #manutencao-message {
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .inline-form {
            display: inline;
        }

        .hidden-form {
            display: none;
        }

        .password-strength {
            display: none;
        }

        .preset-color[data-color="#1a237e"] { background-color: #1a237e; }
        .preset-color[data-color="#2e7d32"] { background-color: #2e7d32; }
        .preset-color[data-color="#d32f2f"] { background-color: #d32f2f; }
        .preset-color[data-color="#f57c00"] { background-color: #f57c00; }
        .preset-color[data-color="#7b1fa2"] { background-color: #7b1fa2; }
        .preset-color[data-color="#455a64"] { background-color: #455a64; }
        .preset-color[data-color="#1976d2"] { background-color: #1976d2; }
        .preset-color[data-color="#388e3c"] { background-color: #388e3c; }


        /* ===== ESTILOS PARA PREVIEW DA COR SECUNDÁRIA ===== */
        .color-preview-section {
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .color-preview-section h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .preview-elements {
            display: flex;
            justify-content: center;
        }

        .preview-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            min-width: 300px;
        }

        .preview-header {
            background: #1a1a1a;
            color: white;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
        }

        .preview-content {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: center;
        }

        .preview-btn {
            background: var(--secondary-color, #ff8f00);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .preview-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .preview-badge {
            background: var(--secondary-color, #ff8f00);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .preview-border {
            padding: 1rem;
            border: 2px solid var(--secondary-color, #ff8f00);
            border-radius: var(--border-radius);
            text-align: center;
            color: var(--dark-color);
            font-weight: 500;
        }

        /* (removido: modo escuro na página de perfil) */
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include ROOT_PATH . '/includes/components/navbar.php'; ?>
        
        <?php include ROOT_PATH . '/includes/components/nav_hamburguer.php'; ?>
        <div class="dashboard-container">
            <main class="dashboard-main">
                <div class="perfil-container">
                    <div class="perfil-header">
                        <div class="perfil-header-content">
                            <h1 class="perfil-title"><i class="fas fa-user-circle"></i> Meu Perfil</h1>
                            <p class="perfil-subtitle">Gerencie suas informações pessoais e senha</p>
                        </div>
                    </div>

                    <?php if (!empty($mensagem)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensagem); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($erro)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($erro); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Informações do Usuário -->
                    <div class="perfil-section">
                        <div class="perfil-section-header">
                            <h3><i class="fas fa-info-circle"></i> Informações do Usuário</h3>
                        </div>
                        <div class="perfil-section-content">
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>Nome</strong>
                                    <span><?php echo htmlspecialchars($nome_atual); ?></span>
                                </div>
                                <div class="info-item">
                                    <strong>E-mail</strong>
                                    <span><?php echo htmlspecialchars($email_atual); ?></span>
                                </div>
                                <div class="info-item">
                                    <strong>Telefone</strong>
                                    <span><?php echo htmlspecialchars($telefone_atual ?: 'Não informado'); ?></span>
                                </div>
                                <div class="info-item">
                                    <strong>Perfil</strong>
                                    <span><?php echo htmlspecialchars(ucfirst($perfil_atual)); ?></span>
                                </div>
                                <?php if ($cod_vendedor_atual): ?>
                                <div class="info-item">
                                    <strong>Código do Vendedor</strong>
                                    <span><?php echo htmlspecialchars($cod_vendedor_atual); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Link para teste da carteira (apenas para admins) -->
                            <?php if (strtolower(trim($perfil_atual)) === 'admin'): ?>
                            <div class="admin-tools-section">
                                <h4 class="admin-tools-title">
                                    <i class="fas fa-flask"></i> Ferramentas de Teste
                                </h4>
                                <p class="admin-tools-desc">
                                    Como administrador, você tem acesso a ferramentas de teste para validar novas funcionalidades.
                                </p>
                                <a href="carteira_teste_clientes/carteira_teste_clientes.php" 
                                   class="btn-perfil admin-tools-link"
                                   target="_blank">
                                    <i class="fas fa-external-link-alt"></i> 
                                    Testar Carteira com Validação CLIENTES
                                </a>
                                <p class="admin-tools-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Abre em nova aba - Versão de teste que usa a tabela CLIENTES para validar COD_VENDEDOR
                                </p>
                            </div>
                            
                            <!-- Controle de Modo Manutenção -->
                            <div class="admin-tools-section" style="margin-top: 1.5rem;">
                                <h4 class="admin-tools-title">
                                    <i class="fas fa-cog"></i> Controle de Manutenção
                                </h4>
                                <p class="admin-tools-desc">
                                    Ative o modo manutenção para bloquear acesso de usuários (exceto admins) ao sistema.
                                </p>
                                
                                <?php 
                                $manutencao_flag = __DIR__ . '/../../includes/config/manutencao.flag';
                                $modo_manutencao_ativo = file_exists($manutencao_flag);
                                ?>
                                
                                <div style="display: flex; align-items: center; gap: 1rem; margin-top: 1rem;">
                                    <div style="flex: 1;">
                                        <p style="margin: 0; font-weight: 600; color: var(--dark-color);">
                                            Status: <span id="manutencao-status" style="color: <?php echo $modo_manutencao_ativo ? '#c62828' : '#2e7d32'; ?>;">
                                                <?php echo $modo_manutencao_ativo ? '🔴 ATIVO' : '🟢 DESATIVADO'; ?>
                                            </span>
                                        </p>
                                        <small style="color: var(--gray); display: block; margin-top: 0.25rem;" id="manutencao-info">
                                            <?php echo $modo_manutencao_ativo 
                                                ? 'Usuários não-admin serão redirecionados para página de manutenção' 
                                                : 'Sistema está acessível para todos os usuários'; ?>
                                        </small>
                                    </div>
                                    <button type="button" 
                                            id="toggle-manutencao-btn" 
                                            class="btn-perfil"
                                            style="white-space: nowrap;"
                                            data-status="<?php echo $modo_manutencao_ativo ? 'ativo' : 'inativo'; ?>">
                                        <i class="fas <?php echo $modo_manutencao_ativo ? 'fa-toggle-on' : 'fa-toggle-off'; ?>" id="toggle-icon"></i>
                                        <span id="toggle-text"><?php echo $modo_manutencao_ativo ? 'Desativar' : 'Ativar'; ?> Manutenção</span>
                                    </button>
                                </div>
                                
                                <div id="manutencao-message" style="margin-top: 1rem; display: none;"></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Foto de Perfil -->
                    <div class="perfil-section">
                        <div class="perfil-section-header">
                            <h3><i class="fas fa-camera"></i> Foto de Perfil</h3>
                        </div>
                        <div class="perfil-section-content">
                            <div class="foto-perfil-container">
                                <div class="foto-perfil-preview">
                                    <?php if ($foto_perfil_atual && file_exists(ROOT_PATH . '/' . $foto_perfil_atual)): ?>
                                        <img src="<?php echo htmlspecialchars(base_url($foto_perfil_atual)); ?>" alt="Foto de Perfil">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="foto-perfil-info">
                                    <h4>Minha Foto de Perfil</h4>
                                    <p>Personalize sua conta com uma foto. Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 5MB.</p>
                                    <div class="foto-perfil-actions">
                                        <div class="file-input-wrapper">
                                            <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" onchange="previewImage(this)">
                                            <label for="foto_perfil" class="file-input-label">
                                                <i class="fas fa-upload"></i> Escolher Foto
                                            </label>
                                        </div>
                                        <?php if ($foto_perfil_atual && file_exists(ROOT_PATH . '/' . $foto_perfil_atual)): ?>
                                        <form method="POST" action="" class="inline-form" onsubmit="return confirm('Tem certeza que deseja remover sua foto de perfil?')">
                                            <button type="submit" name="remover_foto_perfil" class="btn-foto btn-foto-danger">
                                                <i class="fas fa-trash"></i> Remover Foto
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" action="" enctype="multipart/form-data" id="fotoForm" class="hidden-form">
                                <input type="file" id="foto_perfil_hidden" name="foto_perfil" accept="image/*" required>
                                <button type="submit" name="alterar_foto_perfil" class="btn-perfil">
                                    <i class="fas fa-save"></i> Salvar Foto
                                </button>
                            </form>
                            
                            <div class="upload-progress" id="uploadProgress">
                                <p><i class="fas fa-spinner fa-spin"></i> Enviando foto...</p>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progressFill"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alterar Dados Pessoais -->
                    <div class="perfil-section">
                        <div class="perfil-section-header">
                            <h3><i class="fas fa-edit"></i> Alterar Dados Pessoais</h3>
                        </div>
                        <div class="perfil-section-content">
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="nome">Nome:</label>
                                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome_atual); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">E-mail:</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_atual); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="telefone">Telefone:</label>
                                    <input type="tel" id="telefone" name="telefone" value="<?php echo htmlspecialchars($telefone_atual); ?>" placeholder="(11) 99999-9999">
                                    <small>Formato: (11) 99999-9999</small>
                                </div>
                                <button type="submit" name="alterar_dados" class="btn-perfil">
                                    <i class="fas fa-save"></i> Atualizar Dados
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Personalizar Navbar -->
                    <div class="perfil-section">
                        <div class="perfil-section-header">
                            <h3><i class="fas fa-palette"></i> Personalizar Navbar</h3>
                        </div>
                        <div class="perfil-section-content">
                            <form method="POST" action="" id="sidebarColorForm">
                                <div class="form-group">
                                    <label for="sidebar_color">Cor da Navbar:</label>
                                    <div class="color-picker-container">
                                        <input type="color" id="sidebar_color" name="sidebar_color" value="<?php echo htmlspecialchars($sidebar_color_atual); ?>" class="color-picker">
                                        <div class="color-preview" id="colorPreview"></div>
                                        <input type="text" id="color_input" value="<?php echo htmlspecialchars($sidebar_color_atual); ?>" class="color-input" placeholder="#1a237e">
                                    </div>
                                    <small>Escolha uma cor para personalizar sua navbar</small>
                                </div>
                                
                                <div class="color-presets">
                                    <h4>Cores Predefinidas:</h4>
                                    <div class="preset-colors">
                                        <div class="preset-color" data-color="#1a237e" title="Azul Autopel"></div>
                                        <div class="preset-color" data-color="#2e7d32" title="Verde"></div>
                                        <div class="preset-color" data-color="#d32f2f" title="Vermelho"></div>
                                        <div class="preset-color" data-color="#f57c00" title="Laranja"></div>
                                        <div class="preset-color" data-color="#7b1fa2" title="Roxo"></div>
                                        <div class="preset-color" data-color="#455a64" title="Cinza Escuro"></div>
                                        <div class="preset-color" data-color="#1976d2" title="Azul Claro"></div>
                                        <div class="preset-color" data-color="#388e3c" title="Verde Escuro"></div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="alterar_sidebar_color" class="btn-perfil">
                                    <i class="fas fa-save"></i> Salvar Cor da Navbar
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Personalizar Cor Secundária -->
                    <div class="perfil-section">
                        <div class="perfil-section-header">
                            <h3><i class="fas fa-brush"></i> Personalizar Cor Secundária</h3>
                        </div>
                        <div class="perfil-section-content">
                            <form method="POST" action="" id="secondaryColorForm">
                                <div class="form-group">
                                    <label for="secondary_color">Cor Secundária:</label>
                                    <div class="color-picker-container">
                                        <input type="color" id="secondary_color" name="secondary_color" value="<?php echo htmlspecialchars($secondary_color_atual); ?>" class="color-picker">
                                        <div class="color-preview" id="secondaryColorPreview"></div>
                                        <input type="text" id="secondary_color_input" value="<?php echo htmlspecialchars($secondary_color_atual); ?>" class="color-input" placeholder="#ff8f00">
                                    </div>
                                    <small>Escolha uma cor para personalizar elementos de destaque (botões, bordas, destaques)</small>
                                </div>
                                
                                <div class="color-presets">
                                    <h4>Cores Predefinidas:</h4>
                                    <div class="preset-colors">
                                        <div class="preset-color" data-color="#ff8f00" title="Laranja Padrão"></div>
                                        <div class="preset-color" data-color="#ff9800" title="Laranja Claro"></div>
                                        <div class="preset-color" data-color="#ffb74d" title="Laranja Suave"></div>
                                        <div class="preset-color" data-color="#ffcc80" title="Amarelo Laranja"></div>
                                        <div class="preset-color" data-color="#4caf50" title="Verde"></div>
                                        <div class="preset-color" data-color="#2196f3" title="Azul"></div>
                                        <div class="preset-color" data-color="#9c27b0" title="Roxo"></div>
                                        <div class="preset-color" data-color="#e91e63" title="Rosa"></div>
                                        <div class="preset-color" data-color="#00bcd4" title="Ciano"></div>
                                        <div class="preset-color" data-color="#8bc34a" title="Verde Lima"></div>
                                        <div class="preset-color" data-color="#ff5722" title="Laranja Vermelho"></div>
                                        <div class="preset-color" data-color="#795548" title="Marrom"></div>
                                    </div>
                                </div>
                                
                                <!-- Preview da cor secundária -->
                                <div class="color-preview-section">
                                    <h4>Preview da Cor Secundária:</h4>
                                    <div class="preview-elements">
                                        <div class="preview-card">
                                            <div class="preview-header">Header do Card</div>
                                            <div class="preview-content">
                                                <button class="preview-btn">Botão</button>
                                                <span class="preview-badge">Badge</span>
                                                <div class="preview-border">Elemento com Borda</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="alterar_secondary_color" class="btn-perfil">
                                    <i class="fas fa-save"></i> Salvar Cor Secundária
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Alterar Senha -->
                    <div class="perfil-section">
                        <div class="perfil-section-header">
                            <h3><i class="fas fa-lock"></i> Alterar Senha</h3>
                        </div>
                        <div class="perfil-section-content">
                            <form method="POST" action="" id="senhaForm">
                                <div class="form-group">
                                    <label for="senha_atual">Senha Atual:</label>
                                    <input type="password" id="senha_atual" name="senha_atual" required>
                                </div>
                                <div class="form-group">
                                    <label for="nova_senha">Nova Senha:</label>
                                    <input type="password" id="nova_senha" name="nova_senha" minlength="6" required>
                                    <small>A senha deve ter pelo menos 6 caracteres</small>
                                    <div id="password-strength" class="password-strength"></div>
                                </div>
                                <div class="form-group">
                                    <label for="confirmar_senha">Confirmar Nova Senha:</label>
                                    <input type="password" id="confirmar_senha" name="confirmar_senha" minlength="6" required>
                                </div>
                                <button type="submit" name="alterar_senha" class="btn-perfil">
                                    <i class="fas fa-key"></i> Alterar Senha
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    
    <script>
        // Validação de força da senha
        document.getElementById('nova_senha').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                feedback = 'Senha fraca';
                strengthDiv.className = 'password-strength weak';
            } else if (strength <= 4) {
                feedback = 'Senha média';
                strengthDiv.className = 'password-strength medium';
            } else {
                feedback = 'Senha forte';
                strengthDiv.className = 'password-strength strong';
            }
            
            strengthDiv.textContent = feedback;
            strengthDiv.style.display = 'block';
        });

        // Validação de confirmação de senha
        document.getElementById('confirmar_senha').addEventListener('input', function() {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = this.value;
            
            if (confirmarSenha && novaSenha !== confirmarSenha) {
                this.setCustomValidity('As senhas não coincidem');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validação e formatação do telefone
        document.getElementById('telefone').addEventListener('input', function() {
            let telefone = this.value.replace(/\D/g, ''); // Remove caracteres não numéricos
            
            if (telefone.length > 0) {
                if (telefone.length <= 10) {
                    // Formato: (11) 9999-9999
                    telefone = telefone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                } else if (telefone.length <= 11) {
                    // Formato: (11) 99999-9999
                    telefone = telefone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                }
            }
            
            this.value = telefone;
        });

        // Validação do formulário
        document.getElementById('senhaForm').addEventListener('submit', function(e) {
            const novaSenha = document.getElementById('nova_senha').value;
            const confirmarSenha = document.getElementById('confirmar_senha').value;
            
            if (novaSenha !== confirmarSenha) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                return false;
            }
        });

        // Funcionalidade para personalização da navbar
        document.addEventListener('DOMContentLoaded', function() {
            const colorPicker = document.getElementById('sidebar_color');
            const colorInput = document.getElementById('color_input');
            const colorPreview = document.getElementById('colorPreview');
            const presetColors = document.querySelectorAll('#sidebarColorForm .preset-color');
            
            // Função para atualizar a cor
            function updateColor(color) {
                colorPicker.value = color;
                colorInput.value = color.toUpperCase();
                colorPreview.style.backgroundColor = color;
                
                // Remover seleção anterior
                presetColors.forEach(preset => preset.classList.remove('selected'));
                
                // Adicionar seleção à cor correspondente
                const matchingPreset = document.querySelector(`#sidebarColorForm [data-color="${color}"]`);
                if (matchingPreset) {
                    matchingPreset.classList.add('selected');
                }
            }
            
            // Event listener para o seletor de cor nativo
            colorPicker.addEventListener('input', function() {
                updateColor(this.value);
            });
            
            // Event listener para o input de texto
            colorInput.addEventListener('input', function() {
                let color = this.value.trim();
                if (color && !color.startsWith('#')) {
                    color = '#' + color;
                }
                
                if (/^#[a-fA-F0-9]{6}$/.test(color)) {
                    updateColor(color);
                }
            });
            
            // Event listeners para as cores predefinidas
            presetColors.forEach(preset => {
                preset.addEventListener('click', function() {
                    const color = this.getAttribute('data-color');
                    updateColor(color);
                });
            });
            
            // Validação do formulário de cor
            document.getElementById('sidebarColorForm').addEventListener('submit', function(e) {
                const color = colorInput.value.trim();
                if (!/^#[a-fA-F0-9]{6}$/.test(color)) {
                    e.preventDefault();
                    alert('Por favor, selecione uma cor válida!');
                    return false;
                }
            });
            
            // Marcar a cor atual como selecionada
            const currentColor = '<?php echo $sidebar_color_atual; ?>';
            updateColor(currentColor);
        });

        // Funcionalidade para personalização da cor secundária
        document.addEventListener('DOMContentLoaded', function() {
            const secondaryColorPicker = document.getElementById('secondary_color');
            const secondaryColorInput = document.getElementById('secondary_color_input');
            const secondaryColorPreview = document.getElementById('secondaryColorPreview');
            const secondaryPresetColors = document.querySelectorAll('#secondaryColorForm .preset-color');
            
            // Função para atualizar a cor secundária
            function updateSecondaryColor(color) {
                secondaryColorPicker.value = color;
                secondaryColorInput.value = color.toUpperCase();
                secondaryColorPreview.style.backgroundColor = color;
                
                // Atualizar preview em tempo real
                updatePreviewElements(color);
                
                // Remover seleção anterior
                secondaryPresetColors.forEach(preset => preset.classList.remove('selected'));
                
                // Adicionar seleção à cor correspondente
                const matchingPreset = document.querySelector(`#secondaryColorForm [data-color="${color}"]`);
                if (matchingPreset) {
                    matchingPreset.classList.add('selected');
                }
            }
            
            // Função para atualizar elementos de preview
            function updatePreviewElements(color) {
                const previewBtn = document.querySelector('.preview-btn');
                const previewBadge = document.querySelector('.preview-badge');
                const previewBorder = document.querySelector('.preview-border');
                
                if (previewBtn) {
                    previewBtn.style.background = color;
                }
                if (previewBadge) {
                    previewBadge.style.background = color;
                }
                if (previewBorder) {
                    previewBorder.style.borderColor = color;
                }
            }
            
            // Event listener para o seletor de cor nativo
            secondaryColorPicker.addEventListener('input', function() {
                updateSecondaryColor(this.value);
            });
            
            // Event listener para o input de texto
            secondaryColorInput.addEventListener('input', function() {
                let color = this.value.trim();
                if (color && !color.startsWith('#')) {
                    color = '#' + color;
                }
                
                if (/^#[a-fA-F0-9]{6}$/.test(color)) {
                    updateSecondaryColor(color);
                }
            });
            
            // Event listeners para as cores predefinidas
            secondaryPresetColors.forEach(preset => {
                preset.addEventListener('click', function() {
                    const color = this.getAttribute('data-color');
                    updateSecondaryColor(color);
                });
            });
            
            // Validação do formulário de cor secundária
            document.getElementById('secondaryColorForm').addEventListener('submit', function(e) {
                const color = secondaryColorInput.value.trim();
                if (!/^#[a-fA-F0-9]{6}$/.test(color)) {
                    e.preventDefault();
                    alert('Por favor, selecione uma cor válida!');
                    return false;
                }
                
                // Salvar cor secundária no localStorage para aplicação imediata
                try {
                    localStorage.setItem('userSecondaryColor', color);
                    
                    // (removido: integração com modo escuro)
                    
                    console.log('Cor secundária salva:', color);
                } catch (error) {
                    console.warn('Não foi possível salvar cor secundária no localStorage:', error);
                }
            });
            
            // Marcar a cor atual como selecionada
            const currentSecondaryColor = '<?php echo $secondary_color_atual; ?>';
            updateSecondaryColor(currentSecondaryColor);
        });

        // Funcionalidade para foto de perfil
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validar tamanho do arquivo (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Arquivo muito grande. Tamanho máximo permitido: 5MB.');
                    input.value = '';
                    return;
                }
                
                // Validar tipo de arquivo
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Tipo de arquivo não permitido. Use apenas JPG, PNG ou GIF.');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Atualizar preview
                    const preview = document.querySelector('.foto-perfil-preview');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                    
                    // Mostrar formulário de upload
                    const fotoForm = document.getElementById('fotoForm');
                    const hiddenInput = document.getElementById('foto_perfil_hidden');
                    
                    // Criar um novo FileList com o arquivo selecionado
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    hiddenInput.files = dataTransfer.files;
                    
                    fotoForm.style.display = 'block';
                    
                    // Scroll para o formulário
                    fotoForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                };
                reader.readAsDataURL(file);
            }
        }

        // Gerenciar upload com progress
        document.getElementById('fotoForm').addEventListener('submit', function(e) {
            const progressDiv = document.getElementById('uploadProgress');
            const progressFill = document.getElementById('progressFill');
            
            // Mostrar progress
            progressDiv.classList.add('show');
            
            // Simular progress (já que não temos upload real com progress)
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 30;
                if (progress > 90) progress = 90;
                progressFill.style.width = progress + '%';
            }, 200);
            
            // Limpar interval após submit
            setTimeout(() => {
                clearInterval(interval);
                progressFill.style.width = '100%';
            }, 1000);
        });

        // Controle de Modo Manutenção (apenas para admins)
        const toggleManutencaoBtn = document.getElementById('toggle-manutencao-btn');
        if (toggleManutencaoBtn) {
            toggleManutencaoBtn.addEventListener('click', function() {
                const currentStatus = this.getAttribute('data-status');
                const newAcao = currentStatus === 'ativo' ? 'desativar' : 'ativar';
                const btnText = this.querySelector('#toggle-text');
                const btnIcon = this.querySelector('#toggle-icon');
                const statusSpan = document.getElementById('manutencao-status');
                const infoSpan = document.getElementById('manutencao-info');
                const messageDiv = document.getElementById('manutencao-message');
                
                // Desabilitar botão durante a requisição
                this.disabled = true;
                btnText.textContent = 'Processando...';
                
                // Criar FormData
                const formData = new FormData();
                formData.append('acao', newAcao);
                
                // Fazer requisição AJAX
                fetch('<?php echo base_url('includes/ajax/toggle_manutencao_ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Atualizar UI
                        const novoStatus = data.status;
                        this.setAttribute('data-status', novoStatus);
                        
                        if (novoStatus === 'ativo') {
                            statusSpan.textContent = '🔴 ATIVO';
                            statusSpan.style.color = '#c62828';
                            infoSpan.textContent = 'Usuários não-admin serão redirecionados para página de manutenção';
                            btnText.textContent = 'Desativar Manutenção';
                            btnIcon.className = 'fas fa-toggle-on';
                            messageDiv.className = 'alert alert-success';
                            messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        } else {
                            statusSpan.textContent = '🟢 DESATIVADO';
                            statusSpan.style.color = '#2e7d32';
                            infoSpan.textContent = 'Sistema está acessível para todos os usuários';
                            btnText.textContent = 'Ativar Manutenção';
                            btnIcon.className = 'fas fa-toggle-off';
                            messageDiv.className = 'alert alert-success';
                            messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        }
                        
                        messageDiv.style.display = 'block';
                        
                        // Esconder mensagem após 5 segundos
                        setTimeout(() => {
                            messageDiv.style.display = 'none';
                        }, 5000);
                    } else {
                        messageDiv.className = 'alert alert-danger';
                        messageDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + data.message;
                        messageDiv.style.display = 'block';
                        
                        // Restaurar texto do botão
                        btnText.textContent = currentStatus === 'ativo' ? 'Desativar Manutenção' : 'Ativar Manutenção';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    messageDiv.className = 'alert alert-danger';
                    messageDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao alterar modo manutenção. Tente novamente.';
                    messageDiv.style.display = 'block';
                    
                    // Restaurar texto do botão
                    btnText.textContent = currentStatus === 'ativo' ? 'Desativar Manutenção' : 'Ativar Manutenção';
                })
                .finally(() => {
                    this.disabled = false;
                });
            });
        }
    </script>
</body>
</html>
