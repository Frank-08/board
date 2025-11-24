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

// Normalize paths - ensure UPLOAD_DIR has trailing slash and file_path doesn't have leading slash
$uploadDir = rtrim(realpath(UPLOAD_DIR) ?: UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$fileName = basename($document['file_path']); // Get just the filename in case path is stored
$filePath = $uploadDir . $fileName;

// Try alternative path if the above doesn't work
if (!file_exists($filePath)) {
    // Try with the file_path as stored (in case it's already a full path or relative)
    $altPath = UPLOAD_DIR . ltrim($document['file_path'], '/\\');
    if (file_exists($altPath)) {
        $filePath = $altPath;
    } else {
        // Try realpath resolution
        $resolvedPath = realpath(UPLOAD_DIR . $fileName);
        if ($resolvedPath && file_exists($resolvedPath)) {
            $filePath = $resolvedPath;
        } else {
            http_response_code(404);
            error_log("PDF Viewer - File not found. Tried: " . $filePath . ", " . $altPath);
            die('File not found: ' . htmlspecialchars($fileName));
        }
    }
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

