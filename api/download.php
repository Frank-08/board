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
            die('File not found: ' . htmlspecialchars($fileName));
        }
    }
}

header('Content-Type: ' . $document['mime_type']);
header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;

