<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Source of "now". Injected wherever time matters (rate limits, token expiry)
 * so tests can freeze or fast-forward time.
 */
class Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function timestamp(): int
    {
        return $this->now()->getTimestamp();
    }

    /** UTC timestamp in ISO-8601 form, e.g. `2026-01-31T09:15:00Z` (sortable as text). */
    public function iso(string $modifier = 'now'): string
    {
        return self::format($this->now()->modify($modifier));
    }

    public static function format(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
