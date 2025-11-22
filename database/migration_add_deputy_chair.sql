-- Migration to add 'Deputy Chair' and 'Ex-officio' to board_members role enum
-- Run this if your database was created before these roles were added

USE governance_board;

-- First, check current enum values
-- SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'governance_board' 
-- AND TABLE_NAME = 'board_members' 
-- AND COLUMN_NAME = 'role';

-- Alter the role column to include all current enum values
ALTER TABLE board_members 
MODIFY COLUMN role ENUM('Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Member', 'Ex-officio') 
DEFAULT 'Member';

-- Verify the change
-- SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = 'governance_board' 
-- AND TABLE_NAME = 'board_members' 
-- AND COLUMN_NAME = 'role';

