<?php
/**
 * PDF Viewer Endpoint - Serves PDFs inline for embedding
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

// Verify it's a PDF
$fileExtension = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));
if ($fileExtension !== 'pdf' && $document['mime_type'] !== 'application/pdf') {
    http_response_code(400);
    die('File is not a PDF');
}

// Serve PDF inline for embedding
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $document['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;

