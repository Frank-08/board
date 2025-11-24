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
// Query all relevant metadata for file lookup and verification
$stmt = $db->prepare("SELECT id, file_path, file_name, file_size, mime_type, created_at FROM documents WHERE id = ?");
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    die('Document not found');
}

// Get metadata
$storedPath = $document['file_path'];
$storedFileName = $document['file_name'];
$storedFileSize = $document['file_size'];
$docId = $document['id'];
$uploadDir = rtrim(realpath(UPLOAD_DIR) ?: UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Try multiple strategies to find the file
$filePath = null;
$searchAttempts = [];

// Strategy 1: Use file_path as stored (absolute path)
if ($storedPath && file_exists($storedPath)) {
    $filePath = $storedPath;
    $searchAttempts[] = "Absolute path: $storedPath";
}
// Strategy 2: file_path relative to UPLOAD_DIR
elseif ($storedPath && file_exists($uploadDir . $storedPath)) {
    $filePath = $uploadDir . $storedPath;
    $searchAttempts[] = "Relative path: " . $uploadDir . $storedPath;
}
// Strategy 3: Use basename of file_path
elseif ($storedPath) {
    $fileNameFromPath = basename($storedPath);
    if (file_exists($uploadDir . $fileNameFromPath)) {
        $filePath = $uploadDir . $fileNameFromPath;
        $searchAttempts[] = "Basename from path: " . $uploadDir . $fileNameFromPath;
    }
}
// Strategy 4: Use file_name (original filename) - fallback
if (!$filePath && $storedFileName) {
    $fileNameOnly = basename($storedFileName);
    $potentialPath = $uploadDir . $fileNameOnly;
    if (file_exists($potentialPath)) {
        $filePath = $potentialPath;
        $searchAttempts[] = "Original filename: " . $potentialPath;
    }
}
// Strategy 5: Search by file_name with trimmed slashes
if (!$filePath && $storedFileName) {
    $trimmedFileName = ltrim(basename($storedFileName), '/\\');
    $potentialPath = $uploadDir . $trimmedFileName;
    if (file_exists($potentialPath)) {
        $filePath = $potentialPath;
        $searchAttempts[] = "Trimmed filename: " . $potentialPath;
    }
}
// Strategy 6: If we have file_size, search uploads directory for matching size
if (!$filePath && $storedFileSize && is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $fullPath = $uploadDir . $file;
        if (is_file($fullPath) && filesize($fullPath) == $storedFileSize) {
            // Verify it's a PDF
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $filePath = $fullPath;
                $searchAttempts[] = "Size match: $fullPath (size: $storedFileSize)";
                break;
            }
        }
    }
}

// Verify file was found
if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    $errorDetails = [
        "Document ID: $docId",
        "Stored file_path: " . ($storedPath ?: 'NULL'),
        "Stored file_name: " . ($storedFileName ?: 'NULL'),
        "Stored file_size: " . ($storedFileSize ?: 'NULL'),
        "Upload directory: $uploadDir",
        "Upload dir exists: " . (is_dir($uploadDir) ? 'yes' : 'no'),
        "Search attempts: " . implode(', ', $searchAttempts ?: ['none'])
    ];
    if (is_dir($uploadDir)) {
        $uploadFiles = array_slice(scandir($uploadDir), 2);
        $errorDetails[] = "Files in upload dir: " . implode(', ', array_slice($uploadFiles, 0, 10));
    }
    error_log("PDF Viewer - File not found. " . implode(' | ', $errorDetails));
    die('File not found: ' . htmlspecialchars($storedFileName ?: basename($storedPath ?: 'unknown')));
}

// Verify file size matches database record (if available)
if ($storedFileSize && filesize($filePath) != $storedFileSize) {
    error_log("PDF Viewer - File size mismatch. Expected: $storedFileSize, Actual: " . filesize($filePath) . ", File: $filePath");
    // Continue anyway, but log the mismatch
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

