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
require_once __DIR__ . '/inc_fatura_intervencao.php';

$mecanico_id = (int) $_SESSION['mecanico_id'];
$servico_id = (int) ($_GET['servico_id'] ?? $_POST['servico_id'] ?? 0);
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
$perfil = null;
if ($id_carro > 0) {
    $perfil = am_veiculo_perfil_get($con, $id_carro);
}

$horasSugeridas = am_servico_horas_para_fatura($serv);
$minutosReais = am_servico_minutos_trabalho_reais($serv);
if ($minutosReais !== null && $minutosReais > 0) {
    $horasSugeridas = round($minutosReais / 60, 2);
}
$orcamentoEstimado = am_servico_valor_orcamento_aprovado($serv);

echo json_encode([
    'ok' => true,
    'id_carro' => $id_carro,
    'matricula' => (string) ($serv['matricula'] ?? ''),
    'perfil' => $perfil,
    'pedido_pendente' => $id_carro > 0 && am_veiculo_perfil_tem_pedido_pendente_carro($con, $id_carro),
    'horas_sugeridas' => $horasSugeridas,
    'orcamento_aprovado' => round((float) ($serv['orcamento_aprovado'] ?? 0), 2),
    'orcamento_estimado_com_iva' => $orcamentoEstimado,
], JSON_UNESCAPED_UNICODE);
