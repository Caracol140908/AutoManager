<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/ligar.php';
require_once __DIR__ . '/inc_horarios_marcacao.php';

$data = trim((string) ($_GET['data'] ?? ''));
$excluir = (int) ($_GET['excluir_servico_id'] ?? 0);

[$dataOk, $msg] = am_horarios_validar_data_marcacao($data);
if (!$dataOk) {
    echo json_encode([
        'ok' => false,
        'erro' => $msg,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$resumo = am_horarios_resumo_dia($con, $data, $excluir);

echo json_encode([
    'ok' => true,
    'data' => $resumo['data'],
    'slots' => $resumo['slots'],
    'disponiveis' => $resumo['disponiveis'],
    'ocupados' => $resumo['ocupados'],
    'total_disponiveis' => $resumo['total_disponiveis'],
    'total_ocupados' => $resumo['total_ocupados'],
    'expediente' => [
        'inicio' => am_horarios_oficina_inicio(),
        'fim' => am_horarios_oficina_fim(),
        'intervalo_minutos' => am_horarios_intervalo_minutos(),
    ],
], JSON_UNESCAPED_UNICODE);
