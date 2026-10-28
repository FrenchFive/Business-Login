<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\Csrf;
use App\Security\Session;
use App\Support\Clock;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    private Csrf $csrf;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->csrf = new Csrf(new Session(sys_get_temp_dir(), 1800, false, new Clock()));
    }

    public function testTheTokenIsStableForASession(): void
    {
        $token = $this->csrf->token();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame($token, $this->csrf->token());
        self::assertTrue($this->csrf->validate($token));
    }

    public function testWrongOrMissingTokensFail(): void
    {
        $this->csrf->token();

        self::assertFalse($this->csrf->validate(null));
        self::assertFalse($this->csrf->validate(''));
        self::assertFalse($this->csrf->validate(str_repeat('a', 64)));
    }

    public function testNothingValidatesBeforeATokenExists(): void
    {
        self::assertFalse($this->csrf->validate(''));
    }

    public function testRotationInvalidatesTheOldToken(): void
    {
        $old = $this->csrf->token();
        $new = $this->csrf->rotate();

        self::assertNotSame($old, $new);
        self::assertFalse($this->csrf->validate($old));
        self::assertTrue($this->csrf->validate($new));
    }
}
