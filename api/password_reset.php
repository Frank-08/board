<?php
/**
 * Password Reset API Endpoint
 * 
 * Handles password reset requests and password resets.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Handle password reset request (email only)
    if (isset($data['email']) && !isset($data['token'])) {
        $email = trim($data['email'] ?? '');
        
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['error' => 'Email is required']);
            exit;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email format']);
            exit;
        }
        
        // Request password reset
        $result = requestPasswordReset($email);
        
        if ($result['success']) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => $result['message']]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        exit;
    }
    
    // Handle password reset (token and new password)
    if (isset($data['token']) && isset($data['password'])) {
        $token = trim($data['token'] ?? '');
        $password = $data['password'] ?? '';
        
        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Token is required']);
            exit;
        }
        
        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Password is required']);
            exit;
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters long']);
            exit;
        }
        
        // Reset password
        $result = resetPassword($token, $password);
        
        if ($result['success']) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => $result['message']]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        exit;
    }
    
    // Invalid request
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request. Provide either email (for reset request) or token and password (for reset)']);
    
} catch (Exception $e) {
    error_log("Password reset API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred. Please try again later.']);
}

