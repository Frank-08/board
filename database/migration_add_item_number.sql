-- Migration: Add item_number field to agenda_items table
-- This migration adds support for formatted item numbers in format YY.MM.SEQ

USE governance_board;

-- Check if the 'item_number' column exists
SELECT
    COUNT(*)
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = 'governance_board'
    AND TABLE_NAME = 'agenda_items'
    AND COLUMN_NAME = 'item_number' INTO @column_exists;

-- If the column doesn't exist, add it
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE agenda_items ADD COLUMN item_number VARCHAR(20) NULL AFTER position, ADD INDEX idx_item_number (item_number);', 
    'SELECT ''Column item_number already exists in agenda_items table.'';');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing agenda items with item numbers based on their meeting dates
UPDATE agenda_items ai
JOIN meetings m ON ai.meeting_id = m.id
SET ai.item_number = CONCAT(
    DATE_FORMAT(m.scheduled_date, '%y'), '.',
    MONTH(m.scheduled_date), '.',
    ai.position + 1
)
WHERE ai.item_number IS NULL;

