<?php
// reset_password.php
require 'ligar.php';

// Criar um hash para a password "password"
$password = "password";
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>Reset de Password</h2>";
echo "Password original: " . $password . "<br>";
echo "Hash gerado: " . $hash . "<br><br>";

// Inserir ou atualizar o chefe
$email = "chefe@teste.com";
$nome = "Chefe Teste";

// Verificar se o chefe já existe
$sql = "SELECT id_chefe FROM chefes_oficina WHERE email = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Atualizar password
    $sql = "UPDATE chefes_oficina SET password = ?, primeiro_login = 1 WHERE email = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ss", $hash, $email);
    if ($stmt->execute()) {
        echo "[OK] Password atualizada para: " . $password . "<br>";
    } else {
        echo "[ERRO] Erro ao atualizar: " . $con->error;
    }
} else {
    // Inserir novo chefe
    $sql = "INSERT INTO chefes_oficina (nome_chefe, email, password, primeiro_login, ativo) 
            VALUES (?, ?, ?, 1, 1)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("sss", $nome, $email, $hash);
    if ($stmt->execute()) {
        echo "[OK] Chefe criado com password: " . $password . "<br>";
        echo "ID: " . $con->insert_id;
    } else {
        echo "[ERRO] Erro ao criar: " . $con->error;
    }
}

// Verificar se a password verifica
if (password_verify($password, $hash)) {
    echo "<br>[OK] Password verifica corretamente!";
} else {
    echo "<br>[ERRO] Password NÃO verifica!";
}
?>