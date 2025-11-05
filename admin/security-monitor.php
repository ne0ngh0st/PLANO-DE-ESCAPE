<?php
require_once '../includes/config.php';
require_once '../includes/security/TokenSecurity.php';
session_start();

// Verificar se é admin
if (!isset($_SESSION['usuario']) || !in_array(strtolower($_SESSION['usuario']['perfil']), ['admin', 'diretor'])) {
    header('Location: ../index.php');
    exit;
}

// Bloquear acesso de usuários com perfil ecommerce
require_once '../includes/auth/block_ecommerce.php';

$tokenSecurity = new TokenSecurity($pdo);
$stats = $tokenSecurity->getSecurityStats();

// Buscar logs de segurança recentes
$logs = [];
try {
    $sql = "SELECT * FROM security_logs ORDER BY created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Tabela de logs pode não existir ainda
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de Segurança - Autopel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/estilo.css">
    <style>
        .security-dashboard {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .security-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #3b82f6;
        }
        
        .stat-card.danger {
            border-left-color: #dc2626;
        }
        
        .stat-card.warning {
            border-left-color: #d97706;
        }
        
        .stat-card.success {
            border-left-color: #059669;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #1e293b;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .logs-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .log-entry {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            font-family: monospace;
            font-size: 0.875rem;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-event {
            font-weight: bold;
            color: #3b82f6;
        }
        
        .log-danger {
            color: #dc2626;
        }
        
        .log-warning {
            color: #d97706;
        }
        
        .log-success {
            color: #059669;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
    </style>
</head>
<body>
    <div class="security-dashboard">
        <h1><i class="fas fa-shield-alt"></i> Monitor de Segurança</h1>
        
        <div class="actions">
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-sync"></i> Atualizar
            </button>
            <button class="btn btn-danger" onclick="cleanupTokens()">
                <i class="fas fa-trash"></i> Limpar Tokens Expirados
            </button>
        </div>
        
        <div class="security-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['tokens_ativos'] ?? 0; ?></div>
                <div class="stat-label">Tokens Ativos</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $stats['tokens_expirados'] ?? 0; ?></div>
                <div class="stat-label">Tokens Expirados</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-number"><?php echo $stats['tokens_bloqueados'] ?? 0; ?></div>
                <div class="stat-label">Tokens Bloqueados</div>
            </div>
        </div>
        
        <div class="logs-section">
            <h2><i class="fas fa-list"></i> Logs de Segurança Recentes</h2>
            
            <?php if (empty($logs)): ?>
                <p>Nenhum log de segurança encontrado.</p>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <div class="log-entry">
                        <span class="log-event log-<?php echo getLogType($log['event']); ?>">
                            <?php echo htmlspecialchars($log['event']); ?>
                        </span>
                        - Orçamento: <?php echo $log['orcamento_id'] ?? 'N/A'; ?>
                        - IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                        - <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                        <?php if ($log['details']): ?>
                            <br><small><?php echo htmlspecialchars($log['details']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function cleanupTokens() {
            if (confirm('Tem certeza que deseja limpar todos os tokens expirados?')) {
                fetch('../includes/cron/cleanup_tokens.php')
                .then(response => response.text())
                .then(data => {
                    alert('Limpeza concluída!');
                    location.reload();
                })
                .catch(error => {
                    alert('Erro na limpeza: ' + error);
                });
            }
        }
    </script>
</body>
</html>

<?php
function getLogType($event) {
    $dangerEvents = ['token_max_attempts', 'token_already_used', 'token_not_found'];
    $warningEvents = ['token_expired', 'orcamento_expired', 'orcamento_cancelled'];
    $successEvents = ['token_created', 'token_used'];
    
    if (in_array($event, $dangerEvents)) return 'danger';
    if (in_array($event, $warningEvents)) return 'warning';
    if (in_array($event, $successEvents)) return 'success';
    return '';
}
?>




