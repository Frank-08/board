<?php
/**
 * Committee Members API Endpoint (Legacy - use meeting_type_members.php instead)
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

// Require authentication
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

// Check permissions for write operations
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    requirePermission('manage_members');
}

switch ($method) {
    case 'GET':
        if (isset($_GET['member_id'])) {
            // Get all committees for a member
            $memberId = (int)$_GET['member_id'];
            $stmt = $db->prepare("
                SELECT cm.*, c.name as committee_name, c.description as committee_description
                FROM committee_members cm
                JOIN committees c ON cm.committee_id = c.id
                WHERE cm.member_id = ?
                ORDER BY c.name ASC
            ");
            $stmt->execute([$memberId]);
            echo json_encode($stmt->fetchAll());
        } elseif (isset($_GET['committee_id'])) {
            // Get all members for a committee
            $committeeId = (int)$_GET['committee_id'];
            $status = $_GET['status'] ?? null;
            
            $sql = "
                SELECT cm.*, 
                    bm.first_name, bm.last_name, bm.email, bm.phone, bm.title
                FROM committee_members cm
                JOIN board_members bm ON cm.member_id = bm.id
                WHERE cm.committee_id = ?
            ";
            $params = [$committeeId];
            
            if ($status) {
                $sql .= " AND cm.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY 
                FIELD(cm.role, 'Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Ex-officio', 'Member'),
                bm.last_name ASC, bm.first_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } elseif (isset($_GET['id'])) {
            // Get single committee membership
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT cm.*, 
                    c.name as committee_name,
                    bm.first_name, bm.last_name, bm.email, bm.phone, bm.title
                FROM committee_members cm
                JOIN committees c ON cm.committee_id = c.id
                JOIN board_members bm ON cm.member_id = bm.id
                WHERE cm.id = ?
            ");
            $stmt->execute([$id]);
            $membership = $stmt->fetch();
            
            if (!$membership) {
                http_response_code(404);
                echo json_encode(['error' => 'Committee membership not found']);
                exit;
            }
            
            echo json_encode($membership);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'member_id, committee_id, or id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $committeeId = (int)($data['committee_id'] ?? 0);
        $memberId = (int)($data['member_id'] ?? 0);
        
        if (!$committeeId || !$memberId) {
            http_response_code(400);
            echo json_encode(['error' => 'committee_id and member_id are required']);
            exit;
        }
        
        // Check if already exists
        $stmt = $db->prepare("SELECT id FROM committee_members WHERE committee_id = ? AND member_id = ?");
        $stmt->execute([$committeeId, $memberId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Member is already in this committee']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO committee_members (committee_id, member_id, role, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $committeeId,
            $memberId,
            $data['role'] ?? 'Member',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['status'] ?? 'Active'
        ]);
        
        $membershipId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT cm.*, 
                c.name as committee_name,
                bm.first_name, bm.last_name, bm.email, bm.phone, bm.title
            FROM committee_members cm
            JOIN committees c ON cm.committee_id = c.id
            JOIN board_members bm ON cm.member_id = bm.id
            WHERE cm.id = ?
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
        $sql = "UPDATE committee_members SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT cm.*, 
                c.name as committee_name,
                bm.first_name, bm.last_name, bm.email, bm.phone, bm.title
            FROM committee_members cm
            JOIN committees c ON cm.committee_id = c.id
            JOIN board_members bm ON cm.member_id = bm.id
            WHERE cm.id = ?
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
        
        $stmt = $db->prepare("DELETE FROM committee_members WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

