<?php
/**
 * Organizations API Endpoint
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
            $stmt = $db->prepare("SELECT * FROM organizations WHERE id = ?");
            $stmt->execute([$id]);
            $org = $stmt->fetch();
            
            if (!$org) {
                http_response_code(404);
                echo json_encode(['error' => 'Organization not found']);
                exit;
            }
            
            echo json_encode($org);
        } else {
            $stmt = $db->query("SELECT * FROM organizations ORDER BY name ASC");
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
        
        $stmt = $db->prepare("INSERT INTO organizations (name, description, address, phone, email, website) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $data['description'] ?? null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['website'] ?? null
        ]);
        
        $orgId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM organizations WHERE id = ?");
        $stmt->execute([$orgId]);
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
        
        $stmt = $db->prepare("UPDATE organizations SET name = ?, description = ?, address = ?, phone = ?, email = ?, website = ? WHERE id = ?");
        $stmt->execute([
            $data['name'] ?? '',
            $data['description'] ?? null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['website'] ?? null,
            $id
        ]);
        
        $stmt = $db->prepare("SELECT * FROM organizations WHERE id = ?");
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
        
        $stmt = $db->prepare("DELETE FROM organizations WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

