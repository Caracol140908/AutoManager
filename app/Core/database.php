<?php
/**
 * Conexao MySQL central. Substitui o antigo "ligar.php".
 * Disponibiliza a variavel $con globalmente para compatibilidade.
 */

if (!isset($con) || !$con instanceof mysqli) {
    $dbConfig = require dirname(__DIR__, 2) . '/config/database.php';

    $con = @mysqli_connect(
        $dbConfig['host'],
        $dbConfig['user'],
        $dbConfig['password']
    );

    if (!$con) {
        error_log('Erro de conexao a BD: ' . mysqli_connect_error());

        if (php_sapi_name() !== 'cli') {
            die('ERRO DE LIGACAO A BASE DE DADOS: Por favor, contacte o administrador.');
        }
    } else {
        $dbName = $dbConfig['database'];
        $dbEscaped = '`' . str_replace('`', '``', $dbName) . '`';

        if (!mysqli_select_db($con, $dbName)) {
            mysqli_query($con, "CREATE DATABASE IF NOT EXISTS {$dbEscaped} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            mysqli_select_db($con, $dbName);
        }

        mysqli_set_charset($con, $dbConfig['charset'] ?? 'utf8mb4');
    }
}
