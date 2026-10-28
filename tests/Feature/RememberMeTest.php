<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\RememberMe;
use App\Security\Session;

final class RememberMeTest extends FeatureTestCase
{
    public function testARememberedBrowserIsSignedInAgainAfterItsSessionEnds(): void
    {
        $browser = $this->signedIn(remember: true);
        $token = (string) $browser->cookie(RememberMe::COOKIE);
        self::assertMatchesRegularExpression('/^[a-f0-9]{24}:[a-f0-9]{64}$/', $token);

        // Simulate closing the browser: the session cookie is gone, the remember cookie stays.
        $browser->forgetCookie(Session::COOKIE);

        $me = $browser->get('/api/me')->assertStatus(200)->json();
        self::assertSame(self::DEMO_EMAIL, $me['user']['email']);
        self::assertTrue($me['session']['remembered']);
        self::assertSame('login_remembered', $me['recent_activity'][0]['event']);

        // Tokens are single-use: the cookie was rotated...
        self::assertNotSame($token, $browser->cookie(RememberMe::COOKIE));

        // ...so a copy of the old cookie is worthless.
        $copy = $this->browser();
        $copy->setCookie(RememberMe::COOKIE, $token);
        $copy->get('/api/me')->assertStatus(401);
    }

    public function testATamperedTokenRevokesEveryRememberedDevice(): void
    {
        $victim = $this->signedIn('jordan@acme.test', self::DEMO_PASSWORD, remember: true);
        $token = (string) $victim->cookie(RememberMe::COOKIE);
        [$selector] = explode(':', $token);

        $attacker = $this->browser();
        $attacker->setCookie(RememberMe::COOKIE, $selector . ':' . str_repeat('0', 64));
        $attacker->get('/api/me')->assertStatus(401);

        $victimWithoutSession = $this->browser();
        $victimWithoutSession->setCookie(RememberMe::COOKIE, $token);
        $victimWithoutSession->get('/api/me')->assertStatus(401);
    }

    public function testSigningOutForgetsTheDevice(): void
    {
        $browser = $this->signedIn('sam@acme.test', self::DEMO_PASSWORD, remember: true);
        $token = (string) $browser->cookie(RememberMe::COOKIE);

        $browser->visit('/dashboard');
        $browser->post('/api/auth/logout')->assertStatus(200);
        self::assertNull($browser->cookie(RememberMe::COOKIE));

        $copy = $this->browser();
        $copy->setCookie(RememberMe::COOKIE, $token);
        $copy->get('/api/me')->assertStatus(401);
    }

    public function testWithoutRememberMeNoPersistentCookieIsSet(): void
    {
        $browser = $this->signedIn('taylor@acme.test', self::DEMO_PASSWORD);

        self::assertNull($browser->cookie(RememberMe::COOKIE));
    }
}
