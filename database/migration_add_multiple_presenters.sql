-- Migration: Support multiple presenters per agenda item
-- Replaces agenda_items.presenter_id (single FK) with a many-to-many
-- agenda_item_presenters table. Backfills existing single-presenter data
-- before dropping the old column, so nothing is lost.

USE governance_board;

CREATE TABLE IF NOT EXISTS agenda_item_presenters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agenda_item_id INT NOT NULL,
    member_id INT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES board_members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_member (agenda_item_id, member_id),
    INDEX idx_agenda_item_position (agenda_item_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO agenda_item_presenters (agenda_item_id, member_id, position)
SELECT id, presenter_id, 0
FROM agenda_items
WHERE presenter_id IS NOT NULL;

-- Drop the old single-presenter foreign key (name varies by install
-- history, so look it up rather than assuming e.g. agenda_items_ibfk_2)
-- and then the column itself.
SET @fk_name = (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'agenda_items'
      AND COLUMN_NAME = 'presenter_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);
SET @drop_fk_sql = IF(@fk_name IS NOT NULL,
    CONCAT('ALTER TABLE agenda_items DROP FOREIGN KEY `', @fk_name, '`'),
    'SELECT 1');
PREPARE stmt FROM @drop_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE agenda_items DROP COLUMN presenter_id;
