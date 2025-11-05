<?php
/**
 * Callback OAuth do Bling
 * Este arquivo recebe o código de autorização após o usuário autorizar no Bling
 */

session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/BlingAPI.php';

// Verificar se é admin ou diretor
if (!isset($_SESSION['usuario']) || !in_array(strtolower($_SESSION['usuario']['perfil']), ['admin', 'diretor'])) {
    die('Acesso negado. Apenas administradores podem configurar a integração.');
}

try {
    // Verificar se recebeu o código
    if (!isset($_GET['code'])) {
        throw new Exception('Código de autorização não recebido');
    }
    
    // Verificar state para prevenir CSRF
    if (!isset($_GET['state']) || $_GET['state'] !== BLING_OAUTH_STATE) {
        throw new Exception('Estado de segurança inválido');
    }
    
    $code = $_GET['code'];
    
    // Instanciar API e trocar código por tokens
    $bling = new BlingAPI();
    $sucesso = $bling->getAccessToken($code);
    
    if ($sucesso) {
        $_SESSION['mensagem_sucesso'] = 'Integração com Bling configurada com sucesso!';
        header('Location: /Site/gestao-ecommerce.php');
    } else {
        throw new Exception('Falha ao obter token de acesso');
    }
    
} catch (Exception $e) {
    $_SESSION['mensagem_erro'] = 'Erro na autorização: ' . $e->getMessage();
    header('Location: /Site/includes/api/bling_autorizar.php');
}
exit;


