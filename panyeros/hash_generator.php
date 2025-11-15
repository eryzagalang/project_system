<?php
/* * 1. Change 'password123' to any password you want to use.
 * 2. Save this file and open it in your browser (e.g., http://localhost/your_project/hash_generator.php)
 * 3. Copy the long string of text it displays.
 */

$myPassword = 'password123';
$hashedPassword = password_hash($myPassword, PASSWORD_DEFAULT);

echo $hashedPassword;
?>