<?php

declare(strict_types=1);

namespace Tests\Feature;

final class RateLimitingTest extends FeatureTestCase
{
    protected static function environment(): array
    {
        return ['LOGIN_MAX_ATTEMPTS' => '3', 'LOGIN_LOCKOUT_MINUTES' => '15'];
    }

    public function testSignInIsPausedAfterTooManyFailures(): void
    {
        $browser = $this->browser();

        $browser->login('priya@acme.test', 'wrong-guess-1')->assertStatus(401);

        $second = $browser->login('priya@acme.test', 'wrong-guess-2')->assertStatus(401);
        self::assertStringContainsString('1 attempt left', $second->json()['error']['message']);

        $locked = $browser->login('priya@acme.test', 'wrong-guess-3')->assertStatus(429)->assertErrorCode('too_many_attempts');
        $retryAfter = (int) $locked->header('retry-after');
        self::assertGreaterThan(800, $retryAfter);
        self::assertLessThanOrEqual(900, $retryAfter);
        self::assertSame($retryAfter, $locked->json()['error']['retry_after']);

        // Even the right password is refused while the lock is active.
        $browser->login('priya@acme.test', self::DEMO_PASSWORD)->assertStatus(429);
    }

    public function testTheLockOnlyAffectsTheTargetedAccount(): void
    {
        $this->browser()->login(self::DEMO_EMAIL, self::DEMO_PASSWORD)->assertStatus(200);
    }

    public function testResetEmailsToOneAddressAreLimited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->browser()->post('/api/auth/forgot-password', ['email' => 'jordan@acme.test'])->assertStatus(200);
        }

        $this->browser()->post('/api/auth/forgot-password', ['email' => 'jordan@acme.test'])
            ->assertStatus(429)
            ->assertErrorCode('too_many_attempts');
        $this->browser()->post('/api/auth/forgot-password', ['email' => 'taylor@acme.test'])->assertStatus(200);
    }

    public function testFailedAttemptsShowUpInTheTargetedUsersAuditTrail(): void
    {
        $this->browser()->login('sam@acme.test', 'wrong-guess-1')->assertStatus(401);

        $events = $this->signedIn('sam@acme.test', self::DEMO_PASSWORD)->get('/api/account/security')->json()['events'];

        self::assertSame(['login', 'login_failed'], array_column($events, 'event'));
    }
}
