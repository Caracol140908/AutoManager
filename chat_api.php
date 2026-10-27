<?php
session_start();
require __DIR__ . '/ligar.php';
require_once __DIR__ . '/chat_lib.php';

header('Content-Type: application/json; charset=utf-8');

chat_ensure_schema($con);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function chat_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$isAdmin = chat_is_admin_session();
$userId = chat_user_id_from_session();

if ($action === 'unread_count') {
    if ($isAdmin) {
        chat_json(['total' => chat_unread_admin_total($con)]);
    }
    if ($userId) {
        chat_json(['total' => chat_unread_user_total($con, $userId)]);
    }
    chat_json(['total' => 0], 401);
}

if ($action === 'conversations') {
    if (!$isAdmin) {
        chat_json(['error' => 'Não autorizado'], 401);
    }
    $list = chat_list_conversations($con);
    $items = [];
    foreach ($list as $u) {
        $preview = $u['ultima_mensagem'] ?? '';
        if (mb_strlen($preview) > 80) {
            $preview = mb_substr($preview, 0, 80) . '…';
        }
        $items[] = [
            'id' => (int) $u['id_utilizador'],
            'nome' => $u['nome_utilizador'],
            'email' => $u['email'],
            'nao_lidas' => (int) ($u['nao_lidas'] ?? 0),
            'preview' => $preview,
            'ultima_data' => $u['ultima_data'] ? date('d/m H:i', strtotime($u['ultima_data'])) : '',
        ];
    }
    chat_json(['conversations' => $items, 'total_nao_lidas' => chat_unread_admin_total($con)]);
}

if ($action === 'messages') {
    $threadId = (int) ($_GET['thread_id'] ?? $_GET['usuario_id'] ?? 0);
    $sinceId = (int) ($_GET['since_id'] ?? $_GET['ultima_id'] ?? 0);

    if ($isAdmin) {
        if ($threadId <= 0) {
            chat_json(['error' => 'Conversa inválida'], 400);
        }
        if ($sinceId === 0) {
            chat_mark_read_admin_thread($con, $threadId);
        }
    } elseif ($userId) {
        $threadId = $userId;
        if ($sinceId === 0) {
            chat_mark_read_user_thread($con, $userId);
        }
    } else {
        chat_json(['error' => 'Não autenticado'], 401);
    }

    $rows = chat_fetch_thread($con, $threadId, $sinceId);
    $viewer = $isAdmin ? 'admin' : 'user';
    chat_json([
        'messages' => chat_format_messages_for_json($rows, $threadId, $viewer),
        'thread_id' => $threadId,
    ]);
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim((string) ($_POST['mensagem'] ?? ''));

    if ($isAdmin) {
        $paraId = (int) ($_POST['para_usuario_id'] ?? $_POST['usuario_id'] ?? 0);
        $result = chat_insert_admin_message($con, $paraId, $text);
        if (!$result['ok']) {
            chat_json(['error' => $result['error']], 400);
        }
        chat_json(['success' => true, 'id' => $result['id']]);
    }

    if ($userId) {
        $name = $_SESSION['user']['nome_utilizador'] ?? 'Cliente';
        $result = chat_insert_user_message($con, $userId, $name, $text);
        if (!$result['ok']) {
            chat_json(['error' => $result['error']], 400);
        }
        @include_once __DIR__ . '/config_telegram.php';
        if (isset($telegramBot) && method_exists($telegramBot, 'enviarNotificacaoChat')) {
            $linkChat = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/chat_admin.php?usuario=' . $userId;
            $telegramBot->enviarNotificacaoChat($name, $text, $linkChat);
        }
        chat_json(['success' => true, 'id' => $result['id']]);
    }

    chat_json(['error' => 'Não autenticado'], 401);
}

if ($action === 'mark_read') {
    if ($isAdmin) {
        $msgId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $threadId = (int) ($_GET['thread_id'] ?? $_POST['thread_id'] ?? 0);
        if ($msgId > 0) {
            chat_mark_admin_message_read($con, $msgId);
        }
        if ($threadId > 0) {
            chat_mark_read_admin_thread($con, $threadId);
        }
        chat_json(['success' => true]);
    }
    if ($userId) {
        chat_mark_read_user_thread($con, $userId);
        chat_json(['success' => true]);
    }
    chat_json(['error' => 'Não autorizado'], 401);
}

chat_json(['error' => 'Ação inválida'], 400);
