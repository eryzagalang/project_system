<?php
// get_feedbacks.php - Fetch feedbacks for HTML page
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'panyeros');

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch only positive feedbacks (3-5 stars) for public display
    $stmt = $conn->query("SELECT comment, rating FROM feedback WHERE rating >= 3 AND comment != '' ORDER BY ID DESC LIMIT 50");
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>