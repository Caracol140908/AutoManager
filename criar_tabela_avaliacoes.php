<?php
// criar_tabela_avaliacoes.php
// Script para criar a tabela avaliacoes_temp se não existir

require_once('ligar.php');

$sql = "CREATE TABLE IF NOT EXISTS avaliacoes_temp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    avaliacao INT NOT NULL CHECK(avaliacao >= 1 AND avaliacao <= 5),
    comentario TEXT,
    ip_address VARCHAR(45),
    data_avaliacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address)
)";

if ($con->query($sql) === TRUE) {
    echo "[OK] Tabela 'avaliacoes_temp' criada com sucesso!<br>";
} else {
    echo " Erro ao criar tabela: " . $con->error . "<br>";
}

$con->close();
?>
