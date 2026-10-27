<?php
session_start();
unset($_SESSION['orcamentista_id'], $_SESSION['orcamentista_nome'], $_SESSION['orcamentista_email']);
header('Location: login.php');
exit;
