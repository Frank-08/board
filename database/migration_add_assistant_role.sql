-- Migration: Add the 'Assistant' user role - for an automated assistant
-- (e.g. Claude via an API key). It can read everything, file resolutions
-- under categories, manage action items, draft (not approve) minutes and
-- propose agenda items for clerk review. See PERMISSIONS in config/auth.php.

USE governance_board;

ALTER TABLE users
    MODIFY COLUMN role ENUM('Admin', 'Clerk', 'Member', 'Viewer', 'Assistant') DEFAULT 'Viewer';
