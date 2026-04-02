-- Migration: Add sharepoint_url to documents table
-- This migration adds support for storing SharePoint links instead of uploaded files.

USE governance_board;

SELECT
    COUNT(*)
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = 'governance_board'
    AND TABLE_NAME = 'documents'
    AND COLUMN_NAME = 'sharepoint_url' INTO @column_exists;

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE documents ADD COLUMN sharepoint_url VARCHAR(2048) NULL AFTER description;',
    'SELECT ''Column sharepoint_url already exists in documents table.'';');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
