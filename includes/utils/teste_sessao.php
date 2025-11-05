<?php
session_start();

echo "<h2>Teste de Sessão</h2>";
echo "<p>Sessão atual:</p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p>Cookies:</p>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<p>URL atual: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

if (isset($_SESSION['usuario'])) {
    echo "<p style='color: green;'>✅ Usuário logado: " . $_SESSION['usuario']['nome'] . "</p>";
    echo "<p><a href='../carteira.php'>Ir para Carteira</a></p>";
} else {
    echo "<p style='color: red;'>❌ Nenhum usuário logado</p>";
    echo "<p><a href='../index.php'>Ir para Login</a></p>";
}
?> 