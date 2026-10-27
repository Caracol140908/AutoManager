<?php
// logout_chefe.php
session_start();

// Destruir todas as variáveis de sessão específicas do chefe
unset($_SESSION['chefe_id']);
unset($_SESSION['chefe_nome']);
unset($_SESSION['chefe_email']);
unset($_SESSION['primeiro_login_chefe']);

// Redirecionar para o login
header('Location: login.php');
exit();
?>