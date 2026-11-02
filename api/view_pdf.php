<?php
/**
 * PDF Viewer Endpoint - Serves PDFs inline for embedding
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/document_helpers.php';

// Require authentication for PDF viewing
requireAuth();

$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$documentId) {
    http_response_code(400);
    die('Document ID is required');
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT id, sharepoint_url, title FROM documents WHERE id = ?");
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document || empty($document['sharepoint_url'])) {
    http_response_code(404);
    die('Document not found');
}

// Re-validate on the way out: links saved before the validator was
// tightened (or edited in the database) must not become an open redirect
$url = normaliseSharePointUrl($document['sharepoint_url']);
if ($url === null) {
    http_response_code(400);
    die('Document link is invalid');
}

header('Location: ' . $url, true, 302);
exit;

