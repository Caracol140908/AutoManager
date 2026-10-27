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
    echo json_encode(['ok' => false, 'error' => 'Não autorizado']);
    exit;
}

$id_carro = am_servico_resolver_id_carro($con, $serv);
if ($id_carro <= 0) {
    echo json_encode(['ok' => true, 'skipped' => true, 'message' => 'Sem veículo ligado — perfil não atualizado.']);
    exit;
}

$perfilAtual = am_veiculo_perfil_get($con, $id_carro);
$kmPostRaw = trim((string) ($_POST['km_atual'] ?? ''));
if ($kmPostRaw !== '' && preg_match('/^\d+$/', $kmPostRaw) && is_array($perfilAtual) && isset($perfilAtual['km_atual']) && $perfilAtual['km_atual'] !== null && $perfilAtual['km_atual'] !== '') {
    $kmAtual = (int) $perfilAtual['km_atual'];
    $kmNovo = (int) $kmPostRaw;
    if ($kmNovo < $kmAtual) {
        echo json_encode(['ok' => false, 'error' => 'Os km na saída não podem ser inferiores aos já registados.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$d = [
    'propulsao' => is_array($perfilAtual) ? ($perfilAtual['propulsao'] ?? '') : '',
    'motorizacao' => is_array($perfilAtual) ? ($perfilAtual['motorizacao'] ?? '') : '',
    'vin' => is_array($perfilAtual) ? ($perfilAtual['vin'] ?? '') : '',
    'km_atual' => $_POST['km_atual'] ?? '',
    'data_ultima_inspecao' => is_array($perfilAtual) ? ($perfilAtual['data_ultima_inspecao'] ?? '') : '',
    'data_ultimo_servico' => date('Y-m-d'),
    'notas_oficina' => is_array($perfilAtual) ? ($perfilAtual['notas_oficina'] ?? '') : '',
];

$ok = am_veiculo_perfil_upsert($con, $id_carro, $d, 'oficina');
if (!$ok) {
    $det = am_veiculo_perfil_erro_store();
    echo json_encode(['ok' => false, 'error' => $det !== '' ? $det : 'Erro ao guardar perfil'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
