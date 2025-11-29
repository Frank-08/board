-- Migration: Fix users role ENUM to include all roles
-- Run this if you can only save users with certain roles

USE governance_board;

-- Update the role column ENUM to include all valid roles
ALTER TABLE users 
MODIFY COLUMN role ENUM('Admin', 'Clerk', 'Member', 'Viewer') DEFAULT 'Viewer';

