<?php

declare(strict_types=1);

require_once __DIR__ . '/inc_admin_notificacoes.php';

function chat_is_admin_session(): bool
{
    return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true
        && !empty($_SESSION['user_id']);
}

function chat_user_id_from_session(): ?int
{
    if (!empty($_SESSION['user']['id_utilizador'])) {
        return (int) $_SESSION['user']['id_utilizador'];
    }
    return null;
}

function chat_ensure_schema(mysqli $con): void
{
    am_notif_ensure_schema($con);
}

/** @return array<int,array<string,mixed>> */
function chat_fetch_all_messages(mysqli $con): array
{
    am_notif_ensure_schema($con);
    $meta = am_notif_meta($con);
    $dateCol = $meta['date_col'];
    $stmt = $con->prepare("SELECT * FROM admin_notificacoes WHERE tipo IN ('chat_user','chat_admin') ORDER BY `$dateCol` ASC");
    if (!$stmt) {
        return [];
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $out = [];
    foreach ($rows ?: [] as $row) {
        $out[] = am_notif_row_to_chat($row, $meta);
    }
    return $out;
}

function chat_insert_user_message(mysqli $con, int $userId, string $userName, string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return ['ok' => false, 'error' => 'Mensagem vazia'];
    }
    $id = am_notif_insert($con, [
        'tipo' => 'chat_user',
        'id_utilizador' => $userId,
        'remetente_nome' => $userName,
        'mensagem' => $text,
        'estado' => 'ativo',
        'lida' => 0,
        'payload' => [
            'para_usuario_id' => 0,
            'lida_admin' => 0,
            'lida_usuario' => 1,
        ],
    ]);
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Não foi possível enviar'];
    }
    return ['ok' => true, 'id' => $id];
}

function chat_insert_admin_message(mysqli $con, int $paraUserId, string $text): array
{
    $text = trim($text);
    if ($text === '' || $paraUserId <= 0) {
        return ['ok' => false, 'error' => 'Dados inválidos'];
    }
    $id = am_notif_insert($con, [
        'tipo' => 'chat_admin',
        'id_utilizador' => $paraUserId,
        'id_referencia' => $paraUserId,
        'remetente_nome' => 'Administrador',
        'mensagem' => $text,
        'estado' => 'ativo',
        'lida' => 1,
        'payload' => [
            'para_usuario_id' => $paraUserId,
            'lida_admin' => 1,
            'lida_usuario' => 0,
        ],
    ]);
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Não foi possível enviar'];
    }
    chat_mark_read_admin_thread($con, $paraUserId);
    return ['ok' => true, 'id' => $id];
}

function chat_mark_read_admin_thread(mysqli $con, int $clientUserId): void
{
    foreach (chat_fetch_all_messages($con) as $msg) {
        if ((int) ($msg['de_usuario_id'] ?? 0) === $clientUserId
            && ($msg['de_usuario_nome'] ?? '') !== 'Administrador'
            && (int) ($msg['lida_admin'] ?? 0) === 0) {
            am_notif_update_payload($con, (int) $msg['id'], ['lida_admin' => 1], ['lida' => 1]);
        }
    }
}

function chat_mark_read_user_thread(mysqli $con, int $userId): void
{
    foreach (chat_fetch_all_messages($con) as $msg) {
        if (($msg['de_usuario_nome'] ?? '') === 'Administrador'
            && (int) ($msg['para_usuario_id'] ?? 0) === $userId
            && (int) ($msg['lida_usuario'] ?? 0) === 0) {
            am_notif_update_payload($con, (int) $msg['id'], ['lida_usuario' => 1]);
        }
    }
}

function chat_mark_admin_message_read(mysqli $con, int $messageId): void
{
    am_notif_update_payload($con, $messageId, ['lida_admin' => 1], ['lida' => 1]);
}

function chat_fetch_thread(mysqli $con, int $threadUserId, int $sinceId = 0): array
{
    $all = chat_fetch_all_messages($con);
    $out = [];
    foreach ($all as $msg) {
        $uid = (int) ($msg['de_usuario_id'] ?? 0);
        $para = (int) ($msg['para_usuario_id'] ?? 0);
        $isUser = ($msg['de_usuario_nome'] ?? '') !== 'Administrador' && $uid === $threadUserId;
        $isAdmin = ($msg['de_usuario_nome'] ?? '') === 'Administrador' && $para === $threadUserId;
        if (!$isUser && !$isAdmin) {
            continue;
        }
        if ($sinceId > 0 && (int) ($msg['id'] ?? 0) <= $sinceId) {
            continue;
        }
        $out[] = $msg;
        if (count($out) >= 200) {
            break;
        }
    }
    return $out;
}

function chat_unread_admin_total(mysqli $con): int
{
    $n = 0;
    foreach (chat_fetch_all_messages($con) as $msg) {
        if (($msg['de_usuario_nome'] ?? '') !== 'Administrador' && (int) ($msg['lida_admin'] ?? 0) === 0) {
            $n++;
        }
    }
    return $n;
}

function chat_unread_user_total(mysqli $con, int $userId): int
{
    $n = 0;
    foreach (chat_fetch_all_messages($con) as $msg) {
        if (($msg['de_usuario_nome'] ?? '') === 'Administrador'
            && (int) ($msg['para_usuario_id'] ?? 0) === $userId
            && (int) ($msg['lida_usuario'] ?? 0) === 0) {
            $n++;
        }
    }
    return $n;
}

function chat_list_conversations(mysqli $con): array
{
    $threads = [];
    foreach (chat_fetch_all_messages($con) as $msg) {
        $threadId = 0;
        if (($msg['de_usuario_nome'] ?? '') !== 'Administrador') {
            $threadId = (int) ($msg['de_usuario_id'] ?? 0);
        } else {
            $threadId = (int) ($msg['para_usuario_id'] ?? 0);
        }
        if ($threadId <= 0) {
            continue;
        }
        if (!isset($threads[$threadId])) {
            $threads[$threadId] = [
                'id_utilizador' => $threadId,
                'ultima_mensagem' => $msg['mensagem'],
                'ultima_data' => $msg['data_envio'],
                'nao_lidas' => 0,
            ];
        } else {
            $threads[$threadId]['ultima_mensagem'] = $msg['mensagem'];
            $threads[$threadId]['ultima_data'] = $msg['data_envio'];
        }
    }
    foreach ($threads as $tid => &$t) {
        $stmt = $con->prepare('SELECT nome_utilizador, email FROM utilizador WHERE id_utilizador = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $tid);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $t['nome_utilizador'] = $u['nome_utilizador'] ?? 'Cliente';
            $t['email'] = $u['email'] ?? '';
        }
        $t['nao_lidas'] = 0;
        foreach (chat_fetch_all_messages($con) as $msg) {
            if ((int) ($msg['de_usuario_id'] ?? 0) === $tid
                && ($msg['de_usuario_nome'] ?? '') !== 'Administrador'
                && (int) ($msg['lida_admin'] ?? 0) === 0) {
                $t['nao_lidas']++;
            }
        }
    }
    unset($t);
    uasort($threads, static function ($a, $b) {
        return strcmp((string) ($b['ultima_data'] ?? ''), (string) ($a['ultima_data'] ?? ''));
    });
    return $threads;
}

function chat_format_messages_for_json(array $rows, int $threadUserId, string $viewer): array
{
    $out = [];
    foreach ($rows as $row) {
        $isAdmin = ($row['de_usuario_nome'] ?? '') === 'Administrador';
        $out[] = [
            'id' => (int) $row['id'],
            'texto' => $row['mensagem'],
            'remetente' => $isAdmin ? 'admin' : 'user',
            'nome' => $isAdmin ? 'Suporte AutoManager' : ($row['de_usuario_nome'] ?? 'Cliente'),
            'hora' => $row['data_envio'] ? date('H:i', strtotime($row['data_envio'])) : '',
            'data' => $row['data_envio'] ? date('d/m/Y', strtotime($row['data_envio'])) : '',
            'lida_admin' => (int) ($row['lida_admin'] ?? 0),
            'lida_usuario' => (int) ($row['lida_usuario'] ?? 0),
        ];
    }
    return $out;
}

function chat_count_user_messages(mysqli $con, int $userId): int
{
    $n = 0;
    foreach (chat_fetch_all_messages($con) as $msg) {
        if ((int) ($msg['de_usuario_id'] ?? 0) === $userId
            && ($msg['de_usuario_nome'] ?? '') !== 'Administrador') {
            $n++;
        }
    }
    return $n;
}

function chat_latest_user_messages(mysqli $con, int $limit = 5): array
{
    $all = chat_fetch_all_messages($con);
    $userMsgs = [];
    foreach ($all as $msg) {
        if (($msg['de_usuario_nome'] ?? '') !== 'Administrador') {
            $userMsgs[] = $msg;
        }
    }
    usort($userMsgs, static function ($a, $b) {
        return strcmp((string) ($b['data_envio'] ?? ''), (string) ($a['data_envio'] ?? ''));
    });
    return array_slice($userMsgs, 0, $limit);
}

function chat_delete_older_than(mysqli $con, string $dateLimit): int
{
    $deleted = 0;
    $meta = am_notif_meta($con);
    $idCol = $meta['id_col'];
    $dateCol = $meta['date_col'];
    foreach (chat_fetch_all_messages($con) as $msg) {
        $when = (string) ($msg['data_envio'] ?? '');
        if ($when !== '' && $when < $dateLimit) {
            if (am_notif_delete($con, (int) $msg['id'])) {
                $deleted++;
            }
        }
    }
    return $deleted;
}
