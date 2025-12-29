<?php
/**
 * Authentication Helper Functions
 * 
 * Provides session-based authentication and role-based access control.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Role hierarchy (higher index = more permissions)
define('ROLE_HIERARCHY', [
    'Viewer' => 1,
    'Member' => 2,
    'Clerk' => 3,
    'Admin' => 4
]);

// Permission definitions for each action
define('PERMISSIONS', [
    // View permissions (all roles)
    'view_dashboard' => ['Viewer', 'Member', 'Clerk', 'Admin'],
    'view_meetings' => ['Viewer', 'Member', 'Clerk', 'Admin'],
    'view_members' => ['Viewer', 'Member', 'Clerk', 'Admin'],
    'view_documents' => ['Viewer', 'Member', 'Clerk', 'Admin'],
    'view_resolutions' => ['Viewer', 'Member', 'Clerk', 'Admin'],
    
    // Edit permissions (Clerk and Admin)
    'create_meeting' => ['Clerk', 'Admin'],
    'edit_meeting' => ['Clerk', 'Admin'],
    'manage_agenda' => ['Clerk', 'Admin'],
    'manage_attendees' => ['Clerk', 'Admin'],
    'manage_minutes' => ['Clerk', 'Admin'],
    'upload_documents' => ['Clerk', 'Admin'],
    'create_resolution' => ['Clerk', 'Admin'],
    'edit_resolution' => ['Clerk', 'Admin'],
    
    // Member-specific permissions
    'edit_own_attendance' => ['Member', 'Clerk', 'Admin'],
    
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
 * Check if user is logged in
 * 
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data from session
 * 
 * @return array|null User data or null if not logged in
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'Viewer',
        'board_member_id' => $_SESSION['board_member_id'] ?? null
    ];
}

/**
 * Get current user's role
 * 
 * @return string
 */
function getCurrentRole(): string {
    return $_SESSION['role'] ?? 'Viewer';
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
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function login(string $username, string $password): array {
    $db = getDBConnection();
    
    // Find user by username
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password', 'user' => null];
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username or password', 'user' => null];
    }
    
    // Update last login timestamp
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['board_member_id'] = $user['board_member_id'];
    
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
        ]
    ];
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
function generatePasswordResetCsrfToken(string $resetToken): string {
    // Use config secret instead of session - this makes it stateless
    // The CSRF token is derived from the reset token itself, making it secure
    $secret = defined('CSRF_SECRET') && CSRF_SECRET !== 'CHANGE_THIS_TO_A_RANDOM_64_CHARACTER_HEX_STRING' 
        ? CSRF_SECRET 
        : 'default_secret_change_in_config'; // Fallback if not configured
    
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
    $secret = defined('CSRF_SECRET') && CSRF_SECRET !== 'CHANGE_THIS_TO_A_RANDOM_64_CHARACTER_HEX_STRING' 
        ? CSRF_SECRET 
        : 'default_secret_change_in_config'; // Fallback if not configured
    
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
    return $stmt->execute([$passwordHash, $userId]);
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
    
    // Find user by email (don't reveal if email exists for security)
    $user = getUserByEmail($email);
    
    // Always return success message to prevent email enumeration
    // But only send email if user exists
    if (!$user) {
        // Log the attempt but don't reveal to user
        error_log("Password reset requested for non-existent email: $email");
        return [
            'success' => true,
            'message' => 'If an account with that email exists, a password reset link has been sent.'
        ];
    }
    
    // Check rate limiting - prevent abuse
    // Allow max 3 reset requests per hour per email
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE email = ? 
        AND password_reset_expires > NOW() 
        AND password_reset_expires > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$email]);
    $result = $stmt->fetch();
    
    if ($result && $result['count'] > 0) {
        // Check how many recent requests
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM users 
            WHERE email = ? 
            AND password_reset_expires > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$email]);
        $recentCount = $stmt->fetch();
        
        if ($recentCount && $recentCount['count'] >= 3) {
            return [
                'success' => true,
                'message' => 'If an account with that email exists, a password reset link has been sent.'
            ];
        }
    }
    
    // Generate secure random token (64 characters = 32 bytes hex encoded)
    $token = bin2hex(random_bytes(32));
    
    // Calculate expiration time
    $expires = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TOKEN_EXPIRY);
    
    // Store token in database
    $stmt = $db->prepare("
        UPDATE users 
        SET password_reset_token = ?, 
            password_reset_expires = ? 
        WHERE id = ?
    ");
    $stmt->execute([$token, $expires, $user['id']]);
    
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
    $stmt->execute([$token]);
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
    if (strlen($newPassword) < 8) {
        return [
            'success' => false,
            'message' => 'Password must be at least 8 characters long.'
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
    
    return [
        'success' => true,
        'message' => 'Password has been reset successfully. You can now log in with your new password.'
    ];
}

