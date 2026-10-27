<?php
session_start();
require 'ligar.php';

echo "<h2>TESTE DE ESTRUTURA DA BD</h2>";
echo "<hr>";

// Testar tabela mecanicos
echo "<h3>[LISTA] TABELA: mecanicos</h3>";
$sql = "DESCRIBE mecanicos";
if ($result = mysqli_query($con, $sql)) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Key'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>[ERRO] Erro: " . mysqli_error($con) . "</p>";
}

echo "<br><br>";

// Contar registos em mecanicos
echo "<h3>[RELATORIO] DADOS: mecanicos</h3>";
$sql = "SELECT COUNT(*) as total, COUNT(CASE WHEN ativo = 1 THEN 1 END) as ativos FROM mecanicos";
if ($result = mysqli_query($con, $sql)) {
    $row = mysqli_fetch_assoc($result);
    echo "<p>Total de mecânicos: <strong>" . $row['total'] . "</strong></p>";
    echo "<p>Mecânicos ativos (ativo=1): <strong>" . $row['ativos'] . "</strong></p>";
} else {
    echo "<p style='color: red;'>[ERRO] Erro: " . mysqli_error($con) . "</p>";
}

// Listar primeiros 3 mecânicos
echo "<h4>Primeiros 3 mecânicos:</h4>";
$sql = "SELECT id_mecanico, nome, ativo FROM mecanicos LIMIT 3";
if ($result = mysqli_query($con, $sql)) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Ativo</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id_mecanico'] . "</td>";
        echo "<td>" . $row['nome'] . "</td>";
        echo "<td>" . $row['ativo'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>[ERRO] Erro: " . mysqli_error($con) . "</p>";
}

echo "<br><br>";

// Testar tabela reparacoes
echo "<h3>[LISTA] TABELA: reparacoes</h3>";
$sql = "DESCRIBE reparacoes";
if ($result = mysqli_query($con, $sql)) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Key'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>[ERRO] Erro (tabela pode não existir): " . mysqli_error($con) . "</p>";
}

echo "<br><br>";

// Contar registos em reparacoes
echo "<h3>[RELATORIO] DADOS: reparacoes</h3>";
$sql = "SELECT COUNT(*) as total FROM reparacoes";
if ($result = mysqli_query($con, $sql)) {
    $row = mysqli_fetch_assoc($result);
    echo "<p>Total de reparações: <strong>" . $row['total'] . "</strong></p>";
} else {
    echo "<p style='color: orange;'>[AVISO] Aviso: " . mysqli_error($con) . "</p>";
}

echo "<br><br>";

// Listar todas as tabelas
echo "<h3> TODAS AS TABELAS</h3>";
$sql = "SHOW TABLES";
if ($result = mysqli_query($con, $sql)) {
    $tabelas = [];
    while ($row = mysqli_fetch_row($result)) {
        $tabelas[] = $row[0];
    }
    echo "<p>Tabelas encontradas: " . count($tabelas) . "</p>";
    echo "<ul>";
    foreach ($tabelas as $tabela) {
        echo "<li><strong>$tabela</strong></li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>[ERRO] Erro: " . mysqli_error($con) . "</p>";
}

mysqli_close($con);
?>
