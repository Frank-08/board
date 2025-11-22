<?php
/**
 * Application Configuration
 */
define('APP_NAME', 'Governance Board Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/board');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);

// Date format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M d, Y');
define('DISPLAY_DATETIME_FORMAT', 'M d, Y h:i A');

// Session settings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

