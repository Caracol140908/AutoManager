<?php
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/automanager_schema.db');
echo 'Tabelas: ' . $pdo->query('SELECT COUNT(*) FROM tabelas')->fetchColumn() . PHP_EOL;
echo 'Colunas: ' . $pdo->query('SELECT COUNT(*) FROM colunas')->fetchColumn() . PHP_EOL;
echo 'servico: ' . $pdo->query("SELECT COUNT(*) FROM v_schema_completo WHERE tabela='servico'")->fetchColumn() . ' colunas' . PHP_EOL;
foreach ($pdo->query('SELECT chave, valor FROM info_projeto ORDER BY chave') as $r) {
    echo $r['chave'] . ': ' . $r['valor'] . PHP_EOL;
}
