<?php
/**
 * Users API Endpoint
 * 
 * Admin-only endpoint for managing user accounts.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require admin role for all user management operations
requireRole('Admin');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.email, u.role, u.board_member_id, u.is_active, u.last_login, u.created_at,
                       CONCAT(bm.first_name, ' ', bm.last_name) as board_member_name
                FROM users u
                LEFT JOIN board_members bm ON u.board_member_id = bm.id
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                exit;
            }
            
            echo json_encode($user);
        } else {
            // List all users
            $stmt = $db->query("
                SELECT u.id, u.username, u.email, u.role, u.board_member_id, u.is_active, u.last_login, u.created_at,
                       CONCAT(bm.first_name, ' ', bm.last_name) as board_member_name
                FROM users u
                LEFT JOIN board_members bm ON u.board_member_id = bm.id
                ORDER BY u.username
            ");
            echo json_encode($stmt->fetchAll());
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $email = trim($data['email'] ?? '');
        $role = $data['role'] ?? 'Viewer';
        $boardMemberId = !empty($data['board_member_id']) ? (int)$data['board_member_id'] : null;
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        
        // Validation
        if (empty($username) || empty($password) || empty($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Username, password, and email are required']);
            exit;
        }
        
        // Validate role
        $validRoles = ['Admin', 'Clerk', 'Member', 'Viewer'];
        if (!in_array($role, $validRoles)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role']);
            exit;
        }
        
        // Check if username already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Username already exists']);
            exit;
        }
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
            exit;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role, board_member_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $passwordHash, $email, $role, $boardMemberId, $isActive]);
        
        $userId = $db->lastInsertId();
        
        // Return created user
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.role, u.board_member_id, u.is_active, u.created_at,
                   CONCAT(bm.first_name, ' ', bm.last_name) as board_member_name
            FROM users u
            LEFT JOIN board_members bm ON u.board_member_id = bm.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        echo json_encode($stmt->fetch());
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        // Check user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        
        $updates = [];
        $params = [];
        
        // Update username
        if (isset($data['username'])) {
            $username = trim($data['username']);
            if (empty($username)) {
                http_response_code(400);
                echo json_encode(['error' => 'Username cannot be empty']);
                exit;
            }
            // Check uniqueness
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Username already exists']);
                exit;
            }
            $updates[] = "username = ?";
            $params[] = $username;
        }
        
        // Update email
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (empty($email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Email cannot be empty']);
                exit;
            }
            // Check uniqueness
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Email already exists']);
                exit;
            }
            $updates[] = "email = ?";
            $params[] = $email;
        }
        
        // Update password
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            $updates[] = "password_hash = ?";
            $params[] = $passwordHash;
        }
        
        // Update role
        if (isset($data['role'])) {
            $validRoles = ['Admin', 'Clerk', 'Member', 'Viewer'];
            if (!in_array($data['role'], $validRoles)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid role']);
                exit;
            }
            $updates[] = "role = ?";
            $params[] = $data['role'];
        }
        
        // Update board_member_id
        if (array_key_exists('board_member_id', $data)) {
            $updates[] = "board_member_id = ?";
            $params[] = !empty($data['board_member_id']) ? (int)$data['board_member_id'] : null;
        }
        
        // Update is_active
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = (bool)$data['is_active'] ? 1 : 0;
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Return updated user
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.role, u.board_member_id, u.is_active, u.last_login, u.created_at,
                   CONCAT(bm.first_name, ' ', bm.last_name) as board_member_name
            FROM users u
            LEFT JOIN board_members bm ON u.board_member_id = bm.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        // Prevent deleting own account
        $currentUser = getCurrentUser();
        if ($id == $currentUser['id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

