<?php
/**
 * Script de Debug para Verificar Forma de Pagamento
 * Exibe o valor real armazenado no banco de dados
 */

require_once __DIR__ . '/../includes/config.php';

// Verificar se há um ID específico para testar
$orcamento_id = $_GET['id'] ?? null;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Forma de Pagamento</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 { color: #1e40af; border-bottom: 3px solid #1e40af; padding-bottom: 10px; }
        .info-box { background: #e0f2fe; border-left: 4px solid #0284c7; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .warning-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .error-box { background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .success-box { background: #d1fae5; border-left: 4px solid #059669; padding: 15px; margin: 20px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0; }
        th { background: #1e40af; color: white; padding: 12px; text-align: left; font-weight: 600; }
        td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        tr:hover { background: #f9fafb; }
        .code { background: #1f2937; color: #10b981; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; margin: 15px 0; overflow-x: auto; }
        .highlight { background: #fef08a; padding: 2px 6px; border-radius: 3px; font-weight: 600; }
        .btn { background: #1e40af; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #1e3a8a; }
        .form-group { margin: 20px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; width: 200px; }
    </style>
</head>
<body>
    <h1>🔍 Debug - Forma de Pagamento dos Orçamentos</h1>
    <div class="info-box"><strong>📋 Objetivo:</strong> Este script mostra o valor real armazenado no banco de dados para o campo <code>forma_pagamento</code> dos orçamentos.</div>
    <form method="GET">
        <div class="form-group">
            <label for="id">🔎 Buscar orçamento específico (opcional):</label>
            <input type="number" name="id" id="id" placeholder="ID do orçamento" value="<?php echo htmlspecialchars($orcamento_id ?? ''); ?>">
            <button type="submit" class="btn">Buscar</button>
            <a href="debug_forma_pagamento.php" class="btn" style="background: #6b7280;">Ver Todos</a>
        </div>
    </form>
    <?php
    try {
        if ($orcamento_id) {
            $sql = "SELECT id, cliente_nome, forma_pagamento, valor_total, status, data_criacao, token_aprovacao FROM ORCAMENTOS WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $orcamento_id]);
            $orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($orcamentos)) {
                echo '<div class="error-box">❌ <strong>Orçamento não encontrado!</strong> O ID ' . htmlspecialchars($orcamento_id) . ' não existe no banco de dados.</div>';
            }
        } else {
            $sql = "SELECT id, cliente_nome, forma_pagamento, valor_total, status, data_criacao, token_aprovacao FROM ORCAMENTOS ORDER BY id DESC LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo '<div class="info-box">📊 Mostrando os últimos 20 orçamentos (mais recentes primeiro)</div>';
        }
        if (!empty($orcamentos)) {
            echo '<table><thead><tr><th>ID</th><th>Cliente</th><th>Forma de Pagamento (Banco)</th><th>Forma de Pagamento (Exibição)</th><th>Valor</th><th>Status</th><th>Data</th><th>Ações</th></tr></thead><tbody>';
            foreach ($orcamentos as $orc) {
                $forma_pag_banco = $orc['forma_pagamento'] ?? 'NULL';
                $forma_pag_display = ($forma_pag_banco === 'a_vista') ? 'À Vista' : (($forma_pag_banco === '28_ddl') ? '28 DDL' : htmlspecialchars($forma_pag_banco));
                echo '<tr>';
                echo '<td><strong>#' . $orc['id'] . '</strong></td>';
                echo '<td>' . htmlspecialchars($orc['cliente_nome']) . '</td>';
                echo '<td><span class="highlight">' . htmlspecialchars($forma_pag_banco) . '</span></td>';
                echo '<td>' . $forma_pag_display . '</td>';
                echo '<td>R$ ' . number_format($orc['valor_total'], 2, ',', '.') . '</td>';
                echo '<td>' . htmlspecialchars($orc['status']) . '</td>';
                echo '<td>' . date('d/m/Y', strtotime($orc['data_criacao'])) . '</td>';
                echo '<td>';
                if ($orc['token_aprovacao']) {
                    $link_aprovacao = 'aprovar-orcamento.php?token=' . $orc['token_aprovacao'];
                    echo '<a href="' . $link_aprovacao . '" class="btn" target="_blank" style="font-size: 12px; padding: 5px 10px;">Ver Página</a>';
                } else {
                    echo '<span style="color: #999;">Sem token</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '<h2>📊 Estrutura da Coluna no Banco</h2>';
            $stmt_desc = $pdo->query("DESCRIBE ORCAMENTOS forma_pagamento");
            $coluna_info = $stmt_desc->fetch(PDO::FETCH_ASSOC);
            echo '<div class="code">';
            echo '<strong>Campo:</strong> forma_pagamento<br>';
            echo '<strong>Tipo:</strong> ' . htmlspecialchars($coluna_info['Type']) . '<br>';
            echo '<strong>Permite NULL:</strong> ' . htmlspecialchars($coluna_info['Null']) . '<br>';
            echo '<strong>Padrão:</strong> ' . htmlspecialchars($coluna_info['Default'] ?? 'NULL') . '<br>';
            echo '</div>';
            if (strpos($coluna_info['Type'], 'enum') !== false) {
                echo '<div class="warning-box"><strong>⚠️ ATENÇÃO:</strong> A coluna ainda está como ENUM, limitando os valores possíveis!<br>Execute o script <code>atualizar_forma_pagamento_orcamentos.php</code> para converter para VARCHAR e aceitar qualquer valor.</div>';
            } else {
                echo '<div class="success-box"><strong>✅ OK:</strong> A coluna está como VARCHAR, pode aceitar qualquer valor!</div>';
            }
        }
    } catch (PDOException $e) {
        echo '<div class="error-box">';
        echo '<strong>❌ Erro ao buscar dados:</strong><br>';
        echo htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    ?>
    <h2>🔧 Solução de Problemas</h2>
    <div class="info-box">
        <h3>Se a forma de pagamento não está sendo exibida corretamente:</h3>
        <ol>
            <li><strong>Cache do Navegador:</strong> Pressione Ctrl+F5 na página de aprovação para limpar o cache</li>
            <li><strong>Coluna ENUM:</strong> Se a coluna ainda é ENUM, execute o script <code>atualizar_forma_pagamento_orcamentos.php</code></li>
            <li><strong>Valor no Banco:</strong> Verifique nesta página se o valor está correto no banco</li>
            <li><strong>Token Expirado:</strong> Gere um novo link de aprovação</li>
        </ol>
    </div>
    <hr style="margin: 40px 0;">
    <p style="text-align: center; color: #6b7280;">
        <a href="../pages/orcamentos.php" class="btn">📊 Ir para Orçamentos</a>
        <a href="../index.php" class="btn" style="background: #6b7280;">🏠 Voltar ao Início</a>
    </p>
</body>
</html>

