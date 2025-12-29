<?php
/**
 * Application Configuration
 */
define('APP_NAME', 'Together in Council');
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

// Authentication settings
define('AUTH_SESSION_LIFETIME', 3600 * 8); // 8 hours
define('AUTH_COOKIE_NAME', 'board_session');

// Session settings (auth.php handles session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_set_cookie_params([
        'lifetime' => AUTH_SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Password reset settings
define('PASSWORD_RESET_EXPIRE_SECONDS', 3600); // 1 hour

// Email settings (simple fallback). For production, populate these from environment
// or integrate PHPMailer / external provider and keep credentials out of repo.
define('EMAIL_FROM_ADDRESS', 'no-reply@example.com');
define('EMAIL_FROM_NAME', 'Together in Council');
// Optional SMTP settings (used if you replace sendMail() to use SMTP)
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', getenv('SMTP_PORT') ?: '');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');

