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
        } elseif (isset($_GET['organization_id'])) {
            $orgId = (int)$_GET['organization_id'];
            $status = $_GET['status'] ?? null;
            
            $sql = "SELECT * FROM board_members WHERE organization_id = ?";
            $params = [$orgId];
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY 
                FIELD(role, 'Chair', 'Vice Chair', 'Secretary', 'Treasurer', 'Executive Director', 'Member'),
                last_name ASC, first_name ASC";
            
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
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        
        if (!$orgId || empty($firstName) || empty($lastName)) {
            http_response_code(400);
            echo json_encode(['error' => 'organization_id, first_name, and last_name are required']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO board_members (organization_id, first_name, last_name, email, phone, title, role, start_date, end_date, status, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $orgId,
            $firstName,
            $lastName,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['title'] ?? null,
            $data['role'] ?? 'Member',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['status'] ?? 'Active',
            $data['bio'] ?? null
        ]);
        
        $memberId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM board_members WHERE id = ?");
        $stmt->execute([$memberId]);
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
        
        $stmt = $db->prepare("UPDATE board_members SET first_name = ?, last_name = ?, email = ?, phone = ?, title = ?, role = ?, start_date = ?, end_date = ?, status = ?, bio = ? WHERE id = ?");
        $stmt->execute([
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['title'] ?? null,
            $data['role'] ?? 'Member',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['status'] ?? 'Active',
            $data['bio'] ?? null,
            $id
        ]);
        
        $stmt = $db->prepare("SELECT * FROM board_members WHERE id = ?");
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
        
        $stmt = $db->prepare("DELETE FROM board_members WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

