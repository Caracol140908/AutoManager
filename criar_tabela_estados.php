<?php

require 'ligar.php';

$statements = [
    "ALTER TABLE servico ADD COLUMN notas_mecanico LONGTEXT NULL DEFAULT NULL",
    "ALTER TABLE servico ADD COLUMN data_atualizacao_estado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

echo "<h1>Verificando e Atualizando Estrutura da Base de Dados</h1>";

foreach ($statements as $sql) {
    echo "<p>Executando: <code>$sql</code></p>";
    
    if (@$con->query($sql)) {
        echo "<p style='color: green;'>[OK] Executado com sucesso!</p>";
    } else {
        if (strpos($con->error, 'Duplicate column name') !== false) {
            echo "<p style='color: orange;'>[AVISO] Campo já existe (tudo bem!)</p>";
        } else if (empty($con->error)) {
            echo "<p style='color: green;'>[OK] Executado com sucesso!</p>";
        } else {
            echo "<p style='color: red;'>[ERRO] Erro: " . htmlspecialchars($con->error) . "</p>";
        }
    }
    echo "<hr>";
}

echo "<p style='color: blue;'>[OK] Processo concluído!</p>";

?>
