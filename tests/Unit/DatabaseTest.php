<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\PasswordHasher;
use App\Auth\UserRepository;
use App\Database\Database;
use App\Database\DemoSeeder;
use App\Support\Clock;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    private const SCHEMA = __DIR__ . '/../../database/schema';

    public function testMigrationRunsOnceAndSeedsTheDemoWorkspace(): void
    {
        $db = Database::sqlite(':memory:');
        $seeds = 0;
        $seed = static function (Database $db) use (&$seeds): void {
            $seeds++;
            (new DemoSeeder($db))->run();
        };

        $db->migrate(self::SCHEMA, $seed);
        $db->migrate(self::SCHEMA, $seed);

        self::assertSame(1, $seeds);
        self::assertSame(count(DemoSeeder::TEAM), (int) $db->scalar('SELECT COUNT(*) FROM users'));
    }

    public function testDemoPasswordsAreHashedNotStored(): void
    {
        $db = Database::sqlite(':memory:');
        $db->migrate(self::SCHEMA, static fn (Database $db) => (new DemoSeeder($db))->run());

        $user = (new UserRepository($db, new Clock()))->findByEmail(DemoSeeder::EMAIL);

        self::assertNotNull($user);
        self::assertStringNotContainsString(DemoSeeder::PASSWORD, $user->passwordHash);
        self::assertTrue((new PasswordHasher())->verify(DemoSeeder::PASSWORD, $user->passwordHash));
    }
}
