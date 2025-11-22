<?php
/**
 * Database Configuration
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'governance_board');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection
 */
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    
    return $conn;
}
