-- Migration: add password reset columns to users table
ALTER TABLE users
  ADD COLUMN password_reset_token_hash VARCHAR(255) NULL,
  ADD COLUMN password_reset_expires DATETIME NULL,
  ADD COLUMN password_reset_used TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN password_reset_requested_at DATETIME NULL;

-- Optional index
CREATE INDEX idx_password_reset_expires ON users (password_reset_expires);
