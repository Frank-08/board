-- Migration: Convert Committees to Meeting Types
-- This migration:
-- 1. Creates meeting_types table
-- 2. Migrates ENUM values and committee data to meeting_types
-- 3. Renames committee_members to meeting_type_members
-- 4. Updates meetings table (committee_id → meeting_type_id, removes meeting_type ENUM)
-- 5. Updates documents table (committee_id → meeting_type_id)
-- 6. Updates all foreign keys and indexes

USE governance_board;

-- Step 1: Create meeting_types table
CREATE TABLE IF NOT EXISTS meeting_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Migrate ENUM values to meeting_types table
INSERT INTO meeting_types (name, description) VALUES
('Standing Committee', 'Regular standing committee meetings'),
('PiC', 'PiC meeting type'),
('PRC', 'PRC meeting type'),
('RPC', 'RPC meeting type'),
('Workshop', 'Workshop meetings')
ON DUPLICATE KEY UPDATE name=name;

-- Step 3: Migrate existing committees to meeting_types (if committees table exists)
-- Map committee names to meeting types, or create new meeting types
INSERT INTO meeting_types (name, description)
SELECT DISTINCT 
    CASE 
        WHEN c.name LIKE '%Standing%' OR c.name LIKE '%Committee%' THEN 'Standing Committee'
        WHEN c.name LIKE '%PiC%' THEN 'PiC'
        WHEN c.name LIKE '%PRC%' THEN 'PRC'
        WHEN c.name LIKE '%RPC%' THEN 'RPC'
        WHEN c.name LIKE '%Workshop%' THEN 'Workshop'
        ELSE c.name
    END as name,
    c.description
FROM committees c
WHERE NOT EXISTS (
    SELECT 1 FROM meeting_types mt 
    WHERE mt.name = CASE 
        WHEN c.name LIKE '%Standing%' OR c.name LIKE '%Committee%' THEN 'Standing Committee'
        WHEN c.name LIKE '%PiC%' THEN 'PiC'
        WHEN c.name LIKE '%PRC%' THEN 'PRC'
        WHEN c.name LIKE '%RPC%' THEN 'RPC'
        WHEN c.name LIKE '%Workshop%' THEN 'Workshop'
        ELSE c.name
    END
);

-- Step 4: Add meeting_type_id to meetings table (temporarily nullable)
ALTER TABLE meetings 
ADD COLUMN meeting_type_id INT NULL AFTER committee_id;

-- Step 5: Map existing meetings to meeting types based on meeting_type ENUM or committee
UPDATE meetings m
LEFT JOIN meeting_types mt ON (
    CASE m.meeting_type
        WHEN 'Standing Committee' THEN mt.name = 'Standing Committee'
        WHEN 'PiC' THEN mt.name = 'PiC'
        WHEN 'PRC' THEN mt.name = 'PRC'
        WHEN 'RPC' THEN mt.name = 'RPC'
        WHEN 'Workshop' THEN mt.name = 'Workshop'
        ELSE FALSE
    END
)
SET m.meeting_type_id = COALESCE(mt.id, 1); -- Default to first meeting type if no match

-- If meeting_type is NULL, try to map from committee
UPDATE meetings m
LEFT JOIN committees c ON m.committee_id = c.id
LEFT JOIN meeting_types mt ON (
    CASE 
        WHEN c.name LIKE '%Standing%' OR c.name LIKE '%Committee%' THEN mt.name = 'Standing Committee'
        WHEN c.name LIKE '%PiC%' THEN mt.name = 'PiC'
        WHEN c.name LIKE '%PRC%' THEN mt.name = 'PRC'
        WHEN c.name LIKE '%RPC%' THEN mt.name = 'RPC'
        WHEN c.name LIKE '%Workshop%' THEN mt.name = 'Workshop'
        ELSE FALSE
    END
)
SET m.meeting_type_id = COALESCE(m.meeting_type_id, mt.id, 1)
WHERE m.meeting_type_id IS NULL;

-- Step 6: Make meeting_type_id NOT NULL and add foreign key
ALTER TABLE meetings 
MODIFY COLUMN meeting_type_id INT NOT NULL,
ADD FOREIGN KEY (meeting_type_id) REFERENCES meeting_types(id) ON DELETE CASCADE;

-- Step 7: Drop old committee_id column and meeting_type ENUM from meetings
ALTER TABLE meetings 
DROP FOREIGN KEY meetings_ibfk_1,
DROP COLUMN committee_id,
DROP COLUMN meeting_type;

-- Step 8: Update indexes on meetings table
ALTER TABLE meetings 
DROP INDEX IF EXISTS idx_committee_date,
ADD INDEX idx_meeting_type_date (meeting_type_id, scheduled_date);

-- Step 9: Rename committee_members to meeting_type_members
-- First, create the new table structure
CREATE TABLE IF NOT EXISTS meeting_type_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_type_id INT NOT NULL,
    member_id INT NOT NULL,
    role ENUM('Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Member', 'Ex-officio') DEFAULT 'Member',
    start_date DATE,
    end_date DATE,
    status ENUM('Active', 'Inactive', 'Resigned', 'Terminated') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_type_id) REFERENCES meeting_types(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES board_members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_meeting_type_member (meeting_type_id, member_id),
    INDEX idx_meeting_type (meeting_type_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 10: Migrate committee_members to meeting_type_members
-- Map committee_id to meeting_type_id based on committee name or default to first meeting type
INSERT INTO meeting_type_members (meeting_type_id, member_id, role, start_date, end_date, status, created_at, updated_at)
SELECT 
    COALESCE(
        (SELECT mt.id FROM meeting_types mt 
         JOIN committees c ON (
            CASE 
                WHEN c.name LIKE '%Standing%' OR c.name LIKE '%Committee%' THEN mt.name = 'Standing Committee'
                WHEN c.name LIKE '%PiC%' THEN mt.name = 'PiC'
                WHEN c.name LIKE '%PRC%' THEN mt.name = 'PRC'
                WHEN c.name LIKE '%RPC%' THEN mt.name = 'RPC'
                WHEN c.name LIKE '%Workshop%' THEN mt.name = 'Workshop'
                ELSE mt.name = c.name
            END
         )
         WHERE c.id = cm.committee_id LIMIT 1),
        1
    ) as meeting_type_id,
    cm.member_id,
    cm.role,
    cm.start_date,
    cm.end_date,
    cm.status,
    cm.created_at,
    cm.updated_at
FROM committee_members cm
ON DUPLICATE KEY UPDATE 
    role = VALUES(role),
    status = VALUES(status);

-- Step 11: Drop old committee_members table
DROP TABLE IF EXISTS committee_members;

-- Step 12: Update documents table
-- Add meeting_type_id column
ALTER TABLE documents 
ADD COLUMN meeting_type_id INT NULL AFTER committee_id;

-- Map existing committee_id to meeting_type_id
UPDATE documents d
LEFT JOIN committees c ON d.committee_id = c.id
LEFT JOIN meeting_types mt ON (
    CASE 
        WHEN c.name LIKE '%Standing%' OR c.name LIKE '%Committee%' THEN mt.name = 'Standing Committee'
        WHEN c.name LIKE '%PiC%' THEN mt.name = 'PiC'
        WHEN c.name LIKE '%PRC%' THEN mt.name = 'PRC'
        WHEN c.name LIKE '%RPC%' THEN mt.name = 'RPC'
        WHEN c.name LIKE '%Workshop%' THEN mt.name = 'Workshop'
        ELSE mt.name = c.name
    END
)
SET d.meeting_type_id = mt.id
WHERE d.committee_id IS NOT NULL;

-- Drop old committee_id foreign key and column
ALTER TABLE documents 
DROP FOREIGN KEY IF EXISTS documents_ibfk_1,
DROP INDEX IF EXISTS idx_committee,
DROP COLUMN committee_id;

-- Add new foreign key and index
ALTER TABLE documents 
ADD FOREIGN KEY (meeting_type_id) REFERENCES meeting_types(id) ON DELETE CASCADE,
ADD INDEX idx_meeting_type (meeting_type_id);

-- Step 13: Drop committees table (optional - comment out if you want to keep it for reference)
-- DROP TABLE IF EXISTS committees;

