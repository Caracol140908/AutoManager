<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/chat_lib.php';

chat_ensure_schema($con);

header('Content-Type: application/json');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false]);
    exit;
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id > 0) {
    chat_mark_admin_message_read($con, $id);
}
echo json_encode(['success' => true]);
