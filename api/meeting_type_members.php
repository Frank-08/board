<?php
/**
 * Meeting Type Members API Endpoint - Manages many-to-many relationship
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

// Require authentication for all requests
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

// Check permissions for write operations (Admin only - member management)
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    requirePermission('manage_members');
}

switch ($method) {
    case 'GET':
        if (isset($_GET['member_id'])) {
            // Get all meeting types for a member
            $memberId = (int)$_GET['member_id'];
            $stmt = $db->prepare("
                SELECT mtm.*, mt.name as meeting_type_name, mt.description as meeting_type_description
                FROM meeting_type_members mtm
                JOIN meeting_types mt ON mtm.meeting_type_id = mt.id
                WHERE mtm.member_id = ?
                ORDER BY mt.name ASC
            ");
            $stmt->execute([$memberId]);
            echo json_encode($stmt->fetchAll());
        } elseif (isset($_GET['meeting_type_id'])) {
            // Get all members for a meeting type
            $meetingTypeId = (int)$_GET['meeting_type_id'];
            $status = $_GET['status'] ?? null;
            
            $sql = "
                SELECT mtm.*, 
                    bm.first_name, bm.last_name, bm.email, bm.phone, bm.title
                FROM meeting_type_members mtm
                JOIN board_members bm ON mtm.member_id = bm.id
                WHERE mtm.meeting_type_id = ?
            ";
            $params = [$meetingTypeId];
            
            if ($status) {
                $sql .= " AND mtm.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY 
                FIELD(mtm.role, 'Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Ex-officio', 'Member'),
                bm.last_name ASC, bm.first_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } elseif (isset($_GET['id'])) {
            // Get single meeting type membership
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT mtm.*, 
                    mt.name as meeting_type_name,
                    bm.first_name, bm.last_name, bm.email, bm.phone, bm.title
                FROM meeting_type_members mtm
                JOIN meeting_types mt ON mtm.meeting_type_id = mt.id
                JOIN board_members bm ON mtm.member_id = bm.id
                WHERE mtm.id = ?
            ");
            $stmt->execute([$id]);
            $membership = $stmt->fetch();
            
            if (!$membership) {
                http_response_code(404);
                echo json_encode(['error' => 'Meeting type membership not found']);
                exit;
            }
            
            echo json_encode($membership);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'member_id, meeting_type_id, or id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $meetingTypeId = (int)($data['meeting_type_id'] ?? 0);
        $memberId = (int)($data['member_id'] ?? 0);
        
        if (!$meetingTypeId || !$memberId) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_type_id and member_id are required']);
            exit;
        }
        
        // Check if already exists
        $stmt = $db->prepare("SELECT id FROM meeting_type_members WHERE meeting_type_id = ? AND member_id = ?");
        $stmt->execute([$meetingTypeId, $memberId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Member is already in this meeting type']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO meeting_type_members (meeting_type_id, member_id, role, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingTypeId,
            $memberId,
            $data['role'] ?? 'Member',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['status'] ?? 'Active'
        ]);
        
        $membershipId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT mtm.*, 
                mt.name as meeting_type_name,
                bm.first_name, bm.last_name, bm.email, bm.phone, bm.title
            FROM meeting_type_members mtm
            JOIN meeting_types mt ON mtm.meeting_type_id = mt.id
            JOIN board_members bm ON mtm.member_id = bm.id
            WHERE mtm.id = ?
        ");
        $stmt->execute([$membershipId]);
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
        
        $fields = ['role', 'start_date', 'end_date', 'status'];
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
        $sql = "UPDATE meeting_type_members SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT mtm.*, 
                mt.name as meeting_type_name,
                bm.first_name, bm.last_name, bm.email, bm.phone, bm.title
            FROM meeting_type_members mtm
            JOIN meeting_types mt ON mtm.meeting_type_id = mt.id
            JOIN board_members bm ON mtm.member_id = bm.id
            WHERE mtm.id = ?
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
        
        $stmt = $db->prepare("DELETE FROM meeting_type_members WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

