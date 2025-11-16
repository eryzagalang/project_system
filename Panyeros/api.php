<!-- submit_feedback.php - API endpoint for HTML form -->
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panyeros');

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $comment = trim($_POST['comment'] ?? '');
        $rating = (int)($_POST['rating'] ?? 0);

        if (empty($comment) || $rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Invalid feedback data']);
            exit;
        }

        // Insert with anonymous name
        $stmt = $conn->prepare("INSERT INTO feedback (name, comment, rating) VALUES (?, ?, ?)");
        $stmt->execute(['Anonymous', $comment, $rating]);

        echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>