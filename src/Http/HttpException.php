<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * An error that maps directly onto an HTTP response.
 * API requests receive it as `{ "ok": false, "error": { code, message, fields } }`.
 */
class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $fields  per-field validation messages
     * @param array<string, string> $headers extra response headers (e.g. Retry-After)
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }

    /** @param array<string, string> $fields */
    public static function validation(array $fields, string $message = 'Please fix the highlighted fields.'): self
    {
        return new self(422, 'validation_failed', $message, $fields);
    }

    public static function unauthenticated(string $message = 'Please sign in to continue.'): self
    {
        return new self(401, 'unauthenticated', $message);
    }

    public static function tooManyAttempts(int $retryAfter, string $message): self
    {
        return new self(429, 'too_many_attempts', $message, headers: ['Retry-After' => (string) $retryAfter]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $error = ['code' => $this->errorCode, 'message' => $this->getMessage()];

        if ($this->fields !== []) {
            $error['fields'] = $this->fields;
        }

        if (isset($this->headers['Retry-After'])) {
            $error['retry_after'] = (int) $this->headers['Retry-After'];
        }

        return ['ok' => false, 'error' => $error];
    }
}
