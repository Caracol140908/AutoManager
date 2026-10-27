<?php
session_start();
require_once('ligar.php');
require_once __DIR__ . '/inc_admin_notificacoes.php';

header('Content-Type: application/json');

$response = [
    'expired' => false,
    'tempo_restante' => 0,
    'mostrar_popup' => false,
];

if (isset($_SESSION['temp_allowed']) && $_SESSION['temp_allowed'] === true && !isset($_SESSION['user'])) {
    $tempo_restante = $_SESSION['tempo_restante'] ?? 0;

    if ($tempo_restante <= 0) {
        $response['expired'] = true;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!am_avaliacao_temp_ja_feita($con, null, $ip_address)) {
            $response['mostrar_popup'] = true;
            $_SESSION['temp_expired'] = true;
        }
    } else {
        $response['tempo_restante'] = $tempo_restante;
        $_SESSION['tempo_restante'] = $tempo_restante - 1;
    }
} elseif (isset($_SESSION['user']) && !empty($_SESSION['user']['conta_temporaria'])) {
    $criado_em = strtotime($_SESSION['user']['created_at'] ?? 'now');
    $tempo_restante = 120 - (time() - $criado_em);

    if ($tempo_restante <= 0) {
        $response['expired'] = true;
        $email = (string) ($_SESSION['user']['email'] ?? '');
        if ($email !== '' && !am_avaliacao_temp_ja_feita($con, $email, null)) {
            $response['mostrar_popup'] = true;
            $_SESSION['temp_expired'] = true;
        }
    } else {
        $response['tempo_restante'] = (int) ceil($tempo_restante);
    }
} else {
    $response['expired'] = true;
}

echo json_encode($response);
