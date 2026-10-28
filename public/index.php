<?php

declare(strict_types=1);

/*
 * Front controller: every request that is not a static file ends up here.
 *
 *   php -S localhost:8000 -t public public/index.php
 */

// PHP's built-in server: let it serve real files (CSS, JS, fonts) directly.
if (PHP_SAPI === 'cli-server') {
    $file = realpath(__DIR__ . urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));

    if ($file !== false && is_file($file) && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && !str_ends_with($file, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

$app = new App\App(App\Config::load(dirname(__DIR__)));
$app->handle(App\Http\Request::fromGlobals())->send();
