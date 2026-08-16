-- Migration: UCA Manual for Meetings compliance
-- Adds session types, decision methods, expanded resolutions, amendments, procedural proposals

USE governance_board;

-- Agenda items: UCA session metadata
ALTER TABLE agenda_items
    ADD COLUMN session_type ENUM('Worship', 'General', 'Information', 'Deliberative', 'Decision') DEFAULT NULL AFTER item_type,
    ADD COLUMN decision_method ENUM('Consensus', 'Formal Majority', 'Referral', 'None') DEFAULT 'None' AFTER session_type,
    ADD COLUMN speech_limit_minutes INT DEFAULT NULL AFTER decision_method;

ALTER TABLE agenda_templates
    ADD COLUMN session_type ENUM('Worship', 'General', 'Information', 'Deliberative', 'Decision') DEFAULT NULL AFTER item_type,
    ADD COLUMN decision_method ENUM('Consensus', 'Formal Majority', 'Referral', 'None') DEFAULT 'None' AFTER session_type,
    ADD COLUMN speech_limit_minutes INT DEFAULT NULL AFTER decision_method;

-- Expand resolutions for formal majority and consensus record-keeping
ALTER TABLE resolutions
    ADD COLUMN decision_method ENUM('Consensus', 'Formal Majority', 'Referral') DEFAULT 'Consensus' AFTER description,
    ADD COLUMN motion_moved_by INT NULL AFTER decision_method,
    ADD COLUMN motion_seconded_by INT NULL AFTER motion_moved_by,
    ADD COLUMN votes_for INT DEFAULT NULL AFTER motion_seconded_by,
    ADD COLUMN votes_against INT DEFAULT NULL AFTER votes_for,
    ADD COLUMN votes_abstain INT DEFAULT NULL AFTER votes_against,
    ADD COLUMN casting_vote_used BOOLEAN DEFAULT FALSE AFTER votes_abstain,
    ADD COLUMN referral_body VARCHAR(255) DEFAULT NULL AFTER casting_vote_used,
    ADD COLUMN referral_scope TEXT DEFAULT NULL AFTER referral_body,
    ADD COLUMN clerk_notes TEXT DEFAULT NULL AFTER referral_scope;

ALTER TABLE resolutions
    ADD CONSTRAINT fk_resolutions_moved_by FOREIGN KEY (motion_moved_by) REFERENCES board_members(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_resolutions_seconded_by FOREIGN KEY (motion_seconded_by) REFERENCES board_members(id) ON DELETE SET NULL;

-- Extend vote_type and status ENUMs
ALTER TABLE resolutions
    MODIFY COLUMN vote_type ENUM('Voices', 'Show of Hands', 'Cards', 'Written Ballot', 'Formal Procedures') DEFAULT NULL;

ALTER TABLE resolutions
    MODIFY COLUMN status ENUM('Proposed', 'Consensus', 'Agreement', 'Failed', 'Withdrawn', 'Lapsed') DEFAULT 'Proposed';

-- Resolution amendments (formal majority)
CREATE TABLE IF NOT EXISTS resolution_amendments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resolution_id INT NOT NULL,
    amendment_text TEXT NOT NULL,
    moved_by INT NULL,
    seconded_by INT NULL,
    status ENUM('Proposed', 'Carried', 'Lost', 'Lapsed', 'Withdrawn') DEFAULT 'Proposed',
    votes_for INT DEFAULT NULL,
    votes_against INT DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    parent_amendment_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE CASCADE,
    FOREIGN KEY (moved_by) REFERENCES board_members(id) ON DELETE SET NULL,
    FOREIGN KEY (seconded_by) REFERENCES board_members(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_amendment_id) REFERENCES resolution_amendments(id) ON DELETE SET NULL,
    INDEX idx_resolution (resolution_id),
    INDEX idx_position (resolution_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Procedural proposals log
CREATE TABLE IF NOT EXISTS procedural_proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    agenda_item_id INT NULL,
    resolution_id INT NULL,
    proposal_type ENUM(
        'UseOfProcedures', 'OrderOfDay', 'Adjournment', 'PrivateSitting',
        'Referral', 'DecisionNow', 'WithdrawMotion', 'PreviousQuestion',
        'Closure', 'Reconsideration', 'PointOfOrder'
    ) NOT NULL,
    proposed_by INT NULL,
    seconded_by INT NULL,
    outcome ENUM('Carried', 'Lost', 'Lapsed', 'RuledOn', 'Pending') DEFAULT 'Pending',
    requires_leave BOOLEAN DEFAULT FALSE,
    notes TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE SET NULL,
    FOREIGN KEY (resolution_id) REFERENCES resolutions(id) ON DELETE SET NULL,
    FOREIGN KEY (proposed_by) REFERENCES board_members(id) ON DELETE SET NULL,
    FOREIGN KEY (seconded_by) REFERENCES board_members(id) ON DELETE SET NULL,
    INDEX idx_meeting (meeting_id),
    INDEX idx_agenda_item (agenda_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
