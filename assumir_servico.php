<?php
session_start();
require('ligar.php');

header('Content-Type: application/json');

if (!isset($_SESSION['mecanico_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada']);
    exit;
}

if (empty($_POST['servico_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID não especificado']);
    exit;
}

$servico_id = $_POST['servico_id'];
$mecanico_id = intval($_SESSION['mecanico_id']);

// Procurar o serviço na tabela 'servico' (aceitamos id_servico ou id)
$stmt = $con->prepare("SELECT id_servico, id, id_mecanico FROM servico WHERE id_servico = ? OR id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare falhou: ' . $con->error]);
    exit;
}
$stmt->bind_param('ss', $servico_id, $servico_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    // fallback: tentar a tabela plural (compatibilidade)
    $stmt2 = $con->prepare("SELECT id_servico, id, id_mecanico FROM servicos WHERE id_servico = ? OR id = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('ss', $servico_id, $servico_id);
        $stmt2->execute();
        $res = $stmt2->get_result();
        $stmt2->close();
    }
}

if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Serviço não encontrado']);
    exit;
}

$row = $res->fetch_assoc();
$stmt->close();

// Actualizar o serviço para assumir (usar prepared statement)
$id_field = $row['id_servico'] ?? $row['id'];
$upd = $con->prepare("UPDATE servico SET id_mecanico = ?, estado = 'Em Andamento' WHERE id_servico = ? OR id = ?");
if (!$upd) {
    // tentar tabela plural em fallback
    $upd2 = $con->prepare("UPDATE servicos SET id_mecanico = ?, estado = 'Em Andamento' WHERE id_servico = ? OR id = ?");
    if ($upd2) {
        $upd2->bind_param('iss', $mecanico_id, $id_field, $id_field);
        if ($upd2->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $upd2->error]);
        }
        $upd2->close();
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Não foi possível preparar UPDATE: ' . $con->error]);
    exit;
}

$upd->bind_param('iss', $mecanico_id, $id_field, $id_field);
if ($upd->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $upd->error]);
}
$upd->close();
?>