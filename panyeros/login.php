<?php
session_start();

// Database configuration (update with your credentials)
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'panyeros_db');

// Initialize variables
$error = '';
$success = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Database connection
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Prepare and execute query
            $stmt = $conn->prepare("SELECT id, email, password, name FROM users WHERE email = :email AND active = 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['logged_in'] = true;
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch(PDOException $e) {
            $error = 'Connection failed. Please try again later.';
            // Log error: error_log($e->getMessage());
        }
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
            background: #f5f5f5;
            display: flex;
            min-height: 100vh;
        }
        
        .left-section {
            flex: 1;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .food-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            max-width: 500px;
            width: 100%;
            margin-bottom: 30px;
        }
        
        .food-item {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            aspect-ratio: 1;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .food-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .brush-stroke {
            position: absolute;
            width: 200px;
            height: 100px;
            background: #D4874B;
            opacity: 0.8;
            border-radius: 50%;
            transform: rotate(-20deg);
        }
        
        .brush-1 { top: 10%; right: -50px; }
        .brush-2 { top: 35%; left: 35%; width: 250px; }
        .brush-3 { bottom: 30%; right: -30px; width: 180px; }
        
        .logo-text {
            font-family: 'Brush Script MT', cursive;
            font-size: 48px;
            color: #8B5A2B;
            text-align: center;
            font-style: italic;
            margin-top: 20px;
        }
        
        .right-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #fafafa;
        }
        
        .brand-logo {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #F5B461;
            padding: 8px 20px;
            font-weight: bold;
            color: #8B5A2B;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .user-icon {
            width: 120px;
            height: 120px;
            background: #D4874B;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .user-icon::before {
            content: '';
            width: 40px;
            height: 40px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            top: 25px;
        }
        
        .user-icon::after {
            content: '';
            width: 70px;
            height: 70px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            bottom: 10px;
        }
        
        h1 {
            text-align: center;
            font-size: 48px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 15px 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #D4874B;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            font-size: 18px;
        }
        
        .forgot-password {
            text-align: right;
            margin: 10px 0 20px;
        }
        
        .forgot-password a {
            color: #666;
            text-decoration: none;
            font-size: 13px;
        }
        
        .forgot-password a:hover {
            color: #D4874B;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: #D4874B;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            letter-spacing: 2px;
        }
        
        .submit-btn:hover {
            background: #B87439;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        @media (max-width: 968px) {
            body {
                flex-direction: column;
            }
            
            .left-section {
                min-height: 300px;
            }
            
            .brand-logo {
                position: static;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="left-section">
        <div class="brush-stroke brush-1"></div>
        <div class="brush-stroke brush-2"></div>
        <div class="brush-stroke brush-3"></div>
        
        <div class="food-grid">
            <div class="food-item">
                <img src="shrimp-dish.jpg" alt="Shrimp Dish">
            </div>
            <div class="food-item">
                <img src="noodles-dish.jpg" alt="Noodles">
            </div>
            <div class="food-item">
                <img src="glazed-meat.jpg" alt="Glazed Meat">
            </div>
            <div class="food-item">
                <img src="pasta-dish.jpg" alt="Pasta">
            </div>
        </div>
        
        <div class="logo-text">Panyeros Kusina</div>
    </div>
    
    <div class="right-section">
        <div class="brand-logo">panyeros</div>
        
        <div class="login-container">
            <div class="user-icon"></div>
            
            <h1>Login</h1>
            <p class="subtitle">Log in your account</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="Email Address" 
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            placeholder="Password" 
                            required
                        >
                        <span class="toggle-password" onclick="togglePassword()">👁</span>
                    </div>
                </div>
                
                <div class="forgot-password">
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>
                
                <button type="submit" name="submit" class="submit-btn">SUBMIT</button>
            </form>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
        }
    </script>
</body>
</html>