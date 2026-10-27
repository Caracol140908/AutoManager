<?php
/**
 * Bootstrap central da aplicacao.
 * Define constantes de caminho, inicia sessao e carrega DB.
 *
 * Usar em qualquer entrypoint:
 *   require_once __DIR__ . '/../app/Core/bootstrap.php';
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
    define('APP_PATH', APP_ROOT . '/app');
    define('CONFIG_PATH', APP_ROOT . '/config');
    define('STORAGE_PATH', APP_ROOT . '/storage');
    define('PUBLIC_PATH', APP_ROOT . '/public');
    define('SCRIPTS_PATH', APP_ROOT . '/scripts');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
