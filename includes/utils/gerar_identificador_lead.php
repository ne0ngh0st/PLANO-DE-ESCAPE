<?php
/**
 * Função para gerar identificador padronizado de leads
 * Formato: email-telefone-cep
 */

/**
 * Gera identificador padronizado para observações de leads
 * @param string $email Email da lead
 * @param string $telefone Telefone da lead
 * @param string $cep CEP da lead
 * @return string Identificador no formato email-telefone-cep
 */
function gerarIdentificadorLead($email, $telefone, $cep) {
    // Limpar e normalizar os dados
    $email_limpo = trim($email ?? '');
    $telefone_limpo = preg_replace('/\D/', '', $telefone ?? '');
    $cep_limpo = preg_replace('/\D/', '', $cep ?? '');
    
    // Se não tem email válido, retornar hash baseado nos outros campos
    if (empty($email_limpo) || !filter_var($email_limpo, FILTER_VALIDATE_EMAIL)) {
        // Gerar hash baseado em telefone + CEP para leads sem email
        $dados_hash = $telefone_limpo . '|' . $cep_limpo;
        return hash('sha1', $dados_hash);
    }
    
    // Formato padronizado: email-telefone-cep
    return $email_limpo . '-' . $telefone_limpo . '-' . $cep_limpo;
}

/**
 * Extrai componentes do identificador padronizado
 * @param string $identificador Identificador no formato email-telefone-cep
 * @return array Array com email, telefone e cep
 */
function extrairComponentesIdentificador($identificador) {
    // Se é um hash (40 caracteres hexadecimais), retornar como hash
    if (strlen($identificador) == 40 && preg_match('/^[a-f0-9]{40}$/', $identificador)) {
        return [
            'email' => '',
            'telefone' => '',
            'cep' => '',
            'is_hash' => true,
            'hash' => $identificador
        ];
    }
    
    // Tentar extrair componentes do formato email-telefone-cep
    $partes = explode('-', $identificador);
    
    if (count($partes) >= 3) {
        // Pegar email (pode conter hífens, então juntar tudo exceto os últimos 2 elementos)
        $email = implode('-', array_slice($partes, 0, -2));
        $telefone = $partes[count($partes) - 2] ?? '';
        $cep = $partes[count($partes) - 1] ?? '';
        
        return [
            'email' => $email,
            'telefone' => $telefone,
            'cep' => $cep,
            'is_hash' => false,
            'hash' => ''
        ];
    }
    
    // Se não conseguir extrair, tratar como email simples (compatibilidade)
    return [
        'email' => $identificador,
        'telefone' => '',
        'cep' => '',
        'is_hash' => false,
        'hash' => ''
    ];
}

/**
 * Busca leads que correspondem ao identificador
 * @param PDO $pdo Conexão com o banco
 * @param string $identificador Identificador da lead
 * @return array Array de leads correspondentes
 */
function buscarLeadsPorIdentificador($pdo, $identificador) {
    $componentes = extrairComponentesIdentificador($identificador);
    
    if ($componentes['is_hash']) {
        // Para hashes, buscar leads sem email que correspondem ao telefone/CEP
        // Esta é uma implementação simplificada - pode ser expandida conforme necessário
        return [];
    }
    
    $email = $componentes['email'];
    $telefone = $componentes['telefone'];
    $cep = $componentes['cep'];
    
    $where_conditions = [];
    $params = [];
    
    if (!empty($email)) {
        $where_conditions[] = "Email = ?";
        $params[] = $email;
    }
    
    if (!empty($telefone)) {
        $where_conditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(TelefonePrincipalFINAL, '(', ''), ')', ''), ' ', ''), '-', '') = ?";
        $params[] = $telefone;
    }
    
    if (!empty($cep)) {
        $where_conditions[] = "REPLACE(REPLACE(CEP, '-', ''), ' ', '') = ?";
        $params[] = $cep;
    }
    
    if (empty($where_conditions)) {
        return [];
    }
    
    $sql = "SELECT * FROM BASE_LEADS WHERE " . implode(' AND ', $where_conditions);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>


