<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function acceptablePasswords(): iterable
    {
        yield 'letters and digits' => ['correct1horse'];
        yield 'passphrase' => ['Battery staple 42 horse'];
        yield 'unicode letters' => ['Мойпароль2026'];
        yield 'leading space kept' => [' spaced0ut '];
    }

    /** @return iterable<string, array{string, string}> */
    public static function rejectedPasswords(): iterable
    {
        yield 'too short' => ['abc123', 'at least 8'];
        yield 'no digit' => ['onlyletters', 'number'];
        yield 'no letter' => ['1234567890', 'letter'];
        yield 'common' => ['Password123', 'too common'];
        yield 'too long' => [str_repeat('a1', 65), 'at most'];
    }

    #[DataProvider('acceptablePasswords')]
    public function testAcceptsReasonablePasswords(string $password): void
    {
        self::assertNull((new PasswordPolicy())->check($password));
    }

    #[DataProvider('rejectedPasswords')]
    public function testRejectsWeakPasswordsWithAHelpfulMessage(string $password, string $expectedMessage): void
    {
        self::assertStringContainsString($expectedMessage, (string) (new PasswordPolicy())->check($password));
    }

    public function testThePasswordCannotBeTheEmailAddress(): void
    {
        self::assertNotNull((new PasswordPolicy())->check('Alex1@acme.test', 'alex1@acme.test'));
    }
}
