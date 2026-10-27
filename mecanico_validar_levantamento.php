<?php
session_start();
require_once 'ligar.php';
require_once __DIR__ . '/inc_codigo_levantamento.php';

header('Content-Type: application/json');

if (!isset($_SESSION['mecanico_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
    exit;
}

am_garantir_colunas_codigo_levantamento($con);

$servicoId = (int) ($_POST['servico_id'] ?? 0);
$codigo = preg_replace('/\D+/', '', (string) ($_POST['codigo'] ?? ''));
$mecanicoId = (int) $_SESSION['mecanico_id'];

if ($servicoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Serviço inválido.']);
    exit;
}
if (strlen($codigo) !== 6) {
    echo json_encode(['success' => false, 'error' => 'Código inválido.']);
    exit;
}

$cols = [];
$resCols = @$con->query('SHOW COLUMNS FROM servico');
if ($resCols) {
    while ($c = $resCols->fetch_assoc()) {
        $cols[$c['Field']] = true;
    }
}
$mecExpr = isset($cols['id_mecanico']) && isset($cols['mecanico_id'])
    ? 'COALESCE(id_mecanico, mecanico_id)'
    : (isset($cols['id_mecanico']) ? 'id_mecanico' : (isset($cols['mecanico_id']) ? 'mecanico_id' : 'NULL'));
if ($mecExpr === 'NULL') {
    echo json_encode(['success' => false, 'error' => 'Estrutura de base de dados inválida.']);
    exit;
}

$st = $con->prepare("
    SELECT id, matricula, estado_pagamento, estado_do_trabalho, codigo_levantamento, codigo_levantamento_usado_em
    FROM servico
    WHERE id = ? AND {$mecExpr} = ?
    LIMIT 1
");
if (!$st) {
    echo json_encode(['success' => false, 'error' => 'Erro ao validar serviço.']);
    exit;
}
$st->bind_param('ii', $servicoId, $mecanicoId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Serviço não encontrado para este mecânico.']);
    exit;
}
if (strtolower(trim((string) ($row['estado_pagamento'] ?? 'pendente'))) !== 'pago') {
    echo json_encode(['success' => false, 'error' => 'O cliente ainda não liquidou este serviço.']);
    exit;
}
if (!empty($row['codigo_levantamento_usado_em'])) {
    echo json_encode(['success' => false, 'error' => 'Este código já foi utilizado.']);
    exit;
}
if ($codigo !== preg_replace('/\D+/', '', (string) ($row['codigo_levantamento'] ?? ''))) {
    echo json_encode(['success' => false, 'error' => 'Código incorreto.']);
    exit;
}

$up = $con->prepare("
    UPDATE servico
    SET codigo_levantamento_usado_em = NOW(),
        estado_do_trabalho = 'Entregue'
    WHERE id = ? AND {$mecExpr} = ?
");
if (!$up) {
    echo json_encode(['success' => false, 'error' => 'Erro ao guardar entrega.']);
    exit;
}
$up->bind_param('ii', $servicoId, $mecanicoId);
$ok = $up->execute();
$up->close();

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'Não foi possível confirmar a entrega.']);
    exit;
}

$matricula = trim((string) ($row['matricula'] ?? ''));
$msg = $matricula !== ''
    ? 'Entrega confirmada para o veículo ' . $matricula . '.'
    : 'Entrega confirmada com sucesso.';

echo json_encode(['success' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE);
