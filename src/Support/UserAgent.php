<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns a User-Agent string into something a human can read ("Chrome on macOS").
 * Intentionally small: good enough for an activity log, not a device database.
 */
final class UserAgent
{
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox',
        'CriOS/' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
        'curl/' => 'curl',
        'PostmanRuntime/' => 'Postman',
    ];

    private const SYSTEMS = [
        'iPhone' => 'iOS',
        'iPad' => 'iPadOS',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'Mac OS X' => 'macOS',
        'Macintosh' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    public static function describe(string $userAgent): string
    {
        if (trim($userAgent) === '') {
            return 'Unknown device';
        }

        $browser = self::match($userAgent, self::BROWSERS);
        $system = self::match($userAgent, self::SYSTEMS);

        return match (true) {
            $browser !== null && $system !== null => "{$browser} on {$system}",
            $browser !== null => $browser,
            $system !== null => "Browser on {$system}",
            default => 'Unknown device',
        };
    }

    /** desktop | mobile | tablet | other — lets the UI pick an icon. */
    public static function type(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'iPad'), str_contains($userAgent, 'Tablet') => 'tablet',
            str_contains($userAgent, 'Mobi'), str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'Android') => 'mobile',
            str_contains($userAgent, 'Mozilla/') => 'desktop',
            default => 'other',
        };
    }

    /** @param array<string, string> $needles */
    private static function match(string $haystack, array $needles): ?string
    {
        foreach ($needles as $needle => $name) {
            if (str_contains($haystack, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
