<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Server-side password rules. The browser shows the same rules live
 * (see `assets/js/core/password.js`), but this class is the authority.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 128;

    /** A tiny sample of the most breached passwords. Swap for a real breach list in production. */
    private const COMMON = [
        'password', 'password1', 'password123', 'passw0rd', 'qwerty123', 'qwertyuiop', '12345678', '123456789',
        '1234567890', 'iloveyou1', 'welcome1', 'welcome123', 'admin123', 'letmein1', 'abc12345', 'football1',
        'monkey123', 'dragon123', 'sunshine1', 'princess1', 'trustno1', 'baseball1', 'superman1', 'changeme1',
    ];

    /** @return string|null an error message, or null when the password is acceptable */
    public function check(string $password, string $email = ''): ?string
    {
        $length = mb_strlen($password);

        return match (true) {
            $length < self::MIN_LENGTH => 'Use at least ' . self::MIN_LENGTH . ' characters.',
            $length > self::MAX_LENGTH => 'Use at most ' . self::MAX_LENGTH . ' characters.',
            preg_match('/\p{L}/u', $password) !== 1 => 'Include at least one letter.',
            preg_match('/\d/', $password) !== 1 => 'Include at least one number.',
            in_array(mb_strtolower($password), self::COMMON, true) => 'This password is too common. Choose something less guessable.',
            $email !== '' && mb_strtolower($password) === mb_strtolower(trim($email)) => 'Your password cannot be your e-mail address.',
            default => null,
        };
    }
}
