-- Keystone schema for MySQL 8.0.16+ / MariaDB 10.6+ (InnoDB, utf8mb4).
-- Applied automatically on first run by App\Database\Database::migrate().
-- Timestamps are UTC ISO-8601 strings (e.g. 2026-01-31T09:15:00Z) in binary ASCII columns,
-- so they sort and compare exactly like they do on SQLite.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version     INT UNSIGNED NOT NULL PRIMARY KEY,
    applied_at  CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS organizations (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    slug        VARCHAR(140) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at  CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    UNIQUE KEY organizations_slug_unique (slug)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- utf8mb4_unicode_ci makes the e-mail UNIQUE index case-insensitive (like COLLATE NOCASE on SQLite).
CREATE TABLE IF NOT EXISTS users (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(120) NOT NULL,
    email               VARCHAR(254) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    role                VARCHAR(16) NOT NULL DEFAULT 'member',
    job_title           VARCHAR(120) NULL,
    last_login_at       CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
    password_changed_at CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at          CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    updated_at          CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    UNIQUE KEY users_email_unique (email),
    KEY users_organization_idx (organization_id),
    CONSTRAINT users_role_check CHECK (role IN ('owner', 'admin', 'member')),
    CONSTRAINT users_organization_fk FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Audit trail: sign-ins, failures, password changes...
CREATE TABLE IF NOT EXISTS security_events (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NULL,
    email       VARCHAR(254) NOT NULL,
    event       VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ip_address  VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_agent  VARCHAR(500) NOT NULL DEFAULT '',
    created_at  CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    KEY security_events_user_idx (user_id, id),
    CONSTRAINT security_events_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- "Remember me" tokens (selector/validator pattern: only a hash of the validator is stored).
CREATE TABLE IF NOT EXISTS remember_tokens (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    selector        CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    validator_hash  CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_agent      VARCHAR(500) NOT NULL DEFAULT '',
    ip_address      VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    expires_at      CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at      CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    UNIQUE KEY remember_tokens_selector_unique (selector),
    KEY remember_tokens_user_idx (user_id),
    CONSTRAINT remember_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- One-time password reset links (only a hash of the token is stored).
CREATE TABLE IF NOT EXISTS password_resets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at  CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    used_at     CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at  CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    UNIQUE KEY password_resets_token_unique (token_hash),
    CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Fixed-window rate limiter buckets (brute-force protection).
CREATE TABLE IF NOT EXISTS rate_limits (
    bucket    VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    hits      INT UNSIGNED NOT NULL,
    reset_at  BIGINT NOT NULL
) ENGINE = InnoDB;
