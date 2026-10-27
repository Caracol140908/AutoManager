<?php

$dbConfig = require __DIR__ . '/config/database.php';

$con = @mysqli_connect(
    $dbConfig['host'],
    $dbConfig['user'],
    $dbConfig['password']
);

if (!$con) {
    error_log('Erro de conexão à BD: ' . mysqli_connect_error());

    if (php_sapi_name() !== 'cli') {
        die('ERRO DE LIGAÇÃO À BASE DE DADOS: Por favor, contacte o administrador.');
    }
} else {
    $dbName = $dbConfig['database'];
    $dbEscaped = '`' . str_replace('`', '``', $dbName) . '`';

    if (!mysqli_select_db($con, $dbName)) {
        mysqli_query($con, "CREATE DATABASE IF NOT EXISTS {$dbEscaped} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if (!mysqli_select_db($con, $dbName)) {
            error_log('Erro ao selecionar/criar BD: ' . mysqli_error($con));
            if (php_sapi_name() !== 'cli') {
                die('ERRO DE LIGAÇÃO À BASE DE DADOS: Não foi possível criar/selecionar a base de dados.');
            }
        }
    }

    mysqli_set_charset($con, $dbConfig['charset'] ?? 'utf8mb4');
    require_once __DIR__ . '/inc_tabelas_bd.php';
}

if (!function_exists('am_validar_matricula')) {
    $amMatriculaInc = __DIR__ . '/inc_matricula.php';
    if (is_readable($amMatriculaInc)) {
        require_once $amMatriculaInc;
    } else {
        function am_normalizar_matricula(string $matricula): string
        {
            $limpo = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim($matricula)));
            $limpo = substr($limpo, 0, 6);
            if ($limpo === '') {
                return '';
            }
            return implode('-', [substr($limpo, 0, 2), substr($limpo, 2, 2), substr($limpo, 4, 2)]);
        }

        function am_validar_matricula(string $matricula): array
        {
            $normalizada = am_normalizar_matricula($matricula);
            $limpo = preg_replace('/[^A-Z0-9]/', '', $normalizada);
            if ($limpo === '') {
                return [false, 'Introduza a matrícula do veículo.', $normalizada];
            }
            if (strlen($limpo) !== 6) {
                return [false, 'A matrícula deve ter 6 caracteres (ex.: AA-00-AA).', $normalizada];
            }
            if (preg_match('/^(.)\1{5}$/', $limpo)) {
                return [false, 'Matrícula inválida.', $normalizada];
            }
            $formatos = [
                '/^[A-Z]{2}[0-9]{2}[A-Z]{2}$/',
                '/^[0-9]{2}[A-Z]{2}[0-9]{2}$/',
                '/^[0-9]{2}[0-9]{2}[A-Z]{2}$/',
            ];
            foreach ($formatos as $regex) {
                if (preg_match($regex, $limpo)) {
                    return [true, '', $normalizada];
                }
            }
            return [false, 'Formato inválido. Use AA-00-AA, 00-AA-00 ou 00-00-AA.', $normalizada];
        }

        function normalizarMatricula(string $matricula): string
        {
            return am_normalizar_matricula($matricula);
        }
    }
}
?>
