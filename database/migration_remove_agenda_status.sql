-- Migration: Remove status column from agenda_items table
-- This migration removes the status field from agenda items as it's not needed

USE governance_board;

-- Drop the status column from agenda_items table
ALTER TABLE agenda_items DROP COLUMN status;

