<?php
declare(strict_types=1);

namespace App\Auth;

final class PasswordResetService
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetRepository $tokens,
        private AuthService $auth,
        private string $baseUrl,
        private string $logDir
    ) {
    }

    /** @return array{ok:bool,debug_link?:string} */
    public function requestReset(string $email): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => true];
        }

        $user = $this->users->findByEmail($email);
        if (!$user) {
            return ['ok' => true];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $ttl = (int)(getenv('RESET_TOKEN_TTL_MINUTES') ?: 60);
        $userId = (int)$user['id'];
        $this->tokens->createToken($userId, $tokenHash, $ttl);

        $link = rtrim($this->baseUrl, '/') . '/reset-password?token=' . rawurlencode($token);
        $mailOk = $this->sendMail($email, (string)($user['name'] ?? ''), $link);
        if (!$mailOk) {
            $this->log('password-reset mail() failed for ' . $email . ', link=' . $link);
        }

        $env = strtolower(trim((string)getenv('APP_ENV')));
        if ($env !== 'production') {
            return ['ok' => true, 'debug_link' => $link];
        }
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public function resetPassword(string $token, string $newPassword): array
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['ok' => false, 'error' => 'Invalid reset token.'];
        }

        $row = $this->tokens->findValidToken(hash('sha256', $token));
        if (!$row) {
            return ['ok' => false, 'error' => 'Reset link is invalid or expired.'];
        }

        $userId = (int)($row['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Reset link is invalid.'];
        }

        $res = $this->auth->updatePassword($userId, $newPassword);
        if (!($res['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string)($res['error'] ?? 'Unable to update password.')];
        }

        $this->tokens->consumeToken((int)$row['id']);
        $this->tokens->deleteTokensForUser($userId);
        return ['ok' => true];
    }

    public function isTokenFormatValid(string $token): bool
    {
        return (bool)preg_match('/^[a-f0-9]{64}$/', trim($token));
    }

    public function isTokenActive(string $token): bool
    {
        $token = trim($token);
        if (!$this->isTokenFormatValid($token)) {
            return false;
        }
        return $this->tokens->findValidToken(hash('sha256', $token)) !== null;
    }

    private function sendMail(string $toEmail, string $name, string $link): bool
    {
        $toEmail = strtolower(trim($toEmail));
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $fromEmail = strtolower(trim((string)getenv('MAIL_FROM_EMAIL')));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $host = trim((string)parse_url($this->baseUrl, PHP_URL_HOST));
            $fallbackHost = $host !== '' ? $host : 'localhost';
            $fromEmail = 'no-reply@' . $fallbackHost;
        }

        $fromName = trim((string)getenv('MAIL_FROM_NAME'));
        if ($fromName === '') {
            $fromName = 'Parcel Tracker';
        }
        $safeFromName = str_replace(["\r", "\n"], '', $fromName);
        $safeTo = str_replace(["\r", "\n"], '', $toEmail);

        $subject = 'Reset your Parcel Tracker password';
        $displayName = trim($name) !== '' ? trim($name) : 'there';
        $body = "Hi {$displayName},\n\n"
            . "Use this link to reset your password:\n{$link}\n\n"
            . "If you did not request this, you can safely ignore this email.\n";

        $headers = [
            'From: ' . $safeFromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return @mail($safeTo, $subject, $body, implode("\r\n", $headers));
    }

    private function log(string $message): void
    {
        $line = '[' . gmdate('c') . '] ' . $message . "\n";
        @file_put_contents($this->logDir . '/app.log', $line, FILE_APPEND);
    }
}
