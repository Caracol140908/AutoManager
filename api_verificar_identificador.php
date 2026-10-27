<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_nif.php';
require_once __DIR__ . '/inc_iban.php';

$autorizado = !empty($_SESSION['is_admin'])
    || !empty($_SESSION['chefe_id'])
    || !empty($_SESSION['user_id'])
    || !empty($_SESSION['mecanico_id']);

if (!$autorizado) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensagem' => 'Não autorizado.']);
    exit;
}

$tipo = strtolower(trim((string) ($_POST['tipo'] ?? $_GET['tipo'] ?? '')));
$valor = (string) ($_POST['valor'] ?? $_GET['valor'] ?? '');

$excluir = [];
if (!empty($_POST['excluir_utilizador'])) {
    $excluir['utilizador'] = (int) $_POST['excluir_utilizador'];
}
if (!empty($_POST['excluir_mecanicos'])) {
    $excluir['mecanicos'] = (int) $_POST['excluir_mecanicos'];
}
if (!empty($_POST['excluir_chefes_oficina'])) {
    $excluir['chefes_oficina'] = (int) $_POST['excluir_chefes_oficina'];
}

if ($tipo === 'nif') {
    [$ok, $mensagem] = am_validar_nif_unico($con, $valor, $excluir);
} elseif ($tipo === 'iban') {
    [$ok, $mensagem] = am_validar_iban_unico($con, $valor, $excluir);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensagem' => 'Tipo de verificação inválido.']);
    exit;
}

echo json_encode(['ok' => $ok, 'mensagem' => $mensagem]);
