<?php
/**
 * Student Portal Logout - Hiticx
 */

// Start session
if (!session_id()) {
    session_start();
}

// Destroy all session data
session_destroy();

// Redirect to login page
header('Location: portal-login.php');
exit;
?>