<?php
// Teste simples para verificar se o arquivo está acessível
echo "Arquivo de teste acessível!<br>";
echo "Data/Hora: " . date('d/m/Y H:i:s') . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Testar se consegue incluir o config
try {
    $config_path = file_exists('../config.php') ? '../config.php' : '../../config.php';
    echo "Tentando incluir: " . $config_path . "<br>";
    
    if (file_exists($config_path)) {
        echo "Arquivo config.php encontrado!<br>";
    } else {
        echo "Arquivo config.php NÃO encontrado!<br>";
    }
} catch (Exception $e) {
    echo "Erro ao incluir config: " . $e->getMessage() . "<br>";
}

// Testar se consegue acessar a pasta de imagens
$logo_path = file_exists('../assets/img/LOGO AUTOPEL VETOR-01.png') 
    ? '../assets/img/LOGO AUTOPEL VETOR-01.png' 
    : '../../assets/img/LOGO AUTOPEL VETOR-01.png';

if (file_exists($logo_path)) {
    echo "Logo encontrado: " . $logo_path . "<br>";
} else {
    echo "Logo NÃO encontrado!<br>";
}

echo "<br><a href='javascript:history.back()'>Voltar</a>";
?>


