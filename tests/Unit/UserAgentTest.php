<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UserAgent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAgentTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function userAgents(): iterable
    {
        yield 'Chrome on macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', 'Chrome on macOS', 'desktop'];
        yield 'Edge on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0', 'Edge on Windows', 'desktop'];
        yield 'Safari on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari on iOS', 'mobile'];
        yield 'Firefox on Android' => ['Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0', 'Firefox on Android', 'mobile'];
        yield 'Safari on iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/604.1', 'Safari on iPadOS', 'tablet'];
        yield 'curl' => ['curl/8.5.0', 'curl', 'other'];
        yield 'empty' => ['', 'Unknown device', 'other'];
    }

    #[DataProvider('userAgents')]
    public function testDescribesCommonUserAgents(string $userAgent, string $description, string $type): void
    {
        self::assertSame($description, UserAgent::describe($userAgent));
        self::assertSame($type, UserAgent::type($userAgent));
    }
}
