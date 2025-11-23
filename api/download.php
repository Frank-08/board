<?php
/**
 * Document Download Endpoint
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$documentId) {
    http_response_code(400);
    die('Document ID is required');
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT file_path, file_name, mime_type FROM documents WHERE id = ?");
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    die('Document not found');
}

$filePath = UPLOAD_DIR . $document['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

header('Content-Type: ' . $document['mime_type']);
header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;

