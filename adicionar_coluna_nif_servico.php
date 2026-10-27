<?php
require 'ligar.php';

echo "<h1>Atualização da tabela servico (NIF)</h1>";

$sql = "ALTER TABLE servico ADD COLUMN nif VARCHAR(9) NULL AFTER telefone";

if (@$con->query($sql)) {
    echo "<p style='color: green;'>[OK] Coluna 'nif' adicionada com sucesso.</p>";
} else {
    if (strpos($con->error, 'Duplicate column name') !== false) {
        echo "<p style='color: orange;'>[AVISO] A coluna 'nif' já existe.</p>";
    } else {
        echo "<p style='color: red;'>[ERRO] " . htmlspecialchars($con->error) . "</p>";
    }
}

echo "<hr><p>Concluído.</p>";
?>
