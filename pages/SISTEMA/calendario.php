<?php
// Função para encontrar o arquivo conexao.php
function encontrarConexao() {
    $caminhos = [
        __DIR__ . '/includes/conexao.php',
        dirname(__FILE__) . '/includes/conexao.php',
        'includes/conexao.php',
        './includes/conexao.php',
        '../includes/conexao.php',
        '../../includes/conexao.php'
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
    die("Erro: Arquivo conexao.php não encontrado");
}

require_once __DIR__ . '/../../includes/config/config.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

// Usar as informações da sessão diretamente
$usuario = $_SESSION['usuario'];

// Calendário disponível para todos os usuários logados
// Não há restrição de perfil para acessar o calendário

$current_page = 'calendario.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - Autopel BI</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    
    <!-- JavaScript helper para caminhos -->
    <script>
        window.BASE_PATH = '<?php echo defined('SITE_BASE_PATH') ? rtrim(SITE_BASE_PATH, '/') : '/Site'; ?>';
        window.baseUrl = function(path) {
            path = path || '';
            path = path.startsWith('/') ? path.substring(1) : path;
            return window.BASE_PATH + (path ? '/' + path : '');
        };
    </script>
    
    <!-- CSS Customizado -->
            <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="shortcut icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
        <link rel="apple-touch-icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/estilo.css'); ?>">
    
    <style>
        /* Layout principal */
        .d-flex {
            min-height: 100vh;
        }
        
        .flex-grow-1 {
            margin-left: 280px; /* Espaço para a sidebar moderna */
            transition: margin-left 0.3s ease;
        }
        
        /* Quando a sidebar está recolhida */
        body.sidebar-collapsed .flex-grow-1 {
            margin-left: 60px;
        }
        
        /* Estilo moderno seguindo o padrão da carteira */
        .calendario-page {
            padding: 2rem;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .calendario-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .calendario-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 1.8rem;
        }
        
        .calendario-header p {
            color: #757575;
            margin-bottom: 0;
            font-size: 1rem;
        }
        
        .calendario-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .calendario-toolbar {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: white;
        }
        
        .btn-novo-agendamento {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 3px 12px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-novo-agendamento::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-novo-agendamento:hover::before {
            left: 100%;
        }
        
        .btn-novo-agendamento:hover {
            background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        
        .filtros-container {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filtro-select {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            color: #333;
            font-size: 0.9rem;
            min-width: 150px;
        }
        
        .filtro-select:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
            outline: none;
        }
        
        /* Botões de integração com Outlook */
        .outlook-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .btn-outlook {
            background: linear-gradient(135deg, #0078d4 0%, #106ebe 100%);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 120, 212, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .btn-outlook:hover {
            background: linear-gradient(135deg, #106ebe 0%, #005a9e 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 120, 212, 0.4);
        }
        
        .btn-outlook i {
            font-size: 0.9rem;
        }
        
        /* Estilos para o modal do Outlook */
        .outlook-info {
            margin-bottom: 1.5rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 1px solid #2196f3;
            color: #1565c0;
        }
        
        .alert-info i {
            margin-right: 0.5rem;
            color: #2196f3;
        }
        
        .alert-info ol {
            margin: 0.5rem 0 0 1.5rem;
            padding: 0;
        }
        
        .alert-info li {
            margin-bottom: 0.25rem;
        }
        
        .alert-info a {
            color: #1565c0;
            text-decoration: underline;
            font-weight: 600;
        }
        
        .alert-info a:hover {
            color: #0d47a1;
        }
        
        .outlook-actions {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }
        
        /* FullCalendar Customização Moderna */
        .fc {
            font-family: inherit;
        }
        
        .fc-toolbar-title {
            font-size: 1.5em !important;
            font-weight: 600;
            color: #333 !important;
        }
        
        .fc-button-primary {
            background: #4CAF50 !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 0.5rem 1rem !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
        }
        
        .fc-button-primary:hover {
            background: #45a049 !important;
            transform: translateY(-1px);
        }
        
        .fc-button-primary:focus {
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25) !important;
        }
        
        .fc-event {
            border-radius: 6px !important;
            border: none !important;
            padding: 0.25rem 0.5rem !important;
            font-size: 0.85rem !important;
            font-weight: 500 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }
        
        .event-agendado {
            background: #4CAF50 !important;
            color: white !important;
        }
        
        .event-realizado {
            background: #2196F3 !important;
            color: white !important;
        }
        
        .event-cancelado {
            background: #f44336 !important;
            color: white !important;
        }
        
        /* Modal Overlay - Padrão profissional */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        /* Modal de Agendamento - Design Moderno e Profissional */
        .modal-agendamento {
            background: var(--white, #ffffff);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-agendamento-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .modal-agendamento-title {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .modal-agendamento-title i {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .modal-agendamento-title h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
        }
        
        .modal-close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .modal-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        .modal-agendamento-body {
            padding: 2rem;
            max-height: calc(90vh - 140px);
            overflow-y: auto;
        }
        
        .modal-agendamento-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-agendamento-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .modal-agendamento-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .modal-agendamento-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
            font-size: 0.95rem;
        }
        
        .required {
            color: #dc3545;
            font-weight: bold;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            transform: translateY(-1px);
        }
        
        .form-control::placeholder {
            color: #adb5bd;
            font-style: italic;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .modal-agendamento-footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 1px solid #dee2e6;
            padding: 1.5rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            align-items: center;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            font-size: 0.95rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn i {
            font-size: 0.9rem;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 3px 12px rgba(108, 117, 125, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-secondary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-secondary:hover::before {
            left: 100%;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            box-shadow: 0 3px 12px rgba(76, 175, 80, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-success::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-success:hover::before {
            left: 100%;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }
        
        /* Modal de Detalhes - Padrão profissional */
        .modal-detalhes {
            background: var(--white, #ffffff);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
            position: relative;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        .modal-detalhes-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .modal-detalhes-title {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .modal-detalhes-title i {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .modal-detalhes-title h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
        }
        
        .modal-detalhes-body {
            padding: 2rem;
            max-height: calc(90vh - 140px);
            overflow-y: auto;
        }
        
        .modal-detalhes-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-detalhes-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .modal-detalhes-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .modal-detalhes-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .detalhes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .detalhes-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid #4CAF50;
        }
        
        .detalhes-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .detalhes-value {
            color: #212529;
            font-size: 1rem;
        }
        
        .detalhes-observacao {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .detalhes-observacao .detalhes-label {
            color: #856404;
        }
        
        .detalhes-observacao .detalhes-value {
            color: #856404;
            font-style: italic;
        }
        
        .modal-detalhes-footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-top: 1px solid #dee2e6;
            padding: 1.5rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            align-items: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            box-shadow: 0 3px 12px rgba(0, 123, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 3px 12px rgba(220, 53, 69, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-danger::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-danger:hover::before {
            left: 100%;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #b71c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        
        /* Estilos para badges */
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .badge.bg-primary {
            background-color: #4CAF50 !important;
        }
        
        .badge.bg-success {
            background-color: #2196F3 !important;
        }
        
        .badge.bg-danger {
            background-color: #f44336 !important;
        }
        
        /* Responsividade */
        @media (max-width: 1024px) {
            .flex-grow-1 {
                margin-left: 240px;
            }
        }
        
        @media (max-width: 768px) {
            .flex-grow-1 {
                margin-left: 0;
            }
            
            body.sidebar-collapsed .flex-grow-1 {
                margin-left: 0;
            }
            
            .calendario-page {
                padding: 1rem;
                padding-top: 5rem; /* Espaço para o botão mobile */
            }
            
            .calendario-header {
                padding: 1.5rem;
            }
            
            .calendario-toolbar {
                flex-direction: column;
                align-items: stretch;
                padding: 1rem;
            }
            
            .filtros-container {
                justify-content: center;
            }
            
            .outlook-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn-outlook {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .fc-toolbar {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .modal-agendamento,
            .modal-detalhes {
                width: 95%;
                max-height: 95vh;
            }
            
            .modal-agendamento-header,
            .modal-detalhes-header {
                padding: 1rem 1.5rem;
            }
            
            .modal-agendamento-title h3,
            .modal-detalhes-title h3 {
                font-size: 1.1rem;
            }
            
            .modal-agendamento-body,
            .modal-detalhes-body {
                padding: 1rem 1.5rem;
            }
            
            .modal-agendamento-footer,
            .modal-detalhes-footer {
                padding: 1rem 1.5rem;
                flex-direction: column;
            }
            
            .modal-agendamento-footer .btn,
            .modal-detalhes-footer .btn {
                width: 100%;
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .detalhes-grid {
                grid-template-columns: 1fr;
            }
            
            /* Ajustes específicos para mobile */
            .fc-button {
                padding: 0.5rem 0.75rem !important;
                font-size: 0.85rem !important;
            }
            
            .fc-toolbar-title {
                font-size: 1.2em !important;
            }
            
            .btn-novo-agendamento {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .filtro-select {
                min-width: 120px;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 480px) {
            .calendario-page {
                padding: 0.5rem;
                padding-top: 4rem;
            }
            
            .calendario-header {
                padding: 1rem;
            }
            
            .calendario-header h1 {
                font-size: 1.5rem;
            }
            
            .calendario-toolbar {
                padding: 0.75rem;
            }
            
            .fc-toolbar {
                gap: 0.25rem;
            }
            
            .fc-button {
                padding: 0.4rem 0.6rem !important;
                font-size: 0.8rem !important;
            }
            
            .fc-toolbar-title {
                font-size: 1.1em !important;
            }
        }
    </style>
</head>
<body>
            <!-- Incluir navbar -->
            <?php include 'includes/navbar.php'; ?>
            
            <?php 
            // Incluir navegação mobile
            $nav_mobile_path = 'includes/nav_mobile.php';
            if (!file_exists($nav_mobile_path)) {
                $nav_mobile_path = __DIR__ . '/includes/nav_mobile.php';
            }
            include $nav_mobile_path; 
            ?>
            
            <div class="d-flex">
        
        <div class="flex-grow-1">
            <div class="calendario-page">
                <div class="calendario-header">
                    <h1><i class="fas fa-calendar-alt me-2"></i>Calendário de Ligações</h1>
                    <p>Visualize seus agendamentos de ligações com clientes</p>
                </div>
                
                <div class="calendario-container">
                    <div class="calendario-toolbar">
                        <div class="filtros-container">
                            <select class="filtro-select" id="filtroStatus" onchange="filtrarCalendario()">
                                <option value="">Todos os Status</option>
                                <option value="agendado">Agendado</option>
                                <option value="realizado">Realizado</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                            
                            <select class="filtro-select" id="filtroCliente" onchange="filtrarCalendario()">
                                <option value="">Todos os Clientes</option>
                            </select>
                            
                            <!-- Botões de integração com Outlook -->
                            <div class="outlook-buttons">
                                <button class="btn btn-outlook" onclick="exportarTodosICS()" title="Exportar todos os agendamentos para Outlook">
                                    <i class="fas fa-download"></i> Exportar ICS
                                </button>
                                <button class="btn btn-outlook" onclick="abrirModalOutlook()" title="Configurar integração com Outlook">
                                    <i class="fas fa-cog"></i> Configurar Outlook
                                </button>
                                <button class="btn btn-outlook" onclick="sincronizarOutlook()" title="Sincronizar com Outlook">
                                    <i class="fas fa-sync"></i> Sincronizar
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="calendario"></div>
                </div>
            </div>
        </div>
    </div>
    

    
    <!-- Modal de Configuração do Outlook -->
    <div id="modalOutlook" class="modal-overlay">
        <div class="modal-agendamento">
            <div class="modal-agendamento-header">
                <div class="modal-agendamento-title">
                    <i class="fas fa-microsoft"></i>
                    <h3>Configuração do Outlook</h3>
                </div>
                <button class="modal-close-btn" onclick="fecharModalOutlook()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-agendamento-body">
                <div class="outlook-info">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Como configurar:</strong>
                        <ol>
                            <li>Acesse o <a href="https://portal.azure.com" target="_blank">Azure Portal</a></li>
                            <li>Vá para "Azure Active Directory" > "Registros de aplicativo"</li>
                            <li>Crie um novo registro ou use um existente</li>
                            <li>Copie o Client ID, Tenant ID e gere um Client Secret</li>
                            <li>Configure as permissões: Calendars.ReadWrite</li>
                        </ol>
                    </div>
                </div>
                
                <form id="formOutlook">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required">*</span> Client ID
                        </label>
                        <input type="text" class="form-control" id="clientId" required placeholder="Ex: 12345678-1234-1234-1234-123456789012">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required">*</span> Client Secret
                        </label>
                        <input type="password" class="form-control" id="clientSecret" required placeholder="Cole o client secret aqui">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required">*</span> Tenant ID
                        </label>
                        <input type="text" class="form-control" id="tenantId" required placeholder="Ex: 12345678-1234-1234-1234-123456789012">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required">*</span> Email do Outlook
                        </label>
                        <input type="email" class="form-control" id="emailOutlook" required placeholder="seu.email@empresa.com">
                    </div>
                </form>
                
                <div class="outlook-actions">
                    <button type="button" class="btn btn-outlook" onclick="testarConexaoOutlook()">
                        <i class="fas fa-plug"></i> Testar Conexão
                    </button>
                </div>
            </div>
            
            <div class="modal-agendamento-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalOutlook()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-success" onclick="salvarConfigOutlook()">
                    <i class="fas fa-save"></i> Salvar Configuração
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalhes do Agendamento -->
    <div id="modalDetalhes" class="modal-overlay">
        <div class="modal-detalhes">
            <div class="modal-detalhes-header">
                <div class="modal-detalhes-title">
                    <i class="fas fa-info-circle"></i>
                    <h3>Detalhes do Agendamento</h3>
                </div>
                <button class="modal-close-btn" onclick="fecharModalDetalhes()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-detalhes-body" id="detalhesConteudo">
                <!-- Conteúdo será carregado dinamicamente -->
            </div>
            
            <div class="modal-detalhes-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalDetalhes()">
                    <i class="fas fa-times"></i> Fechar
                </button>
                <button type="button" class="btn btn-outlook" onclick="exportarAgendamentoICS(agendamentoAtual.id)">
                    <i class="fas fa-download"></i> Exportar para Outlook
                </button>
                <button type="button" class="btn btn-primary" id="btnMarcarRealizado" style="display: none;">
                    <i class="fas fa-check"></i> Marcar como Realizado
                </button>
                <button type="button" class="btn btn-danger" id="btnCancelarAgendamento" style="display: none;">
                    <i class="fas fa-times"></i> Cancelar Agendamento
                </button>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/pt-br.global.min.js"></script>
    
    <script>
        let calendar;
        let agendamentos = [];
        let agendamentoAtual = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            inicializarCalendario();
            carregarClientes();
            carregarAgendamentos();
            ajustarLayoutSidebar();
            
            // Adicionar eventos para fechar modais clicando fora
            const modalAgendamento = document.getElementById('modalAgendamento');
            const modalDetalhes = document.getElementById('modalDetalhes');
            
            if (modalAgendamento) {
                modalAgendamento.addEventListener('click', function(e) {
                    if (e.target === this) {
                        fecharModalAgendamento();
                    }
                });
            }
            
            if (modalDetalhes) {
                modalDetalhes.addEventListener('click', function(e) {
                    if (e.target === this) {
                        fecharModalDetalhes();
                    }
                });
            }
            
            // Adicionar evento para fechar modal do Outlook clicando fora
            const modalOutlook = document.getElementById('modalOutlook');
            if (modalOutlook) {
                modalOutlook.addEventListener('click', function(e) {
                    if (e.target === this) {
                        fecharModalOutlook();
                    }
                });
            }
            

        });
        
        // Função para ajustar o layout baseado no estado da sidebar
        function ajustarLayoutSidebar() {
            const sidebar = document.querySelector('.sidebar-modern');
            const body = document.body;
            
            if (sidebar) {
                // Verificar se a sidebar está recolhida
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                if (isCollapsed) {
                    body.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                }
                
                // Observar mudanças na sidebar
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            const isCollapsed = sidebar.classList.contains('collapsed');
                            if (isCollapsed) {
                                body.classList.add('sidebar-collapsed');
                            } else {
                                body.classList.remove('sidebar-collapsed');
                            }
                        }
                    });
                });
                
                observer.observe(sidebar, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }
        }
        
        function inicializarCalendario() {
            const calendarEl = document.getElementById('calendario');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia',
                    list: 'Lista'
                },
                height: 'auto',
                events: [],
                eventClick: function(info) {
                    mostrarDetalhesAgendamento(info.event);
                },
                eventDidMount: function(info) {
                    // Adicionar classes CSS baseadas no status
                    const status = info.event.extendedProps.status;
                    if (status === 'realizado') {
                        info.el.classList.add('event-realizado');
                    } else if (status === 'cancelado') {
                        info.el.classList.add('event-cancelado');
                    } else {
                        info.el.classList.add('event-agendado');
                    }
                }
            });
            
            calendar.render();
        }
        
        function carregarClientes() {
            fetch('includes/gerenciar_agendamentos.php?acao=buscar_clientes')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const filtroCliente = document.getElementById('filtroCliente');
                        
                        data.clientes.forEach(cliente => {
                            const optionFiltro = new Option(cliente.nome, cliente.cnpj);
                            filtroCliente.add(optionFiltro);
                        });
                    }
                })
                .catch(error => console.error('Erro ao carregar clientes:', error));
        }
        

        
        function carregarAgendamentos() {
            fetch('includes/gerenciar_agendamentos.php?acao=buscar_agendamentos')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        agendamentos = data.agendamentos;
                        atualizarCalendario();
                    }
                })
                .catch(error => console.error('Erro ao carregar agendamentos:', error));
        }
        
        function atualizarCalendario() {
            // Limpar eventos existentes
            calendar.removeAllEvents();
            
            // Adicionar novos eventos
            agendamentos.forEach(agendamento => {
                calendar.addEvent({
                    id: agendamento.id,
                    title: agendamento.cliente_nome,
                    start: agendamento.data_agendamento,
                    extendedProps: {
                        status: agendamento.status,
                        observacao: agendamento.observacao,
                        cliente_cnpj: agendamento.cliente_cnpj
                    }
                });
            });
        }
        
        function filtrarCalendario() {
            const filtroStatus = document.getElementById('filtroStatus').value;
            const filtroCliente = document.getElementById('filtroCliente').value;
            
            fetch(`includes/gerenciar_agendamentos.php?acao=filtrar_agendamentos&status=${filtroStatus}&cliente=${filtroCliente}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        agendamentos = data.agendamentos;
                        atualizarCalendario();
                    }
                })
                .catch(error => console.error('Erro ao filtrar agendamentos:', error));
        }
        

        

        
        function getStatusColor(status) {
            switch (status) {
                case 'agendado': return 'primary';
                case 'realizado': return 'success';
                case 'cancelado': return 'danger';
                default: return 'secondary';
            }
        }
        
        // Função para aplicar estilos aos badges
        function aplicarEstilosBadges() {
            const badges = document.querySelectorAll('.badge');
            badges.forEach(badge => {
                if (badge.classList.contains('bg-primary')) {
                    badge.style.backgroundColor = '#1a237e !important';
                } else if (badge.classList.contains('bg-success')) {
                    badge.style.backgroundColor = '#4caf50 !important';
                } else if (badge.classList.contains('bg-danger')) {
                    badge.style.backgroundColor = '#f44336 !important';
                }
            });
        }
        
        // ===== FUNÇÕES DE INTEGRAÇÃO COM OUTLOOK =====
        
        /**
         * Exporta todos os agendamentos para arquivo .ics
         */
        function exportarTodosICS() {
            // Criar um link temporário para download
            const link = document.createElement('a');
            link.href = 'includes/outlook_integration.php?acao=exportar_todos_ics';
            link.download = 'agendamentos_autopel_' + new Date().toISOString().slice(0, 10) + '.ics';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Mostrar mensagem de sucesso
            setTimeout(() => {
                alert('Arquivo .ics gerado com sucesso! Você pode importá-lo no Outlook.');
            }, 1000);
        }
        
        /**
         * Exporta um agendamento específico para arquivo .ics
         */
        function exportarAgendamentoICS(agendamentoId) {
            const link = document.createElement('a');
            link.href = 'includes/outlook_integration.php?acao=exportar_ics&agendamento_id=' + agendamentoId;
            link.download = 'agendamento_' + agendamentoId + '.ics';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        /**
         * Abre o modal de configuração do Outlook
         */
        function abrirModalOutlook() {
            document.getElementById('modalOutlook').style.display = 'flex';
        }
        
        /**
         * Fecha o modal de configuração do Outlook
         */
        function fecharModalOutlook() {
            document.getElementById('modalOutlook').style.display = 'none';
        }
        
        /**
         * Salva a configuração do Outlook
         */
        function salvarConfigOutlook() {
            const formData = new FormData();
            formData.append('acao', 'configurar_outlook');
            formData.append('client_id', document.getElementById('clientId').value);
            formData.append('client_secret', document.getElementById('clientSecret').value);
            formData.append('tenant_id', document.getElementById('tenantId').value);
            formData.append('email_outlook', document.getElementById('emailOutlook').value);
            
            fetch('includes/outlook_integration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configuração do Outlook salva com sucesso!');
                    fecharModalOutlook();
                } else {
                    alert('Erro ao salvar configuração: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar configuração do Outlook');
            });
        }
        
        /**
         * Testa a conexão com o Outlook
         */
        function testarConexaoOutlook() {
            const formData = new FormData();
            formData.append('acao', 'testar_conexao_outlook');
            
            fetch('includes/outlook_integration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Conexão com Outlook testada com sucesso!\nEmail: ' + data.email);
                } else {
                    alert('Erro ao testar conexão: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao testar conexão com Outlook');
            });
        }
        
        /**
         * Sincroniza agendamentos com o Outlook
         */
        function sincronizarOutlook() {
            const tipo = prompt('Escolha o tipo de sincronização:\n1 - Exportar para Outlook\n2 - Importar do Outlook\n3 - Sincronização bidirecional\n\nDigite 1, 2 ou 3:');
            
            if (!tipo || !['1', '2', '3'].includes(tipo)) {
                alert('Opção inválida');
                return;
            }
            
            const tipos = {
                '1': 'exportar',
                '2': 'importar',
                '3': 'bidirecional'
            };
            
            const formData = new FormData();
            formData.append('acao', 'sincronizar_outlook');
            formData.append('tipo', tipos[tipo]);
            
            // Mostrar indicador de carregamento
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sincronizando...';
            btn.disabled = true;
            
            fetch('includes/outlook_integration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Sincronização concluída!\n' + data.message);
                    // Recarregar agendamentos se necessário
                    if (data.exportados > 0 || data.importados > 0) {
                        carregarAgendamentos();
                    }
                } else {
                    alert('Erro na sincronização: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro na sincronização com Outlook');
            })
            .finally(() => {
                // Restaurar botão
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
    
    <?php 
    // Incluir footer
    $footer_path = 'includes/footer.php';
    if (!file_exists($footer_path)) {
        $footer_path = __DIR__ . '/includes/footer.php';
    }
    if (file_exists($footer_path)) {
        include $footer_path; 
    }
    ?>
</body>
</html>
