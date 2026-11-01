<?php
/**
 * Change password for the signed-in user. Also where users are sent (by
 * requireLogin()) when must_change_password is set - the seeded admin
 * account and any password an admin set on someone's behalf.
 */
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';
$forced = mustChangePassword();
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Invalid request. Please try again.';
    } elseif (countAuthAttempts('login', $user['username'], LOGIN_FAILURE_WINDOW) >= LOGIN_MAX_FAILURES_PER_ACCOUNT) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $stmt = getDBConnection()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([(int)$user['id']]);
        if (!password_verify($current, (string)$stmt->fetchColumn())) {
            recordAuthAttempt('login', $user['username']);
            $error = 'Your current password was incorrect.';
        } elseif ($new !== $confirm) {
            $error = 'The new passwords do not match.';
        } elseif (hash_equals($current, $new)) {
            $error = 'The new password must be different from the current one.';
        } elseif (($policyError = validatePasswordStrength($new, $user['username'])) !== null) {
            $error = $policyError;
        } else {
            // Also ends the user's other sessions; this one is kept
            updatePassword((int)$user['id'], $new);
            if ($forced) {
                header('Location: index.php');
                exit;
            }
            $success = 'Your password has been changed. Any other signed-in sessions have been signed out.';
        }
    }
}

outputHeader('Change Password', 'change_password.php');
?>
<style>
    .password-card { max-width: 480px; margin: 20px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
    .password-card input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
    .password-card .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
    .password-card .alert-error { background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; }
    .password-card .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #16a34a; }
    .password-card .alert-info { background: #dbeafe; border: 1px solid #bfdbfe; color: #1e40af; }
</style>
<main>
    <div class="password-card">
        <h2>Change Password</h2>
        <?php if ($forced): ?>
            <div class="alert alert-info">You need to choose a new password before you can continue.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <div class="form-group">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" autocomplete="new-password">
                <small style="color: #666;">At least <?php echo PASSWORD_MIN_LENGTH; ?> characters, not containing your username, and not a password known from a data breach.</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
    </div>
</main>
<?php outputFooter(); ?>
</body>
</html>
