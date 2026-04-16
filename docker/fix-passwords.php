<?php
/**
 * Script para corrigir os hashes de senha no banco.
 * Roda automaticamente no entrypoint do container PHP.
 * Todas as senhas são definidas como "admin123".
 */

$maxRetries = 30;
$retry = 0;

$host = getenv('DB_HOST') ?: 'db';
$db = getenv('DB_NAME') ?: 'autopel01';
$user = getenv('DB_USER') ?: 'autopel';
$pass = getenv('DB_PASS') ?: 'autopel_vuln_2026';

while ($retry < $maxRetries) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE USUARIOS SET PASSWORD_HASH = ?');
        $stmt->execute([$hash]);

        echo "Senhas atualizadas com sucesso. Hash: $hash\n";
        echo "Usuarios atualizados: " . $stmt->rowCount() . "\n";
        exit(0);
    } catch (PDOException $e) {
        $retry++;
        echo "Tentativa $retry/$maxRetries - Aguardando MySQL... ({$e->getMessage()})\n";
        sleep(2);
    }
}

echo "ERRO: Não foi possível conectar ao MySQL após $maxRetries tentativas.\n";
exit(1);
