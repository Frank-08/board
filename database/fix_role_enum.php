<?php
/**
 * Script to fix the role ENUM in the database
 * Run this once if you're unable to set "Deputy Chair" role
 * 
 * Usage: php database/fix_role_enum.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDBConnection();
    
    echo "Fixing role ENUM in board_members table...\n";
    
    // Alter the role column to include all current enum values
    $sql = "ALTER TABLE board_members 
            MODIFY COLUMN role ENUM('Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Member', 'Ex-officio') 
            DEFAULT 'Member'";
    
    $db->exec($sql);
    
    echo "✓ Successfully updated role ENUM\n";
    
    // Verify the change
    $stmt = $db->query("SELECT COLUMN_TYPE 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = 'governance_board' 
                        AND TABLE_NAME = 'board_members' 
                        AND COLUMN_NAME = 'role'");
    
    $result = $stmt->fetch();
    echo "\nCurrent role ENUM values: " . $result['COLUMN_TYPE'] . "\n";
    echo "\nYou can now use 'Deputy Chair' and 'Ex-officio' roles!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nYou can also run the SQL manually:\n";
    echo "mysql -u root -p governance_board < database/migration_add_deputy_chair.sql\n";
    exit(1);
}

