<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit;
}

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Debug - Perfil do Usuário</title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 40px; background: #1e1e1e; color: #d4d4d4; }
        .debug-box { background: #252526; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #007acc; }
        .debug-title { color: #4ec9b0; font-size: 18px; font-weight: bold; margin-bottom: 15px; }
        .debug-item { margin: 10px 0; padding: 10px; background: #1e1e1e; border-radius: 4px; }
        .label { color: #9cdcfe; font-weight: bold; display: inline-block; width: 200px; }
        .value { color: #ce9178; }
        .value.null { color: #569cd6; }
        .value.true { color: #4ec9b0; }
        .value.false { color: #f48771; }
        .alert { background: #5a1d1d; border-left-color: #f48771; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .success { background: #1d5a1d; border-left-color: #4ec9b0; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 4px; overflow-x: auto; color: #d4d4d4; }
        .test-section { margin: 30px 0; padding: 20px; background: #252526; border-radius: 8px; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #007acc; padding-bottom: 10px; }
    </style>
</head>
<body>
    <h1 style="color: #4ec9b0;">🔍 Debug - Perfil do Usuário Atual</h1>
    <div class="debug-box">
        <div class="debug-title">📋 Dados da Sessão</div>
        <div class="debug-item"><span class="label">Nome:</span><span class="value"><?php echo htmlspecialchars($usuario['nome'] ?? 'N/A'); ?></span></div>
        <div class="debug-item"><span class="label">Email:</span><span class="value"><?php echo htmlspecialchars($usuario['email'] ?? 'N/A'); ?></span></div>
        <div class="debug-item"><span class="label">Perfil (original):</span><span class="value">"<?php echo htmlspecialchars($usuario['perfil'] ?? 'N/A'); ?>"</span></div>
        <div class="debug-item"><span class="label">Perfil (lowercase):</span><span class="value">"<?php echo htmlspecialchars(strtolower($usuario['perfil'] ?? '')); ?>"</span></div>
        <div class="debug-item"><span class="label">Perfil (trim + lowercase):</span><span class="value">"<?php echo htmlspecialchars(strtolower(trim($usuario['perfil'] ?? ''))); ?>"</span></div>
        <div class="debug-item"><span class="label">Código Vendedor:</span><span class="value"><?php echo htmlspecialchars($usuario['COD_VENDEDOR'] ?? 'N/A'); ?></span></div>
        <div class="debug-item"><span class="label">Código Supervisor:</span><span class="value"><?php echo htmlspecialchars($usuario['COD_SUPER'] ?? 'N/A'); ?></span></div>
    </div>
    <div class="test-section">
        <h2>🧪 Testes de Verificação de Perfil</h2>
        <?php
        $perfil_lower = strtolower(trim($usuario['perfil'] ?? ''));
        $testes = [
            'É Assistente?' => ($perfil_lower === 'assistente'),
            'É Vendedor?' => ($perfil_lower === 'vendedor'),
            'É Representante?' => ($perfil_lower === 'representante'),
            'É Supervisor?' => ($perfil_lower === 'supervisor'),
            'É Diretor?' => ($perfil_lower === 'diretor'),
            'É Admin?' => ($perfil_lower === 'admin'),
        ];
        foreach ($testes as $teste => $resultado) {
            $class = $resultado ? 'true' : 'false';
            $icon = $resultado ? '✅' : '❌';
            $text = $resultado ? 'SIM' : 'NÃO';
            echo "<div class='debug-item'>";
            echo "<span class='label'>$teste</span>";
            echo "<span class='value $class'>$icon $text</span>";
            echo "</div>";
        }
        ?>
    </div>
    <div class="test-section">
        <h2>🔓 Testes de Permissões</h2>
        <?php
        $perfil = strtolower(trim($usuario['perfil'] ?? ''));
        $mostra_stats = in_array($perfil, ['vendedor', 'representante']) && $perfil !== 'assistente';
        echo "<div class='debug-item'><span class='label'>Estatísticas de Ligação:</span><span class='value " . ($mostra_stats ? 'true' : 'false') . "'>" . ($mostra_stats ? '✅ MOSTRA' : '❌ NÃO MOSTRA') . "</span></div>";
        $acesso_dash = !in_array($perfil, ['representante', 'vendedor', 'licitação', 'assistente']);
        echo "<div class='debug-item'><span class='label'>Acesso a Dashboards:</span><span class='value " . ($acesso_dash ? 'true' : 'false') . "'>" . ($acesso_dash ? '✅ SIM' : '❌ NÃO') . "</span></div>";
        $acesso_leads = !in_array($perfil, ['licitação', 'assistente']);
        echo "<div class='debug-item'><span class='label'>Acesso a Leads:</span><span class='value " . ($acesso_leads ? 'true' : 'false') . "'>" . ($acesso_leads ? '✅ SIM' : '❌ NÃO') . "</span></div>";
        $acesso_carteira = ($perfil !== 'licitação');
        echo "<div class='debug-item'><span class='label'>Acesso a Carteira:</span><span class='value " . ($acesso_carteira ? 'true' : 'false') . "'>" . ($acesso_carteira ? '✅ SIM' : '❌ NÃO') . "</span></div>";
        $acesso_pedidos = in_array($perfil, ['vendedor', 'representante', 'supervisor', 'diretor', 'admin', 'assistente']);
        echo "<div class='debug-item'><span class='label'>Acesso a Pedidos:</span><span class='value " . ($acesso_pedidos ? 'true' : 'false') . "'>" . ($acesso_pedidos ? '✅ SIM' : '❌ NÃO') . "</span></div>";
        $acesso_orcamentos = in_array($perfil, ['vendedor', 'representante', 'supervisor', 'diretor', 'admin', 'assistente']);
        echo "<div class='debug-item'><span class='label'>Acesso a Orçamentos:</span><span class='value " . ($acesso_orcamentos ? 'true' : 'false') . "'>" . ($acesso_orcamentos ? '✅ SIM' : '❌ NÃO') . "</span></div>";
        $acesso_cadastro = !in_array($perfil, ['licitação', 'assistente']);
        echo "<div class='debug-item'><span class='label'>Acesso a Cadastro:</span><span class='value " . ($acesso_cadastro ? 'true' : 'false') . "'>" . ($acesso_cadastro ? '✅ SIM' : '❌ NÃO') . "</span></div>";
        $recebe_notif = in_array($perfil, ['admin', 'vendedor', 'supervisor', 'diretor', 'representante', 'assistente']);
        echo "<div class='debug-item'><span class='label'>Recebe Notificações:</span><span class='value " . ($recebe_notif ? 'true' : 'false') . "'>" . ($recebe_notif ? '✅ SIM' : '❌ NÃO') . "</span></div>";
        ?>
    </div>
    <div class="test-section">
        <h2>💾 Dados Diretos do Banco de Dados</h2>
        <?php
        try {
            $stmt = $pdo->prepare("SELECT ID, NOME_COMPLETO, EMAIL, PERFIL, COD_VENDEDOR, COD_SUPER, ATIVO FROM USUARIOS WHERE ID = ?");
            $stmt->execute([$usuario['id']]);
            $user_db = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_db) {
                echo "<div class='debug-box success'>";
                echo "<div class='debug-title'>✅ Dados encontrados no banco</div>";
                echo "<pre>" . print_r($user_db, true) . "</pre>";
                echo "</div>";
                $perfil_sessao = trim($usuario['perfil'] ?? '');
                $perfil_banco = trim($user_db['PERFIL'] ?? '');
                if ($perfil_sessao !== $perfil_banco) {
                    echo "<div class='alert'>";
                    echo "⚠️ <strong>ATENÇÃO:</strong> Há diferença entre o perfil na sessão e no banco!<br>";
                    echo "Sessão: \"$perfil_sessao\"<br>Banco: \"$perfil_banco\"<br><br><strong>Solução:</strong> Faça logout e login novamente para sincronizar.";
                    echo "</div>";
                }
            } else {
                echo "<div class='alert'>❌ Usuário não encontrado no banco de dados!</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='alert'>❌ Erro ao buscar dados: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>
    <div class="test-section">
        <h2>📝 Dump Completo da Sessão</h2>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>
    <div style="text-align: center; margin: 30px 0;">
        <a href="../home.php" style="color: #4ec9b0; text-decoration: none; font-size: 18px;">← Voltar para Home</a>
    </div>
</body>
</html>

