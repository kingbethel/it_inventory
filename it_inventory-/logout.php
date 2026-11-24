<?php
// C:\xampp\htdocs\it_inventory\logout.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    log_activity($pdo, $_SESSION['user_id'], 'LOGOUT', 'Auth', $_SESSION['user_id'], 'User logged out.');
}

// Clear all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start a fresh temporary session for the flash notification
session_start();
set_flash('info', 'You have been logged out safely.');

header("Location: " . BASE_URL . "login.php");
exit;