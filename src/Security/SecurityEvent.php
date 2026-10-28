<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Everything that ends up in the audit trail (`security_events` table).
 */
enum SecurityEvent: string
{
    case AccountCreated = 'account_created';
    case Login = 'login';
    case LoginRemembered = 'login_remembered';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case PasswordChanged = 'password_changed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordReset = 'password_reset';
    case ProfileUpdated = 'profile_updated';
    case DevicesForgotten = 'devices_forgotten';

    public function label(): string
    {
        return match ($this) {
            self::AccountCreated => 'Account created',
            self::Login => 'Signed in',
            self::LoginRemembered => 'Signed in on a remembered device',
            self::LoginFailed => 'Failed sign-in attempt',
            self::Logout => 'Signed out',
            self::PasswordChanged => 'Password changed',
            self::PasswordResetRequested => 'Password reset requested',
            self::PasswordReset => 'Password reset by e-mail link',
            self::ProfileUpdated => 'Profile updated',
            self::DevicesForgotten => 'Remembered devices signed out',
        };
    }

    /** Visual tone used by the dashboard: success | danger | warning | neutral. */
    public function tone(): string
    {
        return match ($this) {
            self::Login, self::LoginRemembered, self::AccountCreated => 'success',
            self::LoginFailed => 'danger',
            self::PasswordChanged, self::PasswordReset, self::PasswordResetRequested, self::DevicesForgotten => 'warning',
            self::Logout, self::ProfileUpdated => 'neutral',
        };
    }

    /** @return list<string> */
    public static function signIns(): array
    {
        return [self::Login->value, self::LoginRemembered->value];
    }
}
