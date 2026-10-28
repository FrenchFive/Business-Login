<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Argon2id (OWASP-recommended parameters) with a bcrypt fallback for PHP
 * builds compiled without libargon2.
 */
final class PasswordHasher
{
    private const ARGON2ID_OPTIONS = ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1];
    private const BCRYPT_OPTIONS = ['cost' => 12];

    /**
     * Pre-computed hashes of a throw-away password. Verifying against one of these
     * when the e-mail is unknown makes "no such user" take as long as "wrong
     * password", so response times do not reveal which accounts exist.
     */
    private const TIMING_EQUALIZER = [
        'argon2id' => '$argon2id$v=19$m=19456,t=2,p=1$NEc3RklNQXZldFQ3cDExSw$l7bOyW7RuByxHSmUhTpCFIBrW9BzG1kcD0tsHru/1wM',
        'bcrypt' => '$2y$12$BBqCbZNOEZ7wULppj2y2/e84DU8rt7tXrf4afT.1rKvnHGuJ.TZuq',
    ];

    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm(), $this->options());
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm(), $this->options());
    }

    /** Burns the same CPU time as a real verification. */
    public function verifyAgainstDummy(string $password): void
    {
        password_verify($password, self::TIMING_EQUALIZER[$this->usesArgon() ? 'argon2id' : 'bcrypt']);
    }

    private function usesArgon(): bool
    {
        return defined('PASSWORD_ARGON2ID');
    }

    private function algorithm(): string
    {
        return $this->usesArgon() ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /** @return array<string, int> */
    private function options(): array
    {
        return $this->usesArgon() ? self::ARGON2ID_OPTIONS : self::BCRYPT_OPTIONS;
    }
}
