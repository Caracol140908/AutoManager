<?php
session_start();
require 'ligar.php';
require_once __DIR__ . '/inc_configuracoes_kv.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

$banner = am_site_banner_get($con);
if ($banner) {
    $_SESSION['ultima_mensagem_vista'] = $banner['data_publicacao'];

    if (!empty($_SESSION['user']['id_utilizador'])) {
        $id_utilizador = (int) $_SESSION['user']['id_utilizador'];
        am_site_banner_mark_seen($con, $id_utilizador, $banner['data_publicacao']);
        $response['success'] = true;
        $response['message'] = 'Mensagem marcada como vista.';
    } else {
        $response['success'] = true;
        $response['message'] = 'Mensagem marcada como vista na sessão.';
    }
} else {
    $response['success'] = true;
    $response['message'] = 'Nenhuma mensagem de atualização encontrada.';
}

echo json_encode($response);
