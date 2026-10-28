<?php

declare(strict_types=1);

namespace Tests\Feature;

final class RequestProtectionTest extends FeatureTestCase
{
    public function testWritesWithoutTheCsrfTokenAreRejected(): void
    {
        $browser = $this->browser();
        $browser->visit('/login');

        $browser->request('POST', '/api/auth/login', '{"email":"demo@acme.test","password":"x"}', ['Content-Type: application/json'])
            ->assertStatus(403)
            ->assertErrorCode('csrf_token_mismatch');

        $browser->request('POST', '/api/auth/login', '{}', ['Content-Type: application/json', 'X-CSRF-Token: ' . str_repeat('0', 64)])
            ->assertStatus(403);
    }

    public function testACsrfTokenFromAnotherSessionIsRejected(): void
    {
        $attacker = $this->browser();
        $attacker->visit('/login');

        $victim = $this->browser();
        $victim->visit('/login');

        $victim->request('POST', '/api/auth/login', '{}', ['Content-Type: application/json', 'X-CSRF-Token: ' . $attacker->csrfToken()])
            ->assertStatus(403);
    }

    public function testTheApiOnlyAcceptsJsonBodies(): void
    {
        $browser = $this->browser();
        $browser->visit('/login');

        $browser->request('POST', '/api/auth/login', 'email=demo%40acme.test&password=x', [
            'Content-Type: application/x-www-form-urlencoded',
            'X-CSRF-Token: ' . $browser->csrfToken(),
        ])->assertStatus(415)->assertErrorCode('unsupported_media_type');

        $browser->request('POST', '/api/auth/login', '{not json', [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . $browser->csrfToken(),
        ])->assertStatus(400)->assertErrorCode('invalid_json');
    }

    public function testAccountEndpointsRequireAuthentication(): void
    {
        $browser = $this->browser();

        foreach (['/api/me', '/api/team', '/api/account/security'] as $endpoint) {
            $browser->get($endpoint)->assertStatus(401)->assertErrorCode('unauthenticated');
        }

        $browser->post('/api/account/profile', ['name' => 'Mallory'])->assertStatus(401);
    }

    public function testUnknownRoutesAndMethodsAreHandledCleanly(): void
    {
        $browser = $this->browser();

        $browser->get('/api/nope')->assertStatus(404)->assertErrorCode('not_found');
        self::assertStringContainsString('Page not found', $browser->get('/nope', ['Accept: text/html'])->assertStatus(404)->body);

        $response = $browser->get('/api/auth/login')->assertStatus(405)->assertErrorCode('method_not_allowed');
        self::assertSame('POST', $response->header('allow'));
    }

    public function testSecurityHeadersAreSentOnEveryResponse(): void
    {
        foreach (['/login', '/api/me'] as $path) {
            $response = $this->browser()->get($path);

            self::assertStringContainsString("script-src 'self'", (string) $response->header('content-security-policy'));
            self::assertStringContainsString("frame-ancestors 'none'", (string) $response->header('content-security-policy'));
            self::assertSame('nosniff', $response->header('x-content-type-options'));
            self::assertSame('DENY', $response->header('x-frame-options'));
            self::assertSame('same-origin', $response->header('referrer-policy'));
            self::assertStringContainsString('no-store', (string) $response->header('cache-control'));
            self::assertNull($response->header('x-powered-by'));
        }
    }

    public function testSessionCookiesAreHttpOnlyAndSameSite(): void
    {
        $cookie = (string) $this->browser()->get('/login')->header('set-cookie');

        self::assertStringContainsString('keystone_session=', $cookie);
        self::assertStringContainsStringIgnoringCase('HttpOnly', $cookie);
        self::assertStringContainsStringIgnoringCase('SameSite=Lax', $cookie);
    }

    public function testStaticAssetsAreServed(): void
    {
        $response = $this->browser()->get('/assets/css/app.css')->assertStatus(200);

        self::assertStringContainsString('text/css', (string) $response->header('content-type'));
    }
}
