<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['mecanico_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Sessão expirada']);
    exit;
}

require_once __DIR__ . '/ligar.php';
require_once __DIR__ . '/inc_veiculo_perfil.php';

$mecanico_id = (int) $_SESSION['mecanico_id'];
$servico_id = (int) ($_POST['servico_id'] ?? 0);
if ($servico_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Serviço inválido']);
    exit;
}

if (am_servico_mec_col_expr($con) === '') {
    echo json_encode(['ok' => false, 'error' => 'Estrutura inválida']);
    exit;
}

$serv = am_servico_fetch_para_mecanico($con, $servico_id, $mecanico_id);
if (!$serv) {
    echo json_encode(['ok' => false, 'error' => 'Serviço não encontrado ou não autorizado']);
    exit;
}

$id_carro = am_servico_resolver_id_carro($con, $serv);
if ($id_carro <= 0) {
    echo json_encode([
        'ok' => false,
        'error' => 'Não foi possível associar este serviço a um veículo nas configurações do cliente (matrícula / conta). Peça ao cliente para registar o carro em “Veículos”.',
    ]);
    exit;
}

$id_utilizador = am_veiculo_perfil_id_utilizador_do_carro($con, $id_carro);
if ($id_utilizador <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Veículo inválido']);
    exit;
}

if (am_veiculo_perfil_ja_foi_solicitado_carro($con, $id_carro)) {
    echo json_encode([
        'ok' => true,
        'already_requested' => true,
        'message' => 'Os dados deste veículo já foram pedidos anteriormente.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ok = am_veiculo_perfil_criar_pedido($con, $id_carro, $id_utilizador, $servico_id, $mecanico_id);
if (!$ok) {
    echo json_encode(['ok' => false, 'error' => 'Não foi possível criar o pedido']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Pedido enviado ao cliente.'], JSON_UNESCAPED_UNICODE);
