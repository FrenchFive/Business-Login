<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\HttpException;
use App\Http\Request;
use App\Security\Csrf;
use App\Security\SecurityEvent;
use App\Security\SecurityLog;
use App\Security\Session;
use App\Support\Clock;

/**
 * Who is signed in, and the sign-in / sign-out transitions.
 */
final class Auth
{
    private ?User $user = null;
    private bool $resolved = false;

    public function __construct(
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly RememberMe $rememberMe,
        private readonly SecurityLog $log,
        private readonly Clock $clock,
    ) {
    }

    /** The signed-in user, from the session or — failing that — a "remember me" cookie. */
    public function user(Request $request): ?User
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $userId = $this->session->get('user_id');

        if (is_int($userId)) {
            $this->user = $this->users->find($userId);

            if ($this->user === null) {
                $this->session->forget('user_id', 'auth_time');
            }

            return $this->user;
        }

        $rememberedId = $this->rememberMe->consume($request);
        $user = $rememberedId === null ? null : $this->users->find($rememberedId);

        if ($user !== null) {
            $this->startSession($user, $request, remember: true);
            $this->log->record(SecurityEvent::LoginRemembered, $request, $user);
        }

        return $this->user;
    }

    public function requireUser(Request $request): User
    {
        return $this->user($request) ?? throw HttpException::unauthenticated(
            $this->session->expired() ? 'Your session expired. Please sign in again.' : 'Please sign in to continue.',
        );
    }

    /** Checks credentials. Takes the same time whether or not the e-mail exists. */
    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            $this->hasher->verifyAgainstDummy($password);

            return null;
        }

        if (!$this->hasher->verify($password, $user->passwordHash)) {
            return null;
        }

        if ($this->hasher->needsRehash($user->passwordHash)) {
            $this->users->rehashPassword($user, $this->hasher->hash($password));
        }

        return $user;
    }

    public function login(User $user, Request $request, bool $remember = false): void
    {
        $this->startSession($user, $request, $remember);
        $this->log->record(SecurityEvent::Login, $request, $user);
    }

    public function logout(Request $request): void
    {
        $user = $this->user($request);

        if ($user !== null) {
            $this->log->record(SecurityEvent::Logout, $request, $user);
        }

        $this->rememberMe->forget($request);
        $this->session->destroy();
        $this->user = null;
    }

    /** When the current session was authenticated (ISO-8601), if known. */
    public function authenticatedAt(): ?string
    {
        $time = $this->session->get('auth_time');

        return is_string($time) ? $time : null;
    }

    /**
     * After a password change: new session ID, other devices signed out,
     * and a fresh "remember me" token for this browser if it had one.
     * (The CSRF token is kept: the open page still needs it for its next request.)
     */
    public function refreshAfterPasswordChange(User $user, Request $request): void
    {
        $hadRememberCookie = $request->cookie(RememberMe::COOKIE) !== null;

        $this->rememberMe->forgetAll($user->id);
        $this->session->regenerate();

        if ($hadRememberCookie) {
            $this->rememberMe->issue($user, $request);
        }
    }

    private function startSession(User $user, Request $request, bool $remember): void
    {
        // A new session ID on every privilege change defeats session fixation.
        $this->session->regenerate();
        $this->session->set('user_id', $user->id);
        $this->session->set('auth_time', $this->clock->iso());
        $this->csrf->rotate();

        if ($remember) {
            $this->rememberMe->issue($user, $request);
        }

        $this->users->touchLastLogin($user);
        $this->user = $user;
        $this->resolved = true;
    }
}
