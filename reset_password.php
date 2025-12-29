<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Together in Council</title>
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
        
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
        }
        
        .login-form input[type="password"]:focus {
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
        
        .alert-success {
            background-color: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 12px;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .password-strength {
            height: 4px;
            background: #e1e1e1;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .password-strength-weak { background-color: #dc2626; }
        .password-strength-medium { background-color: #f59e0b; }
        .password-strength-strong { background-color: #16a34a; }
    </style>
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('password-strength-bar');
            const strengthText = document.getElementById('password-strength-text');
            
            let strength = 0;
            let text = '';
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            
            if (strength <= 2) {
                strengthBar.className = 'password-strength-bar password-strength-weak';
                strengthBar.style.width = '33%';
                text = 'Weak';
            } else if (strength <= 3) {
                strengthBar.className = 'password-strength-bar password-strength-medium';
                strengthBar.style.width = '66%';
                text = 'Medium';
            } else {
                strengthBar.className = 'password-strength-bar password-strength-strong';
                strengthBar.style.width = '100%';
                text = 'Strong';
            }
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                text = '';
            }
            
            if (strengthText) {
                strengthText.textContent = text;
            }
        }
        
        function validatePasswords() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submit-btn');
            
            if (password.length < 8) {
                submitBtn.disabled = true;
                return false;
            }
            
            if (password !== confirmPassword) {
                submitBtn.disabled = true;
                return false;
            }
            
            submitBtn.disabled = false;
            return true;
        }
    </script>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Together in Council</h1>
                <h3>One Church, many councils, discerning together</h3>
                <p>Set your new password</p>
            </div>
            
            <?php
            require_once __DIR__ . '/config/auth.php';
            
            $error = '';
            $success = '';
            $token = $_GET['token'] ?? '';
            $validToken = false;
            
            // If already logged in, redirect
            if (isLoggedIn()) {
                header('Location: index.php');
                exit;
            }
            
            // Validate token
            if (empty($token)) {
                $error = 'Invalid reset link. Please request a new password reset.';
            } else {
                $user = validatePasswordResetToken($token);
                if ($user) {
                    $validToken = true;
                } else {
                    $error = 'Invalid or expired password reset token. Please request a new password reset.';
                }
            }
            
            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
                $token = trim($_POST['token'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                $csrfToken = $_POST['csrf_token'] ?? '';
                
                if (!verifyCsrfToken($csrfToken)) {
                    $error = 'Invalid request. Please try again.';
                } elseif (empty($password) || empty($confirmPassword)) {
                    $error = 'Please enter and confirm your new password.';
                } elseif (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters long.';
                } elseif ($password !== $confirmPassword) {
                    $error = 'Passwords do not match.';
                } else {
                    $result = resetPassword($token, $password);
                    
                    if ($result['success']) {
                        $success = $result['message'];
                        $validToken = false; // Hide form after success
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
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary" style="display: inline-block; text-decoration: none;">Go to Login</a>
                </div>
            <?php elseif ($validToken): ?>
                <form class="login-form" method="POST" action="" onsubmit="return validatePasswords();">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required autofocus
                               minlength="8"
                               oninput="checkPasswordStrength(); validatePasswords();"
                               placeholder="Enter new password (min. 8 characters)">
                        <div class="password-strength">
                            <div id="password-strength-bar" class="password-strength-bar"></div>
                        </div>
                        <div class="password-requirements">
                            <span id="password-strength-text"></span>
                            <div>Password must be at least 8 characters long.</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               minlength="8"
                               oninput="validatePasswords();"
                               placeholder="Confirm new password">
                    </div>
                    
                    <button type="submit" id="submit-btn" class="btn btn-primary" disabled>Reset Password</button>
                </form>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="login.php" style="color: #667eea; text-decoration: none; font-size: 14px;">Back to Login</a>
                </div>
            <?php else: ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="forgot_password.php" style="color: #667eea; text-decoration: none; font-size: 14px;">Request New Password Reset</a>
                </div>
            <?php endif; ?>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> Together in Council</p>
            </div>
        </div>
    </div>
</body>
</html>

