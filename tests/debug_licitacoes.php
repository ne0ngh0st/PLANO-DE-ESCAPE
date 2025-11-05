<?php
// Debug da página de licitações (movido para tests)
session_start();

echo "<h1>Debug da Página de Licitações</h1>";

echo "<h2>1. Verificação de Sessão</h2>";
if (isset($_SESSION['usuario'])) {
    echo "✅ Usuário logado: " . $_SESSION['usuario']['nome'] . "<br>";
    echo "✅ Perfil: " . $_SESSION['usuario']['perfil'] . "<br>";
} else {
    echo "❌ Usuário não logado<br>";
}

echo "<h2>2. Teste de Conexão com BD</h2>";
try {
    require_once __DIR__ . '/../includes/conexao.php';
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM LICITACAO");
    $total = $stmt->fetchColumn();
    echo "✅ Conexão OK - Total de registros: " . $total . "<br>";
} catch (Exception $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "<br>";
}

echo "<h2>3. Teste do Arquivo de Gerenciamento</h2>";
try {
    $_POST['acao'] = 'listar_licitacoes';
    $_POST['filtro_orgao'] = '';
    $_POST['filtro_status'] = '';
    $_POST['filtro_tipo'] = '';
    $_POST['busca'] = '';
    ob_start();
    include __DIR__ . '/../includes/gerenciar_licitacoes.php';
    $output = ob_get_clean();
    echo "✅ Arquivo executado<br>";
    echo "Resposta: " . htmlspecialchars($output) . "<br>";
} catch (Exception $e) {
    echo "❌ Erro no arquivo: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Teste JavaScript</h2>";
echo '<button onclick="testarAjax()">Testar AJAX</button>';
echo '<div id="resultado"></div>';

echo '<script>
async function testarAjax() {
    try {
        console.log("Testando AJAX...");
        const response = await fetch("../includes/gerenciar_licitacoes.php", { method: "POST", body: new FormData(document.createElement("form")), credentials: "same-origin" });
        const result = await response.text();
        console.log("Resposta:", result);
        document.getElementById("resultado").innerHTML = "<pre>" + result + "</pre>";
    } catch (error) {
        console.error("Erro:", error);
        document.getElementById("resultado").innerHTML = "Erro: " + error.message;
    }
}
</script>';
?>

