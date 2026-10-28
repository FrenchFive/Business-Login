<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\Database;
use App\Security\RateLimiter;
use App\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private FrozenClock $clock;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $db = Database::sqlite(':memory:');
        $db->migrate(dirname(__DIR__, 2) . '/database/schema');

        $this->clock = new FrozenClock();
        $this->limiter = new RateLimiter($db, $this->clock);
    }

    public function testCountsHitsWithinTheWindow(): void
    {
        self::assertSame(0, $this->limiter->attempts('key'));
        self::assertSame(1, $this->limiter->hit('key', 60));
        self::assertSame(2, $this->limiter->hit('key', 60));
        self::assertFalse($this->limiter->tooManyAttempts('key', 3));
        self::assertSame(3, $this->limiter->hit('key', 60));
        self::assertTrue($this->limiter->tooManyAttempts('key', 3));
    }

    public function testReportsWhenTheWindowResets(): void
    {
        $this->limiter->hit('key', 60);
        $this->clock->advance(15);

        self::assertSame(45, $this->limiter->availableIn('key'));
        self::assertSame(0, $this->limiter->availableIn('other-key'));
    }

    public function testTheWindowExpires(): void
    {
        $this->limiter->hit('key', 60);
        $this->limiter->hit('key', 60);
        $this->clock->advance(60);

        self::assertSame(0, $this->limiter->attempts('key'));
        self::assertSame(1, $this->limiter->hit('key', 60), 'A new window starts from scratch.');
    }

    public function testKeysAreIndependentAndCanBeCleared(): void
    {
        $this->limiter->hit('a', 60);
        $this->limiter->hit('b', 60);
        $this->limiter->clear('a');

        self::assertSame(0, $this->limiter->attempts('a'));
        self::assertSame(1, $this->limiter->attempts('b'));
    }
}
