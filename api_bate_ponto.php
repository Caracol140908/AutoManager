<?php
header('Content-Type: application/json');
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_admin_notificacoes.php';

if (!isset($_SESSION['mecanico_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit();
}

$acao = $_POST['acao'] ?? '';
$mecanico_id = (int) $_SESSION['mecanico_id'];
$data_hoje = date('Y-m-d');
$timestamp_agora = date('Y-m-d H:i:s');

$sql_check = 'SELECT ativo, data_despedimento FROM mecanicos WHERE id_mecanico = ?';
$stmt = mysqli_prepare($con, $sql_check);
mysqli_stmt_bind_param($stmt, 'i', $mecanico_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $ativo, $data_despedimento);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if ($ativo == 0) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Conta desativada']);
    exit();
}

if ($acao === 'entrada') {
    $reg = am_presenca_get($con, $mecanico_id, $data_hoje);
    if ($reg && !empty($reg['hora_entrada'])) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Entrada já registada hoje às ' . substr((string) $reg['hora_entrada'], 0, 5),
        ]);
        exit();
    }
    $hora_entrada = date('H:i:s');
    $ok = am_presenca_save($con, $mecanico_id, $data_hoje, [
        'hora_entrada' => $hora_entrada,
        'timestamp_entrada' => $timestamp_agora,
    ]);
    echo json_encode($ok
        ? ['sucesso' => true, 'mensagem' => 'Entrada registada com sucesso!', 'hora' => $hora_entrada, 'tipo' => 'entrada']
        : ['sucesso' => false, 'mensagem' => 'Erro ao registar entrada']);
} elseif ($acao === 'saida') {
    $reg = am_presenca_get($con, $mecanico_id, $data_hoje);
    if (!$reg || empty($reg['hora_entrada']) || !empty($reg['hora_saida'])) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Sem entrada registada para hoje']);
        exit();
    }
    $hora_saida = date('H:i:s');
    $horas = round((strtotime($hora_saida) - strtotime((string) $reg['hora_entrada'])) / 3600, 2);
    if ($horas < 0.5) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Horário inválido. Saída antes da entrada?']);
        exit();
    }
    $ok = am_presenca_save($con, $mecanico_id, $data_hoje, [
        'hora_entrada' => $reg['hora_entrada'],
        'timestamp_entrada' => $reg['timestamp_entrada'] ?? null,
        'hora_saida' => $hora_saida,
        'timestamp_saida' => $timestamp_agora,
        'total_horas' => $horas,
    ]);
    echo json_encode($ok
        ? ['sucesso' => true, 'mensagem' => 'Saída registada com sucesso!', 'hora' => $hora_saida, 'horas_trabalhadas' => $horas, 'tipo' => 'saida']
        : ['sucesso' => false, 'mensagem' => 'Erro ao registar saída']);
} elseif ($acao === 'status') {
    $reg = am_presenca_get($con, $mecanico_id, $data_hoje);
    if (!$reg || empty($reg['hora_entrada'])) {
        echo json_encode(['sucesso' => true, 'status' => 'ausente', 'mensagem' => 'Sem registo de presença']);
    } elseif (empty($reg['hora_saida'])) {
        echo json_encode([
            'sucesso' => true,
            'status' => 'presente',
            'hora_entrada' => $reg['hora_entrada'],
            'horas_trabalhadas' => null,
            'mensagem' => 'Presença confirmada. Aguardando saída.',
        ]);
    } else {
        echo json_encode([
            'sucesso' => true,
            'status' => 'completo',
            'hora_entrada' => $reg['hora_entrada'],
            'hora_saida' => $reg['hora_saida'],
            'horas_trabalhadas' => $reg['total_horas'],
            'mensagem' => 'Dia de trabalho concluído',
        ]);
    }
} elseif ($acao === 'historico') {
    $data_inicio = date('Y-m-d', strtotime('-30 days'));
    echo json_encode(['sucesso' => true, 'registos' => am_presenca_historico($con, $mecanico_id, $data_inicio)]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida']);
}
