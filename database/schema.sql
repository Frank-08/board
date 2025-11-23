-- Governance Board Management Database Schema
CREATE DATABASE IF NOT EXISTS governance_board CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE governance_board;

-- Table for committees
CREATE TABLE IF NOT EXISTS committees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for board members (no longer tied to single committee)
CREATE TABLE IF NOT EXISTS board_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    title VARCHAR(100),
    bio TEXT,
    photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Junction table for many-to-many relationship between committees and members
CREATE TABLE IF NOT EXISTS committee_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    committee_id INT NOT NULL,
    member_id INT NOT NULL,
    role ENUM('Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Member', 'Ex-officio') DEFAULT 'Member',
    start_date DATE,
    end_date DATE,
    status ENUM('Active', 'Inactive', 'Resigned', 'Terminated') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES board_members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_committee_member (committee_id, member_id),
    INDEX idx_committee (committee_id),
    INDEX idx_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for board meetings
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    committee_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    meeting_type ENUM('Standing Committee', 'PiC', 'PRC', 'RPC', 'Workshop') DEFAULT 'Standing Committee',
    scheduled_date DATETIME NOT NULL,
    location VARCHAR(255),
    virtual_link VARCHAR(255),
    quorum_required INT DEFAULT 0,
    quorum_met BOOLEAN DEFAULT FALSE,
    status ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled', 'Postponed') DEFAULT 'Scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE,
    INDEX idx_committee_date (committee_id, scheduled_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for meeting attendees
CREATE TABLE IF NOT EXISTS meeting_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    member_id INT NOT NULL,
    attendance_status ENUM('Present', 'Absent', 'Excused', 'Late') DEFAULT 'Absent',
    arrival_time DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES board_members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_meeting_member (meeting_id, member_id),
    INDEX idx_meeting (meeting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for meeting agendas
CREATE TABLE IF NOT EXISTS agenda_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    item_type ENUM('Discussion', 'Action Item', 'Vote', 'Information', 'Presentation') DEFAULT 'Discussion',
    presenter_id INT,
    duration_minutes INT,
    position INT NOT NULL DEFAULT 0,
    outcome TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (presenter_id) REFERENCES board_members(id) ON DELETE SET NULL,
    INDEX idx_meeting_position (meeting_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for meeting minutes
CREATE TABLE IF NOT EXISTS minutes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    prepared_by INT,
    approved_by INT,
    content TEXT NOT NULL,
    action_items TEXT,
    next_meeting_date DATETIME,
    status ENUM('Draft', 'Review', 'Approved', 'Published') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (prepared_by) REFERENCES board_members(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES board_members(id) ON DELETE SET NULL,
    UNIQUE KEY unique_meeting_minutes (meeting_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for agenda item comments in minutes
CREATE TABLE IF NOT EXISTS minutes_agenda_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    minutes_id INT NOT NULL,
    agenda_item_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (minutes_id) REFERENCES minutes(id) ON DELETE CASCADE,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_minutes_agenda_item (minutes_id, agenda_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for resolutions
CREATE TABLE IF NOT EXISTS resolutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    agenda_item_id INT,
    resolution_number VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    motion_moved_by INT,
    motion_seconded_by INT,
    vote_type ENUM('Unanimous', 'Majority', 'Split', 'Tabled', 'Withdrawn') DEFAULT NULL,
    votes_for INT DEFAULT 0,
    votes_against INT DEFAULT 0,
    votes_abstain INT DEFAULT 0,
    status ENUM('Proposed', 'Passed', 'Failed', 'Tabled', 'Withdrawn') DEFAULT 'Proposed',
    effective_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE SET NULL,
    FOREIGN KEY (motion_moved_by) REFERENCES board_members(id) ON DELETE SET NULL,
    FOREIGN KEY (motion_seconded_by) REFERENCES board_members(id) ON DELETE SET NULL,
    INDEX idx_meeting (meeting_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for documents
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    committee_id INT,
    meeting_id INT,
    agenda_item_id INT,
    document_type ENUM('Agenda', 'Minutes', 'Resolution', 'Report', 'Policy', 'Other') DEFAULT 'Other',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (agenda_item_id) REFERENCES agenda_items(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES board_members(id) ON DELETE SET NULL,
    INDEX idx_committee (committee_id),
    INDEX idx_meeting (meeting_id),
    INDEX idx_agenda_item (agenda_item_id),
    INDEX idx_type (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default committee
INSERT INTO committees (name, description, email) VALUES 
('Sample Committee', 'A sample committee for governance board management', 'admin@example.com');

-- Insert sample board members
INSERT INTO board_members (first_name, last_name, email, title) VALUES
('John', 'Smith', 'john.smith@example.com', 'Chief Executive Officer'),
('Jane', 'Doe', 'jane.doe@example.com', 'Chief Financial Officer'),
('Robert', 'Johnson', 'robert.johnson@example.com', 'Legal Counsel'),
('Mary', 'Williams', 'mary.williams@example.com', 'Director');

-- Insert sample committee memberships
INSERT INTO committee_members (committee_id, member_id, role, status) VALUES
(1, 1, 'Chair', 'Active'),
(1, 2, 'Treasurer', 'Active'),
(1, 3, 'Secretary', 'Active'),
(1, 4, 'Member', 'Active');
