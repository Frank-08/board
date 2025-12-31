<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include all PHP files BEFORE any HTML output
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/twofactor.php';
require_once __DIR__ . '/config/database.php';

$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}

// Check if migration has been run
try {
    $db = getDBConnection();
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
    $migrationRun = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $migrationRun = false;
    error_log("Error checking migration: " . $e->getMessage());
}

$userId = $currentUser['id'];
$isEnabled = false;

if ($migrationRun) {
    $isEnabled = isTwoFactorEnabled($userId);
}

$error = '';
$success = '';
$setupMode = false;
$secret = null;
$qrUrl = null;
$backupCodes = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'start_setup') {
            // Generate new secret for setup
            $secret = generateTwoFactorSecret();
            $qrUrl = generateTwoFactorQRUrl($secret, $currentUser['email']);
            $setupMode = true;
            $_SESSION['2fa_setup_secret'] = $secret;
        } elseif ($action === 'verify_setup') {
            // Verify the code and enable 2FA
            $code = trim($_POST['code'] ?? '');
            
            if (empty($code)) {
                $error = 'Please enter the verification code.';
            } elseif (!isset($_SESSION['2fa_setup_secret'])) {
                $error = 'Setup session expired. Please start over.';
            } else {
                $secret = $_SESSION['2fa_setup_secret'];
                
                if (verifyTOTPCode($secret, $code)) {
                    // Generate backup codes
                    $backupCodes = generateBackupCodes(10);
                    
                    // Enable 2FA
                    if (enableTwoFactor($userId, $secret, $backupCodes)) {
                        unset($_SESSION['2fa_setup_secret']);
                        $success = 'Two-factor authentication has been enabled successfully!';
                        $isEnabled = true;
                        $setupMode = false;
                    } else {
                        $error = 'Failed to enable 2FA. Please try again.';
                    }
                } else {
                    $error = 'Invalid code. Please try again.';
                }
            }
        } elseif ($action === 'disable') {
            // Disable 2FA
            if (disableTwoFactor($userId)) {
                $success = 'Two-factor authentication has been disabled.';
                $isEnabled = false;
            } else {
                $error = 'Failed to disable 2FA. Please try again.';
            }
        }
    }
}

// If in setup mode, get the secret from session
if (isset($_SESSION['2fa_setup_secret']) && !$isEnabled) {
    $secret = $_SESSION['2fa_setup_secret'];
    $qrUrl = generateTwoFactorQRUrl($secret, $currentUser['email']);
    $setupMode = true;
}

// Now output the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication Setup - Together in Council</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            margin-bottom: 10px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .card h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .status-enabled {
            background-color: #dcfce7;
            color: #16a34a;
        }
        
        .status-disabled {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .qr-code-container {
            text-align: center;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .qr-code-container img {
            max-width: 250px;
            height: auto;
            border: 4px solid white;
            border-radius: 8px;
        }
        
        .secret-display {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            background-color: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            letter-spacing: 2px;
            margin: 15px 0;
            word-break: break-all;
        }
        
        .backup-codes {
            background-color: #fef3c7;
            border: 2px solid #fbbf24;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .backup-codes h4 {
            margin-top: 0;
            color: #92400e;
        }
        
        .backup-codes-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }
        
        .backup-code {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: bold;
            padding: 10px;
            background-color: white;
            border-radius: 4px;
            text-align: center;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        
        .alert-info {
            background-color: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            font-size: 20px;
            text-align: center;
            letter-spacing: 8px;
        }
        
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #5568d3;
        }
        
        .btn-danger {
            background-color: #dc2626;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #b91c1c;
        }
        
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        
        .instructions {
            background-color: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin: 8px 0;
        }
        
        .step-indicator {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            background-color: #667eea;
            color: white;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php outputHeader('Two-Factor Authentication', 'setup_2fa.php'); ?>
    
    <main>
        <div class="container">
            <div class="page-header">
                <h2>Two-Factor Authentication</h2>
                <p>Add an extra layer of security to your account</p>
            </div>
            
            <?php if (!$migrationRun): ?>
                <div class="card">
                    <h3>Database Migration Required</h3>
                    <div class="alert alert-error">
                        <strong>Error:</strong> The 2FA database migration has not been run yet.
                    </div>
                    <p>Please run the following SQL migration to enable 2FA support:</p>
                    <pre style="background: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 12px;"><?php 
                        $migrationFile = __DIR__ . '/database/migration_add_2fa.sql';
                        if (file_exists($migrationFile)) {
                            echo htmlspecialchars(file_get_contents($migrationFile));
                        } else {
                            echo "Migration file not found at: " . htmlspecialchars($migrationFile);
                        }
                    ?></pre>
                    <p style="margin-top: 15px;">
                        <strong>To run the migration:</strong><br>
                        <code>mysql -u your_username -p governance_board &lt; database/migration_add_2fa.sql</code>
                    </p>
                </div>
            <?php else: ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <h3>Current Status</h3>
                <?php if ($isEnabled): ?>
                    <span class="status-badge status-enabled">✓ Enabled</span>
                    <p>Two-factor authentication is currently enabled for your account.</p>
                    
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <input type="hidden" name="action" value="disable">
                        <button type="submit" class="btn btn-danger">Disable 2FA</button>
                    </form>
                <?php else: ?>
                    <span class="status-badge status-disabled">✗ Disabled</span>
                    <p>Two-factor authentication is not enabled for your account.</p>
                    
                    <?php if (!$setupMode): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                            <input type="hidden" name="action" value="start_setup">
                            <button type="submit" class="btn btn-primary">Enable 2FA</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($setupMode && !$isEnabled): ?>
                <div class="card">
                    <h3>Setup Two-Factor Authentication</h3>
                    
                    <div class="instructions">
                        <p><strong>Follow these steps to set up 2FA:</strong></p>
                        <ol>
                            <li>Install an authenticator app on your phone (Google Authenticator, Authy, Microsoft Authenticator, etc.)</li>
                            <li>Scan the QR code below with your authenticator app</li>
                            <li>Enter the 6-digit code from your app to verify</li>
                            <li>Save your backup codes in a safe place</li>
                        </ol>
                    </div>
                    
                    <div class="qr-code-container">
                        <?php if ($qrUrl): ?>
                        <img src="api/qrcode.php?data=<?php echo urlencode($qrUrl); ?>" 
                             alt="QR Code" 
                             style="max-width: 250px; height: auto; border: 4px solid white; border-radius: 8px;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div style="display: none; color: #dc2626; padding: 20px; text-align: center;">
                            <p><strong>Failed to generate QR code.</strong></p>
                            <p>Please use the manual entry method below.</p>
                        </div>
                        <?php endif; ?>
                        <p style="margin-top: 15px; font-size: 14px; color: #666;">
                            Or scan using this URL: <br>
                            <a href="<?php echo htmlspecialchars($qrUrl); ?>" target="_blank" style="word-break: break-all; color: #667eea;"><?php echo htmlspecialchars($qrUrl); ?></a>
                        </p>
                        <p style="margin-top: 10px; font-size: 14px; color: #666;">
                            Or enter this code manually: <strong style="font-family: monospace; font-size: 16px;"><?php echo htmlspecialchars($secret); ?></strong>
                        </p>
                    </div>
                    
                    <form method="POST" action="" id="verifyForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                        <input type="hidden" name="action" value="verify_setup">
                        
                        <div class="form-group">
                            <label for="code">Enter verification code from your app</label>
                            <input type="text" id="code" name="code" required autofocus 
                                   maxlength="6" pattern="[0-9]{6}" 
                                   placeholder="000000">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Verify and Enable</button>
                    </form>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($backupCodes)): ?>
                <div class="card">
                    <div class="backup-codes">
                        <h4>⚠️ Save These Backup Codes</h4>
                        <p>These codes can be used to access your account if you lose your authenticator device. Each code can only be used once.</p>
                        <div class="backup-codes-list">
                            <?php foreach ($backupCodes as $code): ?>
                                <div class="backup-code"><?php echo htmlspecialchars($code); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <p style="margin-top: 15px; font-size: 14px; color: #92400e;">
                            <strong>Important:</strong> Store these codes in a safe place. You won't be able to see them again after leaving this page.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h3>About Two-Factor Authentication</h3>
                <p>Two-factor authentication (2FA) adds an extra layer of security to your account. Even if someone knows your password, they won't be able to access your account without your authenticator app.</p>
                
                <h4 style="margin-top: 20px;">How it works:</h4>
                <ul>
                    <li>When you log in, you'll enter your username and password as usual</li>
                    <li>Then you'll be asked for a 6-digit code from your authenticator app</li>
                    <li>The code changes every 30 seconds for security</li>
                </ul>
                
                <h4 style="margin-top: 20px;">Backup codes:</h4>
                <p>If you lose access to your authenticator device, you can use one of your backup codes to log in. Make sure to store them securely!</p>
            </div>
            <?php endif; // End migration check ?>
        </div>
    </main>
    
    <?php outputFooter(); ?>
    
    <script>
        // Auto-submit when 6 digits are entered
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('code');
            if (codeInput) {
                codeInput.addEventListener('input', function(e) {
                    const value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                    e.target.value = value;
                    
                    if (value.length === 6) {
                        // Small delay to let user see the code
                        setTimeout(function() {
                            const form = document.getElementById('verifyForm');
                            if (form) {
                                form.submit();
                            }
                        }, 300);
                    }
                });
            }
        });
    </script>
</body>
</html>
