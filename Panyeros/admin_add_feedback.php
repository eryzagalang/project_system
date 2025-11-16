<?php
session_start();

// 1. First, check if the user is logged in at all.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. Second, check if the logged-in user is an admin.
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403);
    die('<h1>Access Denied</h1><p>You do not have permission to view this page.</p>');
}

// --- DATABASE AND FORM PROCESSING STARTS HERE ---

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panyeros');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    
    $name = trim($_POST['customerName'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $rating = $_POST['rating'] ?? '';
    
    if (empty($name) || empty($comment) || empty($rating)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $conn->prepare("INSERT INTO feedback (name, comment, rating) VALUES (:name, :comment, :rating)");
            
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':comment', $comment);
            $stmt->bindParam(':rating', $rating);
            
            $stmt->execute();
            
            $success = "Feedback submitted successfully!";
            
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
    <title>Admin - Add Feedback</title>
    <link rel="stylesheet" href="feed.css">
    
    <style>
        /* This CSS block will center the card */
        body { 
            font-family: 'Segoe UI', sans-serif;
            background: #f4f4f4; 
            
            /* Use Flexbox to center the card */
            display: flex; 
            justify-content: center; /* Horizontally center */
            align-items: center;    /* Vertically center */
            
            min-height: 100vh;      /* Full viewport height */
            padding: 20px;
            margin: 0;
            box-sizing: border-box;
        }

        /* We apply width constraints directly to the card */
        .card {
            max-width: 600px; /* Set a max width */
            width: 100%;       /* Allow it to be responsive */
            
            /* These are just for cleanup */
            margin: 0; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); /* Add a soft shadow */
        }
        
        .card-header { 
            font-size: 24px; 
            font-weight: 600;
        }
        
        .nav-link { 
            display: block; 
            text-align: center; 
            margin-top: 20px; 
            color: #D4874B; 
            text-decoration: none; 
            font-size: 16px; 
        }
        
        /* Alerts */
        .alert { 
            display: none; 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
            border: 1px solid transparent; 
        }
        .alert-success { 
            color: #155724; 
            background-color: #d4edda; 
            border-color: #c3e6cb; 
        }
        .alert-error { 
            color: #721c24; 
            background-color: #f8d7da; 
            border-color: #f5c6cb; 
        }
        .alert.show { 
            display: block; 
        }
    </style>
</head>
<body>
    
    <div class="card">
        <div class="card-header">Admin: Add Test Feedback</div>
        <div class="card-body">
            
            <?php if ($success): ?>
                <div class="alert alert-success show"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error show"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form id="feedbackForm" method="POST" action="admin_add_feedback.php">
                <div class="form-group">
                    <label class="form-label">Customer Name</label>
                    <input type="text" id="customerName" name="customerName" class="form-control" 
                           placeholder="Enter customer's name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Comment</label>
                    <textarea id="comment" name="comment" class="form-control" 
                              placeholder="Enter customer's comment..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Rate us</label>
                    <select id="rating" name="rating" class="rating-select" required>
                        <option value="">Select Rating</</option>
                        <option value="5">5 Stars - Excellent</option>
                        <option value="4">4 Stars - Very Good</option>
                        <option value="3">3 Stars - Good</option>
                        <option value="2">2 Stars - Fair</option>
                        <option value="1">1 Star - Poor</option>
                    </select>
                </div>
                
                <button type="submit" name="submit" class="submit-btn">Submit Feedback</button>
            </form>

            <a href="home.php" class="nav-link">Back to Dashboard</a>
        </div>
    </div>

</body>
</html>