<?php

declare(strict_types=1);

/*
 * Global template helpers. Kept deliberately few.
 */

use App\View\Icons;

if (!function_exists('e')) {
    /** HTML-escape a value for safe output in templates. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('icon')) {
    /** Inline reference to an icon from the sprite (see App\View\Icons). */
    function icon(string $name, string $class = ''): string
    {
        return Icons::render($name, $class);
    }
}

if (!function_exists('asset')) {
    /** Public URL of a file in `public/assets`, with a cache-busting version. */
    function asset(string $path): string
    {
        $file = dirname(__DIR__, 2) . '/public/assets/' . ltrim($path, '/');
        $version = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';

        return '/assets/' . ltrim($path, '/') . '?v=' . $version;
    }
}
