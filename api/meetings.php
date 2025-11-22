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
                SELECT ma.*, bm.first_name, bm.last_name, bm.role, bm.email 
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
        } elseif (isset($_GET['organization_id'])) {
            $orgId = (int)$_GET['organization_id'];
            $status = $_GET['status'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
            
            $sql = "SELECT * FROM meetings WHERE organization_id = ?";
            $params = [$orgId];
            
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
            echo json_encode(['error' => 'id or organization_id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $orgId = (int)($data['organization_id'] ?? 0);
        $title = $data['title'] ?? '';
        $scheduledDate = $data['scheduled_date'] ?? '';
        
        if (!$orgId || empty($title) || empty($scheduledDate)) {
            http_response_code(400);
            echo json_encode(['error' => 'organization_id, title, and scheduled_date are required']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO meetings (organization_id, title, meeting_type, scheduled_date, location, virtual_link, quorum_required, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $orgId,
            $title,
            $data['meeting_type'] ?? 'Regular',
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
        
        $stmt = $db->prepare("UPDATE meetings SET title = ?, meeting_type = ?, scheduled_date = ?, location = ?, virtual_link = ?, quorum_required = ?, quorum_met = ?, status = ?, notes = ? WHERE id = ?");
        $stmt->execute([
            $data['title'] ?? '',
            $data['meeting_type'] ?? 'Regular',
            $data['scheduled_date'] ?? '',
            $data['location'] ?? null,
            $data['virtual_link'] ?? null,
            $data['quorum_required'] ?? 0,
            isset($data['quorum_met']) ? (int)$data['quorum_met'] : 0,
            $data['status'] ?? 'Scheduled',
            $data['notes'] ?? null,
            $id
        ]);
        
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

