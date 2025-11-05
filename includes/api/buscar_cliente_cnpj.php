<?php
session_start();
header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Incluir arquivo de conexão
require_once 'conexao.php';

$usuario = $_SESSION['usuario'];
$cnpj_busca = $_GET['cnpj'] ?? '';

if (empty($cnpj_busca)) {
    echo json_encode(['success' => false, 'message' => 'CNPJ não fornecido']);
    exit;
}

try {
    // Limpar CNPJ (remover caracteres especiais)
    $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj_busca);
    
    // Buscar clientes que contenham a raiz do CNPJ fornecida
    $sql = "SELECT DISTINCT 
                SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) as raiz_cnpj,
                CASE 
                    WHEN ce.cliente_editado IS NOT NULL AND ce.cliente_editado != '' 
                    THEN ce.cliente_editado 
                    ELSE uf.CLIENTE 
                END as nome,
                CASE 
                    WHEN ce.nome_fantasia_editado IS NOT NULL AND ce.nome_fantasia_editado != '' 
                    THEN ce.nome_fantasia_editado 
                    ELSE uf.NOME_FANTASIA 
                END as nome_fantasia,
                CASE 
                    WHEN ce.estado_editado IS NOT NULL AND ce.estado_editado != '' 
                    THEN ce.estado_editado 
                    ELSE uf.ESTADO 
                END as estado
            FROM ultimo_faturamento uf
            LEFT JOIN CLIENTES_EDICOES ce ON uf.CNPJ = ce.cnpj_original AND ce.ativo = 1
            WHERE SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(uf.CNPJ, '.', ''), '/', ''), '-', ''), ' ', ''), 1, 8) LIKE ?
            AND (ce.cliente_editado IS NOT NULL AND ce.cliente_editado != '' OR uf.CLIENTE IS NOT NULL AND uf.CLIENTE != '')";
    
    // Aplicar filtros baseados no perfil do usuário
    $params = [$cnpj_limpo . '%'];
    
    // Se for vendedor, filtrar apenas seus clientes
    if (strtolower(trim($usuario['perfil'])) === 'vendedor' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $sql .= " AND uf.COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    }
    // Se for representante, filtrar apenas seus clientes
    elseif (strtolower(trim($usuario['perfil'])) === 'representante' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $sql .= " AND uf.COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    }
    // Se for supervisor, filtrar clientes dos vendedores sob sua supervisão
    elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $cod_supervisor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $sql .= " AND uf.COD_SUPER = ?";
        $params[] = $cod_supervisor_formatado;
    }
    // Se for diretor, verificar se há filtro de visão específica
    elseif (strtolower(trim($usuario['perfil'])) === 'diretor') {
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        if (!empty($supervisor_selecionado)) {
            $supervisor_formatado = str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT);
            $sql .= " AND EXISTS (SELECT 1 FROM USUARIOS u WHERE u.COD_VENDEDOR = uf.COD_VENDEDOR AND u.COD_SUPER = ? AND u.ATIVO = 1 AND u.PERFIL IN ('vendedor', 'representante'))";
            $params[] = $supervisor_formatado;
        }
    }
    
    $sql .= " ORDER BY nome ASC LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar raiz CNPJ para exibição
    foreach ($clientes as &$cliente) {
        $cliente['cnpj'] = $cliente['raiz_cnpj']; // Usar raiz CNPJ
    }
    
    echo json_encode([
        'success' => true, 
        'clientes' => $clientes,
        'total' => count($clientes)
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar cliente por CNPJ: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
