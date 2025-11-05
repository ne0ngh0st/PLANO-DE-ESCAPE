<?php
require_once __DIR__ . '/../config/conexao.php';

// Configurações do Azure AD
$client_id = 'seu_client_id_aqui';
$client_secret = 'seu_client_secret_aqui';
$redirect_uri = 'hhttps://gestao-comercial.autopel.com//includes/callback.php';
$tenant_id = 'seu_tenant_id_aqui';

// Verificar se há código de autorização
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Trocar código por token de acesso
    $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
    $post_data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($response, true);
    
    if (isset($token_data['access_token'])) {
        // Obter informações do usuário
        $graph_url = 'https://graph.microsoft.com/v1.0/me';
        $headers = [
            'Authorization: Bearer ' . $token_data['access_token'],
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $graph_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $user_response = curl_exec($ch);
        curl_close($ch);
        
        $user_data = json_decode($user_response, true);
        
        if (isset($user_data['mail'])) {
            $email = $user_data['mail'];
            $nome = $user_data['displayName'] ?? $user_data['givenName'] . ' ' . $user_data['surname'];
            
            // Verificar se o usuário já existe
            $stmt = $pdo->prepare("SELECT * FROM USUARIOS WHERE EMAIL = ?");
            $stmt->execute([$email]);
            $usuario_existente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario_existente) {
                // Criar novo usuário
                $stmt = $pdo->prepare("INSERT INTO USUARIOS (EMAIL, NOME_COMPLETO, NOME_EXIBICAO, PERFIL, TIPO_USUARIO, ATIVO, DATA_CRIACAO) VALUES (?, ?, ?, 'VENDEDOR', 'INTERNO', 1, NOW())");
                $stmt->execute([$email, $nome, $nome]);
                
                // Buscar o ID do usuário recém-criado
                $stmt = $pdo->prepare("SELECT * FROM USUARIOS WHERE EMAIL = ?");
                $stmt->execute([$email]);
                $usuario_existente = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Atualizar último login
            $stmt_update_login = $pdo->prepare('UPDATE USUARIOS SET ULTIMO_LOGIN = NOW() WHERE ID = ?');
            $stmt_update_login->execute([$usuario_existente['ID']]);
            
            // Criar sessão
            session_start();
            $_SESSION['usuario'] = [
                'id' => $usuario_existente['ID'],
                'email' => $usuario_existente['EMAIL'],
                'nome' => $usuario_existente['NOME_EXIBICAO'] ?: $usuario_existente['NOME_COMPLETO'],
                'perfil' => $usuario_existente['PERFIL'],
                'cod_vendedor' => $usuario_existente['CODIGO_VENDEDOR']
            ];
            
            // Redirecionar para o dashboard
            header('Location: ../home.php');
            exit;
        }
    }
}

// Se chegou aqui, houve algum erro
header('Location: ../index.php?erro=azure_login_failed');
exit;
?>
