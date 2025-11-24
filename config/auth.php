<?php
/**
 * Authentication Helper Functions
 */
require_once __DIR__ . '/database.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $user = null;
    
    if ($user === null) {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, username, email, first_name, last_name, role FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([getCurrentUserId()]);
        $user = $stmt->fetch();
    }
    
    return $user;
}

/**
 * Require login - redirect to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $loginUrl = BASE_URL . '/login.php';
        if (php_sapi_name() !== 'cli') {
            header('Location: ' . $loginUrl);
            exit;
        }
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        if (php_sapi_name() !== 'cli') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Admin access required']);
            exit;
        }
    }
}

/**
 * Login user
 */
function loginUser($username, $password) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, username, email, password_hash, first_name, last_name, role, is_active FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid username or password'];
    }
    
    if (!$user['is_active']) {
        return ['success' => false, 'error' => 'Account is disabled'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid username or password'];
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    
    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    return [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role']
        ]
    ];
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

