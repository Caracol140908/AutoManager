<?php
/**
 * Perfil orçamentista descontinuado — orçamentos são geridos pelo chefe de oficina.
 */
session_start();
unset($_SESSION['orcamentista_id'], $_SESSION['orcamentista_nome'], $_SESSION['orcamentista_email']);
header('Location: indexchefeoficina.php');
exit;
