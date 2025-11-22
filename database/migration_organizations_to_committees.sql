-- Migration: Rename Organizations to Committees and enable multiple committee memberships
-- This migration:
-- 1. Renames organizations table to committees
-- 2. Creates a junction table for many-to-many relationship
-- 3. Migrates existing data
-- 4. Removes organization_id from board_members

USE governance_board;

-- Step 1: Rename organizations table to committees
RENAME TABLE organizations TO committees;

-- Step 2: Create junction table for many-to-many relationship
CREATE TABLE IF NOT EXISTS committee_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    committee_id INT NOT NULL,
    member_id INT NOT NULL,
    role ENUM('Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Member', 'Ex-officio') DEFAULT 'Member',
    start_date DATE,
    end_date DATE,
    status ENUM('Active', 'Inactive', 'Resigned', 'Terminated') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES board_members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_committee_member (committee_id, member_id),
    INDEX idx_committee (committee_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Migrate existing board_members data to committee_members
INSERT INTO committee_members (committee_id, member_id, role, start_date, end_date, status)
SELECT organization_id, id, role, start_date, end_date, status
FROM board_members
WHERE organization_id IS NOT NULL;

-- Step 4: Remove organization_id and role/status fields from board_members (they're now in committee_members)
-- Note: We keep these fields for backward compatibility initially, but they should be removed
-- ALTER TABLE board_members DROP FOREIGN KEY board_members_ibfk_1;
-- ALTER TABLE board_members DROP COLUMN organization_id;
-- ALTER TABLE board_members DROP COLUMN role;
-- ALTER TABLE board_members DROP COLUMN start_date;
-- ALTER TABLE board_members DROP COLUMN end_date;
-- ALTER TABLE board_members DROP COLUMN status;

-- Step 5: Update meetings table to reference committees
ALTER TABLE meetings 
CHANGE COLUMN organization_id committee_id INT NOT NULL,
ADD FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE;

-- Step 6: Update documents table to reference committees
ALTER TABLE documents 
CHANGE COLUMN organization_id committee_id INT,
ADD FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE;

-- Step 7: Update indexes
ALTER TABLE meetings DROP INDEX idx_organization_date;
ALTER TABLE meetings ADD INDEX idx_committee_date (committee_id, scheduled_date);

ALTER TABLE documents DROP INDEX idx_organization;
ALTER TABLE documents ADD INDEX idx_committee (committee_id);

