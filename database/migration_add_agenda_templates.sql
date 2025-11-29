-- Migration: Add agenda templates table for meeting types
-- This allows defining default agenda items that will be auto-created when a new meeting is created

CREATE TABLE IF NOT EXISTS agenda_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_type_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    item_type ENUM('Discussion', 'Action Item', 'Vote', 'Information', 'Presentation') DEFAULT 'Discussion',
    duration_minutes INT,
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_type_id) REFERENCES meeting_types(id) ON DELETE CASCADE,
    INDEX idx_meeting_type_position (meeting_type_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some common default agenda template items for the first meeting type (Standing Committee)
INSERT INTO agenda_templates (meeting_type_id, title, description, item_type, position) VALUES
(1, 'Call to Order', 'Chair calls the meeting to order', 'Information', 0),
(1, 'Approval of Agenda', 'Review and approve the meeting agenda', 'Vote', 1),
(1, 'Approval of Previous Minutes', 'Review and approve minutes from the previous meeting', 'Vote', 2),
(1, 'Old Business', 'Discussion of unfinished business from previous meetings', 'Discussion', 3),
(1, 'New Business', 'Discussion of new items brought before the committee', 'Discussion', 4),
(1, 'Adjournment', 'Motion to adjourn the meeting', 'Vote', 5);

