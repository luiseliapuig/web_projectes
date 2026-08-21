<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

final class EmailService
{
    public function __construct(private readonly EmailConfig $config)
    {
    }

    public function send(array $message): void
    {
        $errors = $this->config->validationErrors();
        if ($errors !== []) {
            throw new RuntimeException('Configuración de correo incompleta: ' . implode(', ', $errors));
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->isSMTP();
        $mail->Host = $this->config->host;
        $mail->Port = $this->config->port;
        $mail->SMTPAuth = true;
        $mail->Username = $this->config->username;
        $mail->Password = $this->config->password;
        if ($this->config->encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($this->config->encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($this->config->fromAddress, $this->config->fromName);
        if ($this->config->replyToAddress !== '') {
            $mail->addReplyTo($this->config->replyToAddress, $this->config->replyToName);
        }
        $mail->addAddress((string) $message['destinatario'], (string) ($message['nombre_destinatario'] ?? ''));
        $mail->Subject = (string) $message['asunto'];
        $mail->isHTML(true);
        $mail->Body = (string) $message['cuerpo_html'];
        $mail->AltBody = (string) $message['cuerpo_texto'];
        $mail->send();
    }
}
