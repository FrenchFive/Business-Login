<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Database;
use App\Support\Clock;

final class OrganizationRepository
{
    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    /** @return int the new organization id */
    public function create(string $name): int
    {
        return $this->db->insert('organizations', [
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'created_at' => $this->clock->iso(),
        ]);
    }

    public function memberCount(int $organizationId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users WHERE organization_id = :id', ['id' => $organizationId]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'workspace';
        $slug = $base;

        for ($i = 2; $this->db->scalar('SELECT 1 FROM organizations WHERE slug = :slug', ['slug' => $slug]) !== false; $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
