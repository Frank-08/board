<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';
$token = $_GET['token'] ?? '';
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reset Password - Together in Council</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container">
    <h2>Set a new password</h2>
    <?php if (empty($token)): ?>
        <p>Invalid reset link.</p>
        <p><a href="forgot_password.php">Request a new link</a></p>
    <?php else: ?>
        <form method="POST" action="api/password_reset.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div>
                <label for="password">New password</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <div>
                <label for="password_confirm">Confirm</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
            </div>
            <div>
                <button type="submit">Change password</button>
            </div>
        </form>
    <?php endif; ?>

    <p><a href="login.php">Back to sign in</a></p>
</div>
</body>
</html>
