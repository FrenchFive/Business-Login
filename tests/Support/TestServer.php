<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use RuntimeException;

/**
 * Boots the real application on PHP's built-in web server, with its own
 * throw-away database, session and mail directories.
 *
 * Runs against SQLite by default. Export DB_CONNECTION=mysql (plus DB_HOST,
 * DB_PORT, DB_USERNAME, DB_PASSWORD) to run the same tests on MySQL: each
 * server then gets its own temporary MySQL database.
 */
final class TestServer
{
    /** @var resource */
    private $process;

    private function __construct(
        public readonly string $baseUrl,
        public readonly string $storagePath,
        mixed $process,
        private readonly ?string $mysqlDatabase,
    ) {
        $this->process = $process;
    }

    /** @param array<string, string> $env extra environment variables (e.g. LOGIN_MAX_ATTEMPTS) */
    public static function start(array $env = []): self
    {
        $root = dirname(__DIR__, 2);
        $storage = sys_get_temp_dir() . '/keystone-test-' . bin2hex(random_bytes(6));
        $port = self::freePort();

        mkdir($storage, 0777, true);

        $mysqlDatabase = null;

        if (getenv('DB_CONNECTION') === 'mysql') {
            $mysqlDatabase = 'keystone_test_' . bin2hex(random_bytes(4));
            self::mysqlServer()->exec("CREATE DATABASE {$mysqlDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $environment = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'true',
            'APP_DEMO' => 'true',
            'APP_URL' => '',
            'DB_PATH' => $storage . '/database.sqlite',
            'DB_DATABASE' => $mysqlDatabase ?? '',
            'SESSION_PATH' => $storage . '/sessions',
            'MAIL_PATH' => $storage . '/mail',
        ], $env);

        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public', $root . '/public/index.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $storage . '/server.log', 'a'], 2 => ['file', $storage . '/server.log', 'a']],
            $pipes,
            $root,
            $environment,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the PHP built-in server.');
        }

        $server = new self("http://127.0.0.1:{$port}", $storage, $process, $mysqlDatabase);
        $server->waitUntilReady($port);

        return $server;
    }

    /** @return list<string> paths of the e-mails "sent" so far */
    public function sentMail(): array
    {
        return glob($this->storagePath . '/mail/*.eml') ?: [];
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
        self::removeDirectory($this->storagePath);

        if ($this->mysqlDatabase !== null) {
            self::mysqlServer()->exec("DROP DATABASE IF EXISTS {$this->mysqlDatabase}");
        }
    }

    private static function mysqlServer(): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d', getenv('DB_HOST') ?: '127.0.0.1', (int) (getenv('DB_PORT') ?: 3306));

        return new PDO($dsn, getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function waitUntilReady(int $port): void
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50_000);
        }

        $this->stop();
        throw new RuntimeException("The test server did not start on port {$port}.");
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if ($socket === false) {
            throw new RuntimeException("No free port available: {$error}");
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) ? self::removeDirectory($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
