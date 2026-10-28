<?php

declare(strict_types=1);

namespace App\Auth;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly int $organizationId,
        public readonly string $organizationName,
        public readonly string $name,
        public readonly string $email,
        public readonly Role $role,
        public readonly ?string $jobTitle,
        public readonly string $passwordHash,
        public readonly ?string $lastLoginAt,
        public readonly ?string $passwordChangedAt,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row a `users` row joined with `organizations.name` */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            organizationId: (int) $row['organization_id'],
            organizationName: (string) $row['organization_name'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            role: Role::from((string) $row['role']),
            jobTitle: isset($row['job_title']) && $row['job_title'] !== '' ? (string) $row['job_title'] : null,
            passwordHash: (string) $row['password_hash'],
            lastLoginAt: isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            passwordChangedAt: isset($row['password_changed_at']) ? (string) $row['password_changed_at'] : null,
            createdAt: (string) $row['created_at'],
        );
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';

        return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }

    /** Stable 0–5 index used to colour the avatar (kept server-side so PHP and JS agree). */
    public function avatarHue(): int
    {
        return hexdec(substr(md5(mb_strtolower($this->email)), 0, 6)) % 6;
    }

    /**
     * Public representation — never includes the password hash.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->firstName(),
            'initials' => $this->initials(),
            'avatar_hue' => $this->avatarHue(),
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'job_title' => $this->jobTitle,
            'last_login_at' => $this->lastLoginAt,
            'password_changed_at' => $this->passwordChangedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
