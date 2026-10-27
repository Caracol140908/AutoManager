<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/chat_lib.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

chat_ensure_schema($con);

$usuarioId = (int) ($_GET['usuario_id'] ?? 0);
$ultimaId = (int) ($_GET['ultima_id'] ?? 0);
$comLista = isset($_GET['lista']) && $_GET['lista'] === '1';

$out = [
    'mensagens' => [],
    'nao_lidas_total' => chat_unread_admin_total($con),
    'usuarios' => [],
];

if ($comLista) {
    foreach (chat_list_conversations($con) as $u) {
        $out['usuarios'][] = [
            'id' => (int) $u['id_utilizador'],
            'nao_lidas' => (int) ($u['nao_lidas'] ?? 0),
        ];
    }
    $out['usuarios_count'] = count($out['usuarios']);
}

if ($usuarioId > 0) {
    chat_mark_read_admin_thread($con, $usuarioId);
    foreach (chat_fetch_thread($con, $usuarioId, $ultimaId) as $m) {
        $out['mensagens'][] = [
            'id' => (int) $m['id'],
            'de_usuario_id' => $m['de_usuario_id'],
            'de_usuario_nome' => $m['de_usuario_nome'],
            'mensagem' => $m['mensagem'],
            'data_envio' => $m['data_envio'],
        ];
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
