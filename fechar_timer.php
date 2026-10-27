<?php
session_start();

// Guardar que o utilizador fechou o timer
$_SESSION['timer_fechado'] = true;

// Responder com sucesso
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>
