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

function isValidSharePointUrl($url) {
    if (!is_string($url) || trim($url) === '') {
        return false;
    }

    $parsedUrl = parse_url(trim($url));
    if (!$parsedUrl || ($parsedUrl['scheme'] ?? '') !== 'https') {
        return false;
    }

    $host = strtolower($parsedUrl['host'] ?? '');
    return $host !== '' && (strpos($host, 'sharepoint.com') !== false || strpos($host, 'sharepoint-df.com') !== false);
}

function extractFileNameFromUrl($url) {
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) {
        return null;
    }

    $baseName = basename($path);
    if ($baseName === '' || $baseName === '/' || $baseName === '.') {
        return null;
    }

    return urldecode($baseName);
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
        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Request body must be valid JSON']);
            exit;
        }

        $title = trim((string)($data['title'] ?? ''));
        $description = isset($data['description']) ? trim((string)$data['description']) : null;
        $meetingId = !empty($data['meeting_id']) ? (int)$data['meeting_id'] : null;
        $agendaItemId = !empty($data['agenda_item_id']) ? (int)$data['agenda_item_id'] : null;
        $meetingTypeId = !empty($data['meeting_type_id']) ? (int)$data['meeting_type_id'] : null;
        $documentType = $data['document_type'] ?? 'Other';
        $sharePointUrl = trim((string)($data['sharepoint_url'] ?? ''));

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Title is required']);
            exit;
        }

        if (!isValidSharePointUrl($sharePointUrl)) {
            http_response_code(400);
            echo json_encode(['error' => 'A valid HTTPS SharePoint URL is required']);
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

        // Get uploaded_by from current user's session
        $currentUser = getCurrentUser();
        $uploadedBy = $currentUser['board_member_id'] ?? null;
        if (!$uploadedBy) {
            error_log('Document create: board_member_id not found in session for user_id: ' . ($currentUser['id'] ?? 'unknown'));
        }

        $derivedFileName = extractFileNameFromUrl($sharePointUrl);

        try {
            $stmt = $db->prepare("
                INSERT INTO documents (meeting_type_id, meeting_id, agenda_item_id, document_type, title, description, sharepoint_url, file_path, file_name, file_size, mime_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, NULL, NULL, ?)
            ");
            $stmt->execute([
                $meetingTypeId,
                $meetingId,
                $agendaItemId,
                $documentType,
                $title,
                $description,
                $sharePointUrl,
                $derivedFileName,
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
            http_response_code(500);
            echo json_encode(['error' => 'Error saving document: ' . $e->getMessage()]);
            exit;
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

        if (isset($data['sharepoint_url'])) {
            $sharePointUrl = trim((string)$data['sharepoint_url']);
            if (!isValidSharePointUrl($sharePointUrl)) {
                http_response_code(400);
                echo json_encode(['error' => 'A valid HTTPS SharePoint URL is required']);
                exit;
            }

            $updates[] = 'sharepoint_url = ?';
            $params[] = $sharePointUrl;

            $derivedFileName = extractFileNameFromUrl($sharePointUrl);
            $updates[] = 'file_name = ?';
            $params[] = $derivedFileName;
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
        
        $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

