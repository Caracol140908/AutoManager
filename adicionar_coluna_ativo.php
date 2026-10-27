<?php
session_start();
require 'ligar.php';

echo "<h2>[FERRAMENTAS] ADICIONANDO COLUNA 'ativo' NA TABELA 'mecanicos'</h2>";
echo "<hr>";

// 1. Verificar se coluna ativo já existe
$sql_check = "SHOW COLUMNS FROM mecanicos LIKE 'ativo'";
$result = mysqli_query($con, $sql_check);

if (mysqli_num_rows($result) > 0) {
    echo "<p style='color: green;'>[OK] Coluna 'ativo' já existe na tabela 'mecanicos'</p>";
} else {
    echo "<p style='color: orange;'>[AVISO] Coluna 'ativo' não encontrada. Adicionando...</p>";
    
    $sql_add = "ALTER TABLE mecanicos ADD COLUMN ativo TINYINT(1) DEFAULT 1 AFTER password";
    
    if (mysqli_query($con, $sql_add)) {
        echo "<p style='color: green;'>[OK] Coluna 'ativo' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>[ERRO] Erro: " . mysqli_error($con) . "</p>";
    }
}

echo "<br>";

// 2. Verificar se coluna data_despedimento existe
$sql_check = "SHOW COLUMNS FROM mecanicos LIKE 'data_despedimento'";
$result = mysqli_query($con, $sql_check);

if (mysqli_num_rows($result) > 0) {
    echo "<p style='color: green;'>[OK] Coluna 'data_despedimento' já existe</p>";
} else {
    echo "<p style='color: orange;'>[AVISO] Adicionando coluna 'data_despedimento'...</p>";
    
    $sql_add = "ALTER TABLE mecanicos ADD COLUMN data_despedimento DATE NULL AFTER ativo";
    
    if (mysqli_query($con, $sql_add)) {
        echo "<p style='color: green;'>[OK] Coluna 'data_despedimento' adicionada!</p>";
    } else {
        echo "<p style='color: red;'>[ERRO] Erro: " . mysqli_error($con) . "</p>";
    }
}

echo "<br>";

// 3. Verificar se coluna motivo_despedimento existe
$sql_check = "SHOW COLUMNS FROM mecanicos LIKE 'motivo_despedimento'";
$result = mysqli_query($con, $sql_check);

if (mysqli_num_rows($result) > 0) {
    echo "<p style='color: green;'>[OK] Coluna 'motivo_despedimento' já existe</p>";
} else {
    echo "<p style='color: orange;'>[AVISO] Adicionando coluna 'motivo_despedimento'...</p>";
    
    $sql_add = "ALTER TABLE mecanicos ADD COLUMN motivo_despedimento VARCHAR(255) NULL AFTER data_despedimento";
    
    if (mysqli_query($con, $sql_add)) {
        echo "<p style='color: green;'>[OK] Coluna 'motivo_despedimento' adicionada!</p>";
    } else {
        echo "<p style='color: red;'>[ERRO] Erro: " . mysqli_error($con) . "</p>";
    }
}

echo "<br><br>";

// 4. Mostrar estrutura atualizada
echo "<h3>[LISTA] Estrutura da tabela mecanicos:</h3>";
$sql = "DESCRIBE mecanicos";
if ($result = mysqli_query($con, $sql)) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #ff6b00; color: white;'><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Key'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><br>";

// 5. Contar dados
echo "<h3>[RELATORIO] Dados:</h3>";
$sql = "SELECT COUNT(*) as total, COUNT(CASE WHEN ativo = 1 THEN 1 END) as ativos FROM mecanicos";
if ($result = mysqli_query($con, $sql)) {
    $row = mysqli_fetch_assoc($result);
    echo "<p><strong>Total de mecânicos:</strong> " . $row['total'] . "</p>";
    echo "<p><strong>Mecânicos ativos (ativo=1):</strong> " . $row['ativos'] . "</p>";
}

echo "<br>";
echo "<a href='indexchefedeoficina.php' style='padding: 10px 20px; background: #ff6b00; color: white; text-decoration: none; border-radius: 5px;'>[VOLTAR] Voltar ao Painel</a>";

mysqli_close($con);
?>
