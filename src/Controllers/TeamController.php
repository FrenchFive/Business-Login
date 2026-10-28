<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Auth\User;
use App\Auth\UserRepository;
use App\Http\Request;
use App\Http\Response;

/**
 * JSON endpoint listing the members of the signed-in user's workspace.
 * Every query is scoped to the caller's organization, so tenants never see each other.
 */
final class TeamController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly UserRepository $users,
    ) {
    }

    /** GET /api/team */
    public function index(Request $request): Response
    {
        $me = $this->auth->requireUser($request);

        $members = array_map(static fn (User $member): array => [
            'id' => $member->id,
            'name' => $member->name,
            'initials' => $member->initials(),
            'avatar_hue' => $member->avatarHue(),
            'email' => $member->email,
            'role' => $member->role->value,
            'role_label' => $member->role->label(),
            'job_title' => $member->jobTitle,
            'last_login_at' => $member->lastLoginAt,
            'is_you' => $member->id === $me->id,
        ], $this->users->inOrganization($me->organizationId));

        return Response::ok([
            'organization' => ['id' => $me->organizationId, 'name' => $me->organizationName],
            'members' => $members,
        ]);
    }
}
