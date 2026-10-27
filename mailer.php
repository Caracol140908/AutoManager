<?php
/**
 * Helper de envio de email com PHPMailer.
 *
 * Uso rápido:
 * $res = enviarEmailAutoManager([
 *   'to' => 'cliente@email.com',
 *   'to_name' => 'Cliente',
 *   'subject' => 'Assunto',
 *   'html' => '<strong>Olá</strong>',
 *   'text' => 'Olá'
 * ]);
 */

function enviarEmailAutoManager(array $payload): array
{
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        return [
            'ok' => false,
            'error' => 'PHPMailer não está instalado. Execute: composer require phpmailer/phpmailer',
        ];
    }

    require_once $autoloadPath;

    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        return [
            'ok' => false,
            'error' => 'Classe PHPMailer não encontrada.',
        ];
    }

    $to = trim((string)($payload['to'] ?? ''));
    $subject = trim((string)($payload['subject'] ?? ''));
    $html = (string)($payload['html'] ?? '');
    $toName = trim((string)($payload['to_name'] ?? ''));
    $text = (string)($payload['text'] ?? '');
    $attachments = is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [];

    if ($to === '' || $subject === '' || $html === '') {
        return [
            'ok' => false,
            'error' => 'Parâmetros obrigatórios: to, subject, html.',
        ];
    }

    $host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $username = getenv('SMTP_USER') ?: '';
    $password = getenv('SMTP_PASS') ?: '';
    $secure = strtolower((string)(getenv('SMTP_SECURE') ?: 'tls'));
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: ($username ?: 'no-reply@automanager.pt');
    $fromName = getenv('SMTP_FROM_NAME') ?: 'AutoManager';
    $replyTo = getenv('SMTP_REPLY_TO') ?: $fromEmail;
    $replyToName = getenv('SMTP_REPLY_TO_NAME') ?: $fromName;

    if ($username === '' || $password === '') {
        return [
            'ok' => false,
            'error' => 'Credenciais SMTP em falta (SMTP_USER / SMTP_PASS).',
        ];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->CharSet = 'UTF-8';

        if ($secure === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo($replyTo, $replyToName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text !== '' ? $text : strip_tags($html);

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $path = (string)($attachment['path'] ?? '');
            if ($path === '' || !file_exists($path)) {
                continue;
            }
            $name = (string)($attachment['name'] ?? basename($path));
            $mail->addAttachment($path, $name);
        }

        $mail->send();
        return ['ok' => true];
    } catch (\Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
        ];
    }
}

