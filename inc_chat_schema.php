<?php

/**
 * Chat usa admin_notificacoes (tabela permitida).
 */
if (!function_exists('am_ensure_chat_mensagens')) {
    function am_ensure_chat_mensagens(mysqli $con): void
    {
        require_once __DIR__ . '/inc_admin_notificacoes.php';
        am_notif_ensure_schema($con);
    }
}
