<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

// Accept POST { email }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$inputEmail = trim($_POST['email'] ?? '');

// Generic response to avoid user enumeration
$response = ['success' => true, 'message' => 'If an account exists for that email, you will receive a password reset email shortly.'];

if (empty($inputEmail) || !filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode($response);
    exit;
}

$pdo = getDBConnection();

// Rate-limiting and logging should be added here (per-IP, per-account)

$stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $inputEmail]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Respond generically
    echo json_encode($response);
    exit;
}

// Generate token
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRE_SECONDS);

$update = $pdo->prepare('UPDATE users SET password_reset_token_hash = :token_hash, password_reset_expires = :expires, password_reset_used = 0, password_reset_requested_at = :requested_at WHERE id = :id');
$update->execute([
    ':token_hash' => $tokenHash,
    ':expires' => $expiresAt,
    ':requested_at' => date('Y-m-d H:i:s'),
    ':id' => $user['id']
]);

// Build reset link
$resetLink = rtrim(BASE_URL, '/') . '/reset_password.php?token=' . urlencode($token);

$subject = 'Password reset request for ' . APP_NAME;
$body = "<p>Hello,</p>\n<p>We received a request to reset the password for your account. If you made this request, click the link below to set a new password. This link expires in " . (PASSWORD_RESET_EXPIRE_SECONDS/60) . " minutes.</p>\n<p><a href=\"$resetLink\">Reset your password</a></p>\n<p>If you did not request this, you can safely ignore this email.</p>\n";

// Send email (best-effort)
try {
    @sendMail($user['email'], $subject, $body, true);
} catch (Exception $e) {
    // swallow mailing errors; still respond generically
}

echo json_encode($response);
