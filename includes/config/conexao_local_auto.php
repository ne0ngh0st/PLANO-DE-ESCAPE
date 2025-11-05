<?php
// conexao_local_auto.php - Versão que tenta diferentes configurações automaticamente

// ==========================================
// CONFIGURAÇÕES PARA TESTAR
// ==========================================

$configuracoes = [
    ['host' => 'localhost', 'db' => 'autopel01', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'db' => 'autopel', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'db' => 'autopel01', 'user' => 'root', 'pass' => 'root'],
    ['host' => '127.0.0.1', 'db' => 'autopel01', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'db' => 'autopel', 'user' => 'root', 'pass' => '']
];

// ==========================================
// FUNÇÃO PARA TESTAR CONEXÕES
// ==========================================

function testarConexoes($configuracoes) {
    foreach ($configuracoes as $config) {
        try {
            $pdo = new PDO("mysql:host={$config['host']};dbname={$config['db']};charset=utf8", 
                          $config['user'], $config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Testar a conexão
            $pdo->query("SELECT 1");
            
            // Verificar se a tabela observacoes existe
            $sql_check = "SHOW TABLES LIKE 'observacoes'";
            $stmt_check = $pdo->query($sql_check);
            $tabela_existe = $stmt_check->rowCount() > 0;
            
            if ($tabela_existe) {
                return [
                    'success' => true,
                    'pdo' => $pdo,
                    'config' => $config,
                    'message' => "Conexão bem-sucedida com {$config['host']}/{$config['db']}"
                ];
            } else {
                // Se não tem a tabela observacoes, continuar tentando
                continue;
            }
            
        } catch (PDOException $e) {
            // Log do erro para debug
            error_log("Tentativa falhou - Host: {$config['host']}, DB: {$config['db']}, Erro: " . $e->getMessage());
            continue;
        }
    }
    
    return [
        'success' => false,
        'message' => 'Nenhuma configuração funcionou'
    ];
}

// ==========================================
// TENTAR CONECTAR
// ==========================================

$resultado = testarConexoes($configuracoes);

if ($resultado['success']) {
    $pdo = $resultado['pdo'];
    $config_ativa = $resultado['config'];
} else {
    $pdo = null;
}

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
    $pdo = null;
}
?>
