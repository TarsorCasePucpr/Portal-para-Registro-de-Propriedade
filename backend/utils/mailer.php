<?php
declare(strict_types=1);

/**
 * mailer.php — Envio de e-mails via PHPMailer + Gmail SMTP
 *
 * Configuração via .env:
 *   MAIL_USER      → sua conta Gmail (ex: seucorreo@gmail.com)
 *   MAIL_PASS      → App Password de 16 caracteres gerada no Google
 *   MAIL_FROM_NAME → nome exibido no remetente (ex: SNGuard)
 *
 * PHPMailer: baixar em https://github.com/PHPMailer/PHPMailer
 * e colocar a pasta PHPMailer/ na raiz do projeto.
 *
 * LGPD: e-mails enviados apenas para fins declarados ao usuário
 * (confirmação de conta, recuperação de senha, alertas de produto).
 */

require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarEmail(
    string $destinatario,
    string $nome,
    string $assunto,
    string $corpo
): void {
    $user     = $_ENV['MAIL_USER']      ?? '';
    $pass     = $_ENV['MAIL_PASS']      ?? '';
    $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'SNGuard';

    if ($user === '' || $pass === '') {
        error_log('[mailer.php] Credenciais SMTP não configuradas no .env');
        throw new \RuntimeException('Credenciais de e-mail não configuradas.');
    }

    $mail = new PHPMailer();

    try {
        // Configuração SMTP — Gmail SSL porta 465
        $mail->Mailer     = 'smtp';
        $mail->IsSMTP();
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = 0;
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = 'ssl';
        $mail->Host       = 'smtp.gmail.com';
        $mail->Port       = 465;
        $mail->Username   = $user;
        $mail->Password   = $pass;

        // Remetente e destinatário
        $mail->SetFrom($user, $fromName);
        $mail->addAddress($destinatario, $nome);

        // Conteúdo
        $mail->Subject = $assunto;
        $mail->msgHTML(nl2br(htmlspecialchars($corpo, ENT_QUOTES, 'UTF-8')));
        $mail->AltBody = $corpo;

        $mail->send();

    } catch (Exception $e) {
        error_log('[mailer.php] Falha ao enviar para ' . $destinatario . ': ' . $mail->ErrorInfo);
        throw new \RuntimeException('Falha no envio de e-mail: ' . $mail->ErrorInfo);
    }
}
