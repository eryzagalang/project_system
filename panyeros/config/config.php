
<?php
// Database Configuration
$host = 'localhost';
$dbname = 'panyeros';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;panyeros=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]));
}
?>



