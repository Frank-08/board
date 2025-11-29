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
    
    $currentRole = getCurrentRole();
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
    
    $currentRole = getCurrentRole();
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

