-- Migration: Add password reset functionality to users table
-- Run this migration to add password reset support

USE governance_board;

-- Add password reset token fields to users table
ALTER TABLE users 
ADD COLUMN password_reset_token VARCHAR(64) NULL AFTER password_hash,
ADD COLUMN password_reset_expires TIMESTAMP NULL AFTER password_reset_token;

-- Add index on password_reset_token for quick lookups
CREATE INDEX idx_password_reset_token ON users(password_reset_token);

