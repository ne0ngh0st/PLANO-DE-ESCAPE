<?php
// Headers para evitar cache - DEVEM vir antes de qualquer output
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../../includes/config/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath);
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once __DIR__ . '/../../includes/auth/block_ecommerce.php';

// Sincronizar dados do usuário na sessão
sincronizarSessaoUsuario($pdo);

$usuario = $_SESSION['usuario'];

// Restringir acesso: apenas admin e diretor
$perfil_permitido = in_array(strtolower(trim($usuario['perfil'])), ['admin', 'diretor']);
if (!$perfil_permitido) {
    $basePath = defined('SITE_BASE_PATH') ? SITE_BASE_PATH : (function_exists('detectarCaminhoBase') ? detectarCaminhoBase() : '/');
    header('Location: ' . $basePath . 'home?erro=sem_permissao');
    exit;
}

// Determinar se é supervisor para aplicar filtros de equipe
$is_supervisor = (strtolower(trim($usuario['perfil'])) === 'supervisor');
$cod_equipe_supervisor = null;

if ($is_supervisor) {
    // Para supervisores, o COD_VENDEDOR é o código que identifica a equipe
    $cod_equipe_supervisor = $usuario['COD_VENDEDOR'] ?? null;
}

// Definir variáveis para o template
$current_page = 'dashboard_volatilidade.php';

// Parâmetros de filtro - usar desde a primeira ligação
$data_inicio = $_GET['data_inicio'] ?? '2025-08-21'; // Primeira ligação registrada
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$comparar_com = $_GET['comparar_com'] ?? 'ontem'; // ontem, semana_anterior, mes_anterior

// Buscar métricas do período selecionado
$metricas_periodo = [];
$metricas_comparacao = [];
$alertas = [];

try {
    // Buscar métricas do período principal
    $stmt = $pdo->prepare("
        SELECT * FROM metricas_volatilidade_diaria 
        WHERE data_metrica BETWEEN ? AND ? 
        ORDER BY data_metrica DESC
    ");
    $stmt->execute([$data_inicio, $data_fim]);
    $metricas_periodo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar métricas para comparação
    $data_comparacao_inicio = '';
    $data_comparacao_fim = '';
    
    switch ($comparar_com) {
        case 'ontem':
            $data_comparacao_inicio = date('Y-m-d', strtotime('-1 day'));
            $data_comparacao_fim = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'semana_anterior':
            $data_comparacao_inicio = date('Y-m-d', strtotime('-7 days', strtotime($data_inicio)));
            $data_comparacao_fim = date('Y-m-d', strtotime('-7 days', strtotime($data_fim)));
            break;
        case 'mes_anterior':
            $data_comparacao_inicio = date('Y-m-d', strtotime('-1 month', strtotime($data_inicio)));
            $data_comparacao_fim = date('Y-m-d', strtotime('-1 month', strtotime($data_fim)));
            break;
    }
    
    if ($data_comparacao_inicio && $data_comparacao_fim) {
        $stmt = $pdo->prepare("
            SELECT * FROM metricas_volatilidade_diaria 
            WHERE data_metrica BETWEEN ? AND ? 
            ORDER BY data_metrica DESC
        ");
        $stmt->execute([$data_comparacao_inicio, $data_comparacao_fim]);
        $metricas_comparacao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Buscar dados reais de ligações por dia para o gráfico (excluindo ligações excluídas)
    $sql_grafico = "
        SELECT 
            DATE(l.data_ligacao) as data,
            SUM(CASE WHEN l.tipo_contato = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp,
            SUM(CASE WHEN l.tipo_contato = 'presencial' THEN 1 ELSE 0 END) as presencial,
            SUM(CASE WHEN l.tipo_contato = 'telefonica' THEN 1 ELSE 0 END) as telefonica,
            SUM(CASE WHEN l.tipo_contato = 'email' THEN 1 ELSE 0 END) as email,
            COUNT(*) as total
        FROM LIGACOES l";
    
    if ($is_supervisor && $cod_equipe_supervisor) {
        $sql_grafico .= " 
        INNER JOIN USUARIOS u ON l.usuario_id = u.ID 
        WHERE DATE(l.data_ligacao) BETWEEN ? AND ? 
        AND l.status != 'excluida'
        AND u.COD_SUPER = ?";
        $params_grafico = [$data_inicio, $data_fim, $cod_equipe_supervisor];
        
    } else {
        $sql_grafico .= " 
        WHERE DATE(l.data_ligacao) BETWEEN ? AND ? 
        AND l.status != 'excluida'";
        $params_grafico = [$data_inicio, $data_fim];
        
    }
    
    $sql_grafico .= " 
        GROUP BY DATE(l.data_ligacao)
        ORDER BY DATE(l.data_ligacao) ASC";
    
    $stmt = $pdo->prepare($sql_grafico);
    $stmt->execute($params_grafico);
    $dados_grafico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Usar dados de status dos clientes da tabela metricas_volatilidade_diaria
    $dados_status_clientes = $metricas_periodo;
    
    // Buscar alertas recentes
    $stmt = $pdo->prepare("
        SELECT * FROM alertas_volatilidade 
        WHERE data_metrica >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY data_criacao DESC, severidade DESC
        LIMIT 20
    ");
    $stmt->execute();
    $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erro ao buscar métricas: " . $e->getMessage();
}

// Calcular estatísticas resumidas
$estatisticas = [];

if ($is_supervisor && $cod_equipe_supervisor) {
    // Para supervisores: calcular métricas em tempo real baseadas na equipe
    try {
        // Obter hora atual para comparação justa
        $hora_atual = date('H:i:s');
        
        // Contatos WhatsApp da equipe HOJE até agora
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM LIGACOES l
            INNER JOIN USUARIOS u ON l.usuario_id = u.ID
            WHERE l.tipo_contato = 'whatsapp' 
            AND l.status != 'excluida'
            AND DATE(l.data_ligacao) = CURDATE()
            AND TIME(l.data_ligacao) <= ?
            AND u.COD_SUPER = ?
        ");
        $stmt->execute([$hora_atual, $cod_equipe_supervisor]);
        $contatos_whatsapp_hoje = $stmt->fetchColumn();
        
        // Contatos WhatsApp da equipe ONTEM até a mesma hora (para comparação justa)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM LIGACOES l
            INNER JOIN USUARIOS u ON l.usuario_id = u.ID
            WHERE l.tipo_contato = 'whatsapp' 
            AND l.status != 'excluida'
            AND DATE(l.data_ligacao) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND TIME(l.data_ligacao) <= ?
            AND u.COD_SUPER = ?
        ");
        $stmt->execute([$hora_atual, $cod_equipe_supervisor]);
        $contatos_whatsapp_ontem = $stmt->fetchColumn();
        
        // Contatos Presencial da equipe HOJE até agora
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM LIGACOES l
            INNER JOIN USUARIOS u ON l.usuario_id = u.ID
            WHERE l.tipo_contato = 'presencial' 
            AND l.status != 'excluida'
            AND DATE(l.data_ligacao) = CURDATE()
            AND TIME(l.data_ligacao) <= ?
            AND u.COD_SUPER = ?
        ");
        $stmt->execute([$hora_atual, $cod_equipe_supervisor]);
        $contatos_presencial_hoje = $stmt->fetchColumn();
        
        // Contatos Presencial da equipe ONTEM até a mesma hora (para comparação justa)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM LIGACOES l
            INNER JOIN USUARIOS u ON l.usuario_id = u.ID
            WHERE l.tipo_contato = 'presencial' 
            AND l.status != 'excluida'
            AND DATE(l.data_ligacao) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND TIME(l.data_ligacao) <= ?
            AND u.COD_SUPER = ?
        ");
        $stmt->execute([$hora_atual, $cod_equipe_supervisor]);
        $contatos_presencial_ontem = $stmt->fetchColumn();
        
        // Contatos Ligação da equipe HOJE até agora
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM LIGACOES l
            INNER JOIN USUARIOS u ON l.usuario_id = u.ID
            WHERE l.tipo_contato = 'telefonica' 
            AND l.status != 'excluida'
            AND DATE(l.data_ligacao) = CURDATE()
            AND TIME(l.data_ligacao) <= ?
            AND u.COD_SUPER = ?
        ");
        $stmt->execute([$hora_atual, $cod_equipe_supervisor]);
        $contatos_ligacao_hoje = $stmt->fetchColumn();
        
        // Contatos Ligação da equipe ONTEM até a mesma hora (para comparação justa)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM LIGACOES l
            INNER JOIN USUARIOS u ON l.usuario_id = u.ID
            WHERE l.tipo_contato = 'telefonica' 
            AND l.status != 'excluida'
            AND DATE(l.data_ligacao) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND TIME(l.data_ligacao) <= ?
            AND u.COD_SUPER = ?
        ");
        $stmt->execute([$hora_atual, $cod_equipe_supervisor]);
        $contatos_ligacao_ontem = $stmt->fetchColumn();
        
        // Contatos Email da equipe HOJE até agora
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM LIGACOES l
            INNER JOIN USUARIOS u ON l.usuario_id = u.ID
            WHERE l.tipo_contato = 'email' 
            AND l.status != 'excluida'
            AND DATE(l.data_ligacao) = CURDATE()
            AND TIME(l.data_ligacao) <= ?
            AND u.COD_SUPER = ?
        ");
        $stmt->execute([$hora_atual, $cod_equipe_supervisor]);
        $contatos_email_hoje = $stmt->fetchColumn();
        
        // Contatos Email da equipe ONTEM até a mesma hora (para comparação justa)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM LIGACOES l
            INNER JOIN USUARIOS u ON l.usuario_id = u.ID
            WHERE l.tipo_contato = 'email' 
            AND l.status != 'excluida'
            AND DATE(l.data_ligacao) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND TIME(l.data_ligacao) <= ?
            AND u.COD_SUPER = ?
        ");
        $stmt->execute([$hora_atual, $cod_equipe_supervisor]);
        $contatos_email_ontem = $stmt->fetchColumn();
        
        // Para clientes, usar dados globais (não há relação direta com equipe)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total
            FROM ultimo_faturamento uf
        ");
        $stmt->execute();
        $total_clientes = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8)) as total
            FROM ultimo_faturamento uf
            WHERE uf.ULTIMO_FATURAMENTO >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        ");
        $stmt->execute();
        $clientes_ativos = $stmt->fetchColumn();
        
        $clientes_inativos = $total_clientes - $clientes_ativos;
        $clientes_inativando = 0; // Não temos dados específicos para isso
        
        // Calcular variações percentuais
        $variacao_whatsapp = $contatos_whatsapp_ontem > 0 ? 
            (($contatos_whatsapp_hoje - $contatos_whatsapp_ontem) / $contatos_whatsapp_ontem) * 100 : 
            ($contatos_whatsapp_hoje > 0 ? 100 : 0);
            
        $variacao_presencial = $contatos_presencial_ontem > 0 ? 
            (($contatos_presencial_hoje - $contatos_presencial_ontem) / $contatos_presencial_ontem) * 100 : 
            ($contatos_presencial_hoje > 0 ? 100 : 0);
            
        $variacao_ligacao = $contatos_ligacao_ontem > 0 ? 
            (($contatos_ligacao_hoje - $contatos_ligacao_ontem) / $contatos_ligacao_ontem) * 100 : 
            ($contatos_ligacao_hoje > 0 ? 100 : 0);
            
        $variacao_email = $contatos_email_ontem > 0 ? 
            (($contatos_email_hoje - $contatos_email_ontem) / $contatos_email_ontem) * 100 : 
            ($contatos_email_hoje > 0 ? 100 : 0);

        $estatisticas = [
            'total_clientes' => [
                'atual' => $total_clientes,
                'anterior' => 0,
                'variacao' => 0
            ],
            'contatos_whatsapp' => [
                'atual' => $contatos_whatsapp_hoje,
                'anterior' => $contatos_whatsapp_ontem,
                'variacao' => $variacao_whatsapp
            ],
            'contatos_presencial' => [
                'atual' => $contatos_presencial_hoje,
                'anterior' => $contatos_presencial_ontem,
                'variacao' => $variacao_presencial
            ],
            'contatos_ligacao' => [
                'atual' => $contatos_ligacao_hoje,
                'anterior' => $contatos_ligacao_ontem,
                'variacao' => $variacao_ligacao
            ],
            'contatos_email' => [
                'atual' => $contatos_email_hoje,
                'anterior' => $contatos_email_ontem,
                'variacao' => $variacao_email
            ],
            'clientes_ativos' => [
                'atual' => $clientes_ativos,
                'anterior' => 0,
                'variacao' => 0
            ],
            'clientes_inativos' => [
                'atual' => $clientes_inativos,
                'anterior' => 0,
                'variacao' => 0
            ],
            'clientes_inativando' => [
                'atual' => $clientes_inativando,
                'anterior' => 0,
                'variacao' => 0
            ],
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao calcular métricas da equipe: " . $e->getMessage());
        $estatisticas = [];
    }
} else {
    // Para admin/diretor: usar métricas globais da tabela com comparação por horário
    if (!empty($metricas_periodo)) {
        $ultima_metrica = $metricas_periodo[0];
        $penultima_metrica = count($metricas_periodo) > 1 ? $metricas_periodo[1] : null;
        
        // Obter hora atual para comparação justa
        $hora_atual = date('H:i:s');
        $data_anterior = date('Y-m-d', strtotime('-1 day'));
        
        // Buscar dados do dia anterior até a mesma hora para contatos
        $contatos_whatsapp_ontem = 0;
        $contatos_presencial_ontem = 0;
        $contatos_ligacao_ontem = 0;
        $contatos_email_ontem = 0;
        
        try {
            // WhatsApp ontem até a mesma hora
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM LIGACOES 
                WHERE tipo_contato = 'whatsapp' 
                AND status != 'excluida'
                AND DATE(data_ligacao) = ?
                AND TIME(data_ligacao) <= ?
            ");
            $stmt->execute([$data_anterior, $hora_atual]);
            $contatos_whatsapp_ontem = $stmt->fetchColumn();
            
            // Presencial ontem até a mesma hora
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM LIGACOES 
                WHERE tipo_contato = 'presencial' 
                AND status != 'excluida'
                AND DATE(data_ligacao) = ?
                AND TIME(data_ligacao) <= ?
            ");
            $stmt->execute([$data_anterior, $hora_atual]);
            $contatos_presencial_ontem = $stmt->fetchColumn();
            
            // Ligação ontem até a mesma hora
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM LIGACOES 
                WHERE tipo_contato = 'telefonica' 
                AND status != 'excluida'
                AND DATE(data_ligacao) = ?
                AND TIME(data_ligacao) <= ?
            ");
            $stmt->execute([$data_anterior, $hora_atual]);
            $contatos_ligacao_ontem = $stmt->fetchColumn();
            
            // Email ontem até a mesma hora
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM LIGACOES 
                WHERE tipo_contato = 'email' 
                AND status != 'excluida'
                AND DATE(data_ligacao) = ?
                AND TIME(data_ligacao) <= ?
            ");
            $stmt->execute([$data_anterior, $hora_atual]);
            $contatos_email_ontem = $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Erro ao buscar dados do dia anterior: " . $e->getMessage());
        }
        
        $estatisticas = [
            'total_clientes' => [
                'atual' => $ultima_metrica['total_clientes'],
                'anterior' => $penultima_metrica ? $penultima_metrica['total_clientes'] : 0,
                'variacao' => $penultima_metrica ? 
                    (($ultima_metrica['total_clientes'] - $penultima_metrica['total_clientes']) / max($penultima_metrica['total_clientes'], 1)) * 100 : 0
            ],
            'contatos_whatsapp' => [
                'atual' => $ultima_metrica['contatos_whatsapp_hoje'] ?? $ultima_metrica['total_contatos_whatsapp'] ?? 0,
                'anterior' => $contatos_whatsapp_ontem,
                'variacao' => $contatos_whatsapp_ontem > 0 ? 
                    ((($ultima_metrica['contatos_whatsapp_hoje'] ?? $ultima_metrica['total_contatos_whatsapp'] ?? 0) - $contatos_whatsapp_ontem) / $contatos_whatsapp_ontem) * 100 : 
                    (($ultima_metrica['contatos_whatsapp_hoje'] ?? $ultima_metrica['total_contatos_whatsapp'] ?? 0) > 0 ? 100 : 0)
            ],
            'contatos_presencial' => [
                'atual' => $ultima_metrica['contatos_presencial_hoje'] ?? $ultima_metrica['total_contatos_presencial'] ?? 0,
                'anterior' => $contatos_presencial_ontem,
                'variacao' => $contatos_presencial_ontem > 0 ? 
                    ((($ultima_metrica['contatos_presencial_hoje'] ?? $ultima_metrica['total_contatos_presencial'] ?? 0) - $contatos_presencial_ontem) / $contatos_presencial_ontem) * 100 : 
                    (($ultima_metrica['contatos_presencial_hoje'] ?? $ultima_metrica['total_contatos_presencial'] ?? 0) > 0 ? 100 : 0)
            ],
            'contatos_ligacao' => [
                'atual' => $ultima_metrica['contatos_ligacao_hoje'] ?? $ultima_metrica['total_contatos_ligacao'] ?? 0,
                'anterior' => $contatos_ligacao_ontem,
                'variacao' => $contatos_ligacao_ontem > 0 ? 
                    ((($ultima_metrica['contatos_ligacao_hoje'] ?? $ultima_metrica['total_contatos_ligacao'] ?? 0) - $contatos_ligacao_ontem) / $contatos_ligacao_ontem) * 100 : 
                    (($ultima_metrica['contatos_ligacao_hoje'] ?? $ultima_metrica['total_contatos_ligacao'] ?? 0) > 0 ? 100 : 0)
            ],
            'contatos_email' => [
                'atual' => $ultima_metrica['contatos_email_hoje'] ?? $ultima_metrica['total_contatos_email'] ?? 0,
                'anterior' => $contatos_email_ontem,
                'variacao' => $contatos_email_ontem > 0 ? 
                    ((($ultima_metrica['contatos_email_hoje'] ?? $ultima_metrica['total_contatos_email'] ?? 0) - $contatos_email_ontem) / $contatos_email_ontem) * 100 : 
                    (($ultima_metrica['contatos_email_hoje'] ?? $ultima_metrica['total_contatos_email'] ?? 0) > 0 ? 100 : 0)
            ],
        'clientes_ativos' => [
            'atual' => $ultima_metrica['total_clientes_ativos'],
            'anterior' => $penultima_metrica ? $penultima_metrica['total_clientes_ativos'] : 0,
            'variacao' => $penultima_metrica ? 
                (($ultima_metrica['total_clientes_ativos'] - $penultima_metrica['total_clientes_ativos']) / max($penultima_metrica['total_clientes_ativos'], 1)) * 100 : 0
        ],
        'clientes_inativos' => [
            'atual' => $ultima_metrica['total_clientes_inativos'],
            'anterior' => $penultima_metrica ? $penultima_metrica['total_clientes_inativos'] : 0,
            'variacao' => $penultima_metrica ? 
                (($ultima_metrica['total_clientes_inativos'] - $penultima_metrica['total_clientes_inativos']) / max($penultima_metrica['total_clientes_inativos'], 1)) * 100 : 0
        ],
            'clientes_inativando' => [
                'atual' => $ultima_metrica['total_clientes_inativando'],
                'anterior' => $penultima_metrica ? $penultima_metrica['total_clientes_inativando'] : 0,
                'variacao' => $penultima_metrica ? 
                    (($ultima_metrica['total_clientes_inativando'] - $penultima_metrica['total_clientes_inativando']) / max($penultima_metrica['total_clientes_inativando'], 1)) * 100 : 0
            ],
        ];
    }
}

// Buscar dados de contatos por vendedor
$contatos_por_vendedor = [];

try {
    // Consulta para contatos por vendedor
    $sql_contatos = "
        SELECT 
            l.usuario_id,
            u.NOME_EXIBICAO as nome_vendedor,
            l.tipo_contato,
            COUNT(*) as total_contatos
        FROM LIGACOES l
        LEFT JOIN USUARIOS u ON l.usuario_id = u.ID
        WHERE l.status != 'excluida'";
    
    if ($is_supervisor && $cod_equipe_supervisor) {
        $sql_contatos .= " AND u.COD_SUPER = ?";
        $params_contatos = [$cod_equipe_supervisor];
        
    } else {
        $params_contatos = [];
        
    }
    
    $sql_contatos .= "
        GROUP BY l.usuario_id, l.tipo_contato
        ORDER BY l.tipo_contato, total_contatos DESC";
    
    $stmt = $pdo->prepare($sql_contatos);
    $stmt->execute($params_contatos);
    $contatos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar dados por tipo de contato
    foreach ($contatos_raw as $contato) {
        $tipo = $contato['tipo_contato'] ?: 'outros';
        $contatos_por_vendedor[$tipo][] = [
            'nome' => $contato['nome_vendedor'] ?: 'Usuário ' . $contato['usuario_id'],
            'total' => $contato['total_contatos']
        ];
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar contatos por vendedor: " . $e->getMessage());
    $contatos_por_vendedor = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard de Volatilidade Diária - Autopel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
    <link rel="stylesheet" href="<?php echo base_url('assets/css/dashboard-volatilidade.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/dashboard-volatilidade-mobile.css'); ?>?v=<?php echo time(); ?>">
    <link rel="icon" href="<?php echo base_url('assets/img/logo_site.png'); ?>" type="image/png">
    <style>
        /* Estilos para a seção de transições */
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        #secaoTransicoes {
            /* Integrada à seção principal */
        }
        
        #secaoTransicoes .card {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        
        #secaoTransicoes .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        #secaoTransicoes .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        
        #secaoTransicoes .table tbody tr {
            transition: all 0.3s ease;
        }
        
        #secaoTransicoes .table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.01);
        }
        
        #secaoTransicoes .badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
        }
        
        #secaoTransicoes .form-control,
        #secaoTransicoes .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        #secaoTransicoes .form-control:focus,
        #secaoTransicoes .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        #secaoTransicoes .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        #secaoTransicoes .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Incluir navbar -->
        <?php include __DIR__ . '/../../includes/components/navbar.php'; ?>
        <?php include __DIR__ . '/../../includes/components/nav_hamburguer.php'; ?>
        
        <div class="dashboard-container">
            <main class="dashboard-main">
                <!-- Seção de Boas-vindas -->
                <section class="welcome-section">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                    <h2>
                        <i class="fas fa-chart-line"></i>
                        Dashboard de Volatilidade Diária
                        <?php if ($is_supervisor && $cod_equipe_supervisor): ?>
                            <span class="badge bg-info ms-2">
                                <i class="fas fa-users"></i>
                                Equipe <?php echo htmlspecialchars($cod_equipe_supervisor); ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                    <p>
                        <?php if ($is_supervisor && $cod_equipe_supervisor): ?>
                            Acompanhe as variações diárias das principais métricas da sua equipe
                        <?php else: ?>
                            Acompanhe as variações diárias das principais métricas do sistema
                        <?php endif; ?>
                        <br>
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            As comparações são feitas com base no horário atual (<?php echo date('H:i'); ?>), comparando com o mesmo horário do dia anterior
                        </small>
                    </p>
                        </div>
                        <div>
                            <?php if (!$is_supervisor): ?>
                            <button type="button" class="btn btn-primary" id="btnColetarMetricas" title="Coletar métricas manualmente">
                                <i class="fas fa-sync-alt"></i>
                                <span>Coletar Métricas</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                
                <div class="container-fluid">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    
                    <!-- Seção: CONTATOS -->
                    <div class="kpi-section">
                        <h4 class="section-title">
                            <i class="fas fa-comments"></i>
                            Contatos Realizados
                        </h4>
                    <div class="row g-3">
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="card metric-card-compact">
                                <div class="card-body">
                                    <div class="metric-header">
                                            <h6 class="metric-label">WhatsApp</h6>
                                            <div class="metric-icon whatsapp">
                                            <i class="fab fa-whatsapp"></i>
                            </div>
                            </div>
                                        <div class="metric-value"><?php echo number_format($estatisticas['contatos_whatsapp']['atual'] ?? 0); ?></div>
                                    <div class="metric-change <?php echo ($estatisticas['contatos_whatsapp']['variacao'] ?? 0) > 0 ? 'positive' : (($estatisticas['contatos_whatsapp']['variacao'] ?? 0) < 0 ? 'negative' : 'neutral'); ?>">
                                        <?php if (($estatisticas['contatos_whatsapp']['variacao'] ?? 0) > 0): ?>
                                            <i class="fas fa-arrow-up"></i>
                                            <span>+<?php echo number_format($estatisticas['contatos_whatsapp']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php elseif (($estatisticas['contatos_whatsapp']['variacao'] ?? 0) < 0): ?>
                                            <i class="fas fa-arrow-down"></i>
                                            <span><?php echo number_format($estatisticas['contatos_whatsapp']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php else: ?>
                                            <i class="fas fa-minus"></i>
                                            <span>0%</span>
                                        <?php endif; ?>
                            </div>
                                        <?php if (isset($contatos_por_vendedor['whatsapp']) && !empty($contatos_por_vendedor['whatsapp'])): ?>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#whatsappDetails" aria-expanded="false">
                                                    <i class="fas fa-chevron-down"></i> Vendedores
                                </button>
                            </div>
                                        <?php endif; ?>
                                </div>
                            </div>
                    </div>
                    
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="card metric-card-compact">
                                <div class="card-body">
                                    <div class="metric-header">
                                            <h6 class="metric-label">Presencial</h6>
                                            <div class="metric-icon presencial">
                                            <i class="fas fa-handshake"></i>
                                        </div>
                                    </div>
                                        <div class="metric-value"><?php echo number_format($estatisticas['contatos_presencial']['atual'] ?? 0); ?></div>
                                    <div class="metric-change <?php echo ($estatisticas['contatos_presencial']['variacao'] ?? 0) > 0 ? 'positive' : (($estatisticas['contatos_presencial']['variacao'] ?? 0) < 0 ? 'negative' : 'neutral'); ?>">
                                        <?php if (($estatisticas['contatos_presencial']['variacao'] ?? 0) > 0): ?>
                                            <i class="fas fa-arrow-up"></i>
                                            <span>+<?php echo number_format($estatisticas['contatos_presencial']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php elseif (($estatisticas['contatos_presencial']['variacao'] ?? 0) < 0): ?>
                                            <i class="fas fa-arrow-down"></i>
                                            <span><?php echo number_format($estatisticas['contatos_presencial']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php else: ?>
                                            <i class="fas fa-minus"></i>
                                            <span>0%</span>
                                        <?php endif; ?>
                                    </div>
                                        <?php if (isset($contatos_por_vendedor['presencial']) && !empty($contatos_por_vendedor['presencial'])): ?>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#presencialDetails" aria-expanded="false">
                                                    <i class="fas fa-chevron-down"></i> Vendedores
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="card metric-card-compact">
                                <div class="card-body">
                                    <div class="metric-header">
                                            <h6 class="metric-label">Ligação</h6>
                                            <div class="metric-icon ligacao">
                                            <i class="fas fa-phone"></i>
                                        </div>
                                    </div>
                                        <div class="metric-value"><?php echo number_format($estatisticas['contatos_ligacao']['atual'] ?? 0); ?></div>
                                    <div class="metric-change <?php echo ($estatisticas['contatos_ligacao']['variacao'] ?? 0) > 0 ? 'positive' : (($estatisticas['contatos_ligacao']['variacao'] ?? 0) < 0 ? 'negative' : 'neutral'); ?>">
                                        <?php if (($estatisticas['contatos_ligacao']['variacao'] ?? 0) > 0): ?>
                                            <i class="fas fa-arrow-up"></i>
                                            <span>+<?php echo number_format($estatisticas['contatos_ligacao']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php elseif (($estatisticas['contatos_ligacao']['variacao'] ?? 0) < 0): ?>
                                            <i class="fas fa-arrow-down"></i>
                                            <span><?php echo number_format($estatisticas['contatos_ligacao']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php else: ?>
                                            <i class="fas fa-minus"></i>
                                            <span>0%</span>
                                        <?php endif; ?>
                                    </div>
                                        <?php if (isset($contatos_por_vendedor['telefonica']) && !empty($contatos_por_vendedor['telefonica'])): ?>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#ligacaoDetails" aria-expanded="false">
                                                    <i class="fas fa-chevron-down"></i> Vendedores
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="card metric-card-compact">
                                <div class="card-body">
                                    <div class="metric-header">
                                            <h6 class="metric-label">Email</h6>
                                            <div class="metric-icon email">
                                                <i class="fas fa-envelope"></i>
                                        </div>
                                    </div>
                                        <div class="metric-value"><?php echo number_format($estatisticas['contatos_email']['atual'] ?? 0); ?></div>
                                        <div class="metric-change <?php echo ($estatisticas['contatos_email']['variacao'] ?? 0) > 0 ? 'positive' : (($estatisticas['contatos_email']['variacao'] ?? 0) < 0 ? 'negative' : 'neutral'); ?>">
                                            <?php if (($estatisticas['contatos_email']['variacao'] ?? 0) > 0): ?>
                                                <i class="fas fa-arrow-up"></i>
                                                <span>+<?php echo number_format($estatisticas['contatos_email']['variacao'] ?? 0, 1); ?>%</span>
                                            <?php elseif (($estatisticas['contatos_email']['variacao'] ?? 0) < 0): ?>
                                                <i class="fas fa-arrow-down"></i>
                                                <span><?php echo number_format($estatisticas['contatos_email']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php else: ?>
                                            <i class="fas fa-minus"></i>
                                            <span>0%</span>
                                        <?php endif; ?>
                                    </div>
                                        <?php if (isset($contatos_por_vendedor['email']) && !empty($contatos_por_vendedor['email'])): ?>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#emailDetails" aria-expanded="false">
                                                    <i class="fas fa-chevron-down"></i> Vendedores
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detalhes dos Vendedores por Tipo de Contato -->
                        <?php if (!empty($contatos_por_vendedor)): ?>
                            <!-- WhatsApp Details -->
                            <?php if (isset($contatos_por_vendedor['whatsapp']) && !empty($contatos_por_vendedor['whatsapp'])): ?>
                                <div class="collapse mt-3" id="whatsappDetails">
                                    <div class="card">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0">
                                                <i class="fab fa-whatsapp"></i>
                                                Vendedores - WhatsApp
                                            </h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <tbody>
                                                        <?php foreach (array_slice($contatos_por_vendedor['whatsapp'], 0, 5) as $vendedor): ?>
                                                        <tr>
                                                            <td>
                                                                <i class="fab fa-whatsapp me-2 text-success"></i>
                                                                <?php echo htmlspecialchars($vendedor['nome']); ?>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="badge bg-success"><?php echo number_format($vendedor['total']); ?></span>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Presencial Details -->
                            <?php if (isset($contatos_por_vendedor['presencial']) && !empty($contatos_por_vendedor['presencial'])): ?>
                                <div class="collapse mt-3" id="presencialDetails">
                                    <div class="card">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0">
                                                <i class="fas fa-handshake"></i>
                                                Vendedores - Presencial
                                            </h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <tbody>
                                                        <?php foreach (array_slice($contatos_por_vendedor['presencial'], 0, 5) as $vendedor): ?>
                                                        <tr>
                                                            <td>
                                                                <i class="fas fa-handshake me-2 text-success"></i>
                                                                <?php echo htmlspecialchars($vendedor['nome']); ?>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="badge bg-success"><?php echo number_format($vendedor['total']); ?></span>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Ligação Details -->
                            <?php if (isset($contatos_por_vendedor['telefonica']) && !empty($contatos_por_vendedor['telefonica'])): ?>
                                <div class="collapse mt-3" id="ligacaoDetails">
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0">
                                                <i class="fas fa-phone"></i>
                                                Vendedores - Ligação
                                            </h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <tbody>
                                                        <?php foreach (array_slice($contatos_por_vendedor['telefonica'], 0, 5) as $vendedor): ?>
                                                        <tr>
                                                            <td>
                                                                <i class="fas fa-phone me-2 text-primary"></i>
                                                                <?php echo htmlspecialchars($vendedor['nome']); ?>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="badge bg-primary"><?php echo number_format($vendedor['total']); ?></span>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Email Details -->
                            <?php if (isset($contatos_por_vendedor['email']) && !empty($contatos_por_vendedor['email'])): ?>
                                <div class="collapse mt-3" id="emailDetails">
                                    <div class="card">
                                        <div class="card-header bg-secondary text-white">
                                            <h6 class="mb-0">
                                                <i class="fas fa-envelope"></i>
                                                Vendedores - Email
                                            </h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <tbody>
                                                        <?php foreach (array_slice($contatos_por_vendedor['email'], 0, 5) as $vendedor): ?>
                                                        <tr>
                                                            <td>
                                                                <i class="fas fa-envelope me-2 text-secondary"></i>
                                                                <?php echo htmlspecialchars($vendedor['nome']); ?>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="badge bg-secondary"><?php echo number_format($vendedor['total']); ?></span>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Gráfico de Evolução -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="chart-container">
                                <h5 class="mb-2">
                                    <i class="fas fa-chart-line"></i>
                                    Evolução das Métricas
                                </h5>
                                <div class="chart-canvas-container">
                                    <canvas id="evolucaoChart"></canvas>
                                </div>
                                <!-- Indicador mobile para gráficos ocultos -->
                                <div class="mobile-chart-indicator d-none">
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-mobile-alt me-2"></i>
                                        <strong>Gráficos disponíveis na versão desktop</strong>
                                        <br>
                                        <small>Use um dispositivo com tela maior para visualizar os gráficos detalhados</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção: STATUS DOS CLIENTES -->
                    <div class="kpi-section mt-4">
                        <h4 class="section-title">
                            <i class="fas fa-users"></i>
                            Status dos Clientes
                        </h4>
                    <div class="row g-3">
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="card metric-card-compact">
                                <div class="card-body">
                                    <div class="metric-header">
                                            <h6 class="metric-label">Total Clientes</h6>
                                            <div class="metric-icon clientes">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                        <div class="metric-value"><?php echo number_format($estatisticas['total_clientes']['atual'] ?? 0); ?></div>
                                        <div class="metric-change <?php echo ($estatisticas['total_clientes']['variacao'] ?? 0) > 0 ? 'positive' : (($estatisticas['total_clientes']['variacao'] ?? 0) < 0 ? 'negative' : 'neutral'); ?>">
                                            <?php if (($estatisticas['total_clientes']['variacao'] ?? 0) > 0): ?>
                                                <i class="fas fa-arrow-up"></i>
                                                <span>+<?php echo number_format($estatisticas['total_clientes']['variacao'] ?? 0, 1); ?>%</span>
                                            <?php elseif (($estatisticas['total_clientes']['variacao'] ?? 0) < 0): ?>
                                                <i class="fas fa-arrow-down"></i>
                                                <span><?php echo number_format($estatisticas['total_clientes']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php else: ?>
                                            <i class="fas fa-minus"></i>
                                            <span>0%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="card metric-card-compact">
                                <div class="card-body">
                                    <div class="metric-header">
                                        <h6 class="metric-label">Clientes Ativos</h6>
                                            <div class="metric-icon ativos">
                                                <i class="fas fa-user-check"></i>
                                        </div>
                                    </div>
                                        <div class="metric-value"><?php echo number_format($estatisticas['clientes_ativos']['atual'] ?? 0); ?></div>
                                    <div class="metric-change <?php echo ($estatisticas['clientes_ativos']['variacao'] ?? 0) > 0 ? 'positive' : (($estatisticas['clientes_ativos']['variacao'] ?? 0) < 0 ? 'negative' : 'neutral'); ?>">
                                        <?php if (($estatisticas['clientes_ativos']['variacao'] ?? 0) > 0): ?>
                                            <i class="fas fa-arrow-up"></i>
                                            <span>+<?php echo number_format($estatisticas['clientes_ativos']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php elseif (($estatisticas['clientes_ativos']['variacao'] ?? 0) < 0): ?>
                                            <i class="fas fa-arrow-down"></i>
                                            <span><?php echo number_format($estatisticas['clientes_ativos']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php else: ?>
                                            <i class="fas fa-minus"></i>
                                            <span>0%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="card metric-card-compact">
                                <div class="card-body">
                                    <div class="metric-header">
                                        <h6 class="metric-label">Clientes Inativos</h6>
                                            <div class="metric-icon inativos">
                                            <i class="fas fa-user-times"></i>
                                        </div>
                                    </div>
                                        <div class="metric-value"><?php echo number_format($estatisticas['clientes_inativos']['atual'] ?? 0); ?></div>
                                    <div class="metric-change <?php echo ($estatisticas['clientes_inativos']['variacao'] ?? 0) > 0 ? 'negative' : (($estatisticas['clientes_inativos']['variacao'] ?? 0) < 0 ? 'positive' : 'neutral'); ?>">
                                        <?php if (($estatisticas['clientes_inativos']['variacao'] ?? 0) > 0): ?>
                                            <i class="fas fa-arrow-up"></i>
                                            <span>+<?php echo number_format($estatisticas['clientes_inativos']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php elseif (($estatisticas['clientes_inativos']['variacao'] ?? 0) < 0): ?>
                                            <i class="fas fa-arrow-down"></i>
                                            <span><?php echo number_format($estatisticas['clientes_inativos']['variacao'] ?? 0, 1); ?>%</span>
                                        <?php else: ?>
                                            <i class="fas fa-minus"></i>
                                            <span>0%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="card metric-card-compact">
                                <div class="card-body">
                                    <div class="metric-header">
                                        <h6 class="metric-label">Clientes Inativando</h6>
                                            <div class="metric-icon inativando">
                                            <i class="fas fa-user-clock"></i>
                                        </div>
                                    </div>
                                        <div class="metric-value"><?php echo number_format($estatisticas['clientes_inativando']['atual'] ?? 0); ?></div>
                                        <div class="metric-change <?php echo ($estatisticas['clientes_inativando']['variacao'] ?? 0) > 0 ? 'negative' : (($estatisticas['clientes_inativando']['variacao'] ?? 0) < 0 ? 'positive' : 'neutral'); ?>">
                                            <?php if (($estatisticas['clientes_inativando']['variacao'] ?? 0) > 0): ?>
                                                <i class="fas fa-arrow-up"></i>
                                                <span>+<?php echo number_format($estatisticas['clientes_inativando']['variacao'] ?? 0, 1); ?>%</span>
                                            <?php elseif (($estatisticas['clientes_inativando']['variacao'] ?? 0) < 0): ?>
                                                <i class="fas fa-arrow-down"></i>
                                                <span><?php echo number_format($estatisticas['clientes_inativando']['variacao'] ?? 0, 1); ?>%</span>
                                            <?php else: ?>
                                        <i class="fas fa-minus"></i>
                                        <span>0%</span>
                                            <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                    
                    <!-- Subseção: Transições de Status -->
                    <div class="row mt-4" id="secaoTransicoes">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-muted">
                                        <i class="fas fa-exchange-alt me-2"></i>
                                        Transições de Status dos Clientes
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <!-- Filtros -->
                                    <div class="row mb-4">
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <label for="filtroTipoTransicao" class="form-label">Tipo de Transição</label>
                                            <select class="form-select" id="filtroTipoTransicao">
                                                <option value="todas">Todas as Transições</option>
                                                <option value="ativo_inativando">Ativo → Inativando</option>
                                                <option value="inativando_ativo">Inativando → Ativo</option>
                                                <option value="inativando_inativo">Inativando → Inativo</option>
                                                <option value="inativo_ativo">Inativo → Ativo</option>
                                                <option value="novo_ativo">Novo → Ativo</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <label for="filtroDataInicio" class="form-label">Data Início</label>
                                            <input type="date" class="form-control" id="filtroDataInicio" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                                        </div>
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <label for="filtroDataFim" class="form-label">Data Fim</label>
                                            <input type="date" class="form-control" id="filtroDataFim" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-lg-3 col-md-6 mb-3">
                                            <label for="filtroLimite" class="form-label">Limite de Registros</label>
                                            <select class="form-select" id="filtroLimite">
                                                <option value="50">50 registros</option>
                                                <option value="100">100 registros</option>
                                                <option value="200">200 registros</option>
                                                <option value="500">500 registros</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Botões de Ação -->
                                    <div class="row mb-4">
                                        <div class="col-md-8">
                                            <button type="button" class="btn btn-primary" id="btnBuscarTransicoes">
                                                <i class="fas fa-search"></i> Buscar Transições
                                            </button>
                                            <button type="button" class="btn btn-secondary" id="btnLimparFiltros">
                                                <i class="fas fa-eraser"></i> Limpar Filtros
                                            </button>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <span class="badge bg-info fs-6" id="contadorTransicoes">0 transições encontradas</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Loading -->
                                    <div id="loadingTransicoes" class="text-center py-4" style="display: none;">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Carregando...</span>
                                        </div>
                                        <p class="mt-2">Buscando transições...</p>
                                    </div>
                                    
                                    <!-- Estatísticas Resumidas -->
                                    <div id="estatisticasTransicoes" class="row mb-4" style="display: none;">
                                        <div class="col-12">
                                            <h6><i class="fas fa-chart-bar"></i> Resumo das Transições</h6>
                                            <div id="cardsEstatisticas" class="row g-3">
                                                <!-- Cards serão preenchidos via JavaScript -->
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tabela de Transições -->
                                    <div id="tabelaTransicoes" style="display: none;">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Data</th>
                                                        <th>Cliente</th>
                                                        <th>CNPJ</th>
                                                        <th>Transição</th>
                                                        <th>Última Compra</th>
                                                        <th>Dias sem Comprar</th>
                                                        <th>Valor Última Compra</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="corpoTabelaTransicoes">
                                                    <!-- Dados serão preenchidos via JavaScript -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Mensagem quando não há dados -->
                                    <div id="semTransicoes" class="text-center py-4 text-muted" style="display: none;">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <h5>Nenhuma transição encontrada</h5>
                                        <p>Não foram encontradas transições de status para os filtros selecionados.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Status dos Clientes -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="chart-container">
                                <h5 class="mb-2">
                                    <i class="fas fa-chart-bar"></i>
                                    Evolução do Status dos Clientes
                                </h5>
                                <div class="chart-canvas-container">
                                    <canvas id="statusClientesChart"></canvas>
                                </div>
                                <!-- Indicador mobile para gráficos ocultos -->
                                <div class="mobile-chart-indicator d-none">
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-mobile-alt me-2"></i>
                                        <strong>Gráficos disponíveis na versão desktop</strong>
                                        <br>
                                        <small>Use um dispositivo com tela maior para visualizar os gráficos detalhados</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alertas -->
                    <?php if (!empty($alertas)): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="chart-container">
                                <h5 class="mb-2">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Alertas de Volatilidade
                                </h5>
                                <?php foreach ($alertas as $alerta): ?>
                                    <div class="alert-item <?php echo $alerta['severidade']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo ucfirst($alerta['tipo_alerta']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($alerta['data_criacao'])); ?>
                                                </small>
                                                <br>
                                                <?php if ($alerta['descricao']): ?>
                                                    <?php echo htmlspecialchars($alerta['descricao']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php echo $alerta['severidade'] === 'critica' ? 'danger' : ($alerta['severidade'] === 'alta' ? 'warning' : ($alerta['severidade'] === 'media' ? 'info' : 'success')); ?>">
                                                    <?php echo ucfirst($alerta['severidade']); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo number_format($alerta['percentual_mudanca'], 1); ?>%
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    
    <!-- Scripts -->
    <script src="<?php echo base_url('assets/js/dark-mode.js'); ?>?v=<?php echo time(); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?php echo base_url('assets/js/dashboard-volatilidade.js'); ?>?v=<?php echo time(); ?>"></script>
    <script>
        // Dados para os gráficos
        const metricasData = <?php echo json_encode($metricas_periodo); ?>;
        const metricasComparacao = <?php echo json_encode($metricas_comparacao); ?>;
        const dadosGrafico = <?php echo json_encode($dados_grafico); ?>;
        const dadosStatusClientes = <?php echo json_encode($dados_status_clientes); ?>;
        
        // Verificar se há dados antes de criar os gráficos
        if (dadosGrafico && dadosGrafico.length > 0) {
            // Gráfico de evolução
            const ctxEvolucao = document.getElementById('evolucaoChart').getContext('2d');
            new Chart(ctxEvolucao, {
            type: 'line',
            data: {
                labels: dadosGrafico.map(d => new Date(d.data).toLocaleDateString('pt-BR')),
                datasets: [
                    {
                        label: 'WhatsApp',
                        data: dadosGrafico.map(d => parseInt(d.whatsapp)),
                        borderColor: '#25D366',
                        backgroundColor: 'rgba(37, 211, 102, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Presencial',
                        data: dadosGrafico.map(d => parseInt(d.presencial)),
                        borderColor: '#FF6B6B',
                        backgroundColor: 'rgba(255, 107, 107, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Ligação',
                        data: dadosGrafico.map(d => parseInt(d.telefonica)),
                        borderColor: '#4ECDC4',
                        backgroundColor: 'rgba(78, 205, 196, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Email',
                        data: dadosGrafico.map(d => parseInt(d.email)),
                        borderColor: '#9C27B0',
                        backgroundColor: 'rgba(156, 39, 176, 0.1)',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#666',
                            callback: function(value) {
                                return value.toLocaleString('pt-BR');
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
        
        // Gráfico de status dos clientes (barras empilhadas)
        if (dadosStatusClientes && dadosStatusClientes.length > 0) {
            const ctxStatusClientes = document.getElementById('statusClientesChart').getContext('2d');
            new Chart(ctxStatusClientes, {
                type: 'bar',
                data: {
                    labels: dadosStatusClientes.map(d => new Date(d.data_metrica).toLocaleDateString('pt-BR')),
                    datasets: [
                        {
                            label: 'Clientes Ativos',
                            data: dadosStatusClientes.map(d => parseInt(d.total_clientes_ativos || 0)),
                            backgroundColor: '#28a745',
                            borderColor: '#1e7e34',
                            borderWidth: 1,
                            stack: 'clientes'
                        },
                        {
                            label: 'Clientes Inativando',
                            data: dadosStatusClientes.map(d => parseInt(d.total_clientes_inativando || 0)),
                            backgroundColor: '#ffc107',
                            borderColor: '#e0a800',
                            borderWidth: 1,
                            stack: 'clientes'
                        },
                        {
                            label: 'Clientes Inativos',
                            data: dadosStatusClientes.map(d => parseInt(d.total_clientes_inativos || 0)),
                            backgroundColor: '#dc3545',
                            borderColor: '#bd2130',
                            borderWidth: 1,
                            stack: 'clientes'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#667eea',
                            borderWidth: 1,
                            cornerRadius: 8,
                            callbacks: {
                                footer: function(tooltipItems) {
                                    let total = 0;
                                    tooltipItems.forEach(function(tooltipItem) {
                                        total += tooltipItem.parsed.y;
                                    });
                                    return 'Total: ' + total.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#666'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            stacked: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                color: '#666',
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        } else {
            // Mostrar mensagem quando não há dados para status dos clientes
            document.getElementById('statusClientesChart').parentElement.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-chart-bar fa-2x mb-2"></i><p>Nenhum dado de status de clientes para o período selecionado</p></div>';
        }
        
        } else {
            // Mostrar mensagem quando não há dados
            document.getElementById('evolucaoChart').parentElement.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-chart-line fa-2x mb-2"></i><p>Nenhuma ligação registrada para o período selecionado</p></div>';
        }
        
        // Prevenir redimensionamento infinito dos gráficos
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                // Não redimensionar os gráficos automaticamente
                // Eles já são responsivos
            }, 250);
        });
        
        // Auto-refresh a cada 5 minutos
        setInterval(function() {
            location.reload();
        }, 300000);
        
        // Botão de coleta manual de métricas
        document.getElementById('btnColetarMetricas').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            
            // Desabilitar botão e mostrar loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Coletando...</span>';
            
            // Fazer requisição AJAX
            fetch('includes/coletar_metricas_manual.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Mostrar sucesso
                    btn.innerHTML = '<i class="fas fa-check"></i> <span>Sucesso!</span>';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-success');
                    
                    // Mostrar toast de sucesso
                    showToast('Métricas coletadas com sucesso!', 'success');
                    
                    // Recarregar página após 2 segundos
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    // Mostrar erro
                    btn.innerHTML = '<i class="fas fa-times"></i> <span>Erro!</span>';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-danger');
                    
                    // Mostrar toast de erro
                    showToast(data.message || 'Erro ao coletar métricas', 'error');
                    
                    // Restaurar botão após 3 segundos
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-primary');
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                
                // Mostrar erro
                btn.innerHTML = '<i class="fas fa-times"></i> <span>Erro!</span>';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-danger');
                
                // Mostrar toast de erro
                showToast('Erro de conexão', 'error');
                
                // Restaurar botão após 3 segundos
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-primary');
                }, 3000);
            });
        });
        
        // Função para mostrar toast
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remover toast após ser escondido
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
        
        // Função para criar container de toast
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }
        
        // Controle dos botões de detalhes dos vendedores
        const vendedorButtons = document.querySelectorAll('[data-bs-target^="#"]');
        
        vendedorButtons.forEach(button => {
            if (button.getAttribute('data-bs-target').includes('Details')) {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-bs-target');
                    const targetElement = document.querySelector(targetId);
                    const icon = this.querySelector('i');
                    
                    setTimeout(() => {
                        const isExpanded = targetElement.classList.contains('show');
                        if (isExpanded) {
                            icon.className = 'fas fa-chevron-up';
                            this.innerHTML = '<i class="fas fa-chevron-up"></i> Ocultar';
                        } else {
                            icon.className = 'fas fa-chevron-down';
                            this.innerHTML = '<i class="fas fa-chevron-down"></i> Vendedores';
                        }
                    }, 100);
                });
            }
        });
        
        // Controle dos indicadores mobile para gráficos
        function toggleMobileChartIndicators() {
            const isMobile = window.innerWidth <= 768;
            const indicators = document.querySelectorAll('.mobile-chart-indicator');
            
            indicators.forEach(indicator => {
                if (isMobile) {
                    indicator.classList.remove('d-none');
                    indicator.classList.add('d-block');
                } else {
                    indicator.classList.remove('d-block');
                    indicator.classList.add('d-none');
                }
            });
        }
        
        // Executar na carga inicial
        toggleMobileChartIndicators();
        
        // Executar no redimensionamento
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(toggleMobileChartIndicators, 250);
        });
        
        // Funcionalidade da Seção de Transições
        const btnBuscarTransicoes = document.getElementById('btnBuscarTransicoes');
        const btnLimparFiltros = document.getElementById('btnLimparFiltros');
        
        // Botão buscar transições
        btnBuscarTransicoes.addEventListener('click', buscarTransicoes);
        
        // Botão limpar filtros
        btnLimparFiltros.addEventListener('click', function() {
            document.getElementById('filtroTipoTransicao').value = 'todas';
            document.getElementById('filtroDataInicio').value = '<?php echo date('Y-m-d', strtotime('-7 days')); ?>';
            document.getElementById('filtroDataFim').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('filtroLimite').value = '50';
        });
        
        // Carregar transições automaticamente ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            buscarTransicoes();
        });
        
        function buscarTransicoes() {
            const tipo = document.getElementById('filtroTipoTransicao').value;
            const dataInicio = document.getElementById('filtroDataInicio').value;
            const dataFim = document.getElementById('filtroDataFim').value;
            const limite = document.getElementById('filtroLimite').value;
            
            // Mostrar loading
            document.getElementById('loadingTransicoes').style.display = 'block';
            document.getElementById('tabelaTransicoes').style.display = 'none';
            document.getElementById('estatisticasTransicoes').style.display = 'none';
            document.getElementById('semTransicoes').style.display = 'none';
            
            // Fazer requisição AJAX
            fetch(`includes/buscar_transicoes_status.php?tipo=${tipo}&data_inicio=${dataInicio}&data_fim=${dataFim}&limite=${limite}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingTransicoes').style.display = 'none';
                    
                    if (data.success && data.data.transicoes.length > 0) {
                        preencherTabelaTransicoes(data.data.transicoes);
                        preencherEstatisticas(data.data.estatisticas);
                        document.getElementById('contadorTransicoes').textContent = `${data.data.total_encontrado} transições encontradas`;
                        document.getElementById('tabelaTransicoes').style.display = 'block';
                        document.getElementById('estatisticasTransicoes').style.display = 'block';
                    } else {
                        document.getElementById('semTransicoes').style.display = 'block';
                        document.getElementById('contadorTransicoes').textContent = '0 transições encontradas';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    document.getElementById('loadingTransicoes').style.display = 'none';
                    document.getElementById('semTransicoes').style.display = 'block';
                    showToast('Erro ao buscar transições', 'error');
                });
        }
        
        function preencherTabelaTransicoes(transicoes) {
            const tbody = document.getElementById('corpoTabelaTransicoes');
            tbody.innerHTML = '';
            
            transicoes.forEach(transicao => {
                const row = document.createElement('tr');
                
                // Formatar data
                const dataFormatada = new Date(transicao.data_transicao).toLocaleDateString('pt-BR');
                
                // Formatar transição com cores
                const transicaoFormatada = formatarTransicao(transicao.status_anterior, transicao.status_novo);
                
                // Formatar data da última compra
                const dataCompraFormatada = transicao.data_ultima_compra ? 
                    new Date(transicao.data_ultima_compra).toLocaleDateString('pt-BR') : 'N/A';
                
                // Formatar valor
                const valorFormatado = transicao.valor_ultima_compra ? 
                    'R$ ' + parseFloat(transicao.valor_ultima_compra).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : 'N/A';
                
                // Formatar nome do cliente (truncar se muito longo)
                const nomeCliente = transicao.RAZAO_SOCIAL || 'N/A';
                const nomeTruncado = nomeCliente.length > 30 ? nomeCliente.substring(0, 30) + '...' : nomeCliente;
                
                // Formatar CNPJ
                const cnpjFormatado = formatarCNPJ(transicao.cnpj_original || transicao.cnpj_cliente);
                
                row.innerHTML = `
                    <td><small>${dataFormatada}</small></td>
                    <td title="${nomeCliente}"><small>${nomeTruncado}</small></td>
                    <td><small>${cnpjFormatado}</small></td>
                    <td>${transicaoFormatada}</td>
                    <td><small>${dataCompraFormatada}</small></td>
                    <td class="text-center"><small>${transicao.dias_sem_comprar || 'N/A'}</small></td>
                    <td class="text-end"><small>${valorFormatado}</small></td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        function formatarCNPJ(cnpj) {
            if (!cnpj) return 'N/A';
            
            // Remover caracteres não numéricos
            const numeros = cnpj.replace(/\D/g, '');
            
            // Se tem 8 dígitos (CNPJ base), formatar como XX.XXX.XXX
            if (numeros.length === 8) {
                return numeros.replace(/(\d{2})(\d{3})(\d{3})/, '$1.$2.$3');
            }
            
            // Se tem 14 dígitos (CNPJ completo), formatar como XX.XXX.XXX/XXXX-XX
            if (numeros.length === 14) {
                return numeros.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            }
            
            // Se tem 11 dígitos (CPF), formatar como XXX.XXX.XXX-XX
            if (numeros.length === 11) {
                return numeros.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }
            
            return cnpj; // Retornar original se não conseguir formatar
        }
        
        function preencherEstatisticas(estatisticas) {
            const container = document.getElementById('cardsEstatisticas');
            container.innerHTML = '';
            
            estatisticas.forEach(stat => {
                const card = document.createElement('div');
                card.className = 'col-lg-3 col-md-6 col-sm-6 mb-2';
                
                const cor = getCorTransicao(stat.transicao);
                
                card.innerHTML = `
                    <div class="card text-center h-100">
                        <div class="card-body p-2">
                            <h6 class="card-title text-${cor} mb-1" style="font-size: 0.8rem;">${stat.transicao}</h6>
                            <h4 class="text-${cor} mb-1">${stat.quantidade}</h4>
                            <small class="text-muted">clientes</small>
                        </div>
                    </div>
                `;
                
                container.appendChild(card);
            });
        }
        
        function formatarTransicao(statusAnterior, statusNovo) {
            const cores = {
                'ativo': 'success',
                'inativando': 'warning', 
                'inativo': 'danger',
                'novo': 'info'
            };
            
            const corAnterior = cores[statusAnterior] || 'secondary';
            const corNovo = cores[statusNovo] || 'secondary';
            
            return `
                <span class="badge bg-${corAnterior}">${statusAnterior}</span>
                <i class="fas fa-arrow-right mx-1"></i>
                <span class="badge bg-${corNovo}">${statusNovo}</span>
            `;
        }
        
        function getCorTransicao(transicao) {
            if (transicao.includes('ativo -> inativando')) return 'warning';
            if (transicao.includes('inativando -> ativo')) return 'success';
            if (transicao.includes('inativando -> inativo')) return 'danger';
            if (transicao.includes('inativo -> ativo')) return 'success';
            if (transicao.includes('novo -> ativo')) return 'success';
            return 'info';
        }
        
    </script>
</body>
</html>
