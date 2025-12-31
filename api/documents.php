<?php
/**
 * Documents API Endpoint
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Require authentication for all requests
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

// Check permissions for write operations
if ($method === 'POST') {
    requirePermission('upload_documents');
}
if ($method === 'DELETE') {
    requirePermission('delete_document');
}

// Ensure uploads directory exists and is writable
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0777, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create uploads directory']);
        exit;
    }
}
if (!is_writable(UPLOAD_DIR)) {
    http_response_code(500);
    echo json_encode(['error' => 'Uploads directory is not writable']);
    exit;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT d.*, 
                    bm.first_name as uploaded_first_name, bm.last_name as uploaded_last_name,
                    ai.title as agenda_item_title
                FROM documents d
                LEFT JOIN board_members bm ON d.uploaded_by = bm.id
                LEFT JOIN agenda_items ai ON d.agenda_item_id = ai.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            $document = $stmt->fetch();
            
            if (!$document) {
                http_response_code(404);
                echo json_encode(['error' => 'Document not found']);
                exit;
            }
            
            echo json_encode($document);
        } elseif (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT d.*, 
                    bm.first_name as uploaded_first_name, bm.last_name as uploaded_last_name,
                    ai.title as agenda_item_title, ai.position as agenda_item_position
                FROM documents d
                LEFT JOIN board_members bm ON d.uploaded_by = bm.id
                LEFT JOIN agenda_items ai ON d.agenda_item_id = ai.id
                WHERE d.meeting_id = ?
                ORDER BY ai.position ASC, d.created_at DESC
            ");
            $stmt->execute([$meetingId]);
            echo json_encode($stmt->fetchAll());
        } elseif (isset($_GET['agenda_item_id'])) {
            $agendaItemId = (int)$_GET['agenda_item_id'];
            $stmt = $db->prepare("
                SELECT d.*, 
                    bm.first_name as uploaded_first_name, bm.last_name as uploaded_last_name
                FROM documents d
                LEFT JOIN board_members bm ON d.uploaded_by = bm.id
                WHERE d.agenda_item_id = ?
                ORDER BY d.created_at DESC
            ");
            $stmt->execute([$agendaItemId]);
            echo json_encode($stmt->fetchAll());
        } else {
            // Get all documents
            $stmt = $db->prepare("
                SELECT d.*, 
                    bm.first_name as uploaded_first_name, bm.last_name as uploaded_last_name,
                    ai.title as agenda_item_title,
                    m.title as meeting_title, m.scheduled_date as meeting_date,
                    mt.name as meeting_type_name
                FROM documents d
                LEFT JOIN board_members bm ON d.uploaded_by = bm.id
                LEFT JOIN agenda_items ai ON d.agenda_item_id = ai.id
                LEFT JOIN meetings m ON d.meeting_id = m.id
                LEFT JOIN meeting_types mt ON d.meeting_type_id = mt.id
                ORDER BY d.created_at DESC
            ");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        }
        break;
        
    case 'POST':
        // Debug logging (remove in production)
        error_log('POST request received. $_FILES: ' . print_r($_FILES, true));
        error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
        
        // Handle file upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            $title = $_POST['title'] ?? $file['name'];
            $description = $_POST['description'] ?? null;
            $meetingId = !empty($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : null;
            $agendaItemId = !empty($_POST['agenda_item_id']) ? (int)$_POST['agenda_item_id'] : null;
            $meetingTypeId = !empty($_POST['meeting_type_id']) ? (int)$_POST['meeting_type_id'] : null;
            $documentType = $_POST['document_type'] ?? 'Other';
            
            // Get uploaded_by from current user's session
            $currentUser = getCurrentUser();
            $uploadedBy = $currentUser['board_member_id'] ?? null;
            if (!$uploadedBy) {
                error_log('Document upload: board_member_id not found in session for user_id: ' . ($currentUser['id'] ?? 'unknown'));
            }
            
            // Validate file
            $fileSize = $file['size'];
            if ($fileSize > MAX_FILE_SIZE) {
                http_response_code(400);
                echo json_encode(['error' => 'File size exceeds maximum allowed size']);
                exit;
            }
            
            $fileName = $file['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // If uploading to an agenda item, only PDFs are allowed
            if ($agendaItemId && $fileExtension !== 'pdf' && $file['type'] !== 'application/pdf') {
                http_response_code(400);
                echo json_encode(['error' => 'Only PDF files are allowed for agenda items']);
                exit;
            }
            
            if (!in_array($fileExtension, ALLOWED_FILE_TYPES)) {
                http_response_code(400);
                echo json_encode(['error' => 'File type not allowed']);
                exit;
            }
            
            // Generate unique filename
            $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = UPLOAD_DIR . $uniqueFileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save file']);
                exit;
            }
            
            // Get meeting's meeting type if not provided
            if (!$meetingTypeId && $meetingId) {
                $stmt = $db->prepare("SELECT meeting_type_id FROM meetings WHERE id = ?");
                $stmt->execute([$meetingId]);
                $meeting = $stmt->fetch();
                if ($meeting) {
                    $meetingTypeId = $meeting['meeting_type_id'];
                }
            }
            
            // Insert document record
            try {
                $stmt = $db->prepare("
                    INSERT INTO documents (meeting_type_id, meeting_id, agenda_item_id, document_type, title, description, file_path, file_name, file_size, mime_type, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $meetingTypeId,
                    $meetingId,
                    $agendaItemId,
                    $documentType,
                    $title,
                    $description,
                    $uniqueFileName,
                    $fileName,
                    $fileSize,
                    $file['type'],
                    $uploadedBy
                ]);
                
                $documentId = $db->lastInsertId();
                $stmt = $db->prepare("
                    SELECT d.*, 
                        bm.first_name as uploaded_first_name, bm.last_name as uploaded_last_name,
                        ai.title as agenda_item_title
                    FROM documents d
                    LEFT JOIN board_members bm ON d.uploaded_by = bm.id
                    LEFT JOIN agenda_items ai ON d.agenda_item_id = ai.id
                    WHERE d.id = ?
                ");
                $stmt->execute([$documentId]);
                echo json_encode($stmt->fetch());
            } catch (Exception $e) {
                // Delete uploaded file if database insert fails
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                http_response_code(500);
                echo json_encode(['error' => 'Error saving document: ' . $e->getMessage()]);
                exit;
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            $errorCode = $_FILES['file']['error'];
            http_response_code(400);
            echo json_encode(['error' => $errorMessages[$errorCode] ?? 'Unknown upload error']);
            exit;
        } elseif (!isset($_FILES['file'])) {
            // No file was sent - check if this is a JSON metadata update request
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            // Log for debugging
            error_log('No file in $_FILES. Content-Type: ' . $contentType);
            error_log('$_FILES contents: ' . print_r($_FILES, true));
            
            if (strpos($contentType, 'application/json') !== false) {
                // JSON request for updating document metadata
                $data = json_decode(file_get_contents('php://input'), true);
                $id = (int)($data['id'] ?? 0);
                
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID is required']);
                    exit;
                }
                
                $updates = [];
                $params = [];
                
                $fields = ['title', 'description', 'document_type', 'agenda_item_id'];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                if (empty($updates)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No fields to update']);
                    exit;
                }
                
                $params[] = $id;
                $sql = "UPDATE documents SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $stmt = $db->prepare("
                    SELECT d.*, 
                        bm.first_name as uploaded_first_name, bm.last_name as uploaded_last_name,
                        ai.title as agenda_item_title
                    FROM documents d
                    LEFT JOIN board_members bm ON d.uploaded_by = bm.id
                    LEFT JOIN agenda_items ai ON d.agenda_item_id = ai.id
                    WHERE d.id = ?
                ");
                $stmt->execute([$id]);
                echo json_encode($stmt->fetch());
            } else {
                // Expected file upload but no file was provided
                // This could be due to:
                // 1. File not selected in the form
                // 2. PHP upload_max_filesize or post_max_size too small
                // 3. Form not submitted correctly
                $contentType = $_SERVER['CONTENT_TYPE'] ?? 'not set';
                $isMultipart = strpos($contentType, 'multipart/form-data') !== false;
                $errorMsg = 'No file was uploaded. Please select a file to upload.';
                if ($isMultipart) {
                    $errorMsg .= ' (Request received as multipart/form-data but file was not parsed. Check PHP upload settings.)';
                } else {
                    $errorMsg .= ' (Request Content-Type: ' . $contentType . ')';
                }
                http_response_code(400);
                echo json_encode(['error' => $errorMsg]);
                exit;
            }
        }
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        $updates = [];
        $params = [];
        
        $fields = ['title', 'description', 'document_type', 'agenda_item_id'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $id;
        $sql = "UPDATE documents SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT d.*, 
                bm.first_name as uploaded_first_name, bm.last_name as uploaded_last_name,
                ai.title as agenda_item_title
            FROM documents d
            LEFT JOIN board_members bm ON d.uploaded_by = bm.id
            LEFT JOIN agenda_items ai ON d.agenda_item_id = ai.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        // Get file path before deleting
        $stmt = $db->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $document = $stmt->fetch();
        
        if ($document && $document['file_path']) {
            $filePath = UPLOAD_DIR . $document['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

