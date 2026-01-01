-- Migration: Add parent_id and sub_position to agenda_items
-- Adds hierarchical support for sub-agenda items (lettered: a, b, c...)

USE governance_board;

-- Check if the 'parent_id' column exists
SELECT
    COUNT(*)
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = 'governance_board'
    AND TABLE_NAME = 'agenda_items'
    AND COLUMN_NAME = 'parent_id' INTO @column_exists;

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE agenda_items 
        ADD COLUMN parent_id INT NULL AFTER item_number,
        ADD COLUMN sub_position INT NOT NULL DEFAULT 0 AFTER position,
        ADD INDEX idx_parent (parent_id),
        ADD CONSTRAINT fk_agenda_parent FOREIGN KEY (parent_id) REFERENCES agenda_items(id) ON DELETE CASCADE;',
    'SELECT "Columns parent_id and sub_position already exist in agenda_items table.";'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Initialize sub_position for existing rows (if any were added without default)
UPDATE agenda_items SET sub_position = 0 WHERE sub_position IS NULL;
