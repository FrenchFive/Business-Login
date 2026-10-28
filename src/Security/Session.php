<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Clock;
use RuntimeException;

/**
 * Hardened wrapper around PHP's native session.
 *
 * - strict mode + cookie-only IDs (no session IDs in URLs)
 * - HttpOnly, SameSite=Lax and Secure (on HTTPS) cookie flags
 * - idle timeout: an inactive authenticated session is signed out
 */
final class Session
{
    public const COOKIE = 'keystone_session';

    private bool $expired = false;

    public function __construct(
        private readonly string $savePath,
        private readonly int $idleSeconds,
        private readonly bool $secure,
        private readonly Clock $clock,
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!is_dir($this->savePath) && !mkdir($this->savePath, 0775, true) && !is_dir($this->savePath)) {
            throw new RuntimeException("Unable to create session directory: {$this->savePath}");
        }

        session_name(self::COOKIE);
        session_save_path($this->savePath);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start([
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
            'cache_limiter' => '', // caching headers are set by SecurityHeaders
            'gc_maxlifetime' => max($this->idleSeconds, 1440),
        ]);

        $now = $this->clock->timestamp();
        $lastSeen = $_SESSION['_last_activity'] ?? null;

        if (is_int($lastSeen) && $now - $lastSeen > $this->idleSeconds && $this->has('user_id')) {
            $this->forget('user_id', 'auth_time');
            $this->expired = true;
        }

        $_SESSION['_last_activity'] = $now;
    }

    /** True when this request found an authenticated session that had gone idle. */
    public function expired(): bool
    {
        return $this->expired;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /** New session ID, same data. Called on sign-in to defeat session fixation. */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Writes and unlocks the session file early. PHP locks the session for the whole
     * request, so read-only requests release it to let parallel AJAX calls run.
     */
    public function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        if (!headers_sent()) {
            setcookie(self::COOKIE, '', [
                'expires' => 1,
                'path' => '/',
                'secure' => $this->secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}
