<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

require_once 'conexao.php';

try {
    $usuario = $_SESSION['usuario'];
    
    // Obter data atual
    $data_atual = new DateTime();
    $ano_atual = $data_atual->format('Y');
    $mes_atual = $data_atual->format('m');
    
    // Calcular dias no mês e dias passados
    $dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes_atual, $ano_atual);
    $dias_passados = $data_atual->format('j');
    
    // Buscar meta de faturamento do usuário
    $metafat = 0;
    if (isset($pdo)) {
        $stmt_meta = $pdo->prepare("SELECT metafat FROM USUARIOS WHERE COD_VENDEDOR = ? AND ATIVO = 1");
        $cod_vendedor_formatado = str_pad($usuario['cod_vendedor'], 3, '0', STR_PAD_LEFT);
        $stmt_meta->execute([$cod_vendedor_formatado]);
        $resultado_meta = $stmt_meta->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado_meta) {
            $metafat = floatval($resultado_meta['metafat']);
        }
    }
    
    // Buscar faturamento do mês atual
    $faturamento_mes_atual = 0;
    if (isset($pdo)) {
        $data_inicio_mes = sprintf('%04d-%02d-01', $ano_atual, $mes_atual);
        $data_fim_mes = sprintf('%04d-%02d-%02d', $ano_atual, $mes_atual, $dias_no_mes);
        
        $sql_faturamento = "SELECT SUM(VLR_TOTAL) as total FROM ultimo_faturamento 
                           WHERE DATE_FORMAT(STR_TO_DATE(DT_FAT, '%d/%m/%Y'), '%Y-%m-%d') 
                           BETWEEN ? AND ?";
        
        // Aplicar filtros baseados no perfil do usuário
        $where_conditions = [];
        $params = [$data_inicio_mes, $data_fim_mes];
        
        if (strtolower(trim($usuario['perfil'])) === 'vendedor' && !empty($usuario['cod_vendedor'])) {
            $where_conditions[] = "COD_VENDEDOR = ?";
            $params[] = $cod_vendedor_formatado;
        } elseif (strtolower(trim($usuario['perfil'])) === 'representante' && !empty($usuario['cod_vendedor'])) {
            $where_conditions[] = "COD_VENDEDOR = ?";
            $params[] = $cod_vendedor_formatado;
        } elseif (strtolower(trim($usuario['perfil'])) === 'supervisor' && !empty($usuario['cod_vendedor'])) {
            $where_conditions[] = "COD_SUPER = ?";
            $params[] = $cod_vendedor_formatado;
        }
        
        if (!empty($where_conditions)) {
            $sql_faturamento .= " AND " . implode(' AND ', $where_conditions);
        }
        
        $stmt_fat = $pdo->prepare($sql_faturamento);
        $stmt_fat->execute($params);
        $resultado_fat = $stmt_fat->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado_fat && $resultado_fat['total']) {
            $faturamento_mes_atual = floatval($resultado_fat['total']) / 100; // Converter de centavos
        }
    }
    
    // Calcular projeções
    $projecao_diaria = 0;
    $ritmo_atual = 0;
    $meta_diaria = 0;
    
    if ($dias_passados > 0) {
        $ritmo_atual = $faturamento_mes_atual / $dias_passados;
        $projecao_diaria = $faturamento_mes_atual * ($dias_no_mes / $dias_passados);
    }
    
    if ($dias_no_mes > 0) {
        $meta_diaria = $metafat / $dias_no_mes;
    }
    
    // Preparar resposta
    $dados = [
        'ritmo_atual' => round($ritmo_atual, 2),
        'projecao_diaria' => round($projecao_diaria, 2),
        'meta_diaria' => round($meta_diaria, 2),
        'faturamento_mes_atual' => round($faturamento_mes_atual, 2),
        'meta_total' => round($metafat, 2),
        'dias_passados' => $dias_passados,
        'dias_no_mes' => $dias_no_mes
    ];
    
    echo json_encode([
        'success' => true,
        'dados' => $dados
    ]);
    
} catch (Exception $e) {
    error_log("Erro no gráfico de projeções: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar dados de projeções'
    ]);
}
?>
