<?php

declare(strict_types=1);

namespace App\Database;

use App\Config;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Thin wrapper around PDO for SQLite (default) and MySQL/MariaDB.
 * Every query goes through prepared statements.
 */
final class Database
{
    private const SCHEMA_VERSION = 1;

    /** Every table, children first (the order `dropAllTables()` needs). */
    private const TABLES = ['rate_limits', 'password_resets', 'remember_tokens', 'security_events', 'users', 'organizations', 'schema_migrations'];

    public readonly string $driver;

    public function __construct(public readonly PDO $pdo)
    {
        $this->driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** Connects to the database configured with DB_CONNECTION (sqlite | mysql). */
    public static function connect(Config $config): self
    {
        return match ($config->dbConnection) {
            'sqlite' => self::sqlite($config->path($config->databasePath)),
            'mysql' => self::mysql($config->dbHost, $config->dbPort, $config->dbDatabase, $config->dbUsername, $config->dbPassword),
            default => throw new RuntimeException("Unsupported DB_CONNECTION \"{$config->dbConnection}\" (use sqlite or mysql)."),
        };
    }

    public static function sqlite(string $path): self
    {
        if ($path !== ':memory:') {
            $directory = dirname($path);

            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create database directory: {$directory}");
            }
        }

        $pdo = new PDO('sqlite:' . $path, options: self::options());
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        if ($path !== ':memory:') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        return new self($pdo);
    }

    public static function mysql(string $host, int $port, string $database, string $username, string $password): self
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        return new self(new PDO($dsn, $username, $password, self::options()));
    }

    /**
     * Applies `database/schema/<driver>.sql` once, then runs `$afterCreate` (e.g. the
     * demo seeder). Safe to call on every request: it is a no-op once installed.
     *
     * @param (callable(self): void)|null $afterCreate
     */
    public function migrate(string $schemaDirectory, ?callable $afterCreate = null): void
    {
        if ($this->schemaVersion() >= self::SCHEMA_VERSION) {
            return;
        }

        $statements = $this->statements("{$schemaDirectory}/{$this->driver}.sql");

        $this->withInstallLock(function () use ($statements, $afterCreate): void {
            // Another request may have installed the schema while we waited for the lock.
            if ($this->schemaVersion() >= self::SCHEMA_VERSION) {
                return;
            }

            foreach ($statements as $sql) {
                $this->pdo->exec($sql);
            }

            $seed = function () use ($afterCreate): void {
                if ($afterCreate !== null) {
                    $afterCreate($this);
                }

                $this->insert('schema_migrations', ['version' => self::SCHEMA_VERSION, 'applied_at' => gmdate('Y-m-d\TH:i:s\Z')]);
            };

            // SQLite already runs inside the lock's transaction. MySQL commits DDL
            // implicitly, so only the data part can be wrapped in a transaction.
            $this->driver === 'mysql' ? $this->transaction($seed) : $seed();
        });
    }

    /** Drops every application table (used by `bin/setup.php --fresh`). */
    public function dropAllTables(): void
    {
        foreach (self::TABLES as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }

    /** @param array<string, scalar|null> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param array<string, scalar|null> $params
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, scalar|null> $params
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return array_values($this->run($sql, $params)->fetchAll());
    }

    /** @param array<string, scalar|null> $params */
    public function scalar(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    /** @param array<string, scalar|null> $values */
    public function insert(string $table, array $values): int
    {
        $columns = array_keys($values);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns)),
        );

        $this->run($sql, $values);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<int, mixed> */
    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    /**
     * Serialises the first install across concurrent requests.
     *
     * @param callable(): void $install
     */
    private function withInstallLock(callable $install): void
    {
        if ($this->driver === 'mysql') {
            $this->scalar("SELECT GET_LOCK('keystone_install', 30)");

            try {
                $install();
            } finally {
                $this->scalar("SELECT RELEASE_LOCK('keystone_install')");
            }

            return;
        }

        // SQLite: IMMEDIATE takes the write lock up-front.
        $this->pdo->exec('BEGIN IMMEDIATE');

        try {
            $install();
            $this->pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $this->pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    /** @phpstan-impure the version changes once the schema is installed */
    private function schemaVersion(): int
    {
        try {
            return (int) $this->scalar('SELECT MAX(version) FROM schema_migrations');
        } catch (PDOException) {
            return 0; // the table does not exist yet
        }
    }

    /** @return list<string> the SQL statements of a schema file, comments removed */
    private function statements(string $file): array
    {
        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new RuntimeException("Unable to read schema file: {$file}");
        }

        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);

        return array_values(array_filter(array_map('trim', explode(';', $sql)), static fn (string $s): bool => $s !== ''));
    }
}
