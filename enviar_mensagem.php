<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/chat_lib.php';

chat_ensure_schema($con);

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || empty($_SESSION['user']['id_utilizador'])) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mensagem = trim($_POST['mensagem'] ?? '');
    $userId = (int) $_SESSION['user']['id_utilizador'];
    $userName = (string) ($_SESSION['user']['nome_utilizador'] ?? 'Cliente');
    $result = chat_insert_user_message($con, $userId, $userName, $mensagem);
    if (!$result['ok']) {
        echo json_encode(['error' => $result['error']]);
        exit;
    }
    echo json_encode(['success' => true, 'id' => $result['id']]);
}
