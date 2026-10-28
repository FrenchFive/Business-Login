#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Creates (or resets) the local database. Running it is optional: the app also
 * installs itself on the first request.
 *
 *   php bin/setup.php           create the database if it does not exist yet
 *   php bin/setup.php --fresh   wipe the database, sessions and saved e-mails first
 */

use App\Config;
use App\Database\Database;
use App\Database\DemoSeeder;

require dirname(__DIR__) . '/src/bootstrap.php';

$config = Config::load(dirname(__DIR__));
$color = stream_isatty(STDOUT);
$say = static function (string $text, string $style = '0') use ($color): void {
    echo ($color ? "\033[{$style}m{$text}\033[0m" : $text) . PHP_EOL;
};

$db = Database::connect($config);
$target = $db->driver === 'mysql'
    ? "MySQL {$config->dbUsername}@{$config->dbHost}:{$config->dbPort}/{$config->dbDatabase}"
    : 'SQLite ' . $config->path($config->databasePath);

if (in_array('--fresh', $argv, true)) {
    $db->dropAllTables();
    $leftovers = array_merge(
        glob($config->path($config->sessionPath) . '/sess_*') ?: [],
        glob($config->path($config->mailPath) . '/*.eml') ?: [],
    );

    foreach ($leftovers as $file) {
        unlink($file);
    }

    $say('✓ Removed the old tables, sessions and saved e-mails', '32');
}

$db->migrate(
    $config->rootPath . '/database/schema',
    $config->demo ? static fn (Database $db) => (new DemoSeeder($db))->run() : null,
);

$say('✓ Database ready: ' . $target, '32');
$say(sprintf('  %d workspace(s), %d user(s)', $db->scalar('SELECT COUNT(*) FROM organizations'), $db->scalar('SELECT COUNT(*) FROM users')));

if ($config->demo) {
    $say('');
    $say('Demo account', '1');
    $say('  E-mail:   ' . DemoSeeder::EMAIL);
    $say('  Password: ' . DemoSeeder::PASSWORD);
    $say('  (every @acme.test team member uses the same password)', '2');
}

$say('');
$say('Start the app:  php -S localhost:8000 -t public public/index.php', '36');
