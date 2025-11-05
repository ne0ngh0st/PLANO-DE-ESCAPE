<?php
require_once __DIR__ . '/../../includes/config/config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header('Location: ' . base_url('index.php'));
    exit;
}

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];
$perfilUsuario = strtolower($usuario['perfil'] ?? '');

// Verificar se é admin ou diretor
if ($perfilUsuario !== 'admin' && $perfilUsuario !== 'diretor') {
    header('Location: ' . base_url('home'));
    exit;
}

$current_page = 'register.php';

// Processar ações
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        switch ($acao) {
            case 'criar_usuario':
                $nome_completo = trim($_POST['nome_completo']);
                $nome_exibicao = trim($_POST['nome_exibicao']);
                $email = trim($_POST['email']);
                $perfil = $_POST['perfil'];
                $cod_vendedor = trim($_POST['cod_vendedor']);
                $cod_super = trim($_POST['cod_super']);
                $senha = $_POST['senha'];
                $estado = trim($_POST['estado'] ?? '');
                $tipo_usuario = $_POST['tipo_usuario'] ?? '';
                
                // Validações
                if (empty($nome_completo) || empty($email) || empty($perfil) || empty($senha)) {
                    throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email inválido.');
                }
                
                // Verificar se email já existe
                $stmt_check = $pdo->prepare("SELECT ID FROM USUARIOS WHERE EMAIL = ?");
                $stmt_check->execute([$email]);
                if ($stmt_check->fetch()) {
                    throw new Exception('Este email já está em uso.');
                }
                
                // Hash da senha
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                // Gerar username único baseado no email
                $username = strtolower(explode('@', $email)[0]);
                
                // Verificar se username já existe e gerar um único
                $username_original = $username;
                $counter = 1;
                do {
                    $stmt_check_username = $pdo->prepare("SELECT ID FROM USUARIOS WHERE USERNAME = ?");
                    $stmt_check_username->execute([$username]);
                    if ($stmt_check_username->fetch()) {
                        $username = $username_original . $counter;
                        $counter++;
                    } else {
                        break;
                    }
                } while (true);
                
                // Inserir usuário
                $stmt = $pdo->prepare("
                    INSERT INTO USUARIOS (
                        NOME_COMPLETO, NOME_EXIBICAO, EMAIL, USERNAME, PERFIL, 
                        COD_VENDEDOR, COD_SUPER, PASSWORD_HASH, ATIVO, DATA_CRIACAO,
                        ESTADO, TIPO_USUARIO
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?)
                ");
                
                $stmt->execute([
                    $nome_completo,
                    $nome_exibicao ?: $nome_completo,
                    $email,
                    $username,
                    $perfil,
                    $cod_vendedor ?: null,
                    $cod_super ?: null,
                    $senha_hash,
                    $estado ?: null,
                    $tipo_usuario ?: null
                ]);
                
                $mensagem = 'Usuário criado com sucesso!';
                $tipo_mensagem = 'success';
                break;
                
            case 'alterar_senha':
                $user_id = $_POST['user_id'];
                $nova_senha = $_POST['nova_senha'];
                
                if (empty($nova_senha)) {
                    throw new Exception('Nova senha é obrigatória.');
                }
                
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE USUARIOS SET PASSWORD_HASH = ? WHERE ID = ?");
                $stmt->execute([$senha_hash, $user_id]);
                
                $mensagem = 'Senha alterada com sucesso!';
                $tipo_mensagem = 'success';
                break;
                
            case 'alterar_perfil':
                $user_id = $_POST['user_id'];
                $novo_perfil = $_POST['novo_perfil'];
                $cod_vendedor = trim($_POST['cod_vendedor']);
                $cod_super = trim($_POST['cod_super']);
                
                $stmt = $pdo->prepare("
                    UPDATE USUARIOS 
                    SET PERFIL = ?, COD_VENDEDOR = ?, COD_SUPER = ? 
                    WHERE ID = ?
                ");
                $stmt->execute([
                    $novo_perfil,
                    $cod_vendedor ?: null,
                    $cod_super ?: null,
                    $user_id
                ]);
                
                $mensagem = 'Perfil alterado com sucesso!';
                $tipo_mensagem = 'success';
                break;
                
            case 'toggle_ativo':
                $user_id = $_POST['user_id'];
                $novo_status = $_POST['novo_status'];
                
                $stmt = $pdo->prepare("UPDATE USUARIOS SET ATIVO = ? WHERE ID = ?");
                $stmt->execute([$novo_status, $user_id]);
                
                $mensagem = 'Status do usuário alterado com sucesso!';
                $tipo_mensagem = 'success';
                break;
                
            case 'excluir_usuario':
                $user_id = $_POST['user_id'];
                
                // Verificar se não é o próprio usuário logado
                if ($user_id == $usuario['id']) {
                    throw new Exception('Você não pode excluir seu próprio usuário.');
                }
                
                // Verificar se é o último admin
                $stmt_count_admin = $pdo->prepare("SELECT COUNT(*) FROM USUARIOS WHERE PERFIL = 'admin' AND ATIVO = 1");
                $stmt_count_admin->execute();
                $total_admins = $stmt_count_admin->fetchColumn();
                
                $stmt_user_perfil = $pdo->prepare("SELECT PERFIL FROM USUARIOS WHERE ID = ?");
                $stmt_user_perfil->execute([$user_id]);
                $user_perfil = $stmt_user_perfil->fetchColumn();
                
                if ($user_perfil === 'admin' && $total_admins <= 1) {
                    throw new Exception('Não é possível excluir o último administrador do sistema.');
                }
                
                // Excluir usuário (soft delete - marcar como inativo)
                $stmt = $pdo->prepare("UPDATE USUARIOS SET ATIVO = 0 WHERE ID = ?");
                $stmt->execute([$user_id]);
                
                $mensagem = 'Usuário excluído com sucesso!';
                $tipo_mensagem = 'success';
                break;
        }
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// Buscar todos os usuários (se não for requisição AJAX)
if (!isset($_GET['ajax'])) {
    $stmt = $pdo->prepare("
        SELECT 
            ID, NOME_COMPLETO, NOME_EXIBICAO, EMAIL, PERFIL, 
            COD_VENDEDOR, COD_SUPER, ATIVO, DATA_CRIACAO,
            ULTIMO_LOGIN, ESTADO, TIPO_USUARIO
        FROM USUARIOS 
        ORDER BY NOME_COMPLETO
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Processar requisição AJAX para busca
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar_usuarios') {
    header('Content-Type: application/json');
    
    $busca = trim($_GET['busca'] ?? '');
    $filtro_perfil = $_GET['filtro_perfil'] ?? '';
    $filtro_supervisor = $_GET['filtro_supervisor'] ?? '';
    $filtro_estado = $_GET['filtro_estado'] ?? '';
    $filtro_tipo = $_GET['filtro_tipo'] ?? '';
    $filtro_status = $_GET['filtro_status'] ?? '';
    $filtro_login = $_GET['filtro_login'] ?? '';
    
    // Construir query com filtros
    $where_conditions = [];
    $params = [];
    
    if (!empty($busca)) {
        $where_conditions[] = "(NOME_COMPLETO LIKE ? OR EMAIL LIKE ? OR COD_VENDEDOR LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }
    
    if (!empty($filtro_perfil)) {
        $where_conditions[] = "PERFIL = ?";
        $params[] = $filtro_perfil;
    }
    
    if (!empty($filtro_supervisor)) {
        $where_conditions[] = "COD_SUPER = ?";
        $params[] = $filtro_supervisor;
    }
    
    if (!empty($filtro_estado)) {
        $where_conditions[] = "ESTADO = ?";
        $params[] = $filtro_estado;
    }
    
    if (!empty($filtro_tipo)) {
        $where_conditions[] = "TIPO_USUARIO = ?";
        $params[] = $filtro_tipo;
    }
    
    if (!empty($filtro_status)) {
        $where_conditions[] = "ATIVO = ?";
        $params[] = ($filtro_status === 'ativo') ? 1 : 0;
    }
    
    if (!empty($filtro_login)) {
        switch ($filtro_login) {
            case 'hoje':
                $where_conditions[] = "DATE(ULTIMO_LOGIN) = CURDATE()";
                break;
            case 'ontem':
                $where_conditions[] = "DATE(ULTIMO_LOGIN) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'semana':
                $where_conditions[] = "ULTIMO_LOGIN >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $where_conditions[] = "ULTIMO_LOGIN >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'nunca':
                $where_conditions[] = "ULTIMO_LOGIN IS NULL";
                break;
            case '30dias':
                $where_conditions[] = "ULTIMO_LOGIN >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case '60dias':
                $where_conditions[] = "ULTIMO_LOGIN >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)";
                break;
            case '90dias':
                $where_conditions[] = "ULTIMO_LOGIN >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
        }
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "
        SELECT 
            ID, NOME_COMPLETO, NOME_EXIBICAO, EMAIL, PERFIL, 
            COD_VENDEDOR, COD_SUPER, ATIVO, DATA_CRIACAO,
            ULTIMO_LOGIN, ESTADO, TIPO_USUARIO
        FROM USUARIOS 
        $where_clause
        ORDER BY NOME_COMPLETO
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($usuarios);
    exit;
}

// Buscar supervisores para dropdown (incluindo diretores)
$stmt_supervisores = $pdo->prepare("
    SELECT DISTINCT COD_VENDEDOR, NOME_COMPLETO 
    FROM USUARIOS 
    WHERE (PERFIL = 'supervisor' OR PERFIL = 'diretor') AND ATIVO = 1 
    ORDER BY NOME_COMPLETO
");
$stmt_supervisores->execute();
$supervisores = $stmt_supervisores->fetchAll(PDO::FETCH_ASSOC);

// Buscar estados únicos para filtro
$stmt_estados = $pdo->prepare("
    SELECT DISTINCT ESTADO 
    FROM USUARIOS 
    WHERE ESTADO IS NOT NULL AND ESTADO != '' 
    ORDER BY ESTADO
");
$stmt_estados->execute();
$estados = $stmt_estados->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="secondary-color" content="<?php echo htmlspecialchars($usuario['secondary_color'] ?? '#ff8f00'); ?>">
    <title>Gestão de Usuários - Autopel</title>
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/secondary-color.css'); ?>?v=<?php echo time(); ?>">
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/register.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/register-mobile-responsive.css'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/register-mobile-cards.css'); ?>">
</head>
<body>
    <!-- Incluir navbar -->
    <?php include __DIR__ . '/../../includes/components/navbar.php'; ?>
    
    <?php include __DIR__ . '/../../includes/components/nav_hamburguer.php'; ?>
    
    <div class="usuarios-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-users-cog"></i>
                Gestão de Usuários
            </h1>
        </div>
        
        <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $tipo_mensagem; ?>">
            <?php echo htmlspecialchars($mensagem); ?>
        </div>
        <?php endif; ?>
        
        <!-- Filtros de Busca -->
        <div class="filtros-container">
            <h3 class="filtros-title">
                <i class="fas fa-search"></i>
                Buscar e Filtrar Usuários
            </h3>
            
            <div class="filtros-grid">
                <!-- Campo de Busca -->
                <div class="form-group filtro-group">
                    <label class="form-label filtro-label">Buscar</label>
                    <input type="text" id="campo-busca" class="form-control filtro-input" placeholder="Nome, email ou código...">
                </div>
                
                <!-- Filtro Perfil -->
                <div class="form-group filtro-group">
                    <label class="form-label filtro-label">Perfil</label>
                    <select id="filtro-perfil" class="form-control filtro-input">
                        <option value="">Todos</option>
                        <option value="admin">Admin</option>
                        <option value="diretor">Diretor</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="vendedor">Vendedor</option>
                        <option value="representante">Representante</option>
                        <option value="licitação">Licitação</option>
                    </select>
                </div>
                
                <!-- Filtro Supervisor -->
                <div class="form-group filtro-group">
                    <label class="form-label filtro-label">Supervisor</label>
                    <select id="filtro-supervisor" class="form-control filtro-input">
                        <option value="">Todos</option>
                        <?php foreach ($supervisores as $supervisor): ?>
                        <option value="<?php echo htmlspecialchars($supervisor['COD_VENDEDOR']); ?>">
                            <?php echo htmlspecialchars($supervisor['NOME_COMPLETO']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro Estado -->
                <div class="form-group filtro-group">
                    <label class="form-label filtro-label">Estado</label>
                    <select id="filtro-estado" class="form-control filtro-input">
                        <option value="">Todos</option>
                        <?php foreach ($estados as $estado): ?>
                        <option value="<?php echo htmlspecialchars($estado); ?>">
                            <?php echo htmlspecialchars($estado); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro Tipo -->
                <div class="form-group filtro-group">
                    <label class="form-label filtro-label">Tipo</label>
                    <select id="filtro-tipo" class="form-control filtro-input">
                        <option value="">Todos</option>
                        <option value="INTERNO">Interno</option>
                        <option value="EXTERNO">Externo</option>
                    </select>
                </div>
                
                <!-- Filtro Status -->
                <div class="form-group filtro-group">
                    <label class="form-label filtro-label">Status</label>
                    <select id="filtro-status" class="form-control filtro-input">
                        <option value="">Todos</option>
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>
                
                <!-- Filtro Último Login -->
                <div class="form-group filtro-group">
                    <label class="form-label filtro-label">Último Login</label>
                    <select id="filtro-login" class="form-control filtro-input">
                        <option value="">Todos</option>
                        <option value="hoje">Hoje</option>
                        <option value="ontem">Ontem</option>
                        <option value="semana">Esta Semana</option>
                        <option value="mes">Este Mês</option>
                        <option value="nunca">Nunca Logou</option>
                        <option value="30dias">Últimos 30 Dias</option>
                        <option value="60dias">Últimos 60 Dias</option>
                        <option value="90dias">Últimos 90 Dias</option>
                    </select>
                </div>
            </div>
            
            <div class="filtros-actions">
                <button id="btn-limpar-filtros" class="btn-secondary btn-limpar-filtros">
                    <i class="fas fa-times"></i> Limpar Filtros
                </button>
                <span id="contador-resultados" class="contador-resultados">
                    Mostrando todos os usuários
                </span>
            </div>
        </div>

        <div class="usuarios-container-full">
            <div class="usuarios-list">
                <div class="usuarios-header">
                    <div class="usuarios-header-content">
                        <h2 class="usuarios-title">
                            <i class="fas fa-list"></i>
                            Lista de Usuários (<span id="total-usuarios"><?php echo count($usuarios); ?></span>)
                        </h2>
                        <button id="btn-novo-usuario" class="btn-primary btn-novo-usuario">
                            <i class="fas fa-plus"></i>
                            Novo Usuário
                        </button>
                    </div>
                </div>
                
                <!-- Mobile Layout Indicator -->
                <div class="mobile-layout-indicator">
                    <i class="fas fa-mobile-alt"></i> Visualização otimizada para dispositivos móveis
                </div>
                
                <div class="table-wrapper">
                    <table class="usuarios-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Perfil</th>
                                <th>Status</th>
                                <th>Localização</th>
                                <th>Códigos</th>
                                <th>Último Login</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($user['NOME_COMPLETO']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($user['EMAIL']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="user-profile profile-<?php echo strtolower($user['PERFIL']); ?>">
                                        <?php echo htmlspecialchars($user['PERFIL']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="user-status status-<?php echo $user['ATIVO'] ? 'ativo' : 'inativo'; ?>">
                                        <?php echo $user['ATIVO'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="user-location-info">
                                        <?php if ($user['ESTADO']): ?>
                                        <div><strong>Estado:</strong> <?php echo htmlspecialchars($user['ESTADO']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($user['TIPO_USUARIO']): ?>
                                        <div><strong>Tipo:</strong> 
                                            <span class="tipo-usuario <?php echo $user['TIPO_USUARIO'] === 'INTERNO' ? 'interno' : 'externo'; ?>">
                                                <?php echo htmlspecialchars($user['TIPO_USUARIO']); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-codes-info">
                                        <?php if ($user['COD_VENDEDOR']): ?>
                                        <div><strong>Vendedor:</strong> <?php echo htmlspecialchars($user['COD_VENDEDOR']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($user['COD_SUPER']): ?>
                                        <div><strong>Supervisor:</strong> <?php echo htmlspecialchars($user['COD_SUPER']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    if ($user['ULTIMO_LOGIN']) {
                                        echo date('d/m/Y H:i', strtotime($user['ULTIMO_LOGIN']));
                                    } else {
                                        echo 'Nunca';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-edit" onclick="editarPerfil(<?php echo $user['ID']; ?>, '<?php echo htmlspecialchars($user['PERFIL']); ?>', '<?php echo htmlspecialchars($user['COD_VENDEDOR']); ?>', '<?php echo htmlspecialchars($user['COD_SUPER']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action btn-password" onclick="alterarSenha(<?php echo $user['ID']; ?>, '<?php echo htmlspecialchars($user['NOME_COMPLETO']); ?>')">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <button class="btn-action btn-toggle" onclick="toggleAtivo(<?php echo $user['ID']; ?>, <?php echo $user['ATIVO']; ?>, '<?php echo htmlspecialchars($user['NOME_COMPLETO']); ?>')">
                                            <i class="fas fa-<?php echo $user['ATIVO'] ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                        <?php if ($user['ID'] != $usuario['id']): ?>
                                        <button class="btn-action btn-delete" onclick="excluirUsuario(<?php echo $user['ID']; ?>, '<?php echo htmlspecialchars($user['NOME_COMPLETO']); ?>', '<?php echo htmlspecialchars($user['PERFIL']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Cards Container -->
                <div id="mobileUsersContainer">
                    <?php foreach ($usuarios as $user): ?>
                    <div class="user-card">
                        <div class="user-card-header">
                            <div class="user-card-name">
                                <h3><?php echo htmlspecialchars($user['NOME_COMPLETO']); ?></h3>
                                <div class="user-card-email"><?php echo htmlspecialchars($user['EMAIL']); ?></div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <span class="user-profile-badge profile-<?php echo strtolower($user['PERFIL']); ?>">
                                    <?php echo htmlspecialchars($user['PERFIL']); ?>
                                </span>
                                <span class="user-status-badge status-<?php echo $user['ATIVO'] ? 'ativo' : 'inativo'; ?>">
                                    <?php echo $user['ATIVO'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="user-card-info">
                            <div class="user-info-grid">
                                <?php if ($user['ESTADO']): ?>
                                <div class="user-info-item">
                                    <div class="user-info-label">Estado</div>
                                    <div class="user-info-value"><?php echo htmlspecialchars($user['ESTADO']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($user['TIPO_USUARIO']): ?>
                                <div class="user-info-item">
                                    <div class="user-info-label">Tipo</div>
                                    <div class="user-info-value">
                                        <span class="user-type-badge tipo-<?php echo strtolower($user['TIPO_USUARIO']); ?>">
                                            <?php echo htmlspecialchars($user['TIPO_USUARIO']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($user['COD_VENDEDOR']): ?>
                                <div class="user-info-item">
                                    <div class="user-info-label">Código Vendedor</div>
                                    <div class="user-info-value"><?php echo htmlspecialchars($user['COD_VENDEDOR']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($user['COD_SUPER']): ?>
                                <div class="user-info-item">
                                    <div class="user-info-label">Código Supervisor</div>
                                    <div class="user-info-value"><?php echo htmlspecialchars($user['COD_SUPER']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="user-login-info">
                            <div class="user-login-label">Último Login</div>
                            <div class="user-login-value">
                                <?php 
                                if ($user['ULTIMO_LOGIN']) {
                                    echo date('d/m/Y H:i', strtotime($user['ULTIMO_LOGIN']));
                                } else {
                                    echo 'Nunca logou';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="user-card-actions">
                            <div class="user-actions-single-row">
                                <button class="btn-action btn-edit" onclick="editarPerfil(<?php echo $user['ID']; ?>, '<?php echo htmlspecialchars($user['PERFIL']); ?>', '<?php echo htmlspecialchars($user['COD_VENDEDOR']); ?>', '<?php echo htmlspecialchars($user['COD_SUPER']); ?>')">
                                    <i class="fas fa-edit"></i>
                                    <span class="btn-text">Editar</span>
                                </button>
                                <button class="btn-action btn-password" onclick="alterarSenha(<?php echo $user['ID']; ?>, '<?php echo htmlspecialchars($user['NOME_COMPLETO']); ?>')">
                                    <i class="fas fa-key"></i>
                                    <span class="btn-text">Senha</span>
                                </button>
                                <button class="btn-action btn-toggle" onclick="toggleAtivo(<?php echo $user['ID']; ?>, <?php echo $user['ATIVO']; ?>, '<?php echo htmlspecialchars($user['NOME_COMPLETO']); ?>')">
                                    <i class="fas fa-<?php echo $user['ATIVO'] ? 'ban' : 'check'; ?>"></i>
                                    <span class="btn-text"><?php echo $user['ATIVO'] ? 'Desativar' : 'Ativar'; ?></span>
                                </button>
                                <?php if ($user['ID'] != $usuario['id']): ?>
                                <button class="btn-action btn-delete" onclick="excluirUsuario(<?php echo $user['ID']; ?>, '<?php echo htmlspecialchars($user['NOME_COMPLETO']); ?>', '<?php echo htmlspecialchars($user['PERFIL']); ?>')">
                                    <i class="fas fa-trash"></i>
                                    <span class="btn-text">Excluir</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para alterar senha -->
    <div id="modalSenha" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Alterar Senha</h3>
                <button class="close" onclick="fecharModal('modalSenha')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="alterar_senha">
                <input type="hidden" name="user_id" id="senha_user_id">
                
                <div class="form-group">
                    <label class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="senha_user_name" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="nova_senha">Nova Senha *</label>
                    <input type="password" class="form-control" id="nova_senha" name="nova_senha" required minlength="6">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-key"></i>
                    Alterar Senha
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal para alterar perfil -->
    <div id="modalPerfil" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Alterar Perfil</h3>
                <button class="close" onclick="fecharModal('modalPerfil')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="alterar_perfil">
                <input type="hidden" name="user_id" id="perfil_user_id">
                
                <div class="form-group">
                    <label class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="perfil_user_name" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="novo_perfil">Novo Perfil *</label>
                    <select class="form-control" id="novo_perfil" name="novo_perfil" required>
                        <option value="">Selecione o perfil</option>
                        <option value="admin">Administrador</option>
                        <option value="diretor">Diretor</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="vendedor">Vendedor</option>
                        <option value="representante">Representante</option>
                        <option value="licitação">Licitação</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="perfil_cod_vendedor">Código Vendedor</label>
                        <input type="text" class="form-control" id="perfil_cod_vendedor" name="cod_vendedor">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="perfil_cod_super">Código Supervisor</label>
                        <select class="form-control" id="perfil_cod_super" name="cod_super">
                            <option value="">Selecione o supervisor</option>
                            <?php foreach ($supervisores as $supervisor): ?>
                            <option value="<?php echo htmlspecialchars($supervisor['COD_VENDEDOR']); ?>">
                                <?php echo htmlspecialchars($supervisor['NOME_COMPLETO']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-edit"></i>
                    Alterar Perfil
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal para criar novo usuário -->
    <div id="modalNovoUsuario" class="modal">
        <div class="modal-content modal-content-large">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-plus"></i>
                    Criar Novo Usuário
                </h3>
                <button class="close" onclick="fecharModal('modalNovoUsuario')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="acao" value="criar_usuario">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="modal_nome_completo">Nome Completo *</label>
                        <input type="text" class="form-control" id="modal_nome_completo" name="nome_completo" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="modal_nome_exibicao">Nome de Exibição</label>
                        <input type="text" class="form-control" id="modal_nome_exibicao" name="nome_exibicao">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="modal_email">Email *</label>
                    <input type="email" class="form-control" id="modal_email" name="email" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="modal_perfil">Perfil *</label>
                        <select class="form-control" id="modal_perfil" name="perfil" required>
                            <option value="">Selecione o perfil</option>
                            <option value="admin">Administrador</option>
                            <option value="diretor">Diretor</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="vendedor">Vendedor</option>
                            <option value="representante">Representante</option>
                            <option value="licitação">Licitação</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="modal_senha">Senha *</label>
                        <input type="password" class="form-control" id="modal_senha" name="senha" required minlength="6">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="modal_cod_vendedor">Código Vendedor</label>
                        <input type="text" class="form-control" id="modal_cod_vendedor" name="cod_vendedor">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="modal_cod_super">Código Supervisor</label>
                        <select class="form-control" id="modal_cod_super" name="cod_super">
                            <option value="">Selecione o supervisor</option>
                            <?php foreach ($supervisores as $supervisor): ?>
                            <option value="<?php echo htmlspecialchars($supervisor['COD_VENDEDOR']); ?>">
                                <?php echo htmlspecialchars($supervisor['NOME_COMPLETO']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="modal_estado">Estado</label>
                        <select class="form-control" id="modal_estado" name="estado">
                            <option value="">Selecione o estado</option>
                            <?php foreach ($estados as $estado): ?>
                            <option value="<?php echo htmlspecialchars($estado); ?>">
                                <?php echo htmlspecialchars($estado); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="modal_tipo_usuario">Tipo de Usuário</label>
                        <select class="form-control" id="modal_tipo_usuario" name="tipo_usuario">
                            <option value="">Selecione o tipo</option>
                            <option value="INTERNO">Interno</option>
                            <option value="EXTERNO">Externo</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="fecharModal('modalNovoUsuario')">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Criar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Variáveis globais
        let timeoutBusca;
        let usuariosAtuais = <?php echo json_encode($usuarios); ?>;
        
        // Inicializar eventos
        document.addEventListener('DOMContentLoaded', function() {
            // Eventos para busca dinâmica
            document.getElementById('campo-busca').addEventListener('input', debounce(buscarUsuarios, 300));
            document.getElementById('filtro-perfil').addEventListener('change', buscarUsuarios);
            document.getElementById('filtro-supervisor').addEventListener('change', buscarUsuarios);
            document.getElementById('filtro-estado').addEventListener('change', buscarUsuarios);
            document.getElementById('filtro-tipo').addEventListener('change', buscarUsuarios);
            document.getElementById('filtro-status').addEventListener('change', buscarUsuarios);
            document.getElementById('filtro-login').addEventListener('change', buscarUsuarios);
            
            // Botão limpar filtros
            document.getElementById('btn-limpar-filtros').addEventListener('click', limparFiltros);
            
            // Botão novo usuário
            document.getElementById('btn-novo-usuario').addEventListener('click', function() {
                document.getElementById('modalNovoUsuario').style.display = 'block';
            });
            
            // Inicializar toggle mobile/desktop
            toggleMobileElements();
        });
        
        // Toggle mobile/desktop no resize
        window.addEventListener('resize', toggleMobileElements);
        
        // Função debounce para evitar muitas requisições
        function debounce(func, wait) {
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeoutBusca);
                    func(...args);
                };
                clearTimeout(timeoutBusca);
                timeoutBusca = setTimeout(later, wait);
            };
        }
        
        // Função principal de busca
        function buscarUsuarios() {
            const busca = document.getElementById('campo-busca').value;
            const filtroPerfil = document.getElementById('filtro-perfil').value;
            const filtroSupervisor = document.getElementById('filtro-supervisor').value;
            const filtroEstado = document.getElementById('filtro-estado').value;
            const filtroTipo = document.getElementById('filtro-tipo').value;
            const filtroStatus = document.getElementById('filtro-status').value;
            const filtroLogin = document.getElementById('filtro-login').value;
            
            // Mostrar loading
            const tbody = document.querySelector('.usuarios-table tbody');
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Buscando usuários...</td></tr>';
            
            // Fazer requisição AJAX
            const params = new URLSearchParams({
                ajax: 'buscar_usuarios',
                busca: busca,
                filtro_perfil: filtroPerfil,
                filtro_supervisor: filtroSupervisor,
                filtro_estado: filtroEstado,
                filtro_tipo: filtroTipo,
                filtro_status: filtroStatus,
                filtro_login: filtroLogin
            });
            
            fetch(`${window.baseUrl('register')}?${params}`)
                .then(response => response.json())
                .then(usuarios => {
                    usuariosAtuais = usuarios;
                    atualizarTabela(usuarios);
                    atualizarContador(usuarios.length);
                })
                .catch(error => {
                    console.error('Erro na busca:', error);
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: red;"><i class="fas fa-exclamation-triangle"></i> Erro ao buscar usuários</td></tr>';
                });
        }
        
        // Atualizar tabela com novos dados
        function atualizarTabela(usuarios) {
            const tbody = document.querySelector('.usuarios-table tbody');
            
            if (usuarios.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #666;"><i class="fas fa-search"></i> Nenhum usuário encontrado</td></tr>';
                // Atualizar cards mobile também
                atualizarCardsMobile(usuarios);
                return;
            }
            
            let html = '';
            usuarios.forEach(user => {
                const ultimoLogin = user.ULTIMO_LOGIN ? 
                    new Date(user.ULTIMO_LOGIN).toLocaleDateString('pt-BR') + ' ' + 
                    new Date(user.ULTIMO_LOGIN).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'}) : 
                    'Nunca';
                
                const tipoColor = user.TIPO_USUARIO === 'INTERNO' ? '#28a745' : '#007bff';
                
                html += `
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-name">${escapeHtml(user.NOME_COMPLETO)}</div>
                                <div class="user-email">${escapeHtml(user.EMAIL)}</div>
                            </div>
                        </td>
                        <td>
                            <span class="user-profile profile-${user.PERFIL.toLowerCase()}">
                                ${escapeHtml(user.PERFIL)}
                            </span>
                        </td>
                        <td>
                            <span class="user-status status-${user.ATIVO ? 'ativo' : 'inativo'}">
                                ${user.ATIVO ? 'Ativo' : 'Inativo'}
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 0.8rem;">
                                ${user.ESTADO ? `<div><strong>Estado:</strong> ${escapeHtml(user.ESTADO)}</div>` : ''}
                                ${user.TIPO_USUARIO ? `<div><strong>Tipo:</strong> <span style="color: ${tipoColor};">${escapeHtml(user.TIPO_USUARIO)}</span></div>` : ''}
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 0.8rem;">
                                ${user.COD_VENDEDOR ? `<div><strong>Vendedor:</strong> ${escapeHtml(user.COD_VENDEDOR)}</div>` : ''}
                                ${user.COD_SUPER ? `<div><strong>Supervisor:</strong> ${escapeHtml(user.COD_SUPER)}</div>` : ''}
                            </div>
                        </td>
                        <td>${ultimoLogin}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action btn-edit" onclick="editarPerfil(${user.ID}, '${escapeHtml(user.PERFIL)}', '${escapeHtml(user.COD_VENDEDOR || '')}', '${escapeHtml(user.COD_SUPER || '')}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-password" onclick="alterarSenha(${user.ID}, '${escapeHtml(user.NOME_COMPLETO)}')">
                                    <i class="fas fa-key"></i>
                                </button>
                                <button class="btn-action btn-toggle" onclick="toggleAtivo(${user.ID}, ${user.ATIVO}, '${escapeHtml(user.NOME_COMPLETO)}')">
                                    <i class="fas fa-${user.ATIVO ? 'ban' : 'check'}"></i>
                                </button>
                                ${user.ID != <?php echo $usuario['id']; ?> ? `
                                <button class="btn-action btn-delete" onclick="excluirUsuario(${user.ID}, '${escapeHtml(user.NOME_COMPLETO)}', '${escapeHtml(user.PERFIL)}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            
            // Atualizar cards mobile também
            atualizarCardsMobile(usuarios);
        }
        
        // Atualizar contador de resultados
        function atualizarContador(total) {
            document.getElementById('total-usuarios').textContent = total;
            
            const contador = document.getElementById('contador-resultados');
            const totalOriginal = <?php echo count($usuarios); ?>;
            
            if (total === totalOriginal) {
                contador.textContent = 'Mostrando todos os usuários';
            } else {
                contador.textContent = `Mostrando ${total} de ${totalOriginal} usuários`;
            }
        }
        
        // Limpar todos os filtros
        function limparFiltros() {
            document.getElementById('campo-busca').value = '';
            document.getElementById('filtro-perfil').value = '';
            document.getElementById('filtro-supervisor').value = '';
            document.getElementById('filtro-estado').value = '';
            document.getElementById('filtro-tipo').value = '';
            document.getElementById('filtro-status').value = '';
            document.getElementById('filtro-login').value = '';
            
            buscarUsuarios();
        }
        
        // Função para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Atualizar cards mobile com novos dados
        function atualizarCardsMobile(usuarios) {
            const container = document.getElementById('mobileUsersContainer');
            
            if (usuarios.length === 0) {
                container.innerHTML = `
                    <div class="user-empty-state">
                        <i class="fas fa-search"></i>
                        <p>Nenhum usuário encontrado</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            usuarios.forEach(user => {
                const ultimoLogin = user.ULTIMO_LOGIN ? 
                    new Date(user.ULTIMO_LOGIN).toLocaleDateString('pt-BR') + ' ' + 
                    new Date(user.ULTIMO_LOGIN).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'}) : 
                    'Nunca logou';
                
                html += `
                    <div class="user-card">
                        <div class="user-card-header">
                            <div class="user-card-name">
                                <h3>${escapeHtml(user.NOME_COMPLETO)}</h3>
                                <div class="user-card-email">${escapeHtml(user.EMAIL)}</div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <span class="user-profile-badge profile-${user.PERFIL.toLowerCase()}">
                                    ${escapeHtml(user.PERFIL)}
                                </span>
                                <span class="user-status-badge status-${user.ATIVO ? 'ativo' : 'inativo'}">
                                    ${user.ATIVO ? 'Ativo' : 'Inativo'}
                                </span>
                            </div>
                        </div>
                        
                        <div class="user-card-info">
                            <div class="user-info-grid">
                                ${user.ESTADO ? `
                                <div class="user-info-item">
                                    <div class="user-info-label">Estado</div>
                                    <div class="user-info-value">${escapeHtml(user.ESTADO)}</div>
                                </div>
                                ` : ''}
                                
                                ${user.TIPO_USUARIO ? `
                                <div class="user-info-item">
                                    <div class="user-info-label">Tipo</div>
                                    <div class="user-info-value">
                                        <span class="user-type-badge tipo-${user.TIPO_USUARIO.toLowerCase()}">
                                            ${escapeHtml(user.TIPO_USUARIO)}
                                        </span>
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${user.COD_VENDEDOR ? `
                                <div class="user-info-item">
                                    <div class="user-info-label">Código Vendedor</div>
                                    <div class="user-info-value">${escapeHtml(user.COD_VENDEDOR)}</div>
                                </div>
                                ` : ''}
                                
                                ${user.COD_SUPER ? `
                                <div class="user-info-item">
                                    <div class="user-info-label">Código Supervisor</div>
                                    <div class="user-info-value">${escapeHtml(user.COD_SUPER)}</div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="user-login-info">
                            <div class="user-login-label">Último Login</div>
                            <div class="user-login-value">${ultimoLogin}</div>
                        </div>
                        
                        <div class="user-card-actions">
                            <div class="user-actions-single-row">
                                <button class="btn-action btn-edit" onclick="editarPerfil(${user.ID}, '${escapeHtml(user.PERFIL)}', '${escapeHtml(user.COD_VENDEDOR || '')}', '${escapeHtml(user.COD_SUPER || '')}')">
                                    <i class="fas fa-edit"></i>
                                    <span class="btn-text">Editar</span>
                                </button>
                                <button class="btn-action btn-password" onclick="alterarSenha(${user.ID}, '${escapeHtml(user.NOME_COMPLETO)}')">
                                    <i class="fas fa-key"></i>
                                    <span class="btn-text">Senha</span>
                                </button>
                                <button class="btn-action btn-toggle" onclick="toggleAtivo(${user.ID}, ${user.ATIVO}, '${escapeHtml(user.NOME_COMPLETO)}')">
                                    <i class="fas fa-${user.ATIVO ? 'ban' : 'check'}"></i>
                                    <span class="btn-text">${user.ATIVO ? 'Desativar' : 'Ativar'}</span>
                                </button>
                                ${user.ID != <?php echo $usuario['id']; ?> ? `
                                <button class="btn-action btn-delete" onclick="excluirUsuario(${user.ID}, '${escapeHtml(user.NOME_COMPLETO)}', '${escapeHtml(user.PERFIL)}')">
                                    <i class="fas fa-trash"></i>
                                    <span class="btn-text">Excluir</span>
                                </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Toggle entre layout mobile e desktop
        function toggleMobileElements() {
            const isMobile = window.innerWidth <= 768;
            const tableWrapper = document.querySelector('.table-wrapper');
            const mobileContainer = document.getElementById('mobileUsersContainer');
            const mobileIndicator = document.querySelector('.mobile-layout-indicator');
            
            if (isMobile) {
                // Mostrar cards mobile, esconder tabela
                if (tableWrapper) tableWrapper.style.display = 'none';
                if (mobileContainer) mobileContainer.style.display = 'grid';
                if (mobileIndicator) mobileIndicator.style.display = 'block';
            } else {
                // Mostrar tabela, esconder cards mobile
                if (tableWrapper) tableWrapper.style.display = 'block';
                if (mobileContainer) mobileContainer.style.display = 'none';
                if (mobileIndicator) mobileIndicator.style.display = 'none';
            }
        }
        
        function alterarSenha(userId, userName) {
            document.getElementById('senha_user_id').value = userId;
            document.getElementById('senha_user_name').value = userName;
            document.getElementById('modalSenha').style.display = 'block';
        }
        
        function editarPerfil(userId, perfil, codVendedor, codSuper) {
            document.getElementById('perfil_user_id').value = userId;
            // Buscar o nome do usuário de forma mais simples
            const row = document.querySelector(`button[onclick*="editarPerfil(${userId}"]`).closest('tr');
            const userName = row.querySelector('.user-name').textContent;
            document.getElementById('perfil_user_name').value = userName;
            document.getElementById('novo_perfil').value = perfil;
            document.getElementById('perfil_cod_vendedor').value = codVendedor || '';
            document.getElementById('perfil_cod_super').value = codSuper || '';
            document.getElementById('modalPerfil').style.display = 'block';
        }
        
        function toggleAtivo(userId, statusAtual, userName) {
            const novoStatus = statusAtual ? 0 : 1;
            const acao = statusAtual ? 'desativar' : 'ativar';
            
            if (confirm(`Tem certeza que deseja ${acao} o usuário "${userName}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="toggle_ativo">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="novo_status" value="${novoStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function excluirUsuario(userId, userName, userPerfil) {
            let mensagem = `Tem certeza que deseja excluir o usuário "${userName}"?`;
            
            if (userPerfil === 'admin') {
                mensagem += '\n\n⚠️ ATENÇÃO: Este é um usuário administrador.';
            }
            
            mensagem += '\n\nEsta ação não pode ser desfeita.';
            
            if (confirm(mensagem)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="excluir_usuario">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            
            // Limpar formulário do modal de novo usuário
            if (modalId === 'modalNovoUsuario') {
                document.getElementById('modalNovoUsuario').querySelector('form').reset();
            }
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Auto-fechar alertas após 5 segundos
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>
