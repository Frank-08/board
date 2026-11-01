<?php
/**
 * Logout Handler
 * 
 * Destroys the user session and redirects to login page. Only a POST with
 * the session's CSRF token logs out, so another site can't sign users out
 * with a link or image; a plain GET shows a confirmation button instead.
 */

require_once __DIR__ . '/config/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
    logout();
    header('Location: login.php');
    exit;
}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log out - Together in Council</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div style="max-width: 400px; margin: 80px auto; text-align: center;">
        <h2>Log out?</h2>
        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <button type="submit" class="btn btn-primary">Log out</button>
            <a href="index.php" class="btn btn-secondary" style="text-decoration: none;">Cancel</a>
        </form>
    </div>
</body>
</html>
