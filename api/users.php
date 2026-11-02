<?php
/**
 * Users API Endpoint
 * 
 * Admin-only endpoint for managing user accounts.
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require admin role for all user management operations
requireRole('Admin');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDBConnection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

switch ($method) {
    case 'GET':
        try {
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
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => publicErrorMessage($e, 'Database error')]);
            exit;
        }
        break;
        
    case 'POST':
        try {
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
            $validRoles = ['Admin', 'Clerk', 'Member', 'Viewer', 'Assistant'];
            if (!in_array($role, $validRoles, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid role']);
                exit;
            }

            $policyError = validatePasswordStrength((string)$password, $username);
            if ($policyError !== null) {
                http_response_code(400);
                echo json_encode(['error' => $policyError]);
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
            // The admin knows this password, so the user must replace it
            setMustChangePassword((int)$userId, true);
            
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
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => publicErrorMessage($e, 'Database error')]);
            exit;
        }
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
        $passwordChanged = false;
        if (!empty($data['password'])) {
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $policyError = validatePasswordStrength((string)$data['password'], (string)($data['username'] ?? $stmt->fetchColumn()));
            if ($policyError !== null) {
                http_response_code(400);
                echo json_encode(['error' => $policyError]);
                exit;
            }
            $passwordHash = password_hash((string)$data['password'], PASSWORD_DEFAULT);
            $updates[] = "password_hash = ?";
            $params[] = $passwordHash;
            $passwordChanged = true;
        }
        
        // Update role
        if (isset($data['role'])) {
            $validRoles = ['Admin', 'Clerk', 'Member', 'Viewer', 'Assistant'];
            if (!in_array($data['role'], $validRoles, true)) {
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
        $deactivated = false;
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = (bool)$data['is_active'] ? 1 : 0;
            $deactivated = !(bool)$data['is_active'];
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

        // Sign the user out everywhere when their password is reset or the
        // account is switched off (role changes apply on the next request
        // anyway - see revalidateSession())
        $isSelf = (int)getCurrentUser()['id'] === $id;
        if ($passwordChanged) {
            // An admin-set password must be replaced by its owner
            setMustChangePassword($id, !$isSelf);
        }
        if ($passwordChanged || $deactivated) {
            bumpSessionVersion($id);
        }
        
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

