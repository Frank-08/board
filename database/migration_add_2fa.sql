-- Migration: Add 2FA (Two-Factor Authentication) support
-- Run this migration to add 2FA support to your existing database

USE governance_board;

-- Add 2FA fields to users table
ALTER TABLE users 
ADD COLUMN two_factor_secret VARCHAR(255) NULL DEFAULT NULL,
ADD COLUMN two_factor_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN two_factor_backup_codes TEXT NULL DEFAULT NULL COMMENT 'JSON array of backup codes',
ADD COLUMN two_factor_verified_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when 2FA was last verified during setup';

-- Add index for 2FA enabled users
CREATE INDEX idx_two_factor_enabled ON users(two_factor_enabled);

