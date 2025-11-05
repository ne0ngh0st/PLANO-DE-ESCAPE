<?php
// Verificar se a conexão PDO está disponível
if (!isset($pdo)) {
    require_once 'conexao.php';
}

// Verificar se o usuário está definido
if (!isset($usuario)) {
    session_start();
    if (isset($_SESSION['usuario'])) {
        $usuario = $_SESSION['usuario'];
    } else {
        // Retornar valores padrão se não houver usuário
        $estatisticas_gerais = [
            'total_ligacoes' => 0,
            'ligacoes_finalizadas' => 0,
            'ligacoes_canceladas' => 0,
            'media_respostas' => 0
        ];
        return;
    }
}

// Obter estatísticas gerais de ligações (mesma lógica da gestao_ligacoes.php)
try {
    // Preparar condições WHERE baseadas no perfil do usuário
    $where_conditions = ["1=1"];
    $params = [];
    
    // Aplicar filtros baseados no perfil do usuário
    if (strtolower(trim($usuario['perfil'])) === 'vendedor' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "u.COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    }
    // Se for representante, filtrar apenas suas ligações
    elseif (strtolower(trim($usuario['perfil'])) === 'representante' && !empty($usuario['cod_vendedor'])) {
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "u.COD_VENDEDOR = ?";
        $params[] = $cod_vendedor_formatado;
    }
    // Se for supervisor, filtrar ligações dos vendedores sob sua supervisão
    elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
        $cod_supervisor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $where_conditions[] = "u.COD_SUPER = ?";
        $params[] = $cod_supervisor_formatado;
    }
    // Se for diretor ou admin, verificar se há filtro de visão específica
    elseif (in_array(strtolower(trim($usuario['perfil'])), ['diretor', 'admin'])) {
        $supervisor_selecionado = $_GET['visao_supervisor'] ?? '';
        if (!empty($supervisor_selecionado)) {
            $supervisor_formatado = str_pad($supervisor_selecionado, 3, '0', STR_PAD_LEFT);
            $where_conditions[] = "u.COD_SUPER = ?";
            $params[] = $supervisor_formatado;
        }
    }
    
    // Não mostrar ligações excluídas (exclusão lógica) - sempre aplicar primeiro
    $where_conditions[] = "l.status != 'excluida'";
    
    // Filtrar apenas ligações do mês atual (mesmo comportamento das metas de faturamento)
    $mes_atual = date('m');
    $ano_atual = date('Y');
    $where_conditions[] = "MONTH(l.data_ligacao) = ?";
    $where_conditions[] = "YEAR(l.data_ligacao) = ?";
    $params[] = $mes_atual;
    $params[] = $ano_atual;
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Buscar estatísticas (mesma lógica da gestao_ligacoes.php)
    $sql_stats = "
        SELECT 
            COUNT(DISTINCT l.id) as total_ligacoes,
            COUNT(DISTINCT CASE WHEN l.status = 'finalizada' THEN l.id END) as ligacoes_finalizadas,
            COUNT(DISTINCT CASE WHEN l.status != 'finalizada' AND l.status != 'excluida' THEN l.id END) as ligacoes_canceladas,
            AVG(CASE WHEN l.status = 'finalizada' THEN 
                (SELECT COUNT(DISTINCT r2.pergunta_id) 
                 FROM RESPOSTAS_LIGACAO r2 
                 INNER JOIN PERGUNTAS_LIGACAO p2 ON r2.pergunta_id = p2.id 
                 WHERE r2.ligacao_id = l.id AND p2.obrigatoria = TRUE)
            END) as media_respostas
        FROM LIGACOES l
        LEFT JOIN USUARIOS u ON l.usuario_id = u.ID
        LEFT JOIN (
            SELECT DISTINCT CNPJ, CLIENTE
            FROM ultimo_faturamento
            WHERE CNPJ IS NOT NULL AND CNPJ != ''
        ) uf ON l.cliente_id = uf.CNPJ
        $where_clause
    ";
    
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($params);
    $estatisticas_gerais = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
    // Garantir que os valores sejam numéricos
    $estatisticas_gerais['total_ligacoes'] = intval($estatisticas_gerais['total_ligacoes'] ?? 0);
    $estatisticas_gerais['ligacoes_finalizadas'] = intval($estatisticas_gerais['ligacoes_finalizadas'] ?? 0);
    $estatisticas_gerais['ligacoes_canceladas'] = intval($estatisticas_gerais['ligacoes_canceladas'] ?? 0);
    $estatisticas_gerais['media_respostas'] = floatval($estatisticas_gerais['media_respostas'] ?? 0);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar estatísticas gerais de ligações: " . $e->getMessage());
    $estatisticas_gerais = [
        'total_ligacoes' => 0,
        'ligacoes_finalizadas' => 0,
        'ligacoes_canceladas' => 0,
        'media_respostas' => 0
    ];
}
?>
