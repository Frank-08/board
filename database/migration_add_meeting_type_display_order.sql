-- Migration: add meeting_types.display_order
-- api/meeting_types.php orders the meeting type list by display_order, but no
-- earlier schema/migration created the column (some installs added it by hand).
-- Guarded via information_schema so it is safe to run on installs that already
-- have the column (works on both MySQL and MariaDB).

USE governance_board;

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'meeting_types'
      AND COLUMN_NAME = 'display_order'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE meeting_types ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER shortcode',
    'SELECT ''meeting_types.display_order already exists, skipping'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
