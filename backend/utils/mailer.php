<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/../lib/PHPMailer/src/PHPMailer.php";
require __DIR__ . "/../lib/PHPMailer/src/Exception.php";
require __DIR__ . "/../lib/PHPMailer/src/SMTP.php";

function enviarEmail(
    string $destinatario,
    string $nome,
    string $assunto,
    string $corpo
): void {
    $user     = $_ENV['MAIL_USER']      ?? getenv('MAIL_USER')      ?: '';
    $pass     = $_ENV['MAIL_PASS']      ?? getenv('MAIL_PASS')      ?: '';
    $fromName = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'SNGuard';

    if ($user === '' || $pass === '') {
        throw new \RuntimeException('Credenciais de e-mail não configuradas.');
    }

    $mail = new PHPMailer();

    try {
        $mail->Mailer     = "smtp";
        $mail->IsSMTP();
        $mail->CharSet    = "UTF-8";
        $mail->SMTPDebug  = 0;
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = 'ssl';
        $mail->Host       = 'smtp.gmail.com';
        $mail->Port       = 465;

        $mail->Username = $user;
        $mail->Password = $pass;

        $mail->SetFrom($user, $fromName);
        $mail->addAddress($destinatario, $nome);

        $mail->Subject = $assunto;
        $parts = preg_split('/(https?:\/\/\S+)/', $corpo, -1, PREG_SPLIT_DELIM_CAPTURE);
        $htmlCorpo = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                $url = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
                $htmlCorpo .= '<a href="' . $url . '">' . $url . '</a>';
            } else {
                $htmlCorpo .= nl2br(htmlspecialchars($part, ENT_QUOTES, 'UTF-8'));
            }
        }
        $mail->msgHTML($htmlCorpo);
        $mail->AltBody = $corpo;

        $mail->send();

    } catch (Exception $e) {
        error_log('[mailer] Falha ao enviar para ' . $destinatario . ': ' . $mail->ErrorInfo);
        throw new \RuntimeException('Falha no envio de e-mail: ' . $mail->ErrorInfo);
    }
}
