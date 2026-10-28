<?php

declare(strict_types=1);

namespace Tests\Support;

use JsonException;
use PHPUnit\Framework\Assert;

final class TestResponse
{
    /** @param array<string, list<string>> $headers lower-cased names */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        try {
            $data = json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Assert::fail("Expected a JSON response, got:\n" . $this->body);
        }

        Assert::assertIsArray($data);

        return $data;
    }

    public function assertStatus(int $expected): self
    {
        Assert::assertSame($expected, $this->status, "Unexpected HTTP status. Body:\n" . $this->body);

        return $this;
    }

    public function assertRedirect(string $location): self
    {
        Assert::assertContains($this->status, [301, 302, 303], 'Expected a redirect, got ' . $this->status);
        Assert::assertSame($location, $this->header('location'));

        return $this;
    }

    public function assertErrorCode(string $code): self
    {
        Assert::assertSame($code, $this->json()['error']['code'] ?? null, $this->body);

        return $this;
    }

    /** @return array<string, string> */
    public function fieldErrors(): array
    {
        return $this->json()['error']['fields'] ?? [];
    }
}
