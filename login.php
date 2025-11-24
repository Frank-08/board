<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Governance Board Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .login-container h1 {
            text-align: center;
            color: #667eea;
            margin-bottom: 30px;
        }
        
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        .login-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .login-form input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .login-form button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .login-form button:hover {
            background: #5568d3;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Governance Board Management System</h1>
        <div id="errorMessage" class="error-message"></div>
        <form id="loginForm" class="login-form" onsubmit="handleLogin(event)">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="login-footer">
            <p>Default credentials: admin / admin123</p>
            <p style="font-size: 12px; color: #999; margin-top: 10px;">Please change the default password after first login</p>
        </div>
    </div>

    <script>
        // Check if already logged in
        window.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus();
        });

        function checkLoginStatus() {
            fetch('api/auth.php')
                .then(response => response.json())
                .then(data => {
                    if (data.logged_in) {
                        window.location.href = 'index.php';
                    }
                })
                .catch(error => {
                    console.error('Error checking login status:', error);
                });
        }

        function handleLogin(event) {
            event.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('errorMessage');
            
            // Clear previous errors
            errorDiv.classList.remove('show');
            errorDiv.textContent = '';
            
            fetch('api/auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'login',
                    username: username,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to dashboard
                    window.location.href = 'index.php';
                } else {
                    // Show error
                    errorDiv.textContent = data.error || 'Login failed';
                    errorDiv.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.classList.add('show');
            });
        }
    </script>
</body>
</html>

