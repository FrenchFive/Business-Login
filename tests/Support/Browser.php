<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * A minimal browser for feature tests: keeps cookies between requests, reads the
 * CSRF token from pages and sends JSON exactly like `assets/js/core/http.js`.
 */
final class Browser
{
    public const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    /** @var array<string, string> raw cookie values, exactly as the server sent them */
    private array $cookies = [];

    private string $csrfToken = '';

    /** @param non-empty-string $userAgent */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent = self::USER_AGENT,
    ) {
    }

    /** Opens an HTML page and remembers its CSRF token, like a real page load. */
    public function visit(string $path): TestResponse
    {
        $response = $this->get($path, ['Accept: text/html']);

        if (preg_match('/<meta name="csrf-token" content="([a-f0-9]+)">/', $response->body, $m) === 1) {
            $this->csrfToken = $m[1];
        }

        return $response;
    }

    /** @param list<string> $headers */
    public function get(string $path, array $headers = []): TestResponse
    {
        return $this->request('GET', $path, null, $headers);
    }

    /**
     * POSTs JSON with the CSRF header (fetching a token first if needed).
     *
     * @param array<string, mixed> $data
     * @param list<string> $headers
     */
    public function post(string $path, array $data = [], array $headers = []): TestResponse
    {
        if ($this->csrfToken === '') {
            $this->visit('/login');
        }

        return $this->request('POST', $path, json_encode($data, JSON_THROW_ON_ERROR), [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . $this->csrfToken,
            ...$headers,
        ]);
    }

    /**
     * @param non-empty-string $method
     * @param list<string> $headers
     */
    public function request(string $method, string $path, ?string $body = null, array $headers = []): TestResponse
    {
        $curl = curl_init($this->baseUrl . $path);

        if ($curl === false) {
            throw new RuntimeException('curl_init failed');
        }

        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = "{$name}={$value}";
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $this->withDefaultAccept($headers),
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($curl);

        if (!is_string($raw)) {
            throw new RuntimeException('HTTP request failed: ' . curl_error($curl));
        }

        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $responseHeaders = $this->parseHeaders(substr($raw, 0, $headerSize));

        foreach ($responseHeaders['set-cookie'] ?? [] as $cookie) {
            $this->storeCookie($cookie);
        }

        return new TestResponse($status, $responseHeaders, substr($raw, $headerSize));
    }

    public function login(string $email, string $password, bool $remember = false): TestResponse
    {
        $this->visit('/login');

        return $this->post('/api/auth/login', ['email' => $email, 'password' => $password, 'remember' => $remember]);
    }

    public function csrfToken(): string
    {
        return $this->csrfToken;
    }

    /** Decoded cookie value, as PHP will read it from `$_COOKIE`. */
    public function cookie(string $name): ?string
    {
        return isset($this->cookies[$name]) ? urldecode($this->cookies[$name]) : null;
    }

    public function setCookie(string $name, string $value): void
    {
        $this->cookies[$name] = rawurlencode($value);
    }

    public function forgetCookie(string $name): void
    {
        unset($this->cookies[$name]);
    }

    /**
     * @param list<string> $headers
     * @return list<string>
     */
    private function withDefaultAccept(array $headers): array
    {
        foreach ($headers as $header) {
            if (stripos($header, 'accept:') === 0) {
                return $headers;
            }
        }

        return ['Accept: application/json', ...$headers];
    }

    /** @return array<string, list<string>> */
    private function parseHeaders(string $raw): array
    {
        $headers = [];

        foreach (explode("\r\n", $raw) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
        }

        return $headers;
    }

    private function storeCookie(string $header): void
    {
        $parts = array_map('trim', explode(';', $header));
        [$name, $value] = array_pad(explode('=', array_shift($parts), 2), 2, '');
        $expired = $value === '' || $value === 'deleted';

        foreach ($parts as $attribute) {
            if (stripos($attribute, 'expires=') === 0 && strtotime(substr($attribute, 8)) < time()) {
                $expired = true;
            }
            if (stripos($attribute, 'max-age=') === 0 && (int) substr($attribute, 8) <= 0) {
                $expired = true;
            }
        }

        if ($expired) {
            unset($this->cookies[$name]);
        } else {
            $this->cookies[$name] = $value;
        }
    }
}
