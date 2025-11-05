<?php
// conexao_local.php - Versão local para testes sem die()

// ==========================================
// CONFIGURAÇÕES DO BANCO DE DADOS - LOCAL
// ==========================================

// ENDEREÇO DO MYSQL (HOST)
$host = 'localhost';

// NOME DO BANCO DE DADOS
$db = 'autopel01';

// USUÁRIO DO BANCO DE DADOS
$user = 'root';

// SENHA DO BANCO DE DADOS
$pass = '';

// ==========================================
// CÓDIGO DE CONEXÃO COM RETRY
// ==========================================

function conectarComRetry($host, $db, $user, $pass, $maxTentativas = 3) {
    for ($tentativa = 1; $tentativa <= $maxTentativas; $tentativa++) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Configurar SQL_BIG_SELECTS para permitir consultas grandes
            $pdo->exec("SET SQL_BIG_SELECTS=1");
            
            // Testar a conexão
            $pdo->query("SELECT 1");
            
            return $pdo;
            
        } catch (PDOException $e) {
            // Log do erro para debug
            error_log("Tentativa $tentativa - Erro de conexão MySQL Local: " . $e->getMessage());
            
            // Se for a última tentativa, retornar null em vez de die()
            if ($tentativa == $maxTentativas) {
                return null;
            }
            
            // Aguardar um pouco antes da próxima tentativa
            sleep(1);
        }
    }
}

// Tentar conectar com retry
$pdo = conectarComRetry($host, $db, $user, $pass);

// Função para verificar se a conexão está funcionando
function testarConexao() {
    global $pdo;
    try {
        if ($pdo) {
            $pdo->query("SELECT 1");
            return true;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

// Testa a conexão automaticamente
if (!testarConexao()) {
    // Em vez de die(), apenas definir $pdo como null
    $pdo = null;
}
?>
