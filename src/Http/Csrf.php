<?php
declare(strict_types=1);

namespace App\Http;

final class Csrf
{
    public function __construct(private string $sessionKey = 'csrf')
    {
    }

    public function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION[$this->sessionKey]) || !is_string($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = bin2hex(random_bytes(16));
        }

        return (string)$_SESSION[$this->sessionKey];
    }

    public function requireValidPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $expected = $this->token();
        $posted = (string)($_POST['csrf'] ?? '');
        if (!hash_equals($expected, $posted)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Forbidden\n";
            exit;
        }
    }
}

