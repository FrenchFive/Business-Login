<?php

declare(strict_types=1);

/*
 * Bootstraps the application without requiring `composer install`.
 *
 * Composer is only needed for the development tools (PHPUnit, PHPStan).
 * At runtime a tiny PSR-4 autoloader maps the `App\` namespace onto `src/`.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/View/helpers.php';
