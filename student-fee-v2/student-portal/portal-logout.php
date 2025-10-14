<?php
/**
 * Student Portal Logout - Hiticx
 */

require_once __DIR__ . '/portal-bootstrap.php';

hiticx_portal_bootstrap();

// Destroy all session data
session_destroy();

// Redirect to login page
header('Location: portal-login.php');
exit;
