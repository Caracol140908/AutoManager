<?php
session_start();
require_once('ligar.php');
require_once __DIR__ . '/inc_admin_notificacoes.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$avaliacao = (int) ($_POST['avaliacao'] ?? 0);
$comentario = trim($_POST['comentario'] ?? '');
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

if ($nome === '' || $email === '' || $avaliacao < 1 || $avaliacao > 5) {
    echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos corretamente.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido.']);
    exit;
}

if (am_avaliacao_temp_ja_feita($con, $email, $ip_address)) {
    echo json_encode(['success' => false, 'message' => 'Já avaliou anteriormente. Obrigado!']);
    exit;
}

if (am_avaliacao_temp_insert($con, $nome, $email, $avaliacao, $comentario, $ip_address)) {
    $_SESSION['avaliou_temp'] = true;
    $_SESSION['temp_expired'] = false;
    echo json_encode(['success' => true, 'message' => 'Avaliação registada com sucesso! Obrigado.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao registar avaliação.']);
}
