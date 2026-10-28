<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Database;
use App\Support\Clock;

/**
 * One-time, short-lived password reset tokens. Only SHA-256 hashes are stored.
 */
final class PasswordResetBroker
{
    public const LIFETIME_MINUTES = 60;

    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly Clock $clock,
    ) {
    }

    /** Creates a fresh token (invalidating older ones) and returns it in clear text. */
    public function createToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));

        $this->db->run('DELETE FROM password_resets WHERE user_id = :user AND used_at IS NULL', ['user' => $user->id]);
        $this->db->insert('password_resets', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $this->clock->iso('+' . self::LIFETIME_MINUTES . ' minutes'),
            'created_at' => $this->clock->iso(),
        ]);

        return $token;
    }

    /** The user a token belongs to, if the token is unused and not expired. */
    public function userFor(string $token): ?User
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        $userId = $this->db->scalar(
            'SELECT user_id FROM password_resets WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :now',
            ['hash' => hash('sha256', $token), 'now' => $this->clock->iso()],
        );

        return $userId === false ? null : $this->users->find((int) $userId);
    }

    public function markUsed(string $token): void
    {
        $this->db->run(
            'UPDATE password_resets SET used_at = :now WHERE token_hash = :hash',
            ['now' => $this->clock->iso(), 'hash' => hash('sha256', $token)],
        );
    }
}
