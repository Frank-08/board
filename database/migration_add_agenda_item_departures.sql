-- Migration: Record members who left the room during an agenda item
-- (e.g. a declared conflict of interest), entered while taking minutes.

USE governance_board;

CREATE TABLE IF NOT EXISTS agenda_item_departures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agenda_item_id INT NOT NULL,
    member_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    returned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES board_members(id) ON DELETE CASCADE,
    INDEX idx_agenda_item (agenda_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
