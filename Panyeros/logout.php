<?php
// 1. Start the session to access it.
session_start();

// 2. Unset all session variables.
session_unset();

// 3. Destroy the session data from the server.
session_destroy();

// 4. Add cache-control headers to prevent back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// 5. Redirect the user back to the login page.
header("Location: login.php");

// 6. Ensure no further code is executed.
exit;
?>