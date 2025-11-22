<?php
/**
 * Board Members API Endpoint
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
            $stmt = $db->prepare("SELECT * FROM board_members WHERE id = ?");
            $stmt->execute([$id]);
            $member = $stmt->fetch();
            
            if (!$member) {
                http_response_code(404);
                echo json_encode(['error' => 'Member not found']);
                exit;
            }
            
            echo json_encode($member);
        } elseif (isset($_GET['committee_id'])) {
            // Get members for a specific committee
            $committeeId = (int)$_GET['committee_id'];
            $status = $_GET['status'] ?? null;
            
            $sql = "
                SELECT DISTINCT bm.*, 
                    GROUP_CONCAT(CONCAT(c.name, ':', cm.role) SEPARATOR '|') as committees
                FROM board_members bm
                JOIN committee_members cm ON bm.id = cm.member_id
                JOIN committees c ON cm.committee_id = c.id
                WHERE cm.committee_id = ?
            ";
            $params = [$committeeId];
            
            if ($status) {
                $sql .= " AND cm.status = ?";
                $params[] = $status;
            }
            
            $sql .= " GROUP BY bm.id
                ORDER BY 
                    FIELD(cm.role, 'Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Ex-officio', 'Member'),
                    bm.last_name ASC, bm.first_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } else {
            // Get all members
            $stmt = $db->query("SELECT * FROM board_members ORDER BY last_name ASC, first_name ASC");
            echo json_encode($stmt->fetchAll());
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        
        if (empty($firstName) || empty($lastName)) {
            http_response_code(400);
            echo json_encode(['error' => 'first_name and last_name are required']);
            exit;
        }
        
        try {
            // Insert member (no longer has organization_id, role, status - those are in committee_members)
            $stmt = $db->prepare("INSERT INTO board_members (first_name, last_name, email, phone, title, bio) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $firstName,
                $lastName,
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['title'] ?? null,
                $data['bio'] ?? null
            ]);
            
            $memberId = $db->lastInsertId();
            
            // If committees are provided, add them
            if (!empty($data['committee_ids']) && is_array($data['committee_ids'])) {
                foreach ($data['committee_ids'] as $committeeData) {
                    $committeeId = is_array($committeeData) ? $committeeData['committee_id'] : $committeeData;
                    $role = is_array($committeeData) ? ($committeeData['role'] ?? 'Member') : 'Member';
                    $status = is_array($committeeData) ? ($committeeData['status'] ?? 'Active') : 'Active';
                    
                    $stmt = $db->prepare("INSERT INTO committee_members (committee_id, member_id, role, status, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $committeeId,
                        $memberId,
                        $role,
                        $status,
                        $data['start_date'] ?? null,
                        $data['end_date'] ?? null
                    ]);
                }
            }
            
            $stmt = $db->prepare("SELECT * FROM board_members WHERE id = ?");
            $stmt->execute([$memberId]);
            echo json_encode($stmt->fetch());
        } catch (PDOException $e) {
            error_log("Error creating member: " . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => 'Error creating member: ' . $e->getMessage()]);
        }
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        try {
            // Update member basic info (no longer has role, status - those are in committee_members)
            $stmt = $db->prepare("UPDATE board_members SET first_name = ?, last_name = ?, email = ?, phone = ?, title = ?, bio = ? WHERE id = ?");
            $stmt->execute([
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['email'] ?? null,
                $data['phone'] ?? null,
                $data['title'] ?? null,
                $data['bio'] ?? null,
                $id
            ]);
            
            $stmt = $db->prepare("SELECT * FROM board_members WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
        } catch (PDOException $e) {
            error_log("Error updating member: " . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => 'Error updating member: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM board_members WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

