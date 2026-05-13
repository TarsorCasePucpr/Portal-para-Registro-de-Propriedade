<?php
declare(strict_types=1);

require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/crypto.php';

function triggerAdminTelegramStep(\PDO $pdo, int $userId, string $emailForRedirect): void
{
    try {
        $stmt = $pdo->prepare('SELECT telegram_chat_id FROM admin_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $stored = (string) ($stmt->fetchColumn() ?: '');
        $chatId = $stored !== '' ? decryptField($stored) : '';
    } catch (\PDOException $e) {
        error_log('[admin_otp_flow] chat_id lookup: ' . $e->getMessage());
        $chatId = '';
    }

    if ($chatId === '') {
        $_SESSION['admin_fallback_questions'] = true;
        redirect('../../frontend/pages/admin-questions.html?email=' . urlencode($emailForRedirect));
    }

    $otp     = (string) random_int(100000, 999999);
    $otpHash = hash('sha256', $otp);
    $expires = date('Y-m-d H:i:s', time() + 600);

    try {
        $pdo->prepare(
            "UPDATE tokens SET used_at = NOW()
             WHERE  user_id = ? AND type = 'admin_otp' AND used_at IS NULL"
        )->execute([$userId]);

        $pdo->prepare(
            "INSERT INTO tokens (user_id, token_hash, type, expires_at)
             VALUES (:uid, :hash, 'admin_otp', :exp)"
        )->execute([
            'uid'  => $userId,
            'hash' => $otpHash,
            'exp'  => $expires,
        ]);
    } catch (\PDOException $e) {
        error_log('[admin_otp_flow] token: ' . $e->getMessage());
        $_SESSION['admin_fallback_questions'] = true;
        redirect('../../frontend/pages/admin-questions.html?email=' . urlencode($emailForRedirect));
    }

    $sent = sendTelegramMessage(
        $chatId,
        "🔐 <b>SNGuard — Código de acesso admin</b>\n\nSeu código: <code>{$otp}</code>\n\nVálido por 10 minutos. Não compartilhe."
    );

    if (!$sent) {
        error_log("[admin_otp_flow] Telegram falhou para user_id={$userId}");
        $_SESSION['admin_fallback_questions'] = true;
        redirect('../../frontend/pages/admin-questions.html?email=' . urlencode($emailForRedirect));
    }

    unset($_SESSION['admin_fallback_questions']);
    redirect('../../frontend/pages/admin-otp.html?email=' . urlencode($emailForRedirect));
}
