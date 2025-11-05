<?php
// conexao.php - Configuração para KingHost com melhor tratamento de erros

// ==========================================
// CONFIGURAÇÕES DO BANCO DE DADOS - KINGHOST
// ==========================================

// DETECÇÃO DE AMBIENTE (LOCAL VS PRODUÇÃO)
$isLocal = false;
// FORÇAR PRODUÇÃO - descomente a linha abaixo para testar produção localmente
// $isLocal = false;
if (php_sapi_name() === 'cli' || php_sapi_name() === 'cli-server') {
	$isLocal = true;
} else {
	$hostHeader = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
	$serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
	if (in_array($hostHeader, ['localhost', '127.0.0.1']) || in_array($serverAddr, ['127.0.0.1', '::1'])) {
		$isLocal = true;
	}
}

// CONFIGURAÇÕES DEPENDENDO DO AMBIENTE
if ($isLocal) {
	// LOCALHOST (XAMPP)
    $host = 'mysql06-farm88.kinghost.net';
	$db = 'autopel01';
	$user = 'autopel001_add1';
	$pass = 'hGLkABk9EQpRKB5n';
} else {
	// PRODUÇÃO (KINGHOST)
	$host = 'mysql06-farm88.kinghost.net';
	$db = 'autopel01';
	$user = 'autopel001_add1';
	$pass = 'hGLkABk9EQpRKB5n';
}

// ==========================================
// CÓDIGO DE CONEXÃO COM RETRY
// ==========================================

function conectarComRetry($host, $db, $user, $pass, $maxTentativas = 3) {
    for ($tentativa = 1; $tentativa <= $maxTentativas; $tentativa++) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Configurações para evitar "MySQL server has gone away"
            @$pdo->exec("SET SQL_BIG_SELECTS=1");
            @$pdo->exec("SET SESSION wait_timeout=28800");
            @$pdo->exec("SET SESSION interactive_timeout=28800");
            @$pdo->exec("SET SESSION net_read_timeout=120");
            @$pdo->exec("SET SESSION net_write_timeout=120");
            
            // Testar a conexão
            @$pdo->query("SELECT 1");
            
            return $pdo;
            
        } catch (PDOException $e) {
            // Log do erro para debug
            error_log("Tentativa $tentativa - Erro de conexão MySQL KingHost: " . $e->getMessage());
            
            // Se for a última tentativa, logar erro mas não fazer die() para evitar 500
            if ($tentativa == $maxTentativas) {
                error_log("Falha definitiva na conexão MySQL após $maxTentativas tentativas");
                // Retornar null em vez de die() para permitir que a aplicação continue
                // O código que usa $pdo deve verificar se está definido
                return null;
            }
            
            // Aguardar um pouco antes da próxima tentativa
            if ($tentativa < $maxTentativas) {
                sleep(1);
            }
        }
    }
    return null;
}

// Tentar conectar com retry
$pdo = conectarComRetry($host, $db, $user, $pass);

// Se a conexão falhou, definir $pdo como null para evitar erros
if ($pdo === null) {
    error_log("AVISO: Não foi possível estabelecer conexão com o banco de dados.");
    // Não fazer die() aqui para evitar erro 500 - a aplicação pode continuar
}

// Função para verificar se a conexão está funcionando
function testarConexao() {
    global $pdo;
    try {
        if (!isset($pdo) || $pdo === null) {
            return false;
        }
        $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Função para reconectar automaticamente
function reconectarSeNecessario() {
    global $pdo, $host, $db, $user, $pass;
    
    if (!testarConexao()) {
        error_log("Conexão perdida, tentando reconectar...");
        $pdo = conectarComRetry($host, $db, $user, $pass);
        return $pdo;
    }
    return $pdo;
}

// Testa a conexão automaticamente (com verificação segura)
if (isset($pdo) && $pdo !== null) {
    if (!testarConexao()) {
        error_log("Erro: Conexão com o banco de dados falhou após a inicialização.");
        // Não fazer die() aqui para evitar problemas com headers
    }
}