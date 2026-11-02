<?php
/**
 * Meeting Types API Endpoint
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require authentication for all requests
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

// Check permissions for write operations (Admin only)
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    requirePermission('manage_meeting_types');
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("SELECT * FROM meeting_types WHERE id = ?");
            $stmt->execute([$id]);
            $meetingType = $stmt->fetch();
            
            if (!$meetingType) {
                http_response_code(404);
                echo json_encode(['error' => 'Meeting type not found']);
                exit;
            }
            
            echo json_encode($meetingType);
        } else {
            $stmt = $db->query("SELECT * FROM meeting_types ORDER BY display_order ASC, name ASC");
            echo json_encode($stmt->fetchAll());
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';
        
        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Name is required']);
            exit;
        }
        
        $shortcode = isset($data['shortcode']) && trim($data['shortcode']) !== ''
            ? substr(trim($data['shortcode']), 0, 3)
            : null;
        $displayOrder = isset($data['display_order']) ? (int)$data['display_order'] : 0;

        $stmt = $db->prepare("INSERT INTO meeting_types (name, shortcode, description, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $shortcode,
            $data['description'] ?? null,
            $displayOrder
        ]);
        
        $meetingTypeId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM meeting_types WHERE id = ?");
        $stmt->execute([$meetingTypeId]);
        echo json_encode($stmt->fetch());
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        $fields = ['name = ?', 'description = ?'];
        $params = [$data['name'] ?? '', $data['description'] ?? null];

        // Optional fields: only touched when the client sends them, so
        // existing callers that send just name/description don't reset them.
        if (array_key_exists('shortcode', $data)) {
            $fields[] = 'shortcode = ?';
            $params[] = trim((string)$data['shortcode']) !== ''
                ? substr(trim((string)$data['shortcode']), 0, 3)
                : null;
        }
        if (array_key_exists('display_order', $data)) {
            $fields[] = 'display_order = ?';
            $params[] = (int)$data['display_order'];
        }
        $params[] = $id;

        $stmt = $db->prepare("UPDATE meeting_types SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);
        
        $stmt = $db->prepare("SELECT * FROM meeting_types WHERE id = ?");
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
        
        $stmt = $db->prepare("DELETE FROM meeting_types WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

