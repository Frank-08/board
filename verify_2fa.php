<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify 2FA - Together in Council</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .login-form input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            font-size: 20px;
            text-align: center;
            letter-spacing: 8px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .login-form input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .login-form .btn {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-info {
            background-color: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        
        .backup-code-hint {
            margin-top: 15px;
            padding: 12px;
            background-color: #f3f4f6;
            border-radius: 6px;
            font-size: 13px;
            color: #6b7280;
        }
        
        .backup-code-hint a {
            color: #667eea;
            text-decoration: none;
        }
        
        .backup-code-hint a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Two-Factor Authentication</h1>
                <p>Enter the 6-digit code from your authenticator app</p>
            </div>
            
            <?php
            require_once __DIR__ . '/config/auth.php';
            require_once __DIR__ . '/config/twofactor.php';
            
            $error = '';
            $redirect = $_GET['redirect'] ?? 'index.php';
            
            // Check if there's a pending login
            if (!isset($_SESSION['pending_user_id'])) {
                header('Location: login.php?redirect=' . urlencode($redirect));
                exit;
            }
            
            // Check if pending login is not too old (15 minutes max)
            if (!isset($_SESSION['pending_login_time']) || (time() - $_SESSION['pending_login_time']) > 900) {
                unset($_SESSION['pending_user_id'], $_SESSION['pending_username'], 
                      $_SESSION['pending_email'], $_SESSION['pending_role'], 
                      $_SESSION['pending_board_member_id'], $_SESSION['pending_login_time']);
                header('Location: login.php?redirect=' . urlencode($redirect) . '&error=session_expired');
                exit;
            }
            
            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $code = trim($_POST['code'] ?? '');
                $csrfToken = $_POST['csrf_token'] ?? '';
                
                if (!verifyCsrfToken($csrfToken)) {
                    $error = 'Invalid request. Please try again.';
                } elseif (empty($code)) {
                    $error = 'Please enter your 2FA code.';
                } else {
                    $userId = $_SESSION['pending_user_id'];
                    $result = verifyTwoFactorCode($userId, $code);
                    
                    if ($result['success']) {
                        // Complete login
                        if (completeTwoFactorLogin()) {
                            header('Location: ' . $redirect);
                            exit;
                        } else {
                            $error = 'Session expired. Please log in again.';
                        }
                    } else {
                        $error = $result['message'];
                    }
                }
            }
            
            $csrfToken = generateCsrfToken();
            ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <strong>Tip:</strong> You can also use a backup code if you don't have access to your authenticator app.
            </div>
            
            <form class="login-form" method="POST" action="" id="verifyForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <input type="text" id="code" name="code" required autofocus 
                           maxlength="6" pattern="[0-9]{6}" 
                           placeholder="000000"
                           autocomplete="one-time-code">
                </div>
                
                <button type="submit" class="btn btn-primary">Verify</button>
            </form>
            
            <div class="backup-code-hint">
                Don't have your device? <a href="login.php">Go back to login</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-submit when 6 digits are entered
        document.getElementById('code').addEventListener('input', function(e) {
            const value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            e.target.value = value;
            
            if (value.length === 6) {
                // Small delay to let user see the code
                setTimeout(function() {
                    document.getElementById('verifyForm').submit();
                }, 300);
            }
        });
        
        // Focus on code input
        document.getElementById('code').focus();
    </script>
</body>
</html>

