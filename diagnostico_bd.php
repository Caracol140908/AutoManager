<?php
/**
 *  DIAGNÓSTICO COMPLETO - ENCONTRAR ESTRUTURA REAL
 * Mostra EXATAMENTE o que existe na BD
 */

require 'ligar.php';

echo "<h1> DIAGNÓSTICO - ESTRUTURA REAL DA BD</h1>";
echo "<hr>";

// ============================================
// 1. LISTAR TODAS AS TABELAS
// ============================================
echo "<h2>[LISTA] TODAS AS TABELAS</h2>";
$result = mysqli_query($con, "SHOW TABLES");
$tabelas = [];
while ($row = mysqli_fetch_array($result)) {
    $tabelas[] = $row[0];
    echo "<p>[OK] {$row[0]}</p>";
}

// ============================================
// 2. ESTRUTURA DE MECANICOS
// ============================================
echo "<h2>[RELATORIO] TABELA: MECANICOS (ESTRUTURA REAL)</h2>";
if (in_array('mecanicos', $tabelas)) {
    $result = mysqli_query($con, "DESCRIBE mecanicos");
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333;'><th>COLUNA</th><th>TIPO</th><th>NULL</th><th>CHAVE</th><th>DEFAULT</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Dados
    echo "<h3>[INSERIR] Dados em MECANICOS:</h3>";
    $result = mysqli_query($con, "SELECT * FROM mecanicos LIMIT 5");
    echo "<table border='1' cellpadding='10'>";
    if (mysqli_num_rows($result) > 0) {
        $campos = mysqli_fetch_fields($result);
        echo "<tr style='background: #444;'>";
        foreach ($campos as $campo) {
            echo "<th>{$campo->name}</th>";
        }
        echo "</tr>";
        mysqli_data_seek($result, 0);
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            foreach ($row as $valor) {
                echo "<td>$valor</td>";
            }
            echo "</tr>";
        }
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>[ERRO] Tabela NÃO existe!</p>";
}

// ============================================
// 3. ESTRUTURA DE SERVICOS
// ============================================
echo "<h2>[RELATORIO] TABELA: SERVICOS (ESTRUTURA REAL)</h2>";
if (in_array('servicos', $tabelas)) {
    $result = mysqli_query($con, "DESCRIBE servicos");
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333;'><th>COLUNA</th><th>TIPO</th><th>NULL</th><th>CHAVE</th><th>DEFAULT</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Dados
    echo "<h3>[INSERIR] Dados em SERVICOS:</h3>";
    $result = mysqli_query($con, "SELECT * FROM servicos LIMIT 5");
    echo "<table border='1' cellpadding='10'>";
    if (mysqli_num_rows($result) > 0) {
        $campos = mysqli_fetch_fields($result);
        echo "<tr style='background: #444;'>";
        foreach ($campos as $campo) {
            echo "<th>{$campo->name}</th>";
        }
        echo "</tr>";
        mysqli_data_seek($result, 0);
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            foreach ($row as $valor) {
                echo "<td>" . substr($valor, 0, 50) . "</td>";
            }
            echo "</tr>";
        }
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>[ERRO] Tabela NÃO existe!</p>";
}

// ============================================
// 4. ESTRUTURA DE REPARACOES
// ============================================
echo "<h2>[RELATORIO] TABELA: REPARACOES (ESTRUTURA REAL)</h2>";
if (in_array('reparacoes', $tabelas)) {
    $result = mysqli_query($con, "DESCRIBE reparacoes");
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #333;'><th>COLUNA</th><th>TIPO</th><th>NULL</th><th>CHAVE</th><th>DEFAULT</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>[ERRO] Tabela NÃO existe!</p>";
}

mysqli_close($con);
?>
