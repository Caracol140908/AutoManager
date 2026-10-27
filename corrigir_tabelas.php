<?php
/**
 * Script para corrigir a estrutura das tabelas mecanicos e orcamentista
 * Adiciona colunas faltantes e ajusta tipos de dados conforme necessário
 */

require 'ligar.php';

echo "<!DOCTYPE html>
<html lang='pt'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link rel='stylesheet' href='theme.css'>
    <title>Corrigir Tabelas - AUTOMANAGER</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0d1117; color: #c9d1d9; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; }
        h1 { color: #ff6b00; margin-bottom: 10px; }
        .section { background: #161b22; padding: 20px; margin-bottom: 20px; border-radius: 10px; border: 1px solid #30363d; }
        h2 { color: #58a6ff; margin-bottom: 15px; font-size: 18px; }
        .log { background: #0d1117; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #ff6b00; font-family: monospace; font-size: 12px; line-height: 1.6; }
        .success { border-left-color: #3fb950; color: #3fb950; }
        .warning { border-left-color: #ffb41a; color: #ffb41a; }
        .error { border-left-color: #f85149; color: #f85149; }
        .info { border-left-color: #58a6ff; color: #58a6ff; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #30363d; }
        th { background: #0d1117; color: #58a6ff; font-weight: 600; }
        button { background: #ff6b00; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin: 5px; font-size: 14px; }
        button:hover { background: #e55a00; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>[FERRAMENTAS] Corrigir Estrutura das Tabelas</h1>
            <p>Script para ajustar as tabelas mecanicos e orcamentista</p>
        </div>
";

// ============= VERIFICAR E CORRIGIR TABELA MECANICOS =============
echo "<div class='section'>
    <h2>[RELATORIO] Tabela: MECANICOS</h2>";

$result = $con->query("DESCRIBE mecanicos");
if ($result) {
    echo "<div class='log info'>Estrutura atual da tabela mecanicos:</div>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . ($row['Null'] === 'YES' ? 'Sim' : 'Não') . "</td>";
        echo "<td>" . ($row['Default'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Verificar e adicionar colunas faltantes em mecanicos
$campos_necessarios_mec = [
    'morada' => "VARCHAR(200)",
    'data_contratacao' => "DATE",
    'nif' => "VARCHAR(20)",
    'iban' => "VARCHAR(50)"
];

echo "<div class='log warning'>Verificando colunas faltantes...</div>";

foreach ($campos_necessarios_mec as $campo => $tipo) {
    $check = $con->query("SHOW COLUMNS FROM mecanicos LIKE '$campo'");
    if ($check && $check->num_rows == 0) {
        echo "<div class='log warning'>[ERRO] Coluna faltante: <strong>$campo</strong></div>";
        $sql_alter = "ALTER TABLE mecanicos ADD COLUMN $campo $tipo";
        if ($con->query($sql_alter)) {
            echo "<div class='log success'>[OK] Coluna <strong>$campo</strong> adicionada com sucesso!</div>";
        } else {
            echo "<div class='log error'>[ERRO] Erro ao adicionar $campo: " . $con->error . "</div>";
        }
    } else {
        echo "<div class='log success'>[OK] Coluna <strong>$campo</strong> já existe</div>";
    }
}

// Verificar se ordenado_bruto existe, senão criar
$check_salario = $con->query("SHOW COLUMNS FROM mecanicos LIKE 'ordenado_bruto'");
if ($check_salario && $check_salario->num_rows == 0) {
    echo "<div class='log warning'>[ERRO] Coluna faltante: <strong>ordenado_bruto</strong></div>";
    $sql_alter = "ALTER TABLE mecanicos ADD COLUMN ordenado_bruto DECIMAL(10,2)";
    if ($con->query($sql_alter)) {
        echo "<div class='log success'>[OK] Coluna <strong>ordenado_bruto</strong> adicionada com sucesso!</div>";
    } else {
        echo "<div class='log error'>[ERRO] Erro ao adicionar ordenado_bruto: " . $con->error . "</div>";
    }
} else {
    echo "<div class='log success'>[OK] Coluna <strong>ordenado_bruto</strong> já existe</div>";
}

// Renomear contacto para contacto se necessário
$check_contacto = $con->query("SHOW COLUMNS FROM mecanicos LIKE 'contacto'");
if (!($check_contacto && $check_contacto->num_rows > 0)) {
    $check_contact = $con->query("SHOW COLUMNS FROM mecanicos LIKE 'contact'");
    if ($check_contact && $check_contact->num_rows > 0) {
        echo "<div class='log warning'>[AVISO] Coluna <strong>contact</strong> encontrada, renomeando para contacto...</div>";
        if ($con->query("ALTER TABLE mecanicos CHANGE COLUMN contact contacto VARCHAR(20)")) {
            echo "<div class='log success'>[OK] Coluna renomeada de contact para contacto</div>";
        }
    }
}

echo "</div>";

// ============= VERIFICAR E CORRIGIR TABELA ORCAMENTISTA =============
echo "<div class='section'>
    <h2>[RELATORIO] Tabela: ORCAMENTISTA</h2>";

$result = $con->query("DESCRIBE orcamentista");
if ($result) {
    echo "<div class='log info'>Estrutura atual da tabela orcamentista:</div>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . ($row['Null'] === 'YES' ? 'Sim' : 'Não') . "</td>";
        echo "<td>" . ($row['Default'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Verificar e adicionar colunas faltantes em orcamentista
$campos_necessarios_orc = [
    'morada' => "VARCHAR(200)",
    'data_contratacao' => "DATE",
    'nif' => "VARCHAR(20)",
    'iban' => "VARCHAR(50)"
];

echo "<div class='log warning'>Verificando colunas faltantes...</div>";

foreach ($campos_necessarios_orc as $campo => $tipo) {
    $check = $con->query("SHOW COLUMNS FROM orcamentista LIKE '$campo'");
    if ($check && $check->num_rows == 0) {
        echo "<div class='log warning'>[ERRO] Coluna faltante: <strong>$campo</strong></div>";
        $sql_alter = "ALTER TABLE orcamentista ADD COLUMN $campo $tipo";
        if ($con->query($sql_alter)) {
            echo "<div class='log success'>[OK] Coluna <strong>$campo</strong> adicionada com sucesso!</div>";
        } else {
            echo "<div class='log error'>[ERRO] Erro ao adicionar $campo: " . $con->error . "</div>";
        }
    } else {
        echo "<div class='log success'>[OK] Coluna <strong>$campo</strong> já existe</div>";
    }
}

// Verificar se salario_bruto existe, senão criar
$check_salario = $con->query("SHOW COLUMNS FROM orcamentista LIKE 'salario_bruto'");
if ($check_salario && $check_salario->num_rows == 0) {
    echo "<div class='log warning'>[ERRO] Coluna faltante: <strong>salario_bruto</strong></div>";
    $sql_alter = "ALTER TABLE orcamentista ADD COLUMN salario_bruto DECIMAL(10,2)";
    if ($con->query($sql_alter)) {
        echo "<div class='log success'>[OK] Coluna <strong>salario_bruto</strong> adicionada com sucesso!</div>";
    } else {
        echo "<div class='log error'>[ERRO] Erro ao adicionar salario_bruto: " . $con->error . "</div>";
    }
} else {
    echo "<div class='log success'>[OK] Coluna <strong>salario_bruto</strong> já existe</div>";
}

echo "</div>";

// ============= RESUMO FINAL =============
echo "<div class='section'>
    <h2>[OK] Conclusão</h2>
    <div class='log success'>Script de correção executado!</div>
    <p>As tabelas foram verificadas e corrigidas conforme necessário. Agora pode criar mecânicos e orçamentistas sem problemas.</p>
    <div style='margin-top: 20px; text-align: center;'>
        <button onclick=\"window.location='indexchefedeoficina.php'\">Voltar ao Dashboard</button>
        <button onclick=\"location.reload()\">Executar Novamente</button>
    </div>
</div>";

echo "</div></body></html>";
?>
