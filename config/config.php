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

// Logo settings for PDF exports
// Set to null or empty string to disable logo
// LOGO_PATH: Absolute file system path for PDF generation
// LOGO_URL: Web-accessible URL path for HTML exports (relative to web root)
// Example: LOGO_PATH = '/var/www/html/board/assets/images/logo.png'
//          LOGO_URL = 'assets/images/logo.png'
define('LOGO_PATH', __DIR__ . '/../assets/images/logo.png');
define('LOGO_URL', 'assets/images/logo.png'); // Web-accessible path for HTML
define('LOGO_WIDTH', 60); // Width in mm for PDF, or px for HTML
define('LOGO_HEIGHT', 0); // Height in mm for PDF (0 = auto), or px for HTML (0 = auto)

// Date format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M d, Y');
define('DISPLAY_DATETIME_FORMAT', 'M d, Y h:i A');

// Session settings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

