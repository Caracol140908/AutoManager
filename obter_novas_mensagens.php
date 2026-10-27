<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/chat_lib.php';

chat_ensure_schema($con);
header('Content-Type: application/json');

$ultimaId = (int) ($_GET['ultima_id'] ?? 0);
$usuarioId = isset($_SESSION['user']['id_utilizador']) ? (int) $_SESSION['user']['id_utilizador'] : 0;
if ($usuarioId <= 0) {
    echo json_encode([]);
    exit;
}

$rows = chat_fetch_thread($con, $usuarioId, $ultimaId);
$out = [];
foreach ($rows as $row) {
    if ((int) ($row['id'] ?? 0) > $ultimaId) {
        $out[] = [
            'id' => (int) $row['id'],
            'de_usuario_id' => $row['de_usuario_id'],
            'de_usuario_nome' => $row['de_usuario_nome'],
            'mensagem' => $row['mensagem'],
            'data_envio' => $row['data_envio'],
        ];
    }
}
echo json_encode($out);
