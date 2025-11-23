-- Migration: Add agenda_item_id to documents table
-- This migration adds support for linking documents to specific agenda items

USE governance_board;

-- Check if the 'agenda_item_id' column exists
SELECT
    COUNT(*)
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = 'governance_board'
    AND TABLE_NAME = 'documents'
    AND COLUMN_NAME = 'agenda_item_id' INTO @column_exists;

-- If the column doesn't exist, add it
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE documents ADD COLUMN agenda_item_id INT NULL AFTER meeting_id, ADD FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE SET NULL, ADD INDEX idx_agenda_item (agenda_item_id);', 
    'SELECT ''Column agenda_item_id already exists in documents table.'';');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

