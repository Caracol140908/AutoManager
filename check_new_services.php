<?php
session_start();
require 'ligar.php';

// Apenas para chefes de oficina
if (!isset($_SESSION['chefe_id']) || !isset($_SESSION['is_chefe'])) {
    exit(json_encode(['new' => 0]));
}

$result = $con->query("SELECT COUNT(*) as total FROM servico 
    WHERE mecanico_id IS NULL 
    AND estado_do_trabalho IN ('Por Fazer', 'Aguardar Preparação')
    AND data_registo >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

$count = $result->fetch_assoc()['total'];

header('Content-Type: application/json');
echo json_encode(['new' => $count]);
?>