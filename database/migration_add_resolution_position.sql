-- Migration: Add a position column to resolutions so lettered clauses
-- (multiple resolutions linked to the same agenda item, rendered as a
-- single a)/b)/c) list) can be manually reordered instead of always
-- showing in creation order.

USE governance_board;

ALTER TABLE resolutions
    ADD COLUMN position INT NOT NULL DEFAULT 0 AFTER effective_date,
    ADD INDEX idx_agenda_item_position (agenda_item_id, position);

-- Backfill existing rows so clauses already linked to the same agenda item
-- keep their current (creation-order) sequence rather than all collapsing
-- to position 0.
UPDATE resolutions r
JOIN (
    SELECT id, ROW_NUMBER() OVER (PARTITION BY agenda_item_id ORDER BY created_at ASC) - 1 AS rn
    FROM resolutions
) ranked ON ranked.id = r.id
SET r.position = ranked.rn;
