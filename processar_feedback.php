<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_admin_notificacoes.php';
@include_once 'config_telegram.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$assunto = trim($_POST['assunto'] ?? '');
$mensagem = trim($_POST['mensagem'] ?? '');
$tipo = $_POST['tipo'] ?? 'feedback';

if (am_feedback_insert($con, $nome, $email, $tipo, $assunto, $mensagem)) {
    if (isset($telegramBot) && $telegramBot->configurado()) {
        $link_feedback = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/indexadmin.php#feedbacks';
        $telegramBot->enviarNotificacaoFeedback($nome, $email, $assunto, $mensagem, $link_feedback);
    }
    echo json_encode(['success' => true, 'message' => 'Feedback enviado com sucesso!']);
} else {
    echo json_encode(['success' => false, 'error' => 'Erro ao enviar feedback']);
}
