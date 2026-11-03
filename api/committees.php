<?php
/**
 * Committees API Endpoint (removed).
 *
 * Committees were replaced by meeting types. The `committees` table no longer
 * exists, so the old queries threw an unhandled 500. This now returns a clear
 * 410 Gone pointing callers at api/meeting_types.php.
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require authentication so this isn't a public probe point
requireAuth();

http_response_code(410);
echo json_encode([
    'error' => 'This endpoint has been removed. Use /api/meeting_types.php instead.',
    'code'  => 'GONE',
]);
