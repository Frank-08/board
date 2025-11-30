-- Migration: Update resolutions table schema
-- This migration:
-- 1. Removes proposer/seconder fields (motion_moved_by, motion_seconded_by)
-- 2. Removes vote count fields (votes_for, votes_against, votes_abstain)
-- 3. Updates status ENUM to: Proposed, Consensus, Agreement, Failed
-- 4. Updates vote_type ENUM to: Cards, Formal Procedures, Show of Hands
--
-- IMPORTANT: Backup your database before running this migration!
-- Data migration notes:
--   - Status 'Passed' will be converted to 'Agreement'
--   - Status 'Tabled' will be converted to 'Proposed'
--   - Status 'Withdrawn' will be converted to 'Failed'
--   - Vote types that don't match new values will be set to NULL

USE governance_board;

-- Step 1: Migrate existing data to match new ENUM values
-- Update status values that won't exist in new ENUM
UPDATE resolutions 
SET status = 'Agreement' 
WHERE status = 'Passed';

UPDATE resolutions 
SET status = 'Proposed' 
WHERE status = 'Tabled';

UPDATE resolutions 
SET status = 'Failed' 
WHERE status = 'Withdrawn';

-- Update vote_type values that won't exist in new ENUM (set to NULL)
UPDATE resolutions 
SET vote_type = NULL 
WHERE vote_type NOT IN ('Cards', 'Formal Procedures', 'Show of Hands')
   OR vote_type IN ('Unanimous', 'Majority', 'Split', 'Tabled', 'Withdrawn');

-- Step 2: Drop foreign key constraints for motion_moved_by and motion_seconded_by
-- Find and drop the foreign key constraints dynamically
SET @fk1 = NULL;
SET @fk2 = NULL;

SELECT CONSTRAINT_NAME INTO @fk1
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'resolutions' 
  AND COLUMN_NAME = 'motion_moved_by' 
  AND REFERENCED_TABLE_NAME IS NOT NULL
LIMIT 1;

SELECT CONSTRAINT_NAME INTO @fk2
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'resolutions' 
  AND COLUMN_NAME = 'motion_seconded_by' 
  AND REFERENCED_TABLE_NAME IS NOT NULL
LIMIT 1;

SET @sql1 = IF(@fk1 IS NOT NULL, CONCAT('ALTER TABLE resolutions DROP FOREIGN KEY ', @fk1), 'SELECT 1');
SET @sql2 = IF(@fk2 IS NOT NULL, CONCAT('ALTER TABLE resolutions DROP FOREIGN KEY ', @fk2), 'SELECT 1');

PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Step 3: Drop the columns
-- Note: If columns don't exist, these statements will fail - that's okay, just continue
ALTER TABLE resolutions DROP COLUMN motion_moved_by;
ALTER TABLE resolutions DROP COLUMN motion_seconded_by;
ALTER TABLE resolutions DROP COLUMN votes_for;
ALTER TABLE resolutions DROP COLUMN votes_against;
ALTER TABLE resolutions DROP COLUMN votes_abstain;

-- Step 4: Update ENUMs
-- Note: MySQL requires MODIFY COLUMN to change ENUM values
ALTER TABLE resolutions 
MODIFY COLUMN status ENUM('Proposed', 'Consensus', 'Agreement', 'Failed') DEFAULT 'Proposed';

ALTER TABLE resolutions 
MODIFY COLUMN vote_type ENUM('Cards', 'Formal Procedures', 'Show of Hands') DEFAULT NULL;

-- Verification: Check the table structure
-- Run these queries to verify the migration was successful:
-- DESCRIBE resolutions;
-- SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS 
-- WHERE TABLE_SCHEMA = 'governance_board' AND TABLE_NAME = 'resolutions';

