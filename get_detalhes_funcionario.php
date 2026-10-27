<?php
// get_detalhes_funcionario.php - Obter detalhes de um funcionário
header('Content-Type: application/json');
session_start();
require 'ligar.php';

// Verificar conexão
if (!$con) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro de conexão à base de dados']);
    exit();
}

// Verificar se é chefe
if (!isset($_SESSION['chefe_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit();
}

$id = $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID não fornecido']);
    exit();
}

// Tentar buscar como mecânico primeiro
$sql = "SELECT id_mecanico as id, nome_mecanico as nome, especialidade, email, contacto, data_contratacao, 'mecanico' as tipo 
        FROM mecanicos 
        WHERE id_mecanico = ? AND ativo = 1";

$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na consulta']);
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'sucesso' => true,
        'id' => $row['id'],
        'nome' => htmlspecialchars($row['nome']),
        'tipo' => $row['tipo'],
        'especialidade' => htmlspecialchars($row['especialidade'] ?? ''),
        'email' => htmlspecialchars($row['email']),
        'contacto' => htmlspecialchars($row['contacto'] ?? ''),
        'data_contratacao' => $row['data_contratacao'] ?? 'N/A'
    ]);
    mysqli_stmt_close($stmt);
    exit();
}

mysqli_stmt_close($stmt);

echo json_encode(['sucesso' => false, 'mensagem' => 'Funcionário não encontrado']);
?>
