<?php
/**
 * User Logout
 * Campus Complaint Management and Tracking System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();

// Start a fresh session just to hold the logout flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
setFlash('info', 'You have been logged out successfully.');

header('Location: ' . url('login.php'));
exit;
