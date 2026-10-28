<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\PasswordResetBroker;
use App\Auth\UserRepository;
use App\Database\Database;
use App\Database\DemoSeeder;
use App\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class PasswordResetBrokerTest extends TestCase
{
    private FrozenClock $clock;
    private UserRepository $users;
    private PasswordResetBroker $broker;

    protected function setUp(): void
    {
        $db = Database::sqlite(':memory:');
        $this->clock = new FrozenClock();
        $db->migrate(dirname(__DIR__, 2) . '/database/schema', fn (Database $db) => (new DemoSeeder($db, $this->clock))->run());

        $this->users = new UserRepository($db, $this->clock);
        $this->broker = new PasswordResetBroker($db, $this->users, $this->clock);
    }

    public function testATokenResolvesToItsUser(): void
    {
        $user = $this->users->findByEmail(DemoSeeder::EMAIL);
        self::assertNotNull($user);

        $token = $this->broker->createToken($user);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame($user->id, $this->broker->userFor($token)?->id);
    }

    public function testTokensExpireAfterAnHour(): void
    {
        $user = $this->users->findByEmail(DemoSeeder::EMAIL);
        self::assertNotNull($user);
        $token = $this->broker->createToken($user);

        $this->clock->advance(PasswordResetBroker::LIFETIME_MINUTES * 60 - 1);
        self::assertNotNull($this->broker->userFor($token));

        $this->clock->advance(1);
        self::assertNull($this->broker->userFor($token));
    }

    public function testTokensWorkOnce(): void
    {
        $user = $this->users->findByEmail(DemoSeeder::EMAIL);
        self::assertNotNull($user);
        $token = $this->broker->createToken($user);

        $this->broker->markUsed($token);

        self::assertNull($this->broker->userFor($token));
    }

    public function testMalformedTokensAreRejectedWithoutTouchingTheDatabase(): void
    {
        self::assertNull($this->broker->userFor(''));
        self::assertNull($this->broker->userFor("' OR 1=1 --"));
    }
}
