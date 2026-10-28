<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\RememberMe;

final class AccountTest extends FeatureTestCase
{
    public function testTheOverviewSummarisesTheAccount(): void
    {
        $me = $this->signedIn()->get('/api/me')->assertStatus(200)->json();

        self::assertSame(5, $me['organization']['member_count']);
        self::assertSame(1, $me['stats']['sign_ins_30d']);
        self::assertSame('Chrome on macOS', $me['session']['device']);
        self::assertSame(30, $me['session']['idle_timeout_minutes']);
        self::assertNull($me['previous_sign_in']);

        $again = $this->signedIn()->get('/api/me')->json();
        self::assertSame('Chrome on macOS', $again['previous_sign_in']['device']);
    }

    public function testAUserCanUpdateTheirProfile(): void
    {
        $browser = $this->signedIn('jordan@acme.test', self::DEMO_PASSWORD);
        $browser->visit('/dashboard');

        $user = $browser->post('/api/account/profile', ['name' => '  Jordan A. Lee ', 'job_title' => 'Head of Sales'])
            ->assertStatus(200)
            ->json()['user'];

        self::assertSame('Jordan A. Lee', $user['name']);
        self::assertSame('Head of Sales', $user['job_title']);
        self::assertSame('JL', $user['initials']);

        $errors = $browser->post('/api/account/profile', ['name' => 'J', 'job_title' => str_repeat('x', 81)])->assertStatus(422)->fieldErrors();
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('job_title', $errors);
    }

    public function testChangingThePasswordRequiresTheCurrentOne(): void
    {
        $browser = $this->signedIn('priya@acme.test', self::DEMO_PASSWORD);
        $browser->visit('/dashboard');

        $errors = $browser->post('/api/account/password', ['current_password' => 'nope', 'password' => 'Fresh-Pass-2027'])
            ->assertStatus(422)
            ->fieldErrors();
        self::assertSame(['current_password'], array_keys($errors));

        $errors = $browser->post('/api/account/password', ['current_password' => self::DEMO_PASSWORD, 'password' => self::DEMO_PASSWORD])
            ->assertStatus(422)
            ->fieldErrors();
        self::assertSame(['password'], array_keys($errors));
    }

    public function testChangingThePasswordSignsOutOtherRememberedDevices(): void
    {
        $laptop = $this->signedIn('sam@acme.test', self::DEMO_PASSWORD, remember: true);
        $phone = $this->signedIn('sam@acme.test', self::DEMO_PASSWORD, remember: true);
        $phoneToken = (string) $phone->cookie(RememberMe::COOKIE);

        $laptop->visit('/dashboard');
        $laptop->post('/api/account/password', ['current_password' => self::DEMO_PASSWORD, 'password' => 'Fresh-Pass-2027'])->assertStatus(200);

        // This browser stays signed in (and keeps a fresh remember-me token)...
        $laptop->get('/api/me')->assertStatus(200);
        $devices = $laptop->get('/api/account/security')->json()['devices'];
        self::assertCount(1, $devices);
        self::assertTrue($devices[0]['current']);

        // ...while the phone's remember-me token no longer works.
        $stolen = $this->browser();
        $stolen->setCookie(RememberMe::COOKIE, $phoneToken);
        $stolen->get('/api/me')->assertStatus(401);

        $this->browser()->login('sam@acme.test', self::DEMO_PASSWORD)->assertStatus(401);
        $this->browser()->login('sam@acme.test', 'Fresh-Pass-2027')->assertStatus(200);
    }

    public function testTheOpenPageKeepsWorkingAfterAPasswordChange(): void
    {
        // Regression: the page's CSRF token must survive the password change,
        // otherwise the next click (e.g. "Sign out") would be rejected.
        $browser = $this->signedIn('jordan@acme.test', self::DEMO_PASSWORD);
        $browser->visit('/dashboard');

        $browser->post('/api/account/password', ['current_password' => self::DEMO_PASSWORD, 'password' => 'Fresh-Pass-2027'])->assertStatus(200);
        $browser->post('/api/auth/logout')->assertStatus(200);
        $browser->get('/api/me')->assertStatus(401);
    }

    public function testTheSecurityTabListsEventsAndCanForgetAllDevices(): void
    {
        $this->signedIn('taylor@acme.test', self::DEMO_PASSWORD, remember: true);
        $browser = $this->signedIn('taylor@acme.test', self::DEMO_PASSWORD, remember: true);

        $security = $browser->get('/api/account/security')->assertStatus(200)->json();
        self::assertCount(2, $security['devices']);
        self::assertSame(['login', 'login'], array_column($security['events'], 'event'));
        self::assertSame('Signed in', $security['events'][0]['label']);

        $browser->visit('/dashboard');
        $response = $browser->post('/api/account/devices/forget')->assertStatus(200)->json();
        self::assertSame(2, $response['forgotten']);
        self::assertSame([], $browser->get('/api/account/security')->json()['devices']);
    }

    public function testTheTeamListOnlyShowsTheCallersWorkspace(): void
    {
        $team = $this->signedIn()->get('/api/team')->assertStatus(200)->json();

        self::assertSame('Acme Inc.', $team['organization']['name']);
        self::assertCount(5, $team['members']);
        self::assertSame('owner', $team['members'][0]['role']);
        self::assertTrue($team['members'][0]['is_you']);
        self::assertSame(['owner', 'admin', 'member', 'member', 'member'], array_column($team['members'], 'role'));
    }
}
