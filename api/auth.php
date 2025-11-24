<?php
/**
 * Authentication API Endpoint
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Check if user is logged in
        if (isLoggedIn()) {
            $user = getCurrentUser();
            echo json_encode([
                'logged_in' => true,
                'user' => $user
            ]);
        } else {
            echo json_encode([
                'logged_in' => false
            ]);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';
        
        if ($action === 'login') {
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['error' => 'Username and password are required']);
                exit;
            }
            
            $result = loginUser($username, $password);
            
            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(401);
                echo json_encode(['error' => $result['error']]);
            }
        } elseif ($action === 'logout') {
            logoutUser();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

