<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'panyeros');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "message" => "Database connection failed. Please try again later."
        ]);
        exit;
    }
    die("Connection failed: " . $e->getMessage());
}

// Handle POST request for login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode([
            "success" => false,
            "message" => "Please enter both username and password"
        ]);
        exit;
    }

    try {
        // Query to get user by username
        $sql = "SELECT * FROM login WHERE username = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists
        if ($user) {
            // Try password_verify first (for hashed passwords)
            $passwordMatch = password_verify($password, $user['password']);
            
            // Fallback to plain text comparison (temporary, for migration)
            if (!$passwordMatch && trim($password) === trim($user['password'])) {
                $passwordMatch = true;
            }
            
            if ($passwordMatch) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['logged_in'] = true;

                // Return success response
                echo json_encode([
                    "success" => true,
                    "message" => "Login successful!",
                    "redirect" => "home.php"
                ]);
                exit;
            }
        }
        
        // Invalid credentials
        echo json_encode([
            "success" => false,
            "message" => "Invalid username or password"
        ]);
        exit;
        
    } catch(PDOException $e) {
        echo json_encode([
            "success" => false,
            "message" => "An error occurred. Please try again later."
        ]);
        error_log("Login error: " . $e->getMessage());
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panyeros Kusina</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667db4ff 0%, #667db4ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            display: flex;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
        }

        .left-section {
            flex: 1;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .food-image-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .food-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .brand-overlay {
            position: relative;
            z-index: 2;
            background: rgba(0, 0, 0, 0.5);
            width: 100%;
            padding: 30px;
            text-align: center;
        }

        .brand-name {
            font-family: 'Brush Script MT', cursive;
            font-size: 36px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .tagline {
            color: white;
            font-size: 14px;
            margin-top: 10px;
            font-style: italic;
        }

        .right-section {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo img {
            width: 120%;
            height: 120%;
            object-fit: contain;
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            text-align: center;
            color: #999;
            margin-bottom: 40px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: #666;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #a1887f;
        }

        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            font-size: 13px;
        }

        .remember {
            display: flex;
            align-items: center;
            color: #666;
        }

        .remember input {
            margin-right: 6px;
        }

        .forgot-password {
            color: #a1887f;
            text-decoration: none;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #353230ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background: #8d6e63;
        }

        .login-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .left-section {
                padding: 40px 20px;
            }

            .right-section {
                padding: 40px 30px;
            }

            .brand-name {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-section">
            <div class="food-image-container">
                <img src="back.jpg" alt="Panyeros Kusina Food" class="food-image">
            </div>
            
            <div class="brand-overlay">
                <div class="brand-name">Panyeros Kusina</div>
                <div class="tagline">Authentic Filipino Cuisine</div>
            </div>
        </div>

        <div class="right-section">
            <div class="logo">
                <img src="logo.jpg" alt="Login Icon">
            </div>
            
            <h2>Login</h2>
            <p class="subtitle">Login to your account</p>

            <div id="message"></div>

            <form id="loginForm" method="POST">
                <div class="form-group">
                    <label for="username">Email/Username</label>
                    <input type="text" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="options">
                    <label class="remember">
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn">Login Now</button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById('message');
            const submitBtn = this.querySelector('.login-btn');
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';
            
            try {
                // Send login request to same page
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success message
                    messageDiv.innerHTML = '<div class="success">' + data.message + '</div>';
                    
                    // Redirect after 1 second
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    // Show error message
                    messageDiv.innerHTML = '<div class="error">' + data.message + '</div>';
                    
                    // Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Login Now';
                }
            } catch (error) {
                // Handle network or server errors
                messageDiv.innerHTML = '<div class="error">An error occurred. Please try again.</div>';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Login Now';
                console.error('Login error:', error);
            }
        });
    </script>
</body>
</html>