<?php
// api_alterar_password_chefe.php
// API para alterar password do chefe de oficina (primeiro login)
header('Content-Type: application/json');
session_start();
require 'ligar.php';

// Verificar se está logado
if (!isset($_SESSION['chefe_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']);
    exit();
}

// Receber dados do JSON
$input = json_decode(file_get_contents('php://input'), true);

$passwordAtual = $input['passwordAtual'] ?? '';
$novaSenha = $input['novaSenha'] ?? '';
$confirmarSenha = $input['confirmarSenha'] ?? '';

// Validações
if (empty($passwordAtual) || empty($novaSenha) || empty($confirmarSenha)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Por favor preencha todos os campos']);
    exit();
}

if ($novaSenha !== $confirmarSenha) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'As passwords não coincidem']);
    exit();
}

if (strlen($novaSenha) < 6) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'A password deve ter no mínimo 6 caracteres']);
    exit();
}

$chefe_id = $_SESSION['chefe_id'];

// Buscar password atual do chefe
$sql = "SELECT password FROM chefes_oficina WHERE id_chefe = ?";
if (!$stmt = mysqli_prepare($con, $sql)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $con->error]);
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $chefe_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $hash_atual);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// Verificar se a password atual está correta
if (!password_verify($passwordAtual, $hash_atual)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'A password atual está incorreta']);
    exit();
}

// Hash da nova password
$novo_hash = password_hash($novaSenha, PASSWORD_BCRYPT);

// Atualizar password e marcar como primeiro login concluído
$sql_update = "UPDATE chefes_oficina SET password = ?, primeiro_login = 0 WHERE id_chefe = ?";
if (!$stmt_update = mysqli_prepare($con, $sql_update)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $con->error]);
    exit();
}

mysqli_stmt_bind_param($stmt_update, "si", $novo_hash, $chefe_id);

if (mysqli_stmt_execute($stmt_update)) {
    mysqli_stmt_close($stmt_update);
    
    // Atualizar a sessão
    $_SESSION['primeiro_login'] = false;
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Password alterada com sucesso!'
    ]);
    exit();
} else {
    mysqli_stmt_close($stmt_update);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao atualizar a password: ' . mysqli_error($con)
    ]);
    exit();
}
?>
