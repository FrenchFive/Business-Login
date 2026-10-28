-- Keystone schema for SQLite (the zero-configuration default).
-- Applied automatically on first run by App\Database\Database::migrate().
-- Timestamps are UTC ISO-8601 text (e.g. 2026-01-31T09:15:00Z), so they sort and compare as strings.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version     INTEGER PRIMARY KEY,
    applied_at  TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS organizations (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    slug        TEXT    NOT NULL UNIQUE,
    created_at  TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id     INTEGER NOT NULL REFERENCES organizations (id) ON DELETE CASCADE,
    name                TEXT    NOT NULL,
    email               TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash       TEXT    NOT NULL,
    role                TEXT    NOT NULL DEFAULT 'member' CHECK (role IN ('owner', 'admin', 'member')),
    job_title           TEXT,
    last_login_at       TEXT,
    password_changed_at TEXT,
    created_at          TEXT    NOT NULL,
    updated_at          TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS users_organization_idx ON users (organization_id);

-- Audit trail: sign-ins, failures, password changes...
CREATE TABLE IF NOT EXISTS security_events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users (id) ON DELETE CASCADE,
    email       TEXT    NOT NULL,
    event       TEXT    NOT NULL,
    ip_address  TEXT    NOT NULL,
    user_agent  TEXT    NOT NULL DEFAULT '',
    created_at  TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS security_events_user_idx ON security_events (user_id, id);

-- "Remember me" tokens (selector/validator pattern: only a hash of the validator is stored).
CREATE TABLE IF NOT EXISTS remember_tokens (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    selector        TEXT    NOT NULL UNIQUE,
    validator_hash  TEXT    NOT NULL,
    user_agent      TEXT    NOT NULL DEFAULT '',
    ip_address      TEXT    NOT NULL DEFAULT '',
    expires_at      TEXT    NOT NULL,
    created_at      TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS remember_tokens_user_idx ON remember_tokens (user_id);

-- One-time password reset links (only a hash of the token is stored).
CREATE TABLE IF NOT EXISTS password_resets (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    token_hash  TEXT    NOT NULL UNIQUE,
    expires_at  TEXT    NOT NULL,
    used_at     TEXT,
    created_at  TEXT    NOT NULL
);

-- Fixed-window rate limiter buckets (brute-force protection).
CREATE TABLE IF NOT EXISTS rate_limits (
    bucket    TEXT    PRIMARY KEY,
    hits      INTEGER NOT NULL,
    reset_at  INTEGER NOT NULL
);
