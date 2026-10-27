# Configuração de Email (PHPMailer)

Este projeto usa `mailer.php` com PHPMailer.

## 1) Instalar PHPMailer

```bash
composer require phpmailer/phpmailer
```

## 2) Definir variáveis de ambiente SMTP

- `SMTP_HOST` (ex: `smtp.gmail.com`)
- `SMTP_PORT` (ex: `587`)
- `SMTP_SECURE` (`tls` ou `ssl`)
- `SMTP_USER` (email SMTP)
- `SMTP_PASS` (password/app password SMTP)
- `SMTP_FROM_EMAIL` (opcional)
- `SMTP_FROM_NAME` (opcional)
- `SMTP_REPLY_TO` (opcional)
- `SMTP_REPLY_TO_NAME` (opcional)

## 3) Exemplo de uso

```php
require_once 'mailer.php';

$res = enviarEmailAutoManager([
  'to' => 'cliente@email.com',
  'to_name' => 'Cliente',
  'subject' => 'Assunto',
  'html' => '<p>Mensagem</p>',
  'text' => 'Mensagem'
]);

if (!$res['ok']) {
  // log de erro
}
```

