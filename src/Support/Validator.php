<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\HttpException;

/**
 * Typed input validation. Each accessor returns a clean value and records
 * an error message (first error per field wins); `validate()` throws a
 * 422 with all field errors at once.
 *
 *     $v = new Validator($request->json());
 *     $email = $v->email('email');
 *     $name  = $v->string('name', 'Full name', max: 80);
 *     $v->validate();
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $input */
    public function __construct(private readonly array $input)
    {
    }

    public function string(string $field, string $label, int $min = 1, int $max = 255, bool $trim = true): string
    {
        $raw = $this->input[$field] ?? '';
        $value = is_scalar($raw) ? (string) $raw : '';
        $value = $trim ? trim($value) : $value;
        $length = mb_strlen($value);

        if ($length === 0 && $min > 0) {
            $this->addError($field, "{$label} is required.");
        } elseif ($length < $min) {
            $this->addError($field, "{$label} must be at least {$min} characters.");
        } elseif ($length > $max) {
            $this->addError($field, "{$label} must be at most {$max} characters.");
        }

        return $value;
    }

    public function optionalString(string $field, string $label, int $max = 255): ?string
    {
        $value = $this->string($field, $label, min: 0, max: $max);

        return $value === '' ? null : $value;
    }

    /** Passwords are never trimmed: leading/trailing spaces are part of the secret. */
    public function password(string $field, string $label = 'Password'): string
    {
        return $this->string($field, $label, max: 4096, trim: false);
    }

    public function email(string $field, string $label = 'E-mail'): string
    {
        $value = mb_strtolower($this->string($field, $label, max: 254));

        if (!$this->has($field) && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, 'Enter a valid e-mail address, like name@company.com.');
        }

        return $value;
    }

    public function boolean(string $field): bool
    {
        return filter_var($this->input[$field] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function validate(): void
    {
        if ($this->errors !== []) {
            throw HttpException::validation($this->errors);
        }
    }
}
