<?php
declare(strict_types=1);

namespace App\Auth;

use PDO;
use RuntimeException;

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, password_hash, avatar_url, created_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, avatar_url, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(string $name, string $email, string $passwordHash, ?string $avatarUrl = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, avatar_url, created_at, updated_at)
             VALUES (:name, :email, :password_hash, :avatar_url, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'avatar_url' => $this->sanitizeAvatarUrl($avatarUrl),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET password_hash = :password_hash, updated_at = UTC_TIMESTAMP()
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $userId,
            'password_hash' => $passwordHash,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findByOAuthAccount(string $provider, string $providerUserId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.password_hash, u.avatar_url, u.created_at
             FROM oauth_accounts oa
             INNER JOIN users u ON u.id = oa.user_id
             WHERE oa.provider = :provider AND oa.provider_user_id = :provider_user_id
             LIMIT 1'
        );
        $stmt->execute([
            'provider' => strtolower(trim($provider)),
            'provider_user_id' => trim($providerUserId),
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function linkOAuthAccount(int $userId, string $provider, string $providerUserId, ?string $providerEmail): void
    {
        $provider = strtolower(trim($provider));
        $providerUserId = trim($providerUserId);
        if ($provider === '' || $providerUserId === '') {
            throw new RuntimeException('Invalid OAuth account mapping.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_accounts
               (user_id, provider, provider_user_id, provider_email, created_at, updated_at)
             VALUES
               (:user_id, :provider, :provider_user_id, :provider_email, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
               user_id = VALUES(user_id),
               provider_email = VALUES(provider_email),
               updated_at = UTC_TIMESTAMP()'
        );
        $stmt->execute([
            'user_id' => $userId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'provider_email' => $this->sanitizeEmailNullable($providerEmail),
        ]);
    }

    public function updateProfileFromOAuth(int $userId, ?string $name, ?string $avatarUrl): void
    {
        $name = trim((string)$name);
        $avatarUrl = $this->sanitizeAvatarUrl($avatarUrl);

        $parts = [];
        $params = ['id' => $userId];
        if ($name !== '') {
            $parts[] = 'name = :name';
            $params['name'] = substr($name, 0, 80);
        }
        if ($avatarUrl !== null) {
            $parts[] = 'avatar_url = :avatar_url';
            $params['avatar_url'] = $avatarUrl;
        }

        if ($parts === []) {
            return;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $parts) . ', updated_at = UTC_TIMESTAMP() WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function sanitizeEmailNullable(?string $email): ?string
    {
        $email = strtolower(trim((string)$email));
        if ($email === '') {
            return null;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return substr($email, 0, 190);
    }

    private function sanitizeAvatarUrl(?string $url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }
        return substr($url, 0, 255);
    }
}
