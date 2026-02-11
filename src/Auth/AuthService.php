<?php
declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

final class AuthService
{
    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_LAST_REGEN = 'auth_last_regen';

    public function __construct(private UserRepository $users)
    {
    }

    /** @return array{ok:bool,error?:string,user_id?:int} */
    public function register(string $name, string $email, string $password): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if (strlen($name) < 2 || strlen($name) > 80) {
            return ['ok' => false, 'error' => 'Name must be between 2 and 80 characters.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }
        if (!$this->isStrongPassword($password)) {
            return ['ok' => false, 'error' => 'Password must be at least 10 chars and include upper, lower, and number.'];
        }
        if ($this->users->findByEmail($email)) {
            return ['ok' => false, 'error' => 'An account with this email already exists.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Unable to hash password.');
        }

        $id = $this->users->create($name, $email, $hash);
        return ['ok' => true, 'user_id' => $id];
    }

    /** @return array{ok:bool,error?:string,user_id?:int} */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $user = $this->users->findByEmail($email);
        if (!$user) {
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        $hash = (string)($user['password_hash'] ?? '');
        if (!password_verify($password, $hash)) {
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            // Rehash optional; skipped here to keep auth path simple and deterministic.
        }

        $userId = (int)$user['id'];
        $this->storeUserId($userId);
        return ['ok' => true, 'user_id' => $userId];
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_USER_ID], $_SESSION[self::SESSION_LAST_REGEN]);
        session_regenerate_id(true);
    }

    public function userId(): ?int
    {
        $v = $_SESSION[self::SESSION_USER_ID] ?? null;
        if (!is_int($v) && !is_numeric($v)) {
            return null;
        }

        $id = (int)$v;
        if ($id <= 0) {
            return null;
        }

        $this->rotateSessionIdIfNeeded();
        return $id;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        $id = $this->userId();
        if (!$id) {
            return null;
        }
        return $this->users->findById($id);
    }

    private function storeUserId(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_LAST_REGEN] = time();
    }

    private function rotateSessionIdIfNeeded(): void
    {
        $last = (int)($_SESSION[self::SESSION_LAST_REGEN] ?? 0);
        if ($last <= 0 || (time() - $last) >= 900) {
            session_regenerate_id(true);
            $_SESSION[self::SESSION_LAST_REGEN] = time();
        }
    }

    private function isStrongPassword(string $password): bool
    {
        if (strlen($password) < 10) {
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        return true;
    }
}

