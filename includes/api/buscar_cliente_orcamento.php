<?php
// PREVENIR QUALQUER OUTPUT ANTES DO JSON
ob_start();

// Configurar headers para JSON ANTES DE QUALQUER COISA
header('Content-Type: application/json; charset=utf-8');

// Desabilitar exibição de erros para não quebrar o JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // IMPORTANTE: Usar config.php que já tem toda configuração de sessão correta
    // Isso garante que a sessão seja configurada da mesma forma que o resto do sistema
    require_once __DIR__ . '/../config/config.php';
    
    // LIMPAR BUFFER ANTES DE VERIFICAR SESSÃO
    ob_clean();
    
    // Verificar se o usuário está logado
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
        // SEMPRE retornar JSON, nunca HTML
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Sessão expirada. Por favor, faça login novamente.', 
            'session_expired' => true
        ]);
        exit;
    }
    
    // Limpar buffer antes de continuar
    ob_clean();
    
    // Verificar se a conexão foi estabelecida (config.php já inclui conexao.php)
    if (!isset($pdo) || !$pdo) {
        throw new Exception('Erro ao conectar ao banco de dados');
    }
    
    // Garantir que o header JSON ainda está ativo
    header('Content-Type: application/json; charset=utf-8', true);
    
} catch (Exception $e) {
    // Limpar qualquer output antes de retornar erro
    ob_clean();
    error_log("Erro na configuração: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8', true);
    echo json_encode(['success' => false, 'message' => 'Erro de configuração: ' . $e->getMessage()]);
    exit;
}

// Limpar buffer final antes de processar
ob_end_clean();

// Usar a mesma conexão para leads (mesmo banco)
$pdo_leads = $pdo;

$termo_busca = $_GET['termo'] ?? '';

if (empty($termo_busca)) {
    echo json_encode(['success' => false, 'message' => 'Termo de busca não fornecido']);
    exit;
}

try {
    // Verificar se é CNPJ (número) ou nome
    $termo_limpo = preg_replace('/[^0-9]/', '', $termo_busca);
    $is_cnpj = strlen($termo_limpo) >= 8;
    
    if ($is_cnpj) {
        // Buscar por raiz do CNPJ na tabela CLIENTES e BASE_LEADS
        $raiz_cnpj = substr($termo_limpo, 0, 8);
        
        // Buscar na tabela CLIENTES
        $sql_clientes = "SELECT 
                    'cliente' as origem,
                    COD_CLIENT,
                    CNPJ,
                    CLIENTE as cliente_nome,
                    NOME_FANTASIA as nome_fantasia,
                    EMailNFe as email,
                    CONCAT('(', DDD, ') ', Telefone) as telefone,
                    Endereco as endereco,
                    Estado as estado,
                    CEP as cep
                FROM CLIENTES
                WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = :raiz_cnpj";
        
        $stmt_clientes = $pdo->prepare($sql_clientes);
        $stmt_clientes->execute(['raiz_cnpj' => $raiz_cnpj]);
        $resultados_clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar na tabela BASE_LEADS (se conexão disponível)
        $resultados_leads = [];
        if (isset($pdo_leads)) {
            $sql_leads = "SELECT 
                        'lead' as origem,
                        cnpj as CNPJ,
                        COALESCE(NULLIF(RAZAOSOCIAL, ''), NULLIF(NOMEFANTASIA, ''), NULLIF(nomefinal, ''), 'N/A') as cliente_nome,
                        NOMEFANTASIA as nome_fantasia,
                        NULLIF(Email, '') as email,
                        TelefonePrincipalFINAL as telefone,
                        endereoCNPJJA as endereco,
                        UF as estado,
                        CEP as cep
                    FROM BASE_LEADS
                    WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) = :raiz_cnpj
                    LIMIT 10";
            
            try {
                $stmt_leads = $pdo_leads->prepare($sql_leads);
                $stmt_leads->execute(['raiz_cnpj' => $raiz_cnpj]);
                $resultados_leads = $stmt_leads->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Erro ao buscar na BASE_LEADS: " . $e->getMessage());
            }
        }
        
        // Combinar resultados
        $resultados = array_merge($resultados_clientes, $resultados_leads);
        
        if (!empty($resultados)) {
            // Sempre retornar como array para manter consistência
            $resultados_formatados = array_map(function($r) {
                return [
                    'cliente_nome' => $r['cliente_nome'] ?? '',
                    'nome_fantasia' => $r['nome_fantasia'] ?? '',
                    'cnpj' => $r['CNPJ'] ?? '',
                    'email' => $r['email'] ?? '',
                    'telefone' => $r['telefone'] ?? '',
                    'endereco' => $r['endereco'] ?? '',
                    'estado' => $r['estado'] ?? '',
                    'cep' => $r['cep'] ?? '',
                    'origem' => $r['origem'] ?? ''
                ];
            }, $resultados);
            
            echo json_encode([
                'success' => true,
                'data' => $resultados_formatados,
                'multiple' => count($resultados) > 1
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cliente/Lead não encontrado']);
        }
    } else {
        // Buscar por nome na tabela CLIENTES e BASE_LEADS
        
        // Buscar na tabela CLIENTES
        $sql_clientes = "SELECT 
                    'cliente' as origem,
                    COD_CLIENT,
                    CNPJ,
                    CLIENTE as cliente_nome,
                    NOME_FANTASIA as nome_fantasia,
                    EMailNFe as email,
                    CONCAT('(', DDD, ') ', Telefone) as telefone,
                    Endereco as endereco,
                    Estado as estado,
                    CEP as cep
                FROM CLIENTES
                WHERE CLIENTE LIKE :termo OR NOME_FANTASIA LIKE :termo
                LIMIT 10";
        
        $stmt_clientes = $pdo->prepare($sql_clientes);
        $stmt_clientes->execute(['termo' => '%' . $termo_busca . '%']);
        $resultados_clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar na tabela BASE_LEADS (se conexão disponível)
        $resultados_leads = [];
        if (isset($pdo_leads)) {
            $sql_leads = "SELECT 
                        'lead' as origem,
                        cnpj as CNPJ,
                        COALESCE(NULLIF(RAZAOSOCIAL, ''), NULLIF(NOMEFANTASIA, ''), NULLIF(nomefinal, ''), 'N/A') as cliente_nome,
                        NOMEFANTASIA as nome_fantasia,
                        NULLIF(Email, '') as email,
                        TelefonePrincipalFINAL as telefone,
                        endereoCNPJJA as endereco,
                        UF as estado,
                        CEP as cep
                    FROM BASE_LEADS
                    WHERE RAZAOSOCIAL LIKE :termo OR NOMEFANTASIA LIKE :termo OR nomefinal LIKE :termo
                    LIMIT 10";
            
            try {
                $stmt_leads = $pdo_leads->prepare($sql_leads);
                $stmt_leads->execute(['termo' => '%' . $termo_busca . '%']);
                $resultados_leads = $stmt_leads->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Erro ao buscar na BASE_LEADS: " . $e->getMessage());
            }
        }
        
        // Combinar resultados
        $resultados = array_merge($resultados_clientes, $resultados_leads);
        
        if (!empty($resultados)) {
            $resultados_formatados = array_map(function($r) {
                return [
                    'cliente_nome' => $r['cliente_nome'] ?? '',
                    'nome_fantasia' => $r['nome_fantasia'] ?? '',
                    'cnpj' => $r['CNPJ'] ?? '',
                    'email' => $r['email'] ?? '',
                    'telefone' => $r['telefone'] ?? '',
                    'endereco' => $r['endereco'] ?? '',
                    'estado' => $r['estado'] ?? '',
                    'cep' => $r['cep'] ?? '',
                    'origem' => $r['origem'] ?? ''
                ];
            }, $resultados);
            
            echo json_encode([
                'success' => true,
                'data' => $resultados_formatados,
                'multiple' => count($resultados) > 1
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cliente/Lead não encontrado']);
        }
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar cliente: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Erro ao buscar cliente: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar: ' . $e->getMessage()]);
}
