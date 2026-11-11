<?php
//Set TZ
date_default_timezone_set('Australia/Sydney');
/**
 * Authentication Helper Functions
 * 
 * Provides session-based authentication and role-based access control.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/twofactor.php';

// Send this app's errors to a dedicated file when configured (view it with
// scripts/logs.sh). Not set on live installs whose config.php predates it.
if (defined('APP_ERROR_LOG') && APP_ERROR_LOG !== '') {
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ERROR_LOG);
}

/**
 * JSON safety net for api/*.php. Without it an uncaught exception (e.g. a
 * query against a table/column a pending migration hasn't created yet) or a
 * fatal error ends the request with an empty 500 body, and the browser only
 * reports "Unexpected end of JSON input". Instead, log the detail and send
 * a JSON error with a reference id that scripts/logs.sh can look up.
 */
function sendApiFatalError(string $detail): void {
    $ref = bin2hex(random_bytes(4));
    error_log("[$ref] Unhandled API error in " . ($_SERVER['SCRIPT_NAME'] ?? '?') . ": $detail");
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    $message = "Server error (error reference $ref)";
    echo json_encode(['error' => $message, 'message' => $message]);
}

if (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'api') {
    set_exception_handler(function (Throwable $e) {
        sendApiFatalError(get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    });
    register_shutdown_function(function () {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            sendApiFatalError("{$err['message']} at {$err['file']}:{$err['line']}");
        }
    });
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Whether this request reached us over HTTPS (directly or via Cloudflare /
 * a TLS-terminating proxy) - same test config.php uses for the cookie.
 */
function requestIsHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (strpos($_SERVER['HTTP_CF_VISITOR'] ?? '', '"scheme":"https"') !== false);
}

/**
 * Security headers for every page and API response. Sent from PHP (not only
 * .htaccess) because deploy.sh never syncs .htaccess to the live site.
 * The pages still rely on inline <script> blocks and onclick handlers, so
 * script-src has to allow 'unsafe-inline'; the rest of the policy still
 * blocks plugins, <base> hijacking, framing by other sites, off-site form
 * posts and fetch/XHR to other origins.
 */
function sendSecurityHeaders(): void {
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; "
        . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    if (requestIsHttps()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
sendSecurityHeaders();

/**
 * Client IP used for throttling. REMOTE_ADDR unless TRUSTED_CLIENT_IP_HEADER
 * (e.g. 'HTTP_CF_CONNECTING_IP' behind Cloudflare) is configured - only set
 * that when the origin is reachable through the proxy alone, otherwise the
 * header can be spoofed.
 */
function clientIp(): string {
    if (defined('TRUSTED_CLIENT_IP_HEADER') && TRUSTED_CLIENT_IP_HEADER !== '') {
        $value = trim(explode(',', (string)($_SERVER[TRUSTED_CLIENT_IP_HEADER] ?? ''))[0]);
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Throttling log (auth_attempts table) for failed logins, failed 2FA codes
 * and password reset requests. If the table doesn't exist yet (migration
 * not run) these fail open and log, rather than locking everyone out.
 */
function recordAuthAttempt(string $action, string $identifier): void {
    try {
        $stmt = getDBConnection()->prepare("INSERT INTO auth_attempts (action, identifier, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$action, mb_substr(strtolower($identifier), 0, 255), clientIp()]);
    } catch (PDOException $e) {
        error_log('auth_attempts insert failed (run database/migration_security_hardening.sql?): ' . $e->getMessage());
    }
}

function countAuthAttempts(string $action, string $identifier, int $windowSeconds): int {
    try {
        $stmt = getDBConnection()->prepare("
            SELECT COUNT(*) FROM auth_attempts
            WHERE action = ? AND identifier = ? AND created_at > (NOW() - INTERVAL ? SECOND)
        ");
        $stmt->execute([$action, mb_substr(strtolower($identifier), 0, 255), $windowSeconds]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('auth_attempts query failed: ' . $e->getMessage());
        return 0;
    }
}

function countAuthAttemptsFromIp(string $action, int $windowSeconds): int {
    try {
        $stmt = getDBConnection()->prepare("
            SELECT COUNT(*) FROM auth_attempts
            WHERE action = ? AND ip_address = ? AND created_at > (NOW() - INTERVAL ? SECOND)
        ");
        $stmt->execute([$action, clientIp(), $windowSeconds]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('auth_attempts query failed: ' . $e->getMessage());
        return 0;
    }
}

function clearAuthAttempts(string $action, string $identifier): void {
    try {
        $stmt = getDBConnection()->prepare("DELETE FROM auth_attempts WHERE action = ? AND identifier = ?");
        $stmt->execute([$action, mb_substr(strtolower($identifier), 0, 255)]);
        // Housekeeping: nothing older than a day is ever counted
        getDBConnection()->exec("DELETE FROM auth_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    } catch (PDOException $e) {
        error_log('auth_attempts delete failed: ' . $e->getMessage());
    }
}

// Throttling limits (failures within the window)
const LOGIN_MAX_FAILURES_PER_ACCOUNT = 10;
const LOGIN_MAX_FAILURES_PER_IP = 50;
const LOGIN_FAILURE_WINDOW = 900;          // 15 minutes
const TWOFACTOR_MAX_FAILURES_PER_USER = 5;
const TWOFACTOR_FAILURE_WINDOW = 900;
const RESET_MAX_REQUESTS_PER_EMAIL = 3;
const RESET_MAX_REQUESTS_PER_IP = 10;
const RESET_REQUEST_WINDOW = 3600;
const RESET_TOKEN_MAX_ATTEMPTS_PER_IP = 20;   // token-submission attempts per IP
const RESET_TOKEN_WINDOW = 900;               // 15 minutes
const PASSWORD_MIN_LENGTH = 12;

/**
 * Check a new password against the policy. Returns an error message, or
 * null if it's acceptable. Also checks the Have I Been Pwned range API
 * (k-anonymity: only the first 5 hex chars of the SHA-1 leave the server)
 * unless PASSWORD_BREACH_CHECK is false; that check fails open if the
 * service can't be reached.
 */
function validatePasswordStrength(string $password, string $username = ''): ?string {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }
    if (strlen($password) > 1000) {
        return 'Password is too long.';
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        return 'Password must not contain your username.';
    }
    $common = ['changeme123', 'password1234', '123456789012', 'qwertyuiop12', 'togetherincouncil', 'presbytery123'];
    if (in_array(strtolower($password), $common, true)) {
        return 'That password is too common - please choose another.';
    }
    if (isPasswordBreached($password)) {
        return 'That password has appeared in a known data breach - please choose another.';
    }
    return null;
}

function isPasswordBreached(string $password): bool {
    if (defined('PASSWORD_BREACH_CHECK') && PASSWORD_BREACH_CHECK === false) {
        return false;
    }
    $sha1 = strtoupper(sha1($password));
    $prefix = substr($sha1, 0, 5);
    $suffix = substr($sha1, 5);
    $context = stream_context_create(['http' => [
        'timeout' => 3,
        'header' => "User-Agent: TogetherInCouncil\r\nAdd-Padding: true\r\n",
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents('https://api.pwnedpasswords.com/range/' . $prefix, false, $context);
    if ($body === false) {
        error_log('Pwned Passwords check unavailable - skipped');
        return false;
    }
    foreach (preg_split('/\r?\n/', $body) as $line) {
        [$hashSuffix, $count] = array_pad(explode(':', trim($line)), 2, '0');
        if ($hashSuffix === $suffix && (int)$count > 0) {
            return true;
        }
    }
    return false;
}

/**
 * Invalidate every other session for a user (password change/reset,
 * 2FA disabled, account deactivated). Sessions carry the session_version
 * they were issued under and are rejected once it moves on. The current
 * session is kept if it belongs to that user.
 */
function bumpSessionVersion(int $userId): void {
    try {
        $db = getDBConnection();
        $db->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$userId]);
        if ((int)($_SESSION['user_id'] ?? 0) === $userId) {
            $stmt = $db->prepare("SELECT session_version FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $_SESSION['session_version'] = (int)$stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log('session_version bump failed (run database/migration_security_hardening.sql?): ' . $e->getMessage());
    }
}

/**
 * Re-check the session's user against the database once per request, so
 * that deactivating, deleting or re-roling a user (or changing their
 * password elsewhere) takes effect immediately instead of when the 8-hour
 * session expires. Refreshes the cached role/details in the session.
 *
 * @return bool Whether the session is (still) valid
 */
function revalidateSession(): bool {
    static $checked = null;
    if ($checked !== null) {
        return $checked;
    }
    if (empty($_SESSION['user_id'])) {
        return $checked = false;
    }
    $stmt = getDBConnection()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $row = $stmt->fetch();
    $valid = $row
        && !empty($row['is_active'])
        && (int)($row['session_version'] ?? 0) === (int)($_SESSION['session_version'] ?? 0);
    if (!$valid) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        return $checked = false;
    }
    $_SESSION['username'] = $row['username'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['board_member_id'] = $row['board_member_id'];
    $_SESSION['must_change_password'] = !empty($row['must_change_password']);
    return $checked = true;
}

/**
 * Cross-site request forgery guard for cookie-authenticated API writes.
 * The browser always sends Origin on fetch() POST/PUT/DELETE (and
 * Sec-Fetch-Site in current browsers), so a request from another site -
 * including a sibling subdomain, which SameSite=Lax does not stop - is
 * rejected. API-key clients (no cookie) are not affected.
 */
function requireSameOriginForWrites(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) || empty($_SESSION['user_id'])) {
        return;
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $ok = true;
    if ($origin !== null) {
        $parts = parse_url($origin);
        $originHost = strtolower(($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        $ok = $origin !== 'null' && $originHost !== '' && $originHost === $host;
    } else {
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
        $ok = !in_array($site, ['cross-site', 'same-site'], true);
    }
    if (!$ok) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Cross-site request blocked', 'code' => 'CSRF']);
        exit;
    }
}

/**
 * Log an exception in full and return a message that is safe to show the
 * client: no SQL, table/column names or file paths, just a summary and a
 * reference to find the log entry by.
 */
function publicErrorMessage(Throwable $e, string $summary): string {
    $ref = bin2hex(random_bytes(4));
    error_log("[$ref] $summary: " . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    return "$summary (error reference $ref)";
}

// Role hierarchy (higher index = more permissions)
define('ROLE_HIERARCHY', [
    'Viewer' => 1,
    'Member' => 2,
    'Clerk' => 3,
    'Admin' => 4,
    // An automated assistant (e.g. Claude via an API key). Ranked with
    // Viewer so hasRole() checks never grant it more - everything it can do
    // is listed explicitly in PERMISSIONS below.
    'Assistant' => 1,
]);

// Permission definitions for each action
define('PERMISSIONS', [
    // View permissions (all roles)
    'view_dashboard' => ['Viewer', 'Member', 'Clerk', 'Admin', 'Assistant'],
    'view_meetings' => ['Viewer', 'Member', 'Clerk', 'Admin', 'Assistant'],
    'view_members' => ['Viewer', 'Member', 'Clerk', 'Admin', 'Assistant'],
    'view_documents' => ['Viewer', 'Member', 'Clerk', 'Admin', 'Assistant'],
    'view_resolutions' => ['Viewer', 'Member', 'Clerk', 'Admin', 'Assistant'],
    // Members' email addresses and phone numbers. Viewers and the Assistant
    // see names/titles only (api responses are redacted with
    // redactMemberContacts()).
    'view_member_contacts' => ['Member', 'Clerk', 'Admin'],
    
    // Edit permissions (Clerk and Admin)
    'create_meeting' => ['Clerk', 'Admin'],
    'edit_meeting' => ['Clerk', 'Admin'],
    'manage_agenda' => ['Clerk', 'Admin'],
    'manage_attendees' => ['Clerk', 'Admin'],
    'manage_minutes' => ['Clerk', 'Admin'],
    'upload_documents' => ['Clerk', 'Admin'],
    'create_resolution' => ['Clerk', 'Admin'],
    'edit_resolution' => ['Clerk', 'Admin'],
    // Filing a resolution under a register category is indexing, not a change
    // to what was resolved, so it stays allowed after minutes are approved.
    'categorise_resolutions' => ['Clerk', 'Admin', 'Assistant'],
    'manage_procedural_proposals' => ['Clerk', 'Admin'],
    // Create/edit action items arising from a meeting (not locked by
    // approved minutes - following them up is the point).
    'manage_action_items' => ['Clerk', 'Admin', 'Assistant'],
    'delete_action_items' => ['Clerk', 'Admin'],
    // Write minutes content while they're still Draft. Changing status,
    // approving, or editing minutes past Draft needs manage_minutes.
    'draft_minutes' => ['Clerk', 'Admin', 'Assistant'],
    
    // Member-specific permissions
    'edit_own_attendance' => ['Member', 'Clerk', 'Admin'],
    // Proposals go to clerks for review (agenda_submissions), so the
    // Assistant can draft the next agenda without editing it directly.
    'submit_agenda_item' => ['Member', 'Clerk', 'Admin', 'Assistant'],
    // Update status/notes on action items assigned to your linked member
    'update_own_action_items' => ['Member', 'Clerk', 'Admin'],
    
    // Admin-only permissions
    'manage_members' => ['Admin'],
    'manage_meeting_types' => ['Admin'],
    'manage_users' => ['Admin'],
    'delete_meeting' => ['Admin'],
    'delete_member' => ['Admin'],
    'delete_resolution' => ['Admin'],
    'delete_document' => ['Admin'],
]);

/**
 * Resolve the user for an X-API-Key request header, if present and valid.
 * Non-browser clients (e.g. the Word minutes macros) authenticate this way
 * instead of via session cookie; the key authenticates as the user it was
 * generated for, so it carries that user's normal role/permissions - see
 * database/generate_api_key.php. Result is cached per-request since this
 * can be called multiple times (isLoggedIn, getCurrentUser, getCurrentRole).
 *
 * @return array|null User data (same shape as getCurrentUser()) or null
 */
function currentApiKeyUser(): ?array {
    static $resolved = false;
    static $user = null;

    if ($resolved) {
        return $user;
    }
    $resolved = true;

    $rawKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($rawKey === '') {
        return null;
    }

    $db = getDBConnection();
    $keyHash = hash('sha256', $rawKey);
    $stmt = $db->prepare("
        SELECT ak.id as api_key_id, u.id, u.username, u.email, u.role, u.board_member_id
        FROM api_keys ak
        JOIN users u ON u.id = ak.user_id
        WHERE ak.key_hash = ? AND ak.revoked_at IS NULL AND u.is_active = TRUE
    ");
    $stmt->execute([$keyHash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $update = $db->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
    $update->execute([$row['api_key_id']]);

    $user = [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'role' => $row['role'],
        'board_member_id' => $row['board_member_id'] !== null ? (int)$row['board_member_id'] : null
    ];
    return $user;
}

/**
 * Check if user is logged in, via session cookie or X-API-Key header
 *
 * @return bool
 */
function isLoggedIn(): bool {
    if (revalidateSession()) {
        return true;
    }
    return currentApiKeyUser() !== null;
}

/**
 * Get current user data from session or X-API-Key header
 *
 * @return array|null User data or null if not logged in
 */
function getCurrentUser(): ?array {
    if (revalidateSession()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['role'] ?? 'Viewer',
            'board_member_id' => $_SESSION['board_member_id'] ?? null
        ];
    }
    return currentApiKeyUser();
}

/**
 * Get current user's role, via session cookie or X-API-Key header
 *
 * @return string
 */
function getCurrentRole(): string {
    if (revalidateSession()) {
        return $_SESSION['role'] ?? 'Viewer';
    }
    $apiUser = currentApiKeyUser();
    return $apiUser['role'] ?? 'Viewer';
}

/**
 * Check if current user has a specific role
 * 
 * @param string $role Role to check
 * @return bool
 */
function hasRole(string $role): bool {
    if (!isLoggedIn()) {
        return false;
    }
    
    $currentRole = ucfirst(strtolower(getCurrentRole())); // Normalize to "Admin", "Clerk", etc.
    $role = ucfirst(strtolower($role)); // Normalize the requested role too
    $currentLevel = ROLE_HIERARCHY[$currentRole] ?? 0;
    $requiredLevel = ROLE_HIERARCHY[$role] ?? 999;
    
    return $currentLevel >= $requiredLevel;
}

/**
 * Check if current user has permission for an action
 * 
 * @param string $action Action to check
 * @return bool
 */
function hasPermission(string $action): bool {
    if (!isLoggedIn()) {
        return false;
    }
    
    $currentRole = ucfirst(strtolower(getCurrentRole())); // Normalize to "Admin", "Clerk", etc.
    $allowedRoles = PERMISSIONS[$action] ?? [];
    
    return in_array($currentRole, $allowedRoles);
}

/**
 * Check if current user is an admin
 * 
 * @return bool
 */
function isAdmin(): bool {
    return hasRole('Admin');
}

/**
 * Whether the current user may use the in-app Claude features (suggest
 * categories, suggest action items). Limited by username to
 * CLAUDE_ALLOWED_USERS in config/config.php - not by role - because each
 * call is billed to the organisation's Anthropic API key.
 */
function canUseClaude(): bool {
    if (!isLoggedIn()) {
        return false;
    }
    $user = getCurrentUser();
    $username = strtolower((string)($user['username'] ?? ''));
    $allowed = array_map('strtolower', defined('CLAUDE_ALLOWED_USERS') ? CLAUDE_ALLOWED_USERS : []);
    return $username !== '' && in_array($username, $allowed, true);
}

/**
 * Require user to be logged in, redirect to login if not
 * For use in frontend pages
 * 
 * @param string $redirectUrl URL to redirect to after login
 */
function requireLogin(string $redirectUrl = ''): void {
    if (!isLoggedIn()) {
        $redirect = $redirectUrl ?: $_SERVER['REQUEST_URI'];
        header('Location: login.php?redirect=' . urlencode($redirect));
        exit;
    }
    if (mustChangePassword() && basename($_SERVER['PHP_SELF'] ?? '') !== 'change_password.php') {
        header('Location: change_password.php');
        exit;
    }
}

/**
 * Whether the logged-in (session) user must set a new password before doing
 * anything else - set for the seeded admin account and for passwords an
 * admin sets on someone's behalf.
 */
function mustChangePassword(): bool {
    return revalidateSession() && !empty($_SESSION['must_change_password']);
}

/**
 * Remove email/phone from board-member rows unless the current user has
 * view_member_contacts. Works on a single row or a list of rows.
 *
 * @param array $rows
 * @param array $fields Contact columns to strip
 * @return array
 */
function redactMemberContacts(array $rows, array $fields = ['email', 'phone']): array {
    if (hasPermission('view_member_contacts')) {
        return $rows;
    }
    $strip = function (array $row) use ($fields) {
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = null;
            }
        }
        return $row;
    };
    if ($rows && array_keys($rows) === range(0, count($rows) - 1) && is_array(reset($rows))) {
        return array_map($strip, $rows);
    }
    return $strip($rows);
}

/**
 * Sanitise a post-login redirect target so it can only point back into this
 * site. Accepts relative paths ("meetings.php?id=1") and root-relative paths
 * ("/export/agenda.php?meeting_id=1"); rejects anything with a scheme or
 * host, protocol-relative ("//evil"), backslash tricks ("/\\evil", "\\evil")
 * and control characters (header injection). Falls back to index.php.
 *
 * @param mixed $target Raw redirect value (e.g. from $_GET['redirect'])
 * @return string Safe redirect target
 */
function safeRedirectTarget($target): string {
    $fallback = 'index.php';
    if (!is_string($target) || $target === '') {
        return $fallback;
    }
    // Control chars (CR/LF header injection, tabs/NUL browsers strip silently)
    if (preg_match('/[\x00-\x1F\x7F]/', $target)) {
        return $fallback;
    }
    // Browsers treat "\" as "/", so "/\evil.com" is protocol-relative too
    if (strpos($target, '\\') !== false) {
        return $fallback;
    }
    if (strpos($target, '//') === 0) {
        return $fallback;
    }
    $parts = parse_url($target);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }
    // A colon before any "/", "?" or "#" means a scheme (e.g. "javascript:")
    if (preg_match('~^[^/?#]*:~', $target)) {
        return $fallback;
    }
    return $target;
}

/**
 * Gate for pages under export/: redirect to the site's login page (one level
 * up, unlike requireLogin() which redirects relative to the current
 * directory) if not logged in, and 403 if the user can't view meetings.
 * API-key auth works via isLoggedIn()/hasPermission().
 */
function requireExportAccess(): void {
    if (!isLoggedIn()) {
        header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
    if (mustChangePassword()) {
        header('Location: ../change_password.php');
        exit;
    }
    if (!hasPermission('view_meetings')) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'You do not have permission to view this export.';
        exit;
    }
}

/**
 * Require user to be logged in, return 401 JSON response if not
 * For use in API endpoints
 * 
 * @return void
 */
function requireAuth(): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required', 'code' => 'AUTH_REQUIRED']);
        exit;
    }
    requireSameOriginForWrites();
    if (mustChangePassword()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'You must change your password before continuing', 'code' => 'PASSWORD_CHANGE_REQUIRED']);
        exit;
    }
}

/**
 * Require user to have a minimum role level
 * For use in API endpoints
 * 
 * @param string $role Minimum role required
 */
function requireRole(string $role): void {
    requireAuth();
    
    if (!hasRole($role)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Insufficient permissions', 'code' => 'FORBIDDEN']);
        exit;
    }
}

/**
 * Require user to have permission for an action
 * For use in API endpoints
 * 
 * @param string $action Action that requires permission
 */
function requirePermission(string $action): void {
    requireAuth();
    
    if (!hasPermission($action)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Insufficient permissions for this action', 'code' => 'FORBIDDEN']);
        exit;
    }
}

/**
 * Attempt to log in a user
 * 
 * @param string $username Username
 * @param string $password Plain text password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null, 'requires_2fa' => bool]
 */
function login(string $username, string $password): array {
    $db = getDBConnection();
    $failed = ['success' => false, 'message' => 'Invalid username or password', 'user' => null, 'requires_2fa' => false];

    // Throttle guessing, per account and per client IP. The same generic
    // message is returned so the limit doesn't reveal which usernames exist.
    if (countAuthAttempts('login', $username, LOGIN_FAILURE_WINDOW) >= LOGIN_MAX_FAILURES_PER_ACCOUNT
        || countAuthAttemptsFromIp('login', LOGIN_FAILURE_WINDOW) >= LOGIN_MAX_FAILURES_PER_IP) {
        return ['success' => false, 'message' => 'Too many failed sign-in attempts. Please wait 15 minutes and try again.', 'user' => null, 'requires_2fa' => false];
    }

    // Find user by username
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Always run password_verify (against a dummy hash for unknown users) so
    // response time doesn't reveal whether the username exists
    $hash = $user['password_hash'] ?? '$2y$10$nZaVOMtvODC0Fx3OTquuB.Db2XNePtX.XH.MyGfTcQnBYsXek2ZTG';
    if (!password_verify($password, $hash) || !$user) {
        recordAuthAttempt('login', $username);
        return $failed;
    }
    clearAuthAttempts('login', $username);

    // Upgrade the hash if PHP's default cost/algorithm has moved on
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
           ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    
    // Check if 2FA is enabled
    $twoFactorEnabled = isset($user['two_factor_enabled']) && $user['two_factor_enabled'] == 1;
    
    if ($twoFactorEnabled) {
        // Store temporary session data for 2FA verification
        // Don't set full session yet - wait for 2FA verification
        $_SESSION['pending_user_id'] = $user['id'];
        $_SESSION['pending_username'] = $user['username'];
        $_SESSION['pending_email'] = $user['email'];
        $_SESSION['pending_role'] = $user['role'];
        $_SESSION['pending_board_member_id'] = $user['board_member_id'];
        $_SESSION['pending_session_version'] = (int)($user['session_version'] ?? 0);
        $_SESSION['pending_must_change_password'] = !empty($user['must_change_password']);
        $_SESSION['pending_login_time'] = time();
        $_SESSION['pending_2fa_failures'] = 0;
        
        // Regenerate session ID
        session_regenerate_id(true);
        
        return [
            'success' => true,
            'message' => 'Password verified. Please enter your 2FA code.',
            'user' => null,
            'requires_2fa' => true
        ];
    }
    
    // No 2FA required - complete login
    // Update last login timestamp
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['board_member_id'] = $user['board_member_id'];
    $_SESSION['session_version'] = (int)($user['session_version'] ?? 0);
    $_SESSION['must_change_password'] = !empty($user['must_change_password']);
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'board_member_id' => $user['board_member_id']
        ],
        'requires_2fa' => false
    ];
}

/**
 * Forget a half-finished (password OK, 2FA pending) login
 */
function clearPendingLogin(): void {
    unset($_SESSION['pending_user_id'], $_SESSION['pending_username'],
          $_SESSION['pending_email'], $_SESSION['pending_role'],
          $_SESSION['pending_board_member_id'], $_SESSION['pending_login_time'],
          $_SESSION['pending_session_version'], $_SESSION['pending_must_change_password'],
          $_SESSION['pending_2fa_failures']);
}

/**
 * Complete login after 2FA verification
 * 
 * @return bool Success
 */
function completeTwoFactorLogin(): bool {
    if (!isset($_SESSION['pending_user_id'])) {
        return false;
    }
    
    // Check if pending login is not too old (15 minutes max)
    if (!isset($_SESSION['pending_login_time']) || (time() - $_SESSION['pending_login_time']) > 900) {
        clearPendingLogin();
        return false;
    }
    
    $db = getDBConnection();
    
    // Update last login timestamp
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['pending_user_id']]);
    
    // Set session variables
    $_SESSION['user_id'] = $_SESSION['pending_user_id'];
    $_SESSION['username'] = $_SESSION['pending_username'];
    $_SESSION['email'] = $_SESSION['pending_email'];
    $_SESSION['role'] = $_SESSION['pending_role'];
    $_SESSION['board_member_id'] = $_SESSION['pending_board_member_id'];
    $_SESSION['session_version'] = (int)($_SESSION['pending_session_version'] ?? 0);
    $_SESSION['must_change_password'] = !empty($_SESSION['pending_must_change_password']);
    
    // Clear pending session data
    clearPendingLogin();
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    return true;
}

/**
 * Log out the current user
 */
function logout(): void {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Generate a CSRF token
 * 
 * @return string
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token
 * 
 * @param string $token Token to verify
 * @return bool
 */
function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate a CSRF token for password reset (stateless, works behind Cloudflare)
 * Uses a config secret instead of session, so it works even if sessions fail
 * 
 * @param string $resetToken The password reset token
 * @return string
 */
function passwordResetCsrfSecret(): ?string {
    if (!defined('CSRF_SECRET') || CSRF_SECRET === '' || CSRF_SECRET === 'CHANGE_THIS_TO_A_RANDOM_64_CHARACTER_HEX_STRING') {
        return null;
    }
    return CSRF_SECRET;
}

function generatePasswordResetCsrfToken(string $resetToken): string {
    // Use config secret instead of session - this makes it stateless
    // The CSRF token is derived from the reset token itself, making it secure.
    // No secret configured: fall back to the ordinary session token rather
    // than a publicly known default secret.
    $secret = passwordResetCsrfSecret();
    if ($secret === null) {
        return generateCsrfToken();
    }
    
    // Generate token based on reset token + secret + timestamp (hourly window)
    // This ensures the token changes periodically but doesn't require sessions
    $timeWindow = floor(time() / 3600); // Changes every hour
    $data = $resetToken . $secret . $timeWindow;
    return hash_hmac('sha256', $data, $secret);
}

/**
 * Verify a CSRF token for password reset (stateless)
 * 
 * @param string $token Token to verify
 * @param string $resetToken The password reset token
 * @return bool
 */
function verifyPasswordResetCsrfToken(string $token, string $resetToken): bool {
    $secret = passwordResetCsrfSecret();
    if ($secret === null) {
        return verifyCsrfToken($token);
    }
    
    // Check current hour and previous hour (to handle edge cases)
    $timeWindow = floor(time() / 3600);
    
    // Try current hour
    $data = $resetToken . $secret . $timeWindow;
    $expectedToken = hash_hmac('sha256', $data, $secret);
    if (hash_equals($expectedToken, $token)) {
        return true;
    }
    
    // Try previous hour (in case of clock skew or slow submission)
    $data = $resetToken . $secret . ($timeWindow - 1);
    $expectedToken = hash_hmac('sha256', $data, $secret);
    return hash_equals($expectedToken, $token);
}

/**
 * Get user by ID
 * 
 * @param int $userId
 * @return array|null
 */
function getUserById(int $userId): ?array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, username, email, role, board_member_id, is_active, last_login, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Create a new user
 * 
 * @param string $username
 * @param string $password
 * @param string $email
 * @param string $role
 * @param int|null $boardMemberId
 * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
 */
function createUser(string $username, string $password, string $email, string $role = 'Viewer', ?int $boardMemberId = null): array {
    $db = getDBConnection();
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username already exists', 'user_id' => null];
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already exists', 'user_id' => null];
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role, board_member_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $passwordHash, $email, $role, $boardMemberId]);
    
    return [
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => $db->lastInsertId()
    ];
}

/**
 * Update user password
 * 
 * @param int $userId
 * @param string $newPassword
 * @return bool
 */
function updatePassword(int $userId, string $newPassword): bool {
    $db = getDBConnection();
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $ok = $stmt->execute([$passwordHash, $userId]);
    clearMustChangePassword($userId);
    bumpSessionVersion($userId);
    return $ok;
}

function setMustChangePassword(int $userId, bool $required): void {
    if (!$required) {
        clearMustChangePassword($userId);
        return;
    }
    try {
        getDBConnection()->prepare("UPDATE users SET must_change_password = TRUE WHERE id = ?")->execute([$userId]);
    } catch (PDOException $e) {
        error_log('must_change_password update failed (run database/migration_security_hardening.sql?): ' . $e->getMessage());
    }
}

function clearMustChangePassword(int $userId): void {
    try {
        getDBConnection()->prepare("UPDATE users SET must_change_password = FALSE WHERE id = ?")->execute([$userId]);
        if ((int)($_SESSION['user_id'] ?? 0) === $userId) {
            $_SESSION['must_change_password'] = false;
        }
    } catch (PDOException $e) {
        error_log('must_change_password update failed: ' . $e->getMessage());
    }
}

/**
 * Get user by email address
 * 
 * @param string $email
 * @return array|null
 */
function getUserByEmail(string $email): ?array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, username, email, role, board_member_id, is_active, last_login, created_at FROM users WHERE email = ? AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Request password reset - generates token and sends email
 * 
 * @param string $email User email address
 * @return array ['success' => bool, 'message' => string]
 */
function requestPasswordReset(string $email): array {
    require_once __DIR__ . '/email.php';

    $db = getDBConnection();
    $genericMessage = 'If an account with that email exists, a password reset link has been sent.';

    // Per-IP rate limit applies to EVERY request, including ones for
    // non-existent emails, so the cap can't be bypassed by cycling through
    // made-up addresses. Record the attempt before the user lookup so fake
    // requests still count toward the IP limit.
    if (countAuthAttemptsFromIp('reset_request', RESET_REQUEST_WINDOW) >= RESET_MAX_REQUESTS_PER_IP) {
        error_log("Password reset IP rate limit hit from " . clientIp());
        return ['success' => true, 'message' => $genericMessage];
    }
    recordAuthAttempt('reset_request', $email);

    // Find user by email (don't reveal if email exists for security)
    $user = getUserByEmail($email);

    // Always return the same message to prevent email enumeration;
    // only send an email if the user exists.
    if (!$user) {
        error_log("Password reset requested for non-existent email: $email");
        return ['success' => true, 'message' => $genericMessage];
    }

    // Per-address cap so one real account can't be email-bombed. The current
    // request is already recorded above, hence '>' rather than '>='.
    if (countAuthAttempts('reset_request', $email, RESET_REQUEST_WINDOW) > RESET_MAX_REQUESTS_PER_EMAIL) {
        error_log("Password reset rate limit hit for $email from " . clientIp());
        return ['success' => true, 'message' => $genericMessage];
    }

    // Generate secure random token (64 characters = 32 bytes hex encoded)
    $token = bin2hex(random_bytes(32));
    
    // Calculate expiration time
    $expires = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TOKEN_EXPIRY);
    
    // Store only a hash of the token, so a database leak doesn't hand out
    // working reset links (same approach as api_keys)
    $stmt = $db->prepare("
        UPDATE users 
        SET password_reset_token = ?, 
            password_reset_expires = ? 
        WHERE id = ?
    ");
    $stmt->execute([hash('sha256', $token), $expires, $user['id']]);
    
    // Send password reset email
    $emailResult = sendPasswordResetEmail($email, $token);
    
    if (!$emailResult['success']) {
        // If email fails, still return success to user (security best practice)
        // But log the error
        error_log("Failed to send password reset email to $email: " . $emailResult['message']);
    }
    
    return [
        'success' => true,
        'message' => 'If an account with that email exists, a password reset link has been sent.'
    ];
}

/**
 * Validate password reset token
 * 
 * @param string $token Password reset token
 * @return array|null User data if token is valid, null otherwise
 */
function validatePasswordResetToken(string $token): ?array {
    $db = getDBConnection();
    
    // Find user with valid, non-expired token
    $stmt = $db->prepare("
        SELECT id, username, email, role, board_member_id, is_active 
        FROM users 
        WHERE password_reset_token = ? 
        AND password_reset_expires > NOW()
        AND is_active = TRUE
    ");
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
    
    return $user ?: null;
}

/**
 * Reset password using token
 * 
 * @param string $token Password reset token
 * @param string $newPassword New password
 * @return array ['success' => bool, 'message' => string]
 */
function resetPassword(string $token, string $newPassword): array {
    $db = getDBConnection();
    
    // Validate token
    $user = validatePasswordResetToken($token);
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'Invalid or expired password reset token. Please request a new password reset.'
        ];
    }
    
    // Validate password strength
    $policyError = validatePasswordStrength($newPassword, $user['username']);
    if ($policyError !== null) {
        return [
            'success' => false,
            'message' => $policyError
        ];
    }
    
    // Hash new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password and invalidate token
    $stmt = $db->prepare("
        UPDATE users 
        SET password_hash = ?, 
            password_reset_token = NULL, 
            password_reset_expires = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$passwordHash, $user['id']]);

    // A reset is often done because the account was compromised: end every
    // existing session, and the forced-change flag is satisfied
    clearMustChangePassword((int)$user['id']);
    bumpSessionVersion((int)$user['id']);
    clearAuthAttempts('login', $user['username']);
    
    return [
        'success' => true,
        'message' => 'Password has been reset successfully. You can now log in with your new password.'
    ];
}

