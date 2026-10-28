<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Synchronizer-token CSRF protection.
 *
 * The token lives in the session, is printed in a `<meta name="csrf-token">` tag,
 * and the JS client sends it back in the `X-CSRF-Token` header on every write.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);

        return is_string($token) && $token !== '' ? $token : $this->rotate();
    }

    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::KEY, $token);

        return $token;
    }

    public function validate(?string $token): bool
    {
        $expected = $this->session->get(self::KEY);

        return is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}
