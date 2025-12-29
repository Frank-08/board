<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token = $_POST['token'] ?? '';
$newPassword = $_POST['password'] ?? '';

if (empty($token) || empty($newPassword) || strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Invalid token or password.']);
    exit;
}

$tokenHash = hash('sha256', $token);
$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT id, password_reset_expires, password_reset_used FROM users WHERE password_reset_token_hash = :token_hash LIMIT 1');
$stmt->execute([':token_hash' => $tokenHash]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
    exit;
}

if ($user['password_reset_used']) {
    echo json_encode(['success' => false, 'message' => 'This reset link has already been used.']);
    exit;
}

if (strtotime($user['password_reset_expires']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Reset token has expired.']);
    exit;
}

// Update password
$hashed = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $pdo->prepare('UPDATE users SET password_hash = :pwd, password_reset_used = 1, password_reset_token_hash = NULL, password_reset_expires = NULL, password_reset_requested_at = NULL, updated_at = NOW() WHERE id = :id');
$update->execute([':pwd' => $hashed, ':id' => $user['id']]);

// TODO: Invalidate existing sessions for this user if you track sessions in DB.

echo json_encode(['success' => true, 'message' => 'Password has been reset successfully. You may now sign in with your new password.']);
