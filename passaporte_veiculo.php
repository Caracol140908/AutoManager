<?php
session_start();

if (!empty($_SESSION['user']['id_utilizador'])) {
    header('Location: configuracoes.php?seccao=veiculos');
    exit;
}

header('Location: login.php');
exit;
