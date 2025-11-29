-- Migration: Fix users table - add missing columns
-- Run this if you're getting "Unknown column 'board_member_id'" errors

USE governance_board;

-- Add board_member_id column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS board_member_id INT NULL AFTER role,
ADD CONSTRAINT fk_users_board_member FOREIGN KEY (board_member_id) REFERENCES board_members(id) ON DELETE SET NULL;

-- Add updated_at column if it doesn't exist  
ALTER TABLE users
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add indexes if they don't exist (will silently fail if they do)
CREATE INDEX IF NOT EXISTS idx_board_member ON users(board_member_id);

