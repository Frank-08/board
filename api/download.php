<?php
/**
 * Document Download Endpoint
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require authentication for document downloads
requireAuth();

$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$documentId) {
    http_response_code(400);
    die('Document ID is required');
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT sharepoint_url, title FROM documents WHERE id = ?");
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document || empty($document['sharepoint_url'])) {
    http_response_code(404);
    die('Document not found');
}

if (!filter_var($document['sharepoint_url'], FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die('Document link is invalid');
}

header('Location: ' . $document['sharepoint_url'], true, 302);
exit;

