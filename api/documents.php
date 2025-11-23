<?php
/**
 * Documents API - upload/list/delete documents
 * Supports:
 *  - GET ?agenda_item_id= or ?meeting_id=     -> list documents
 *  - POST (multipart/form-data) files[] + meeting_id + agenda_item_id(optional) -> upload one or more files
 *  - DELETE JSON { id: <document_id> }        -> delete document (and unlink file)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($method === 'GET') {
    if (isset($_GET['agenda_item_id'])) {
        $aid = (int)$_GET['agenda_item_id'];
        $stmt = $db->prepare("SELECT * FROM documents WHERE agenda_item_id = ? ORDER BY created_at ASC");
        $stmt->execute([$aid]);
        $rows = $stmt->fetchAll();
        respond($rows);
    } elseif (isset($_GET['meeting_id'])) {
        $mid = (int)$_GET['meeting_id'];
        $stmt = $db->prepare("SELECT * FROM documents WHERE meeting_id = ? ORDER BY created_at ASC");
        $stmt->execute([$mid]);
        $rows = $stmt->fetchAll();
        respond($rows);
    } else {
        respond(['error' => 'agenda_item_id or meeting_id is required'], 400);
    }
}

if ($method === 'POST') {
    // Expect multipart/form-data with files[] and meeting_id, optional agenda_item_id
    $meeting_id = isset($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0;
    $agenda_item_id = isset($_POST['agenda_item_id']) ? (int)$_POST['agenda_item_id'] : null;
    $uploaded_by = isset($_POST['uploaded_by']) ? (int)$_POST['uploaded_by'] : null;

    if (!$meeting_id) {
        respond(['error' => 'meeting_id is required'], 400);
    }

    if (empty($_FILES) || !isset($_FILES['files'])) {
        respond(['error' => 'No files uploaded. Use input name="files[]"'], 400);
    }

    $files = $_FILES['files'];
    $saved = [];

    // Ensure upload dir exists
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        $error = $files['error'][$i];
        if ($error !== UPLOAD_ERR_OK) {
            // skip this file
            continue;
        }
        $originalName = basename($files['name'][$i]);
        $size = (int)$files['size'][$i];
        $tmp = $files['tmp_name'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($size > MAX_FILE_SIZE) {
            // skip oversized
            continue;
        }

        if (!in_array($ext, ALLOWED_FILE_TYPES)) {
            // skip disallowed type
            continue;
        }

        // generate unique file name
        $safeName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($tmp, $destPath)) {
            continue;
        }

        // store relative path for web access (assumes uploads/ is web-accessible at /uploads/)
        $relativePath = 'uploads/' . $safeName;

        $stmt = $db->prepare("INSERT INTO documents (committee_id, meeting_id, meeting_id, agenda_item_id, document_type, title, description, file_path, file_name, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        // Some older schemas may not have committee_id; set null for committee_id and document_type default to 'Agenda'
        // Use prepared INSERT with referenced columns (explicit columns to be safe)
        $stmt = $db->prepare("INSERT INTO documents (committee_id, meeting_id, agenda_item_id, document_type, title, description, file_path, file_name, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $mime = mime_content_type($destPath) ?: ($ext === 'pdf' ? 'application/pdf' : 'application/octet-stream');
        $title = pathinfo($originalName, PATHINFO_FILENAME);

        $stmt->execute([
            null, // committee_id (optional)
            $meeting_id,
            $agenda_item_id,
            'Agenda',
            $title,
            null,
            $relativePath,
            $originalName,
            $size,
            $mime,
            $uploaded_by
        ]);

        $id = $db->lastInsertId();
        $saved[] = [
            'id' => (int)$id,
            'meeting_id' => $meeting_id,
            'agenda_item_id' => $agenda_item_id,
            'file_path' => $relativePath,
            'file_name' => $originalName,
            'file_size' => $size,
            'mime_type' => $mime
        ];
    }

    respond(['uploaded' => $saved]);
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if (!$id) respond(['error' => 'ID is required'], 400);

    $stmt = $db->prepare("SELECT file_path FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) respond(['error' => 'Document not found'], 404);

    $filePath = __DIR__ . '/../' . $row['file_path']; // relative 'uploads/...' from project root
    if (file_exists($filePath)) {
        @unlink($filePath);
    }

    $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->execute([$id]);

    respond(['success' => true]);
}

respond(['error' => 'Method not allowed'], 405);
