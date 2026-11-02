<?php
/**
 * Application Configuration
 */
define('APP_NAME', 'Together in Council');
define('APP_VERSION', '1.0.0');
// Must be https:// in production - password reset links are built from it
define('BASE_URL', 'http://localhost/board');

// Organisation name shown on the agenda/minutes letterhead, e.g.
// "{ORGANIZATION_NAME} {meeting type name}" -> "PRESBYTERY OF YARRA YARRA STANDING COMMITTEE"
define('ORGANIZATION_NAME', 'Presbytery of Yarra Yarra');

// Document links are stored as SharePoint URLs (no local file upload settings required).
// SharePoint tenants document links may point at: tenant names ('contoso' allows
// contoso.sharepoint.com and contoso-my.sharepoint.com) or full host names.
// Empty = any *.sharepoint.com host (anyone can create a tenant - set this).
define('SHAREPOINT_TENANTS', []);

// Claude (in-app "Suggest categories" / "Suggest action items").
// Only these usernames may use it - each call is billed to the API key.
// Requires the Anthropic PHP SDK: run `composer require anthropic-ai/sdk guzzlehttp/guzzle` in
// the app root (adds to any existing vendor/, e.g. TCPDF).
// The API key is read from the ANTHROPIC_API_KEY environment variable
// (e.g. `SetEnv ANTHROPIC_API_KEY ...` in the Apache vhost, or the PHP-FPM
// pool's env[]); failing that, set it here. Never commit a real key.
define('CLAUDE_ALLOWED_USERS', ['jacob']);
define('CLAUDE_MODEL', 'claude-opus-5-5');
define('ANTHROPIC_API_KEY', '');

// Logo settings for PDF exports
// Set to null or empty string to disable logo
// LOGO_PATH: Absolute file system path for PDF generation
// LOGO_URL: Web-accessible URL path for HTML exports (relative to web root)
// Example: LOGO_PATH = '/var/www/html/board/assets/images/logo.png'
//          LOGO_URL = 'assets/images/logo.png'
define('LOGO_PATH', __DIR__ . '/../assets/images/logo.png');
define('LOGO_URL', 'assets/images/logo.png'); // Web-accessible path for HTML
define('LOGO_WIDTH', 150); // Width in mm for PDF
define('LOGO_WIDTH_HTML', 150); //px for HTML
define('LOGO_HEIGHT', 0); // Height in mm for PDF (0 = auto), or px for HTML (0 = auto)

// Date format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M d, Y');
define('DISPLAY_DATETIME_FORMAT', 'M d, Y h:i A');

// Encrypts TOTP (2FA) secrets at rest. 64 hex chars:
//   php -r "echo bin2hex(random_bytes(32));"
// Keep it out of database backups. If it is lost, everyone with 2FA must re-enrol.
// Empty = secrets stored unencrypted (as before).
define('TWOFACTOR_ENCRYPTION_KEY', '');

// Server variable holding the real client IP, used for login/reset throttling.
// Behind Cloudflare set 'HTTP_CF_CONNECTING_IP' - but ONLY if the origin
// can't be reached except through Cloudflare, or the header can be forged.
// Empty = REMOTE_ADDR.
define('TRUSTED_CLIENT_IP_HEADER', '');

// Reject new passwords found in the Have I Been Pwned breach corpus (only the
// first 5 chars of the SHA-1 are sent; skipped if the API can't be reached).
define('PASSWORD_BREACH_CHECK', true);

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

