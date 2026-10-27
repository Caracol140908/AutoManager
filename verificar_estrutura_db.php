<?php

session_start();
require 'ligar.php';

$erros = [];
$sucessos = [];

$dbConfig = require __DIR__ . '/config/database.php';
if (!$con->select_db($dbConfig['database'])) {
    die('Erro ao selecionar base de dados');
}

$result = $con->query("DESCRIBE servico");
$campos_existentes = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $campos_existentes[] = $row['Field'];
    }
}

echo "<h2>Campos encontrados na tabela servico:</h2>";
echo "<pre>" . print_r($campos_existentes, true) . "</pre>";

if (!in_array('notas_mecanico', $campos_existentes)) {
    echo "<p>Campo 'notas_mecanico' NÃO encontrado. Criando...</p>";
    $sql = "ALTER TABLE servico ADD COLUMN notas_mecanico LONGTEXT NULL DEFAULT NULL";
    if ($con->query($sql)) {
        echo "<p style='color: green;'>[OK] Campo 'notas_mecanico' criado com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>[ERRO] Erro ao criar campo: " . $con->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>[OK] Campo 'notas_mecanico' já existe!</p>";
}

if (!in_array('estado_do_trabalho', $campos_existentes)) {
    echo "<p style='color: red;'>[ERRO] Campo 'estado_do_trabalho' NÃO encontrado!</p>";
} else {
    echo "<p style='color: green;'>[OK] Campo 'estado_do_trabalho' encontrado!</p>";
}

?>
