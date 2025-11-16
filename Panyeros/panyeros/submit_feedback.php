<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panyeros');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get POST data
    $feedback_text = isset($_POST['feedback-text']) ? trim($_POST['feedback-text']) : '';
    $rating = isset($_POST['rating']) ? trim($_POST['rating']) : '';
    
    // Validate input
    if (empty($feedback_text) || strlen($feedback_text) < 10) {
        echo json_encode(['success' => false, 'message' => 'Feedback must be at least 10 characters long']);
        exit;
    }
    
    if (empty($rating) || !is_numeric($rating) || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid rating']);
        exit;
    }
    
    // Connect to database
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Insert feedback into database
    $sql = "INSERT INTO feedback (name, comment, rating, created_at, archived) 
            VALUES (:name, :comment, :rating, NOW(), 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'name' => 'Anonymous',  // Always anonymous as per your HTML form
        'comment' => $feedback_text,
        'rating' => $rating
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Salamat sa inyong feedback! Your feedback has been submitted successfully.'
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>