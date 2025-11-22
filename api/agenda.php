<?php
/**
 * Agenda Items API Endpoint
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name,
                    r.id as resolution_id, r.title as resolution_title, r.resolution_number, r.status as resolution_status
                FROM agenda_items ai
                LEFT JOIN board_members bm ON ai.presenter_id = bm.id
                LEFT JOIN resolutions r ON ai.id = r.agenda_item_id
                WHERE ai.id = ?
            ");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                http_response_code(404);
                echo json_encode(['error' => 'Agenda item not found']);
                exit;
            }
            
            echo json_encode($item);
        } elseif (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name,
                    r.id as resolution_id, r.title as resolution_title, r.resolution_number, r.status as resolution_status
                FROM agenda_items ai
                LEFT JOIN board_members bm ON ai.presenter_id = bm.id
                LEFT JOIN resolutions r ON ai.id = r.agenda_item_id
                WHERE ai.meeting_id = ?
                ORDER BY ai.position ASC
            ");
            $stmt->execute([$meetingId]);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'id or meeting_id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $title = $data['title'] ?? '';
        
        if (!$meetingId || empty($title)) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id and title are required']);
            exit;
        }
        
        // Get max position
        $stmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 as new_position FROM agenda_items WHERE meeting_id = ?");
        $stmt->execute([$meetingId]);
        $result = $stmt->fetch();
        $position = $result['new_position'];
        
        $stmt = $db->prepare("INSERT INTO agenda_items (meeting_id, title, description, item_type, presenter_id, duration_minutes, position, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingId,
            $title,
            $data['description'] ?? null,
            $data['item_type'] ?? 'Discussion',
            !empty($data['presenter_id']) ? (int)$data['presenter_id'] : null,
            !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
            $position,
            $data['status'] ?? 'Pending'
        ]);
        
        $itemId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name
            FROM agenda_items ai
            LEFT JOIN board_members bm ON ai.presenter_id = bm.id
            WHERE ai.id = ?
        ");
        $stmt->execute([$itemId]);
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
        
        $updates = [];
        $params = [];
        
        $fields = ['title', 'description', 'item_type', 'presenter_id', 'duration_minutes', 'position', 'status', 'outcome'];
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
        $sql = "UPDATE agenda_items SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name
            FROM agenda_items ai
            LEFT JOIN board_members bm ON ai.presenter_id = bm.id
            WHERE ai.id = ?
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
        
        $stmt = $db->prepare("DELETE FROM agenda_items WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

