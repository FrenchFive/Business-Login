<?php

declare(strict_types=1);

namespace App\Security;

use App\Database\Database;
use App\Support\Clock;

/**
 * Fixed-window rate limiter stored in the database, used to slow down
 * credential stuffing and brute-force attacks.
 *
 *     if ($limiter->tooManyAttempts($key, 5)) { ... 429 ... }
 *     $limiter->hit($key, 900);   // count a failure, window of 15 minutes
 *     $limiter->clear($key);      // e.g. after a successful sign-in
 */
final class RateLimiter
{
    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function attempts(string $key): int
    {
        $row = $this->bucket($key);

        return $row === null ? 0 : (int) $row['hits'];
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /** Records one attempt and returns the number of attempts in the current window. */
    public function hit(string $key, int $decaySeconds): int
    {
        $now = $this->clock->timestamp();
        $resetAt = $now + $decaySeconds;

        // One atomic upsert, so concurrent requests can never under-count.
        // Only the conflict clause differs between SQLite and MySQL.
        $onConflict = $this->db->driver === 'mysql' ? 'ON DUPLICATE KEY UPDATE' : 'ON CONFLICT (bucket) DO UPDATE SET';

        $this->db->run(
            "INSERT INTO rate_limits (bucket, hits, reset_at) VALUES (:bucket, 1, :reset_at)
             {$onConflict}
                hits     = CASE WHEN rate_limits.reset_at <= :now THEN 1 ELSE rate_limits.hits + 1 END,
                reset_at = CASE WHEN rate_limits.reset_at <= :now_again THEN :new_reset_at ELSE rate_limits.reset_at END",
            ['bucket' => $key, 'reset_at' => $resetAt, 'now' => $now, 'now_again' => $now, 'new_reset_at' => $resetAt],
        );

        return $this->attempts($key);
    }

    /** Seconds until the current window resets (0 when not limited). */
    public function availableIn(string $key): int
    {
        $row = $this->bucket($key);

        return $row === null ? 0 : max(0, (int) $row['reset_at'] - $this->clock->timestamp());
    }

    public function clear(string $key): void
    {
        $this->db->run('DELETE FROM rate_limits WHERE bucket = :bucket', ['bucket' => $key]);
    }

    /** @return array<string, mixed>|null the bucket, if one exists and has not expired */
    private function bucket(string $key): ?array
    {
        return $this->db->first(
            'SELECT hits, reset_at FROM rate_limits WHERE bucket = :bucket AND reset_at > :now',
            ['bucket' => $key, 'now' => $this->clock->timestamp()],
        );
    }
}
