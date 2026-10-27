<?php
require('ligar.php');
require_once __DIR__ . '/inc_configuracoes_kv.php';

function enviarNotificacaoTelegram($mensagem) {
    $con = $GLOBALS['con'];
    $bot_token = am_cfg_get($con, 'telegram_bot_token', '');
    $chat_id = am_cfg_get($con, 'telegram_chat_id_admin', '');

    if ($bot_token === '' || $chat_id === '') {
        return false;
    }

    $mensagem_encoded = urlencode($mensagem);
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage?chat_id={$chat_id}&text={$mensagem_encoded}";
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['timeout' => 5],
    ]);
    $response = @file_get_contents($url, false, $context);
    return $response !== false;
}
