<?php

/**

 * Tema global AutoManager (preto + laranja) — opcional.

 *

 * As páginas na raiz já emitem o link para theme.css inline (sem depender deste ficheiro).

 * Podes incluir este ficheiro para manter o HTML mais limpo, desde que o envies para o servidor:

 *   <?php include __DIR__ . '/theme_link.php'; ?>

 *

 * Equivalente inline (não precisa de theme_link.php no hosting):

 *   <?php require __DIR__ . '/inc_am_head.php'; ?>

 */

if (!defined('AM_THEME_LINK_INCLUDED')) {

    define('AM_THEME_LINK_INCLUDED', true);

    $amThemeHref = 'theme.css';

    echo '<link rel="stylesheet" href="' . htmlspecialchars($amThemeHref, ENT_QUOTES, 'UTF-8') . '">' . "\n";

}

