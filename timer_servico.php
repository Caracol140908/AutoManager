<?php
session_start();
require 'ligar.php';

header('Content-Type: application/json');

if (!isset($_SESSION['mecanico_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$mecanico_id = $_SESSION['mecanico_id'];
$servico_id = intval($_POST['servico_id'] ?? 0);
$acao = $_POST['acao'] ?? '';

if ($servico_id <= 0 || !in_array($acao, ['iniciar', 'parar'])) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    exit;
}

$stmt = $con->prepare("SELECT * FROM servico WHERE id = ? AND mecanico_id = ?");
$stmt->bind_param("ii", $servico_id, $mecanico_id);
$stmt->execute();
$result = $stmt->get_result();
$servico = $result->fetch_assoc();
$stmt->close();

if (!$servico) {
    echo json_encode(['success' => false, 'error' => 'Serviço não encontrado']);
    exit;
}

if ($acao === 'iniciar') {
    $hora_inicio = date('Y-m-d H:i:s');
    $stmt = $con->prepare("UPDATE servico SET hora_inicio_trabalho = ?, estado_do_trabalho = 'Em Andamento' WHERE id = ?");
    $stmt->bind_param("si", $hora_inicio, $servico_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'hora_inicio' => $hora_inicio]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao iniciar timer']);
    }
    
} elseif ($acao === 'parar') {
    $hora_fim = date('Y-m-d H:i:s');
    
    $inicio = new DateTime($servico['hora_inicio_trabalho']);
    $fim = new DateTime($hora_fim);
    $intervalo = $inicio->diff($fim);
    $minutos = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;
    
    $tempo_total = ($servico['tempo_trabalho_minutos'] ?? 0) + $minutos;
    
    $stmt = $con->prepare("UPDATE servico SET hora_fim_trabalho = ?, tempo_trabalho_minutos = ? WHERE id = ?");
    $stmt->bind_param("sii", $hora_fim, $tempo_total, $servico_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'hora_fim' => $hora_fim,
            'tempo_minutos' => $minutos,
            'tempo_total' => $tempo_total
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao parar timer']);
    }
}

$stmt->close();
?>