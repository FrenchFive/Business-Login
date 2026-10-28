<?php

declare(strict_types=1);

namespace Tests\Feature;

final class RegistrationTest extends FeatureTestCase
{
    public function testANewCompanyCanCreateAWorkspace(): void
    {
        $browser = $this->browser();
        $browser->visit('/register')->assertStatus(200);

        $response = $browser->post('/api/auth/register', [
            'company' => 'Globex Corporation',
            'name' => 'Hank Scorpio',
            'email' => 'hank@globex.test',
            'password' => 'Volcano-Lair-42',
        ])->assertStatus(201);

        self::assertSame('/dashboard', $response->json()['redirect']);

        // Signed in straight away, as the owner of a brand-new, isolated workspace.
        $me = $browser->get('/api/me')->assertStatus(200)->json();
        self::assertSame('owner', $me['user']['role']);
        self::assertSame('Globex Corporation', $me['organization']['name']);

        $team = $browser->get('/api/team')->assertStatus(200)->json();
        self::assertSame(['hank@globex.test'], array_column($team['members'], 'email'));
    }

    public function testAnEmailCanOnlyBeRegisteredOnce(): void
    {
        $response = $this->browser()->post('/api/auth/register', [
            'company' => 'Copycat Ltd',
            'name' => 'Copy Cat',
            'email' => 'Demo@Acme.test',
            'password' => 'Another-Pass-99',
        ])->assertStatus(422);

        self::assertArrayHasKey('email', $response->fieldErrors());
    }

    public function testWeakPasswordsAreRejected(): void
    {
        foreach (['short1', 'onlyletters', '1234567890', 'password123'] as $password) {
            $response = $this->browser()->post('/api/auth/register', [
                'company' => 'Initech',
                'name' => 'Peter Gibbons',
                'email' => 'peter@initech.test',
                'password' => $password,
            ])->assertStatus(422);

            self::assertArrayHasKey('password', $response->fieldErrors(), "'{$password}' should be rejected");
        }
    }

    public function testEveryMissingFieldIsReportedAtOnce(): void
    {
        $fields = $this->browser()->post('/api/auth/register', [])->assertStatus(422)->fieldErrors();

        self::assertSame(['company', 'name', 'email', 'password'], array_keys($fields));
    }
}
