<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\OrganizationRepository;
use App\Auth\PasswordHasher;
use App\Auth\PasswordPolicy;
use App\Auth\RememberMe;
use App\Auth\UserRepository;
use App\Config;
use App\Http\Request;
use App\Http\Response;
use App\Security\SecurityEvent;
use App\Security\SecurityLog;
use App\Support\Clock;
use App\Support\UserAgent;
use App\Support\Validator;

/**
 * JSON endpoints for the signed-in user's own account.
 */
final class AccountController
{
    public function __construct(
        private readonly Config $config,
        private readonly Auth $auth,
        private readonly UserRepository $users,
        private readonly OrganizationRepository $organizations,
        private readonly PasswordHasher $hasher,
        private readonly PasswordPolicy $policy,
        private readonly RememberMe $rememberMe,
        private readonly SecurityLog $log,
        private readonly Clock $clock,
    ) {
    }

    /** GET /api/me — everything the dashboard overview needs in one round-trip. */
    public function me(Request $request): Response
    {
        $user = $this->auth->requireUser($request);
        $since = $this->clock->iso('-30 days');

        return Response::ok([
            'user' => $user->toArray(),
            'organization' => [
                'id' => $user->organizationId,
                'name' => $user->organizationName,
                'member_count' => $this->organizations->memberCount($user->organizationId),
            ],
            'stats' => [
                'sign_ins_30d' => $this->log->countSince($user, SecurityEvent::signIns(), $since),
                'failed_30d' => $this->log->countSince($user, [SecurityEvent::LoginFailed->value], $since),
                'remembered_devices' => count($this->rememberMe->devicesFor($user, $request)),
            ],
            'previous_sign_in' => $this->log->previousSignIn($user),
            'session' => [
                'signed_in_at' => $this->auth->authenticatedAt(),
                'ip' => $request->ip,
                'device' => UserAgent::describe($request->userAgent()),
                'device_type' => UserAgent::type($request->userAgent()),
                'remembered' => $request->cookie(RememberMe::COOKIE) !== null,
                'remember_days' => $this->config->rememberDays,
                'idle_timeout_minutes' => $this->config->sessionIdleMinutes,
            ],
            'recent_activity' => $this->log->recentFor($user, 5),
        ]);
    }

    /** POST /api/account/profile  { name, job_title } */
    public function updateProfile(Request $request): Response
    {
        $user = $this->auth->requireUser($request);

        $v = new Validator($request->json());
        $name = $v->string('name', 'Full name', min: 2, max: 80);
        $jobTitle = $v->optionalString('job_title', 'Job title', max: 80);
        $v->validate();

        $user = $this->users->updateProfile($user, $name, $jobTitle);
        $this->log->record(SecurityEvent::ProfileUpdated, $request, $user);

        return Response::ok(['user' => $user->toArray()], 'Profile saved.');
    }

    /** POST /api/account/password  { current_password, password } */
    public function changePassword(Request $request): Response
    {
        $user = $this->auth->requireUser($request);

        $v = new Validator($request->json());
        $current = $v->password('current_password', 'Current password');
        $password = $v->password('password', 'New password');

        if (!$v->has('current_password') && !$this->hasher->verify($current, $user->passwordHash)) {
            $v->addError('current_password', 'That is not your current password.');
        }

        if (!$v->has('password')) {
            if (($error = $this->policy->check($password, $user->email)) !== null) {
                $v->addError('password', $error);
            } elseif ($password === $current) {
                $v->addError('password', 'Choose a password you have not used here before.');
            }
        }

        $v->validate();

        $this->users->updatePassword($user, $this->hasher->hash($password));
        $this->auth->refreshAfterPasswordChange($user, $request);
        $this->log->record(SecurityEvent::PasswordChanged, $request, $user);

        return Response::ok([], 'Password updated. Other remembered devices have been signed out.');
    }

    /** GET /api/account/security — audit trail + remembered devices. */
    public function security(Request $request): Response
    {
        $user = $this->auth->requireUser($request);

        return Response::ok([
            'events' => $this->log->recentFor($user, 25),
            'devices' => $this->rememberMe->devicesFor($user, $request),
            'password_changed_at' => $user->passwordChangedAt,
        ]);
    }

    /** POST /api/account/devices/forget — sign out every remembered device. */
    public function forgetDevices(Request $request): Response
    {
        $user = $this->auth->requireUser($request);
        $count = $this->rememberMe->forgetAll($user->id);
        $this->rememberMe->forget($request);

        if ($count === 0) {
            return Response::ok(['forgotten' => 0], 'There were no remembered devices to sign out.');
        }

        $this->log->record(SecurityEvent::DevicesForgotten, $request, $user);

        return Response::ok(
            ['forgotten' => $count],
            sprintf('%d remembered device%s signed out.', $count, $count === 1 ? '' : 's'),
        );
    }
}
