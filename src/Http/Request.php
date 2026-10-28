<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Immutable snapshot of the incoming HTTP request.
 */
final class Request
{
    /** @var array<string, mixed>|null */
    private ?array $decodedJson = null;

    /**
     * @param array<string, string>       $headers lower-cased header names
     * @param array<string, mixed>        $query
     * @param array<string, string>       $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly string $body = '',
        public readonly string $ip = '127.0.0.1',
        public readonly bool $secure = false,
        public readonly string $host = 'localhost',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
                $headers[$name] = $_SERVER[$key];
            }
        }

        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $https = $_SERVER['HTTPS'] ?? '';

        return new self(
            method: strtoupper(is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET'),
            path: self::normalizePath((string) parse_url($uri, PHP_URL_PATH)),
            query: $_GET,
            headers: $headers,
            cookies: array_filter($_COOKIE, 'is_string'),
            body: (string) file_get_contents('php://input'),
            // Deliberately REMOTE_ADDR only: X-Forwarded-For is client-controlled unless
            // you sit behind a proxy you trust (configure that at the web-server level).
            ip: is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
            secure: ($https !== '' && $https !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443,
            host: $headers['host'] ?? 'localhost',
        );
    }

    /** `/login/` → `/login`, `` → `/` */
    public static function normalizePath(string $path): string
    {
        return '/' . trim($path, '/');
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function queryString(string $key): string
    {
        $value = $this->query[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    public function userAgent(): string
    {
        return mb_substr($this->header('user-agent') ?? '', 0, 500);
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    /** True for methods that may change state and therefore need CSRF protection. */
    public function isUnsafe(): bool
    {
        return !in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function isJson(): bool
    {
        return str_starts_with(strtolower($this->header('content-type') ?? ''), 'application/json');
    }

    /**
     * Decoded JSON body.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->decodedJson !== null) {
            return $this->decodedJson;
        }

        if (trim($this->body) === '') {
            return $this->decodedJson = [];
        }

        $data = json_decode($this->body, true);

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new HttpException(400, 'invalid_json', 'The request body must be a JSON object.');
        }

        /** @var array<string, mixed> $data */
        return $this->decodedJson = $data;
    }

    /** Base URL used for links in e-mails (APP_URL wins over the Host header). */
    public function baseUrl(string $configured = ''): string
    {
        if ($configured !== '') {
            return $configured;
        }

        return ($this->secure ? 'https' : 'http') . '://' . $this->host;
    }
}
