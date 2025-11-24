-- Migration: Rename PiC to Presbytery in Council
-- This migration updates the meeting type name from "PiC" to "Presbytery in Council"

USE governance_board;

-- Update the meeting type name
UPDATE meeting_types 
SET name = 'Presbytery in Council', 
    description = 'Presbytery in Council meeting type'
WHERE name = 'PiC';

-- Verify the update
SELECT id, name, description 
FROM meeting_types 
WHERE name = 'Presbytery in Council';

