<?php
/**
 * Whoami API Endpoint - Returns the current authenticated user and their
 * full permission map. Works for both session-cookie and X-API-Key clients
 * (the same requireAuth() every other endpoint uses), so non-browser
 * clients (e.g. a mobile app) can discover the acting user's role and
 * exactly which actions they're allowed to perform without needing to be
 * told out-of-band.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$permissions = [];
foreach (array_keys(PERMISSIONS) as $action) {
    $permissions[$action] = hasPermission($action);
}

echo json_encode([
    'user' => getCurrentUser(),
    'permissions' => $permissions
]);
