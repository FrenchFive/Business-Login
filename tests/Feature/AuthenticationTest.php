<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Security\Session;

final class AuthenticationTest extends FeatureTestCase
{
    public function testGuestsAreRedirectedFromTheDashboardToTheSignInPage(): void
    {
        $this->browser()->get('/dashboard')->assertRedirect('/login');
        $this->browser()->get('/')->assertRedirect('/login');
    }

    public function testTheSignInPageRendersWithACsrfToken(): void
    {
        $browser = $this->browser();
        $page = $browser->visit('/login')->assertStatus(200);

        self::assertStringContainsString('Welcome back', $page->body);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $browser->csrfToken());
        self::assertStringContainsString('text/html', (string) $page->header('content-type'));
    }

    public function testAUserCanSignInAndReachTheirDashboard(): void
    {
        $browser = $this->browser();
        $response = $browser->login(self::DEMO_EMAIL, self::DEMO_PASSWORD)->assertStatus(200);

        self::assertTrue($response->json()['ok']);
        self::assertSame('/dashboard', $response->json()['redirect']);

        $me = $browser->get('/api/me')->assertStatus(200)->json();
        self::assertSame('Alex Morgan', $me['user']['name']);
        self::assertSame('Acme Inc.', $me['organization']['name']);
        self::assertArrayNotHasKey('password_hash', $me['user']);

        self::assertStringContainsString('Alex Morgan', $browser->get('/dashboard')->assertStatus(200)->body);
        $browser->get('/login')->assertRedirect('/dashboard');
    }

    public function testEmailAddressesAreCaseInsensitive(): void
    {
        $this->browser()->login('  DEMO@Acme.TEST ', self::DEMO_PASSWORD)->assertStatus(200);
    }

    public function testTheSessionIdChangesOnSignInToPreventSessionFixation(): void
    {
        $browser = $this->browser();
        $browser->visit('/login');
        $before = $browser->cookie(Session::COOKIE);

        $browser->post('/api/auth/login', ['email' => self::DEMO_EMAIL, 'password' => self::DEMO_PASSWORD])->assertStatus(200);

        self::assertNotNull($before);
        self::assertNotSame($before, $browser->cookie(Session::COOKIE));
    }

    public function testAWrongPasswordAndAnUnknownAccountGetTheSameAnswer(): void
    {
        $wrongPassword = $this->browser()->login(self::DEMO_EMAIL, 'not-the-password-1');
        $unknownAccount = $this->browser()->login('nobody@acme.test', 'not-the-password-1');

        $wrongPassword->assertStatus(401)->assertErrorCode('invalid_credentials');
        $unknownAccount->assertStatus(401)->assertErrorCode('invalid_credentials');
        self::assertSame($wrongPassword->json()['error']['message'], $unknownAccount->json()['error']['message']);
    }

    public function testMissingOrMalformedFieldsAreReportedPerField(): void
    {
        $response = $this->browser()->login('not-an-email', '')->assertStatus(422)->assertErrorCode('validation_failed');

        self::assertArrayHasKey('email', $response->fieldErrors());
        self::assertArrayHasKey('password', $response->fieldErrors());
    }

    public function testAUserCanSignOut(): void
    {
        $browser = $this->signedIn();
        $browser->visit('/dashboard');

        $response = $browser->post('/api/auth/logout')->assertStatus(200);
        self::assertSame('/login?status=signed-out', $response->json()['redirect']);

        $browser->get('/api/me')->assertStatus(401)->assertErrorCode('unauthenticated');
        $browser->get('/dashboard')->assertRedirect('/login');
    }
}
