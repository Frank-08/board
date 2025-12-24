<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Together in Council</title>
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
        
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .login-form input[type="text"]:focus,
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Together in Council</h1>
                <h3>One Church, many councils, discerning together</h3>
                <p>Sign in to access the system</p>
            </div>
            
            <?php
            require_once __DIR__ . '/config/auth.php';
            
            $error = '';
            $redirect = $_GET['redirect'] ?? 'index.php';
            
            // If already logged in, redirect
            if (isLoggedIn()) {
                header('Location: ' . $redirect);
                exit;
            }
            
            // Handle login form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $csrfToken = $_POST['csrf_token'] ?? '';
                
                if (!verifyCsrfToken($csrfToken)) {
                    $error = 'Invalid request. Please try again.';
                } elseif (empty($username) || empty($password)) {
                    $error = 'Please enter both username and password.';
                } else {
                    $result = login($username, $password);
                    
                    if ($result['success']) {
                        header('Location: ' . $redirect);
                        exit;
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
            
            <form class="login-form" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> Together in Council</p>
            </div>
        </div>
    </div>
</body>
</html>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Together in Council</title>
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
        
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .login-form input[type="text"]:focus,
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Together in Council</h1>
                <p>Sign in to access the system</p>
            </div>
            
            <?php
            require_once __DIR__ . '/config/auth.php';
            
            $error = '';
            $success = '';
            $redirect = $_GET['redirect'] ?? 'index.php';
            
            // If already logged in, redirect
            if (isLoggedIn()) {
                header('Location: ' . $redirect);
                exit;
            }
            
            // Handle login form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $csrfToken = $_POST['csrf_token'] ?? '';
                
                // Verify CSRF token
                if (!verifyCsrfToken($csrfToken)) {
                    $error = 'Invalid request. Please try again.';
                } elseif (empty($username) || empty($password)) {
                    $error = 'Please enter both username and password.';
                } else {
                    $result = login($username, $password);
                    
                    if ($result['success']) {
                        // Redirect to intended page or dashboard
                        header('Location: ' . $redirect);
                        exit;
                    } else {
                        $error = $result['message'];
                    }
                }
            }
            
            // Generate CSRF token
            $csrfToken = generateCsrfToken();
            ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form class="login-form" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> Together in Council</p>
            </div>
        </div>
    </div>
</body>
</html>

