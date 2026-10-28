<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Database;
use App\Http\Request;
use App\Support\Clock;
use App\Support\UserAgent;

/**
 * Persistent "Remember me" login using the selector/validator pattern.
 *
 * Cookie value: `<selector>:<validator>`. The database stores the selector in
 * clear (for lookup) and only a SHA-256 hash of the validator, so a leaked
 * database cannot be replayed as cookies. Tokens are single-use: each
 * successful auto-login rotates the token.
 */
final class RememberMe
{
    public const COOKIE = 'keystone_remember';

    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
        private readonly int $lifetimeDays,
        private readonly bool $secure,
    ) {
    }

    public function issue(User $user, Request $request): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = $this->clock->now()->modify("+{$this->lifetimeDays} days");

        $this->db->insert('remember_tokens', [
            'user_id' => $user->id,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip,
            'expires_at' => Clock::format($expires),
            'created_at' => $this->clock->iso(),
        ]);

        $this->setCookie("{$selector}:{$validator}", $expires->getTimestamp());
    }

    /**
     * Validates the cookie and, when valid, rotates it.
     *
     * @return int|null the remembered user id
     */
    public function consume(Request $request): ?int
    {
        [$selector, $validator] = $this->parseCookie($request) ?? [null, null];

        if ($selector === null || $validator === null) {
            return null;
        }

        $token = $this->db->first(
            'SELECT id, user_id, validator_hash, expires_at FROM remember_tokens WHERE selector = :selector',
            ['selector' => $selector],
        );

        if ($token === null || (string) $token['expires_at'] <= $this->clock->iso()) {
            $this->forget($request);

            return null;
        }

        if (!hash_equals((string) $token['validator_hash'], hash('sha256', $validator))) {
            // Right selector, wrong validator: the cookie was probably stolen and
            // already used. Revoke every remembered device for this user.
            $this->forgetAll((int) $token['user_id']);
            $this->clearCookie();

            return null;
        }

        $this->db->run('DELETE FROM remember_tokens WHERE id = :id', ['id' => $token['id']]);

        return (int) $token['user_id'];
    }

    /** Forgets the token held by the current browser. */
    public function forget(Request $request): void
    {
        $parsed = $this->parseCookie($request);

        if ($parsed !== null) {
            $this->db->run('DELETE FROM remember_tokens WHERE selector = :selector', ['selector' => $parsed[0]]);
        }

        if ($request->cookie(self::COOKIE) !== null) {
            $this->clearCookie();
        }
    }

    /** Signs every remembered device out (password change/reset, "forget devices"). */
    public function forgetAll(int $userId): int
    {
        return $this->db->run('DELETE FROM remember_tokens WHERE user_id = :user', ['user' => $userId])->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function devicesFor(User $user, Request $request): array
    {
        $current = $this->parseCookie($request)[0] ?? null;
        $rows = $this->db->all(
            'SELECT selector, user_agent, ip_address, created_at, expires_at FROM remember_tokens
             WHERE user_id = :user AND expires_at > :now ORDER BY id DESC',
            ['user' => $user->id, 'now' => $this->clock->iso()],
        );

        return array_map(static fn (array $row): array => [
            'device' => UserAgent::describe((string) $row['user_agent']),
            'device_type' => UserAgent::type((string) $row['user_agent']),
            'ip' => (string) $row['ip_address'],
            'created_at' => (string) $row['created_at'],
            'expires_at' => (string) $row['expires_at'],
            'current' => $current !== null && hash_equals((string) $row['selector'], $current),
        ], $rows);
    }

    /** @return array{0: string, 1: string}|null */
    private function parseCookie(Request $request): ?array
    {
        $value = $request->cookie(self::COOKIE) ?? '';

        if (preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $value, $m) !== 1) {
            return null;
        }

        return [$m[1], $m[2]];
    }

    private function setCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearCookie(): void
    {
        $this->setCookie('', 1);
    }
}
