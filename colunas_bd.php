<?php
require 'ligar.php';

echo "<h1> COLUNAS EXATAS DA BD</h1>";
echo "<hr>";

// MECANICOS
echo "<h2>MECANICOS - COLUNAS</h2>";
$result = mysqli_query($con, "DESCRIBE mecanicos");
if ($result) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333;'><th>COLUNA</th><th>TIPO</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td><strong>{$row['Field']}</strong></td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
}

echo "<h2>MECANICOS - PRIMEIROS 3 REGISTOS</h2>";
$result = mysqli_query($con, "SELECT * FROM mecanicos LIMIT 3");
if ($result && mysqli_num_rows($result) > 0) {
    $campos = mysqli_fetch_fields($result);
    echo "<table border='1' cellpadding='10'>";
    echo "<tr style='background: #444;'>";
    foreach ($campos as $campo) {
        echo "<th>{$campo->name}</th>";
    }
    echo "</tr>";
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        foreach ($row as $valor) {
            echo "<td>" . htmlspecialchars($valor ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

// SERVICOS
echo "<h2>SERVICOS - COLUNAS</h2>";
$result = mysqli_query($con, "DESCRIBE servicos");
if ($result) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333;'><th>COLUNA</th><th>TIPO</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td><strong>{$row['Field']}</strong></td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
}

// REPARACOES
echo "<h2>REPARACOES - COLUNAS</h2>";
$result = mysqli_query($con, "DESCRIBE reparacoes");
if ($result) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333;'><th>COLUNA</th><th>TIPO</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td><strong>{$row['Field']}</strong></td><td>{$row['Type']}</td></tr>";
    }
    echo "</table>";
}

mysqli_close($con);
?>
