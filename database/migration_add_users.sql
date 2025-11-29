-- Migration: Add users table for authentication
-- Run this migration to add authentication support to your existing database

USE governance_board;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Clerk', 'Member', 'Viewer') DEFAULT 'Viewer',
    board_member_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (board_member_id) REFERENCES board_members(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_board_member (board_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
-- IMPORTANT: Change this password immediately after first login!
-- Default credentials: admin / changeme123
-- Password hash generated with: password_hash('changeme123', PASSWORD_DEFAULT)
INSERT INTO users (username, password_hash, email, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'Admin', TRUE);

-- Note: The password hash above is for 'changeme123'
-- In production, generate a new hash using PHP:
-- php -r "echo password_hash('your_secure_password', PASSWORD_DEFAULT);"

