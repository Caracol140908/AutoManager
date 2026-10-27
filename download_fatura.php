<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_fatura_intervencao.php';
require_once __DIR__ . '/inc_fatura_documento.php';

@$con->query("ALTER TABLE servico ADD COLUMN fatura_linhas_json LONGTEXT NULL");
@$con->query("ALTER TABLE servico ADD COLUMN numero_fatura VARCHAR(40) NULL");
am_ensure_servico_fecho_cols($con);

if (!isset($_GET['servico_id'])) {
    die('Link de fatura inválido.');
}

$servico_id = (int) $_GET['servico_id'];
if ($servico_id <= 0) {
    die('Link de fatura inválido.');
}

$servico = null;
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($token !== '') {
    $stmt = $con->prepare("SELECT s.* FROM servico s WHERE s.id = ? AND s.token_pagamento = ? AND LOWER(COALESCE(s.estado_pagamento, '')) = 'pago'");
    $stmt->bind_param('is', $servico_id, $token);
    $stmt->execute();
    $servico = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$servico && !empty($_SESSION['mecanico_id'])) {
    $mecanico_id = (int) $_SESSION['mecanico_id'];
    $stmt = $con->prepare("
        SELECT s.*
        FROM servico s
        WHERE s.id = ?
          AND (s.mecanico_id = ? OR s.id_mecanico = ?)
          AND (
            LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%conclu%'
            OR LOWER(COALESCE(s.estado_do_trabalho, '')) LIKE '%pronto%'
            OR LOWER(COALESCE(s.estado_pagamento, '')) = 'pago'
            OR s.numero_fatura IS NOT NULL AND s.numero_fatura <> ''
          )
        LIMIT 1
    ");
    $stmt->bind_param('iii', $servico_id, $mecanico_id, $mecanico_id);
    $stmt->execute();
    $servico = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$servico && !empty($_SESSION['user_id']) && !empty($_SESSION['is_admin'])) {
    $stmt = $con->prepare('SELECT s.* FROM servico s WHERE s.id = ? LIMIT 1');
    $stmt->bind_param('i', $servico_id);
    $stmt->execute();
    $servico = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$servico && !empty($_SESSION['user']['email'])) {
    $email = (string) $_SESSION['user']['email'];
    $stmt = $con->prepare('SELECT s.*, u.nome_utilizador, u.carro FROM servico s LEFT JOIN utilizador u ON LOWER(TRIM(u.email)) = LOWER(TRIM(s.email)) WHERE s.id = ? AND s.email = ? LIMIT 1');
    $stmt->bind_param('is', $servico_id, $email);
    $stmt->execute();
    $servico = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$servico) {
    die('Fatura não encontrada ou acesso não autorizado.');
}

$syncFat = am_servico_sincronizar_divida_pagamento($con, $servico_id);
if (is_array($syncFat)) {
    $servico = array_merge($servico, $syncFat);
}

$doc = am_fatura_documento_montar($con, $servico);
$acoes = [
    ['onclick' => 'window.print()', 'label' => 'Imprimir fatura', 'class' => 'fat-btn--primary'],
    ['onclick' => 'window.close()', 'label' => 'Fechar'],
];

header('Content-Type: text/html; charset=utf-8');
am_fatura_documento_emitir($doc, ['acoes' => $acoes]);
