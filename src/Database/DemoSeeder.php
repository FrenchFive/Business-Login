<?php

declare(strict_types=1);

namespace App\Database;

use App\Auth\OrganizationRepository;
use App\Auth\PasswordHasher;
use App\Auth\Role;
use App\Auth\UserRepository;
use App\Support\Clock;

/**
 * Demo workspace so the app is explorable the moment it starts.
 * Runs once, on first boot, when APP_DEMO=true.
 */
final class DemoSeeder
{
    public const EMAIL = 'demo@acme.test';
    public const PASSWORD = 'Demo-Pass-2026';
    public const ORGANIZATION = 'Acme Inc.';

    /** @var list<array{0: string, 1: string, 2: Role, 3: string}> name, e-mail, role, job title */
    public const TEAM = [
        ['Alex Morgan', self::EMAIL, Role::Owner, 'Head of Operations'],
        ['Priya Shah', 'priya@acme.test', Role::Admin, 'Engineering Manager'],
        ['Jordan Lee', 'jordan@acme.test', Role::Member, 'Account Executive'],
        ['Sam Rivera', 'sam@acme.test', Role::Member, 'Product Designer'],
        ['Taylor Kim', 'taylor@acme.test', Role::Member, 'Finance Analyst'],
    ];

    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock = new Clock(),
        private readonly PasswordHasher $hasher = new PasswordHasher(),
    ) {
    }

    public function run(): void
    {
        $organizationId = (new OrganizationRepository($this->db, $this->clock))->create(self::ORGANIZATION);
        $users = new UserRepository($this->db, $this->clock);

        foreach (self::TEAM as [$name, $email, $role, $jobTitle]) {
            $users->create($organizationId, $name, $email, $this->hasher->hash(self::PASSWORD), $role, $jobTitle);
        }
    }
}
