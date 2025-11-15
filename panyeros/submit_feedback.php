<?php
header('Content-Type: application/json');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panyeros');

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    // Validate input
    if (!isset($_POST['comment']) || !isset($_POST['rating'])) {
        throw new Exception('Missing required fields');
    }

    $comment = trim($_POST['comment']);
    $rating = intval($_POST['rating']);

    // Validate comment length
    if (strlen($comment) < 10) {
        throw new Exception('Comment must be at least 10 characters long');
    }

    // Validate rating range
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Rating must be between 1 and 5');
    }

    // Connect to database
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Insert feedback (using "Anonymous" as the name since HTML form doesn't collect names)
    $stmt = $conn->prepare("INSERT INTO feedback (name, comment, rating) VALUES (:name, :comment, :rating)");
    $stmt->execute([
        ':name' => 'Anonymous',
        ':comment' => $comment,
        ':rating' => $rating
    ]);

    $response['success'] = true;
    $response['message'] = 'Feedback submitted successfully';
    $response['feedback_id'] = $conn->lastInsertId();

} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch(Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>