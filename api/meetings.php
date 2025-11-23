<?php
/**
 * Meetings API Endpoint
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
            $stmt = $db->prepare("SELECT * FROM meetings WHERE id = ?");
            $stmt->execute([$id]);
            $meeting = $stmt->fetch();
            
            if (!$meeting) {
                http_response_code(404);
                echo json_encode(['error' => 'Meeting not found']);
                exit;
            }
            
            // Get attendees
            $stmt = $db->prepare("
                SELECT ma.*, bm.first_name, bm.last_name, bm.email 
                FROM meeting_attendees ma 
                JOIN board_members bm ON ma.member_id = bm.id 
                WHERE ma.meeting_id = ?
            ");
            $stmt->execute([$id]);
            $meeting['attendees'] = $stmt->fetchAll();
            
            // Get agenda items
            $stmt = $db->prepare("
                SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name
                FROM agenda_items ai
                LEFT JOIN board_members bm ON ai.presenter_id = bm.id
                WHERE ai.meeting_id = ?
                ORDER BY ai.position ASC
            ");
            $stmt->execute([$id]);
            $meeting['agenda_items'] = $stmt->fetchAll();
            
            echo json_encode($meeting);
        } elseif (isset($_GET['meeting_type_id'])) {
            $meetingTypeId = (int)$_GET['meeting_type_id'];
            $status = $_GET['status'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
            
            $sql = "SELECT * FROM meetings WHERE meeting_type_id = ?";
            $params = [$meetingTypeId];
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY scheduled_date DESC";
            
            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'id or meeting_type_id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $meetingTypeId = (int)($data['meeting_type_id'] ?? 0);
        $title = $data['title'] ?? '';
        $scheduledDate = $data['scheduled_date'] ?? '';
        
        if (!$meetingTypeId || empty($title) || empty($scheduledDate)) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_type_id, title, and scheduled_date are required']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO meetings (meeting_type_id, title, scheduled_date, location, virtual_link, quorum_required, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingTypeId,
            $title,
            $scheduledDate,
            $data['location'] ?? null,
            $data['virtual_link'] ?? null,
            $data['quorum_required'] ?? 0,
            $data['status'] ?? 'Scheduled',
            $data['notes'] ?? null
        ]);
        
        $meetingId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
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
        
        $fields = ['title', 'meeting_type_id', 'scheduled_date', 'location', 'virtual_link', 'quorum_required', 'quorum_met', 'status', 'notes'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                if ($field === 'quorum_met') {
                    $params[] = (int)$data[$field];
                } else {
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
        $sql = "UPDATE meetings SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("SELECT * FROM meetings WHERE id = ?");
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
        
        $stmt = $db->prepare("DELETE FROM meetings WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

