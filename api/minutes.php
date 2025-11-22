<?php
/**
 * Meeting Minutes API Endpoint
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
                SELECT m.*, 
                    pb.first_name as prepared_first_name, pb.last_name as prepared_last_name,
                    ab.first_name as approved_first_name, ab.last_name as approved_last_name
                FROM minutes m
                LEFT JOIN board_members pb ON m.prepared_by = pb.id
                LEFT JOIN board_members ab ON m.approved_by = ab.id
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            $minutes = $stmt->fetch();
            
            if (!$minutes) {
                http_response_code(404);
                echo json_encode(['error' => 'Minutes not found']);
                exit;
            }
            
            echo json_encode($minutes);
        } elseif (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT m.*, 
                    pb.first_name as prepared_first_name, pb.last_name as prepared_last_name,
                    ab.first_name as approved_first_name, ab.last_name as approved_last_name
                FROM minutes m
                LEFT JOIN board_members pb ON m.prepared_by = pb.id
                LEFT JOIN board_members ab ON m.approved_by = ab.id
                WHERE m.meeting_id = ?
            ");
            $stmt->execute([$meetingId]);
            $minutes = $stmt->fetch();
            echo json_encode($minutes ?: null);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'id or meeting_id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $content = $data['content'] ?? '';
        
        if (!$meetingId || empty($content)) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id and content are required']);
            exit;
        }
        
        // Check if minutes already exist
        $stmt = $db->prepare("SELECT id FROM minutes WHERE meeting_id = ?");
        $stmt->execute([$meetingId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Minutes already exist for this meeting']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO minutes (meeting_id, prepared_by, content, action_items, next_meeting_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingId,
            !empty($data['prepared_by']) ? (int)$data['prepared_by'] : null,
            $content,
            $data['action_items'] ?? null,
            $data['next_meeting_date'] ?? null,
            $data['status'] ?? 'Draft'
        ]);
        
        $minutesId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT m.*, 
                pb.first_name as prepared_first_name, pb.last_name as prepared_last_name,
                ab.first_name as approved_first_name, ab.last_name as approved_last_name
            FROM minutes m
            LEFT JOIN board_members pb ON m.prepared_by = pb.id
            LEFT JOIN board_members ab ON m.approved_by = ab.id
            WHERE m.id = ?
        ");
        $stmt->execute([$minutesId]);
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
        
        $fields = ['content', 'action_items', 'next_meeting_date', 'status', 'prepared_by'];
        
        // Special handling for approval
        if (isset($data['approve']) && $data['approve']) {
            $updates[] = "approved_by = ?";
            $params[] = $data['approved_by'] ?? null;
            $updates[] = "approved_at = NOW()";
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            } else {
                $updates[] = "status = 'Approved'";
            }
        } else {
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $id;
        $sql = "UPDATE minutes SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT m.*, 
                pb.first_name as prepared_first_name, pb.last_name as prepared_last_name,
                ab.first_name as approved_first_name, ab.last_name as approved_last_name
            FROM minutes m
            LEFT JOIN board_members pb ON m.prepared_by = pb.id
            LEFT JOIN board_members ab ON m.approved_by = ab.id
            WHERE m.id = ?
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
        
        $stmt = $db->prepare("DELETE FROM minutes WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

