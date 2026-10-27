<?php
require 'ligar.php';

$sql = "CREATE TABLE IF NOT EXISTS orcamentista (
    id_orcamentista INT AUTO_INCREMENT PRIMARY KEY,
    nome_orcamentista VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo TINYINT DEFAULT 1
)";

if ($con->query($sql) === TRUE) {
    echo "[OK] Tabela 'orcamentista' criada com sucesso!";
} else {
    echo "[ERRO] Erro ao criar tabela: " . $con->error;
}

$con->close();
?>
