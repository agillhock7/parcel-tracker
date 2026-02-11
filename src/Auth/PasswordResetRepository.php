<?php
declare(strict_types=1);

namespace App\Auth;

use PDO;

final class PasswordResetRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createToken(int $userId, string $tokenHash, int $ttlMinutes): void
    {
        $ttlMinutes = max(10, min(240, $ttlMinutes));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

        $purge = $this->pdo->prepare(
            'DELETE FROM password_reset_tokens
             WHERE user_id = :user_id OR expires_at < UTC_TIMESTAMP()'
        );
        $purge->execute(['user_id' => $userId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens
               (user_id, token_hash, expires_at, created_at)
             VALUES
               (:user_id, :token_hash, :expires_at, UTC_TIMESTAMP())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function findValidToken(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, expires_at
             FROM password_reset_tokens
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function consumeToken(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE password_reset_tokens
             SET used_at = UTC_TIMESTAMP()
             WHERE id = :id AND used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
    }

    public function deleteTokensForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
