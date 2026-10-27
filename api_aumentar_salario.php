<?php
// api_aumentar_salario.php - Sistema de Aumento de Salário
header('Content-Type: application/json');
session_start();
require 'ligar.php';

// Verificar se é chefe
if (!isset($_SESSION['chefe_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit();
}

$chefe_id = $_SESSION['chefe_id'];
$funcionario_id = $_POST['funcionario_id'] ?? '';
$tipo = $_POST['tipo'] ?? ''; // mecanico
$novo_salario = $_POST['novo_salario'] ?? 0;
$motivo = $_POST['motivo'] ?? '';
$data_hoje = date('Y-m-d');

// Validações
if (!$funcionario_id || !$tipo || $novo_salario <= 0) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos']);
    exit();
}

if ($tipo === 'mecanico') {
    // Buscar mecânico
    $sql = "SELECT id_mecanico, nome_mecanico, ordenado_bruto FROM mecanicos WHERE id_mecanico = ? AND ativo = 1";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $funcionario_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $nome, $salario_atual);
    $existe = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    if (!$existe) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Mecânico não encontrado']);
        exit();
    }
    
    // Verificar se há aumento real
    if ($novo_salario <= $salario_atual) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'O novo salário deve ser superior ao atual']);
        exit();
    }
    
    // Atualizar salário
    $sql_update = "UPDATE mecanicos SET ordenado_bruto = ? WHERE id_mecanico = ?";
    $stmt_update = mysqli_prepare($con, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "di", $novo_salario, $funcionario_id);
    
    if (mysqli_stmt_execute($stmt_update)) {
        mysqli_stmt_close($stmt_update);
        
        // Registar no histórico
        $sql_historico = "INSERT INTO historico_salarios (id_mecanico, salario_anterior, salario_novo, data_alteracao, motivo_alteracao, alterado_por) 
                         VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_hist = mysqli_prepare($con, $sql_historico);
        mysqli_stmt_bind_param($stmt_hist, "iddssi", $funcionario_id, $salario_atual, $novo_salario, $data_hoje, $motivo, $chefe_id);
        mysqli_stmt_execute($stmt_hist);
        mysqli_stmt_close($stmt_hist);
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Salário aumentado com sucesso',
            'aumento' => round($novo_salario - $salario_atual, 2),
            'percentagem' => round((($novo_salario - $salario_atual) / $salario_atual) * 100, 1)
        ]);
    } else {
        mysqli_stmt_close($stmt_update);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar salário']);
    }

} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Tipo inválido. Apenas mecânicos podem ser geridos.']);
}
?>
