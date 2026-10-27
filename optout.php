<?php
// Opt-out de marketing — usa tabela configuracoes
require_once 'ligar.php';
require_once __DIR__ . '/inc_configuracoes_kv.php';

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    die('Token inválido.');
}

$raw = am_cfg_get($con, 'emails_marketing_optout', '{}');
$list = json_decode($raw, true);
if (!is_array($list)) {
    $list = [];
}
$list[$token] = date('Y-m-d H:i:s');
am_cfg_set($con, 'emails_marketing_optout', json_encode($list, JSON_UNESCAPED_UNICODE));

echo '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Opt-out</title></head><body>';
echo '<h1>Preferência registada</h1><p>Deixará de receber emails de marketing.</p>';
echo '</body></html>';
