<?php
/**
 * Logout Handler
 * 
 * Destroys the user session and redirects to login page.
 */

require_once __DIR__ . '/config/auth.php';

// Log out the user
logout();

// Redirect to login page with success message
header('Location: login.php');
exit;

