<?php
/**
 * Committees API Endpoint
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
            $stmt = $db->prepare("SELECT * FROM committees WHERE id = ?");
            $stmt->execute([$id]);
            $committee = $stmt->fetch();
            
            if (!$committee) {
                http_response_code(404);
                echo json_encode(['error' => 'Committee not found']);
                exit;
            }
            
            echo json_encode($committee);
        } else {
            $stmt = $db->query("SELECT * FROM committees ORDER BY name ASC");
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
        
        $stmt = $db->prepare("INSERT INTO committees (name, description, address, phone, email, website) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            $data['description'] ?? null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['website'] ?? null
        ]);
        
        $committeeId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM committees WHERE id = ?");
        $stmt->execute([$committeeId]);
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
        
        $stmt = $db->prepare("UPDATE committees SET name = ?, description = ?, address = ?, phone = ?, email = ?, website = ? WHERE id = ?");
        $stmt->execute([
            $data['name'] ?? '',
            $data['description'] ?? null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['website'] ?? null,
            $id
        ]);
        
        $stmt = $db->prepare("SELECT * FROM committees WHERE id = ?");
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
        
        $stmt = $db->prepare("DELETE FROM committees WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

