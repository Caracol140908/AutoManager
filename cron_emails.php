<?php
// cron_emails.php — marketing via configuracoes (sem tabela emails_marketing)
require_once 'ligar.php';
require_once __DIR__ . '/inc_configuracoes_kv.php';

$raw = am_cfg_get($con, 'emails_marketing_fila', '[]');
$fila = json_decode($raw, true);
if (!is_array($fila) || $fila === []) {
    exit(0);
}

$optouts = json_decode(am_cfg_get($con, 'emails_marketing_optout', '{}'), true);
if (!is_array($optouts)) {
    $optouts = [];
}

$restante = [];
$enviados = 0;
foreach ($fila as $item) {
    if (!is_array($item) || empty($item['email'])) {
        continue;
    }
    $token = (string) ($item['token_optout'] ?? md5((string) $item['email']));
    if (isset($optouts[$token])) {
        continue;
    }
    $total = (int) ($item['total_envios'] ?? 0);
    if ($total >= 5) {
        continue;
    }
    $nome = (string) ($item['nome'] ?? 'Visitante');
    $email = (string) $item['email'];
    $assunto = 'AutoManager — novidades para si';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $body = "<p>Olá, {$nome}!</p><p>Experimente o AutoManager em <a href=\"http://{$host}/registar.php\">criar conta grátis</a>.</p>";
    $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: AutoManager <noreply@automanager.local>\r\n";
    if (@mail($email, $assunto, $body, $headers)) {
        $item['total_envios'] = $total + 1;
        $item['ultimo_envio'] = date('Y-m-d H:i:s');
        $enviados++;
    }
    if ((int) ($item['total_envios'] ?? 0) < 5) {
        $restante[] = $item;
    }
}

am_cfg_set($con, 'emails_marketing_fila', json_encode($restante, JSON_UNESCAPED_UNICODE));
error_log("cron_emails: enviados {$enviados}");
