<?php
/**
 * Meeting Attendees API Endpoint
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
        if (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT ma.*, bm.first_name, bm.last_name, bm.email, bm.phone, bm.title,
                    mtm.role, mtm.status as membership_status
                FROM meeting_attendees ma
                JOIN board_members bm ON ma.member_id = bm.id
                JOIN meetings m ON ma.meeting_id = m.id
                LEFT JOIN meeting_type_members mtm ON bm.id = mtm.member_id AND m.meeting_type_id = mtm.meeting_type_id
                WHERE ma.meeting_id = ?
                ORDER BY 
                    FIELD(mtm.role, 'Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Ex-officio', 'Member'),
                    bm.last_name ASC
            ");
            $stmt->execute([$meetingId]);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $memberId = (int)($data['member_id'] ?? 0);
        
        if (!$meetingId || !$memberId) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id and member_id are required']);
            exit;
        }
        
        // Check if already exists
        $stmt = $db->prepare("SELECT id FROM meeting_attendees WHERE meeting_id = ? AND member_id = ?");
        $stmt->execute([$meetingId, $memberId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Attendee already exists for this meeting']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO meeting_attendees (meeting_id, member_id, attendance_status, arrival_time, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingId,
            $memberId,
            $data['attendance_status'] ?? 'Absent',
            $data['arrival_time'] ?? null,
            $data['notes'] ?? null
        ]);
        
        $attendeeId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT ma.*, bm.first_name, bm.last_name, bm.email, bm.phone, bm.title,
                cm.role, cm.status as membership_status
            FROM meeting_attendees ma
            JOIN board_members bm ON ma.member_id = bm.id
            JOIN meetings m ON ma.meeting_id = m.id
            LEFT JOIN committee_members cm ON bm.id = cm.member_id AND m.committee_id = cm.committee_id
            WHERE ma.id = ?
        ");
        $stmt->execute([$attendeeId]);
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
        
        // Handle member_id separately as it needs uniqueness check
        if (isset($data['member_id'])) {
            $newMemberId = (int)$data['member_id'];
            
            // Get current attendee to check meeting_id
            $stmt = $db->prepare("SELECT meeting_id, member_id FROM meeting_attendees WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            
            if ($current && $current['member_id'] != $newMemberId) {
                // Check if new member_id already exists for this meeting
                $stmt = $db->prepare("SELECT id FROM meeting_attendees WHERE meeting_id = ? AND member_id = ? AND id != ?");
                $stmt->execute([$current['meeting_id'], $newMemberId, $id]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['error' => 'This member is already an attendee for this meeting']);
                    exit;
                }
                $updates[] = "member_id = ?";
                $params[] = $newMemberId;
            }
        }
        
        $fields = ['attendance_status', 'arrival_time', 'notes'];
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
        $sql = "UPDATE meeting_attendees SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
            $stmt = $db->prepare("
                SELECT ma.*, bm.first_name, bm.last_name, bm.email, bm.phone, bm.title,
                    mtm.role, mtm.status as membership_status
                FROM meeting_attendees ma
                JOIN board_members bm ON ma.member_id = bm.id
                JOIN meetings m ON ma.meeting_id = m.id
                LEFT JOIN meeting_type_members mtm ON bm.id = mtm.member_id AND m.meeting_type_id = mtm.meeting_type_id
                WHERE ma.id = ?
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
        
        $stmt = $db->prepare("DELETE FROM meeting_attendees WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

