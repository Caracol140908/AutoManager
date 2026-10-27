<?php
/**
 * Marca as notificações do cliente como lidas (JSON).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['user']['id_utilizador'])) {
    echo json_encode(['ok' => false, 'error' => 'auth'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/ligar.php';
require_once __DIR__ . '/inc_utilizador_notificacoes.php';

$id = (int) $_SESSION['user']['id_utilizador'];
$m = am_utilizador_marcar_notificacoes_lidas($con, $id);

echo json_encode([
    'ok' => true,
    'pulse' => $m['pulse'],
    'count' => $m['count'],
    'raw_count' => (int) ($m['raw_count'] ?? 0),
    'sections' => $m['sections'] ?? ['dados' => 0, 'veiculos' => 0, 'marcacoes' => 0],
    'section_pulses' => $m['section_pulses'] ?? ['dados' => '', 'veiculos' => '', 'marcacoes' => ''],
    'tipos' => $m['tipos'] ?? [],
    'ts' => time(),
], JSON_UNESCAPED_UNICODE);
