<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal `.env` reader. Real environment variables always win, so the same
 * code works with a `.env` file locally and with injected variables in CI or
 * containers.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $fileValues = [];

    public static function load(string $path): void
    {
        self::$fileValues = [];

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            self::$fileValues[$key] = trim($value, "\"'");
        }
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = getenv($key);

        if ($value === false) {
            $value = self::$fileValues[$key] ?? null;
        }

        return $value === null || $value === '' ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::string($key);

        return $value === '' ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function int(string $key, int $default): int
    {
        $value = filter_var(self::string($key), FILTER_VALIDATE_INT);

        return $value === false ? $default : $value;
    }
}
