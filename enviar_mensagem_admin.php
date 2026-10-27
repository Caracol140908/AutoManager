<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/chat_lib.php';

chat_ensure_schema($con);

header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$mensagem = trim($_POST['mensagem'] ?? '');
$paraId = (int) ($_POST['para_usuario_id'] ?? $_POST['usuario_id'] ?? 0);
$result = chat_insert_admin_message($con, $paraId, $mensagem);
if (!$result['ok']) {
    echo json_encode(['error' => $result['error']]);
    exit;
}
echo json_encode(['success' => true, 'id' => $result['id']]);
