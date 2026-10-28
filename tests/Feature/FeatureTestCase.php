<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\DemoSeeder;
use PHPUnit\Framework\TestCase;
use Tests\Support\Browser;
use Tests\Support\TestServer;

/**
 * Feature tests talk to the real app over HTTP. Each test class gets a fresh
 * server and a freshly seeded database, so classes never affect each other.
 */
abstract class FeatureTestCase extends TestCase
{
    protected const DEMO_EMAIL = DemoSeeder::EMAIL;
    protected const DEMO_PASSWORD = DemoSeeder::PASSWORD;

    protected static ?TestServer $server = null;

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('The curl extension is required for feature tests.');
        }

        static::$server = TestServer::start(static::environment());
    }

    public static function tearDownAfterClass(): void
    {
        static::$server?->stop();
        static::$server = null;
    }

    /** @return array<string, string> environment overrides for this test class */
    protected static function environment(): array
    {
        return [];
    }

    /** @param non-empty-string $userAgent */
    protected function browser(string $userAgent = Browser::USER_AGENT): Browser
    {
        return new Browser($this->server()->baseUrl, $userAgent);
    }

    protected function server(): TestServer
    {
        return static::$server ?? throw new \LogicException('Test server is not running.');
    }

    /** A browser that is already signed in. */
    protected function signedIn(string $email = self::DEMO_EMAIL, string $password = self::DEMO_PASSWORD, bool $remember = false): Browser
    {
        $browser = $this->browser();
        $browser->login($email, $password, $remember)->assertStatus(200);

        return $browser;
    }
}
