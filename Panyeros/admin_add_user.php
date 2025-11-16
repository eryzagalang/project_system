<?php
session_start();

// 1. First, check if the user is logged in at all.
// If not, they go back to the login page.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. Second, check if the logged-in user is an admin.
// If they are NOT an admin, we stop the page with an error.
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    // Set the HTTP response code to "403 Forbidden"
    http_response_code(403);
    
    // Stop the script and show a message.
    die('<h1>Access Denied</h1><p>You do not have permission to view this page.</p>');
}

// --- ALL YOUR ADMIN PAGE HTML GOES BELOW THIS ---
// (Only admins will ever reach this point)
?>

<?php

// -----------------------------------------------------------------
// 2. DATABASE CONFIG
// -----------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Your confirmed username
define('DB_PASS', '');     // Your confirmed password
define('DB_NAME', 'panyeros');

// Initialize variables for messages
$error = '';
$success = '';

// -----------------------------------------------------------------
// 3. PROCESS THE "ADD USER" FORM
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    // Check if the admin checkbox was checked.
    // If it's set, value is 1. If not, value is 0.
    $is_admin = isset($_POST['is_admin']) ? 1 : 0; 
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        
        try {
            // Database connection
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Error: User already exists
                $error = 'An account with this email already exists.';
            } else {
                // Email is unique, proceed
                
                // HASH THE PASSWORD
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Prepare and execute INSERT query with is_admin
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, active, is_admin) VALUES (:name, :email, :password, 1, :is_admin)");
                
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':is_admin', $is_admin); // Bind the checkbox value
                
                $stmt->execute();
                
                // Success! Give a helpful message
                $user_type = ($is_admin == 1) ? "Admin User" : "User";
                $success = "{$user_type} '{$name}' created successfully!";
            }
            
        } catch(PDOException $e) {
            $error = 'Connection failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Add User</title>
    <style>
        /* This is a simple, clean style for the admin page */
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 50px;
            margin: 0;
            color: #333;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 30px;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box; /* Fixes padding issue */
        }
        
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #D4874B;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .submit-btn:hover {
            background: #B87439;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid transparent;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border-color: #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #393;
            border-color: #cfc;
        }

        .nav-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #D4874B;
            text-decoration: none;
        }

        .form-group-check {
            display: flex;
            align-items: center;
        }
        .form-group-check input[type="checkbox"] {
            width: auto; /* Override the 100% width */
            margin-right: 10px;
        }
        .form-group-check label {
            margin-bottom: 0; /* Override the default margin */
        }

    </style>
</head>
<body>

    <div class="container">
        <h1>Add New User</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="admin_add_user.php">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            

        <div class="form-group form-group-check">
            <input type="checkbox" id="is_admin" name="is_admin" value="1">
            <label for="is_admin">Make this user an Admin</label>
        </div>

        <button type="submit" name="submit" class="submit-btn">Create User</button>
        </form>

        <a href="home.php" class="nav-link">Back to Dashboard</a>
    </div>

</body>
</html>