-- Security hardening (penetration test findings, Nov 2026)
--
--  * auth_attempts: throttling log for failed logins, failed 2FA codes and
--    password reset requests (config/auth.php).
--  * users.session_version: bumped on password change/reset, 2FA disable and
--    deactivation; sessions issued under an older version are rejected.
--  * users.must_change_password: forces a new password at next sign-in
--    (seeded admin account, passwords set by an admin).
--  * users.two_factor_last_step: last accepted TOTP time step, so a code
--    can't be replayed.
--
-- Reset tokens are now stored as SHA-256 hashes, so any outstanding
-- (plaintext) reset links are cleared - affected users just request a new one.
-- The code tolerates this migration not having been run yet (throttling and
-- replay protection are skipped and logged), but run it promptly.

USE governance_board;

CREATE TABLE IF NOT EXISTS auth_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(30) NOT NULL COMMENT 'login, 2fa, reset_request',
    identifier VARCHAR(255) NOT NULL COMMENT 'lower-cased username / email / user:<id>',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_identifier (action, identifier, created_at),
    INDEX idx_action_ip (action, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN session_version INT NOT NULL DEFAULT 0,
    ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN two_factor_last_step BIGINT NULL DEFAULT NULL;

-- Outstanding reset links were stored in plaintext
UPDATE users SET password_reset_token = NULL, password_reset_expires = NULL;

-- The seeded admin account, if its password was never changed
UPDATE users SET must_change_password = TRUE
WHERE username = 'admin'
  AND password_hash = '$2y$10$HzhW7DsplQeYwgYmKjwzd.IFBCm.lu32Qwl4/fxL6zKO9Ok0W/BEe';
