<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Database;
use App\Support\Clock;

final class UserRepository
{
    private const SELECT = 'SELECT users.*, organizations.name AS organization_name
                            FROM users JOIN organizations ON organizations.id = users.organization_id';

    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function find(int $id): ?User
    {
        $row = $this->db->first(self::SELECT . ' WHERE users.id = :id', ['id' => $id]);

        return $row === null ? null : User::fromRow($row);
    }

    /** Case-insensitive (COLLATE NOCASE on SQLite, a *_ci collation on MySQL). */
    public function findByEmail(string $email): ?User
    {
        $row = $this->db->first(self::SELECT . ' WHERE users.email = :email', ['email' => trim($email)]);

        return $row === null ? null : User::fromRow($row);
    }

    public function emailExists(string $email): bool
    {
        return $this->db->scalar('SELECT 1 FROM users WHERE email = :email', ['email' => trim($email)]) !== false;
    }

    /** @return list<User> owners first, then admins, then members (alphabetical) */
    public function inOrganization(int $organizationId): array
    {
        $rows = $this->db->all(
            self::SELECT . " WHERE users.organization_id = :org
             ORDER BY CASE users.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END, LOWER(users.name)",
            ['org' => $organizationId],
        );

        return array_map(User::fromRow(...), $rows);
    }

    public function create(int $organizationId, string $name, string $email, string $passwordHash, Role $role, ?string $jobTitle = null): User
    {
        $now = $this->clock->iso();

        $id = $this->db->insert('users', [
            'organization_id' => $organizationId,
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'role' => $role->value,
            'job_title' => $jobTitle,
            'password_changed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find($id) ?? throw new \LogicException('User vanished right after insert.');
    }

    public function updateProfile(User $user, string $name, ?string $jobTitle): User
    {
        $this->db->run(
            'UPDATE users SET name = :name, job_title = :job_title, updated_at = :now WHERE id = :id',
            ['name' => $name, 'job_title' => $jobTitle, 'now' => $this->clock->iso(), 'id' => $user->id],
        );

        return $this->find($user->id) ?? $user;
    }

    public function updatePassword(User $user, string $passwordHash): void
    {
        $now = $this->clock->iso();

        $this->db->run(
            'UPDATE users SET password_hash = :hash, password_changed_at = :changed_at, updated_at = :updated_at WHERE id = :id',
            ['hash' => $passwordHash, 'changed_at' => $now, 'updated_at' => $now, 'id' => $user->id],
        );
    }

    /** Silent upgrade when the hashing algorithm or cost changes. */
    public function rehashPassword(User $user, string $passwordHash): void
    {
        $this->db->run('UPDATE users SET password_hash = :hash WHERE id = :id', ['hash' => $passwordHash, 'id' => $user->id]);
    }

    public function touchLastLogin(User $user): void
    {
        $this->db->run('UPDATE users SET last_login_at = :now WHERE id = :id', ['now' => $this->clock->iso(), 'id' => $user->id]);
    }
}
