<?php

declare(strict_types=1);

namespace Tests\Feature;

final class PasswordResetTest extends FeatureTestCase
{
    public function testUnknownAddressesGetTheSameAnswerAndNoEmail(): void
    {
        $known = $this->browser()->post('/api/auth/forgot-password', ['email' => 'jordan@acme.test'])->assertStatus(200)->json();
        $mailCount = count($this->server()->sentMail());

        $unknown = $this->browser()->post('/api/auth/forgot-password', ['email' => 'ghost@acme.test'])->assertStatus(200)->json();

        self::assertSame($known['message'], $unknown['message']);
        self::assertArrayNotHasKey('demo', $unknown);
        self::assertCount($mailCount, $this->server()->sentMail());
    }

    public function testTheFullResetFlowWorksOnceAndOnlyOnce(): void
    {
        $response = $this->browser()->post('/api/auth/forgot-password', ['email' => 'taylor@acme.test'])->assertStatus(200)->json();

        // Demo mode hands the link to the UI; the same link is in the e-mail on disk.
        $url = $response['demo']['reset_url'];
        self::assertMatchesRegularExpression('#/reset-password\?token=[a-f0-9]{64}$#', $url);
        $mails = array_map('file_get_contents', $this->server()->sentMail());
        self::assertCount(1, array_filter($mails, static fn (string|false $mail): bool => str_contains((string) $mail, $url)));

        $token = substr($url, strpos($url, 'token=') + 6);
        $browser = $this->browser();
        self::assertStringContainsString('Choose a new password', $browser->visit('/reset-password?token=' . $token)->assertStatus(200)->body);

        $reset = $browser->post('/api/auth/reset-password', ['token' => $token, 'password' => 'Brand-New-Pass-7'])->assertStatus(200);
        self::assertSame('/login?status=password-reset', $reset->json()['redirect']);

        $this->browser()->login('taylor@acme.test', self::DEMO_PASSWORD)->assertStatus(401);
        $this->browser()->login('taylor@acme.test', 'Brand-New-Pass-7')->assertStatus(200);

        // The link is single-use.
        $this->browser()->post('/api/auth/reset-password', ['token' => $token, 'password' => 'Another-Pass-8'])
            ->assertStatus(410)
            ->assertErrorCode('invalid_token');
        self::assertStringContainsString('This link has expired', $this->browser()->visit('/reset-password?token=' . $token)->body);
    }

    public function testRequestingANewLinkInvalidatesThePreviousOne(): void
    {
        $first = $this->browser()->post('/api/auth/forgot-password', ['email' => 'sam@acme.test'])->json()['demo']['reset_url'];
        $this->browser()->post('/api/auth/forgot-password', ['email' => 'sam@acme.test'])->assertStatus(200);

        $token = substr($first, strpos($first, 'token=') + 6);
        $this->browser()->post('/api/auth/reset-password', ['token' => $token, 'password' => 'Brand-New-Pass-7'])->assertStatus(410);
    }

    public function testTheNewPasswordMustFollowThePolicy(): void
    {
        $url = $this->browser()->post('/api/auth/forgot-password', ['email' => 'priya@acme.test'])->json()['demo']['reset_url'];
        $token = substr($url, strpos($url, 'token=') + 6);

        $response = $this->browser()->post('/api/auth/reset-password', ['token' => $token, 'password' => 'weak'])->assertStatus(422);

        self::assertArrayHasKey('password', $response->fieldErrors());
    }

    public function testGarbageTokensAreRejected(): void
    {
        $this->browser()->post('/api/auth/reset-password', ['token' => 'nope', 'password' => 'Brand-New-Pass-7'])->assertStatus(410);
        self::assertStringContainsString('This link has expired', $this->browser()->visit('/reset-password?token=nope')->body);
    }
}
