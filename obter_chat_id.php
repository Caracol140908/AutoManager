<?php
require 'ligar.php';
require_once __DIR__ . '/inc_configuracoes_kv.php';

header('Content-Type: application/json');
$token = am_cfg_get($con, 'telegram_bot_token', '');
echo json_encode(['bot_token' => $token !== '' ? $token : null]);
