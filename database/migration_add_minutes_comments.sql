-- Migration: Add minutes_agenda_comments table
-- This migration adds support for comments on agenda items within minutes

USE governance_board;

-- Create table for agenda item comments in minutes
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

