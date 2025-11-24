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

// Handle file_path - it may be stored as just filename or as a full/relative path
$storedPath = $document['file_path'];
$uploadDir = rtrim(realpath(UPLOAD_DIR) ?: UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Try different path combinations
$filePath = null;

// 1. If file_path is an absolute path, use it directly
if (file_exists($storedPath)) {
    $filePath = $storedPath;
}
// 2. If file_path is relative to UPLOAD_DIR (most common case)
elseif (file_exists($uploadDir . $storedPath)) {
    $filePath = $uploadDir . $storedPath;
}
// 3. Try with just the filename (in case full path was stored)
else {
    $fileName = basename($storedPath);
    if (file_exists($uploadDir . $fileName)) {
        $filePath = $uploadDir . $fileName;
    }
    // 4. Try with trimmed leading slashes
    else {
        $trimmedPath = ltrim($storedPath, '/\\');
        if (file_exists($uploadDir . $trimmedPath)) {
            $filePath = $uploadDir . $trimmedPath;
        }
    }
}

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    error_log("PDF Viewer - File not found. Stored path: " . $storedPath . ", Upload dir: " . $uploadDir);
    die('File not found: ' . htmlspecialchars(basename($storedPath)));
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

