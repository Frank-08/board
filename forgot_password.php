<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Forgot Password - Together in Council</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container">
    <h2>Forgot your password?</h2>
    <p>Enter your email address and we'll send a link to reset your password.</p>

    <form method="POST" action="api/password_reset_request.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <button type="submit">Send reset link</button>
        </div>
    </form>

    <p><a href="login.php">Back to sign in</a></p>
</div>
</body>
</html>
