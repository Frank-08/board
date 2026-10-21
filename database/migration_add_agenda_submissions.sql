-- Migration: agenda item submissions. Members propose an agenda item for an
-- upcoming meeting with a link to their report on SharePoint; a Clerk/Admin
-- (manage_agenda) accepts it - which creates the agenda item and a linked
-- documents row - or declines it with a note back to the submitter.

USE governance_board;

CREATE TABLE IF NOT EXISTS agenda_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    submitted_by INT NULL,
    submitter_member_id INT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT,
    proposal TEXT,
    item_type ENUM('Discussion', 'Action Item', 'Vote', 'Information', 'Presentation') DEFAULT 'Discussion',
    decision_method ENUM('Consensus', 'Formal Majority', 'Referral', 'None') DEFAULT 'None',
    report_type ENUM('Written', 'Verbal') DEFAULT 'Written',
    duration_minutes INT DEFAULT NULL,
    sharepoint_url VARCHAR(2048),
    status ENUM('Pending', 'Accepted', 'Declined', 'Withdrawn') DEFAULT 'Pending',
    review_notes TEXT,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    agenda_item_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (submitter_member_id) REFERENCES board_members(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE SET NULL,
    INDEX idx_meeting_status (meeting_id, status),
    INDEX idx_submitted_by (submitted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
