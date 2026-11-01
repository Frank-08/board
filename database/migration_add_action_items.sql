-- Migration: Add a structured action items list (owner, due date, status)
-- per meeting, tracked after the meeting on action_items.php and the
-- meeting's Action Items tab. The free-text minutes.action_items column is
-- kept so existing minutes still show their action items.

USE governance_board;

CREATE TABLE IF NOT EXISTS action_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    agenda_item_id INT NULL,
    resolution_id INT NULL,
    description TEXT NOT NULL,
    assigned_member_id INT NULL,
    assigned_to_name VARCHAR(150) NULL,
    due_date DATE NULL,
    status ENUM('Open', 'In Progress', 'Done', 'Cancelled') NOT NULL DEFAULT 'Open',
    notes TEXT NULL,
    completed_at TIMESTAMP NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE SET NULL,
    FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_member_id) REFERENCES board_members(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_meeting (meeting_id),
    INDEX idx_status_due (status, due_date),
    INDEX idx_assigned_member (assigned_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
