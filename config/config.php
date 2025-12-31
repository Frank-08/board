<?php
/**
 * Application Configuration
 */
define('APP_NAME', 'Together in Council');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/board');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
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

// Password reset settings
define('PASSWORD_RESET_TOKEN_EXPIRY', 3600); // 1 hour in seconds

// CSRF secret for password reset (stateless, works behind Cloudflare)
// Generate a random secret: php -r "echo bin2hex(random_bytes(32));"
// Keep this secret secure and don't change it once set
define('CSRF_SECRET', 'CHANGE_THIS_TO_A_RANDOM_64_CHARACTER_HEX_STRING');

// Zoho SMTP Configuration
// Configure these with your Zoho SMTP credentials
define('SMTP_HOST', 'smtp.zoho.com');
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', ''); // Your Zoho email address
define('SMTP_PASSWORD', ''); // Your Zoho app password (not your regular password)
define('SMTP_FROM_EMAIL', ''); // From email address (usually same as SMTP_USERNAME)
define('SMTP_FROM_NAME', APP_NAME); // From name for emails
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

// Session settings (auth.php handles session_start)
// Cloudflare-compatible session settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    // Use secure cookies if HTTPS is detected (Cloudflare handles SSL termination)
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false);
    session_set_cookie_params([
        'lifetime' => AUTH_SESSION_LIFETIME,
        'path' => '/',
        'domain' => '', // Empty domain works better with Cloudflare
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

