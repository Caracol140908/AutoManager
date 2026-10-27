<?php
$to = "luiscaracol1409@gmail.com";
$subject = "Assunto do E-mail";
$message = "Olá! Este é um e-mail enviado pelo PHP.";
$headers = "From: luiscaracol140908@gmail.com\r\n" .
           "Reply-To: luiscaracol1409@gmail.com\r\n" .
           "X-Mailer: PHP/" . phpversion();

if (mail($to, $subject, $message, $headers)) {
    echo "E-mail enviado com sucesso!";
} else {
    echo "Falha ao enviar o e-mail.";
}
?>
