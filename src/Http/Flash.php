<?php
declare(strict_types=1);

namespace App\Http;

final class Flash
{
    public function __construct(private string $sessionKey = 'flash')
    {
    }

    public function set(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[$this->sessionKey] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /** @return array{type:string, message:string}|null */
    public function consume(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $v = $_SESSION[$this->sessionKey] ?? null;
        unset($_SESSION[$this->sessionKey]);

        if (!is_array($v) || !is_string($v['type'] ?? null) || !is_string($v['message'] ?? null)) {
            return null;
        }

        return [
            'type' => $v['type'],
            'message' => $v['message'],
        ];
    }
}

