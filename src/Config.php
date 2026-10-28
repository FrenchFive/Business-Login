<?php

declare(strict_types=1);

namespace App;

use App\Support\Env;

/**
 * Immutable application settings, read once per request from the environment.
 * See `.env.example` for every available option.
 */
final class Config
{
    public function __construct(
        public readonly string $rootPath,
        public readonly string $appName = 'Keystone',
        public readonly string $appUrl = '',
        public readonly string $env = 'local',
        public readonly bool $debug = false,
        public readonly bool $demo = true,
        public readonly string $dbConnection = 'sqlite',
        public readonly string $databasePath = 'storage/database.sqlite',
        public readonly string $dbHost = '127.0.0.1',
        public readonly int $dbPort = 3306,
        public readonly string $dbDatabase = 'keystone',
        public readonly string $dbUsername = 'root',
        public readonly string $dbPassword = '',
        public readonly string $sessionPath = 'storage/sessions',
        public readonly string $mailPath = 'storage/mail',
        public readonly int $sessionIdleMinutes = 30,
        public readonly int $rememberDays = 30,
        public readonly int $loginMaxAttempts = 5,
        public readonly int $loginLockoutMinutes = 15,
    ) {
    }

    public static function load(string $rootPath): self
    {
        Env::load($rootPath . '/.env');

        return new self(
            rootPath: $rootPath,
            appName: Env::string('APP_NAME', 'Keystone'),
            appUrl: rtrim(Env::string('APP_URL'), '/'),
            env: Env::string('APP_ENV', 'local'),
            debug: Env::bool('APP_DEBUG', false),
            demo: Env::bool('APP_DEMO', true),
            dbConnection: strtolower(Env::string('DB_CONNECTION', 'sqlite')),
            databasePath: Env::string('DB_PATH', 'storage/database.sqlite'),
            dbHost: Env::string('DB_HOST', '127.0.0.1'),
            dbPort: Env::int('DB_PORT', 3306),
            dbDatabase: Env::string('DB_DATABASE', 'keystone'),
            dbUsername: Env::string('DB_USERNAME', 'root'),
            dbPassword: Env::string('DB_PASSWORD'),
            sessionPath: Env::string('SESSION_PATH', 'storage/sessions'),
            mailPath: Env::string('MAIL_PATH', 'storage/mail'),
            sessionIdleMinutes: max(1, Env::int('SESSION_IDLE_MINUTES', 30)),
            rememberDays: max(1, Env::int('REMEMBER_DAYS', 30)),
            loginMaxAttempts: max(1, Env::int('LOGIN_MAX_ATTEMPTS', 5)),
            loginLockoutMinutes: max(1, Env::int('LOGIN_LOCKOUT_MINUTES', 15)),
        );
    }

    /** Resolves a path relative to the project root (absolute paths are kept). */
    public function path(string $path): string
    {
        if ($path === ':memory:' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return $this->rootPath . '/' . ltrim($path, '/');
    }

    public function isProduction(): bool
    {
        return $this->env === 'production';
    }
}
