<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\OrganizationRepository;
use App\Auth\PasswordHasher;
use App\Auth\PasswordPolicy;
use App\Auth\PasswordResetBroker;
use App\Auth\RememberMe;
use App\Auth\Role;
use App\Auth\User;
use App\Auth\UserRepository;
use App\Config;
use App\Database\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Mail\FileMailer;
use App\Security\RateLimiter;
use App\Security\SecurityEvent;
use App\Security\SecurityLog;
use App\Support\Validator;
use PDOException;

/**
 * JSON endpoints for signing in, signing up and recovering an account.
 */
final class AuthController
{
    private const REGISTRATIONS_PER_HOUR = 10;
    private const RESET_REQUESTS_PER_ADDRESS = 3;
    private const RESET_REQUESTS_PER_NETWORK = 10;

    public function __construct(
        private readonly Config $config,
        private readonly Database $db,
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly OrganizationRepository $organizations,
        private readonly PasswordHasher $hasher,
        private readonly PasswordPolicy $policy,
        private readonly PasswordResetBroker $resets,
        private readonly RememberMe $rememberMe,
        private readonly RateLimiter $limiter,
        private readonly SecurityLog $log,
        private readonly FileMailer $mailer,
    ) {
    }

    /** POST /api/auth/login  { email, password, remember } */
    public function login(Request $request): Response
    {
        $v = new Validator($request->json());
        $email = $v->email('email');
        $password = $v->password('password');
        $remember = $v->boolean('remember');
        $v->validate();

        // Two buckets: one per account+IP (stops guessing one password), and a
        // wider one per IP (slows down spraying many accounts from one machine).
        $accountKey = $this->loginKey($email, $request->ip);
        $networkKey = 'login-ip:' . $request->ip;
        $maxAttempts = $this->config->loginMaxAttempts;

        $this->throttle($accountKey, $maxAttempts);
        $this->throttle($networkKey, $maxAttempts * 4);

        $user = $this->auth->attempt($email, $password);

        if ($user === null) {
            $decay = $this->config->loginLockoutMinutes * 60;
            $attempts = $this->limiter->hit($accountKey, $decay);
            $this->limiter->hit($networkKey, $decay);
            $this->log->record(SecurityEvent::LoginFailed, $request, $this->users->findByEmail($email), $email);

            $left = $maxAttempts - $attempts;
            $message = 'Incorrect e-mail or password.';

            if ($left <= 0) {
                throw $this->lockedOut($accountKey);
            }

            if ($left <= 2) {
                $message .= sprintf(' %d attempt%s left before sign-in is paused.', $left, $left === 1 ? '' : 's');
            }

            throw new HttpException(401, 'invalid_credentials', $message);
        }

        $this->limiter->clear($accountKey);
        $this->auth->login($user, $request, $remember);

        return Response::ok(
            ['redirect' => '/dashboard', 'user' => $user->toArray()],
            "Welcome back, {$user->firstName()}!",
        );
    }

    /** POST /api/auth/register  { company, name, email, password } */
    public function register(Request $request): Response
    {
        $networkKey = 'register:' . $request->ip;

        if ($this->limiter->tooManyAttempts($networkKey, self::REGISTRATIONS_PER_HOUR)) {
            throw HttpException::tooManyAttempts(
                $this->limiter->availableIn($networkKey),
                'Too many workspaces were created from your network. Please try again later.',
            );
        }

        $v = new Validator($request->json());
        $company = $v->string('company', 'Company name', min: 2, max: 80);
        $name = $v->string('name', 'Full name', min: 2, max: 80);
        $email = $v->email('email', 'Work e-mail');
        $password = $v->password('password');

        if (!$v->has('password') && ($error = $this->policy->check($password, $email)) !== null) {
            $v->addError('password', $error);
        }

        if (!$v->has('email') && $this->users->emailExists($email)) {
            $v->addError('email', 'An account with this e-mail already exists. Try signing in instead.');
        }

        $v->validate();

        $hash = $this->hasher->hash($password);

        try {
            $user = $this->db->transaction(function () use ($company, $name, $email, $hash): User {
                $organizationId = $this->organizations->create($company);

                return $this->users->create($organizationId, $name, $email, $hash, Role::Owner);
            });
        } catch (PDOException $e) {
            // Two sign-ups racing for the same address: the UNIQUE index decides.
            if ($e->getCode() === '23000') {
                throw HttpException::validation(['email' => 'An account with this e-mail already exists. Try signing in instead.']);
            }

            throw $e;
        }

        $this->limiter->hit($networkKey, 3600);
        $this->log->record(SecurityEvent::AccountCreated, $request, $user);
        $this->auth->login($user, $request);

        return Response::ok(
            ['redirect' => '/dashboard', 'user' => $user->toArray()],
            "Welcome to {$this->config->appName}, {$user->firstName()}! Your workspace is ready.",
            201,
        );
    }

    /** POST /api/auth/logout */
    public function logout(Request $request): Response
    {
        $this->auth->logout($request);

        return Response::ok(['redirect' => '/login?status=signed-out'], 'You have been signed out.');
    }

    /** POST /api/auth/forgot-password  { email } */
    public function forgotPassword(Request $request): Response
    {
        $v = new Validator($request->json());
        $email = $v->email('email');
        $v->validate();

        // Per address (nobody can flood one inbox) and per network (no mass mailing).
        $addressKey = 'forgot:' . hash('sha256', $email);
        $networkKey = 'forgot-ip:' . $request->ip;
        $this->throttle($addressKey, self::RESET_REQUESTS_PER_ADDRESS);
        $this->throttle($networkKey, self::RESET_REQUESTS_PER_NETWORK);
        $this->limiter->hit($addressKey, $this->config->loginLockoutMinutes * 60);
        $this->limiter->hit($networkKey, $this->config->loginLockoutMinutes * 60);

        $data = [];
        $user = $this->users->findByEmail($email);

        // Same answer whether or not the account exists (no user enumeration).
        // In production, send the e-mail from a queue so timing is identical too.
        if ($user !== null) {
            $url = $request->baseUrl($this->config->appUrl) . '/reset-password?token=' . $this->resets->createToken($user);
            $file = $this->mailer->send($user->email, "Reset your {$this->config->appName} password", $this->resetEmail($user, $url));
            $this->log->record(SecurityEvent::PasswordResetRequested, $request, $user);

            if ($this->config->demo) {
                // Demo mode only: hand the link to the UI because no real e-mail is sent.
                $data['demo'] = ['reset_url' => $url, 'mail_file' => 'storage/mail/' . basename($file)];
            }
        }

        return Response::ok(
            $data,
            'If an account exists for that address, a reset link is on its way. It expires in ' . PasswordResetBroker::LIFETIME_MINUTES . ' minutes.',
        );
    }

    /** POST /api/auth/reset-password  { token, password } */
    public function resetPassword(Request $request): Response
    {
        $v = new Validator($request->json());
        $token = $v->string('token', 'Reset token', max: 64);
        $password = $v->password('password', 'New password');
        $v->validate();

        $user = $this->resets->userFor($token)
            ?? throw new HttpException(410, 'invalid_token', 'This reset link is invalid or has expired. Please request a new one.');

        if (($error = $this->policy->check($password, $user->email)) !== null) {
            throw HttpException::validation(['password' => $error]);
        }

        $this->users->updatePassword($user, $this->hasher->hash($password));
        $this->resets->markUsed($token);
        $this->rememberMe->forgetAll($user->id);
        $this->limiter->clear($this->loginKey($user->email, $request->ip));
        $this->log->record(SecurityEvent::PasswordReset, $request, $user);

        return Response::ok(['redirect' => '/login?status=password-reset'], 'Your password has been reset. You can now sign in.');
    }

    private function loginKey(string $email, string $ip): string
    {
        return 'login:' . hash('sha256', mb_strtolower($email) . '|' . $ip);
    }

    private function throttle(string $key, int $maxAttempts): void
    {
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw $this->lockedOut($key);
        }
    }

    private function lockedOut(string $key): HttpException
    {
        $seconds = $this->limiter->availableIn($key);
        $minutes = max(1, (int) ceil($seconds / 60));

        return HttpException::tooManyAttempts(
            $seconds,
            sprintf('Too many attempts. For your security, please wait %d minute%s and try again.', $minutes, $minutes === 1 ? '' : 's'),
        );
    }

    private function resetEmail(User $user, string $url): string
    {
        $app = $this->config->appName;
        $minutes = PasswordResetBroker::LIFETIME_MINUTES;

        return <<<TEXT
            Hi {$user->firstName()},

            We received a request to reset the password for your {$app} account ({$user->email}).

            Choose a new password here (the link expires in {$minutes} minutes and works once):
            {$url}

            If you didn't ask for this, you can safely ignore this e-mail — your password won't change.

            — The {$app} team
            TEXT;
    }
}
