<?php

declare(strict_types=1);

namespace App\Security;

use App\Auth\User;
use App\Database\Database;
use App\Http\Request;
use App\Support\Clock;
use App\Support\UserAgent;

/**
 * Append-only audit trail of security-relevant events.
 */
final class SecurityLog
{
    public function __construct(
        private readonly Database $db,
        private readonly Clock $clock,
    ) {
    }

    public function record(SecurityEvent $event, Request $request, ?User $user, ?string $email = null): void
    {
        $this->db->insert('security_events', [
            'user_id' => $user?->id,
            'email' => mb_strtolower($user->email ?? $email ?? ''),
            'event' => $event->value,
            'ip_address' => $request->ip,
            'user_agent' => $request->userAgent(),
            'created_at' => $this->clock->iso(),
        ]);
    }

    /** @return list<array<string, mixed>> newest first, shaped for the API */
    public function recentFor(User $user, int $limit = 20): array
    {
        $rows = $this->db->all(
            'SELECT id, event, ip_address, user_agent, created_at FROM security_events
             WHERE user_id = :user ORDER BY id DESC LIMIT ' . max(1, $limit),
            ['user' => $user->id],
        );

        return array_map(self::present(...), $rows);
    }

    /** @param list<string> $events */
    public function countSince(User $user, array $events, string $since): int
    {
        $placeholders = [];
        $params = ['user' => $user->id, 'since' => $since];

        foreach ($events as $i => $event) {
            $placeholders[] = ':e' . $i;
            $params['e' . $i] = $event;
        }

        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM security_events WHERE user_id = :user AND created_at >= :since
             AND event IN (' . implode(', ', $placeholders) . ')',
            $params,
        );
    }

    /**
     * The sign-in before the current one ("Last sign-in: yesterday from …").
     *
     * @return array<string, mixed>|null
     */
    public function previousSignIn(User $user): ?array
    {
        $row = $this->db->first(
            'SELECT id, event, ip_address, user_agent, created_at FROM security_events
             WHERE user_id = :user AND event IN (:login, :remembered) ORDER BY id DESC LIMIT 1 OFFSET 1',
            ['user' => $user->id, 'login' => SecurityEvent::Login->value, 'remembered' => SecurityEvent::LoginRemembered->value],
        );

        return $row === null ? null : self::present($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function present(array $row): array
    {
        $event = SecurityEvent::tryFrom((string) $row['event']);
        $userAgent = (string) $row['user_agent'];

        return [
            'id' => (int) $row['id'],
            'event' => (string) $row['event'],
            'label' => $event?->label() ?? (string) $row['event'],
            'tone' => $event?->tone() ?? 'neutral',
            'ip' => (string) $row['ip_address'],
            'device' => UserAgent::describe($userAgent),
            'device_type' => UserAgent::type($userAgent),
            'at' => (string) $row['created_at'],
        ];
    }
}
