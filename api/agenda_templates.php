<?php
/**
 * Agenda Templates API Endpoint
 * Manages default agenda item templates for meeting types
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

// Check permissions for write operations
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    requirePermission('manage_agenda');
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Get single template item
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("SELECT * FROM agenda_templates WHERE id = ?");
            $stmt->execute([$id]);
            $template = $stmt->fetch();
            
            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template item not found']);
                exit;
            }
            
            echo json_encode($template);
        } elseif (isset($_GET['meeting_type_id'])) {
            // Get all templates for a meeting type
            $meetingTypeId = (int)$_GET['meeting_type_id'];
            $stmt = $db->prepare("
                SELECT * FROM agenda_templates 
                WHERE meeting_type_id = ? 
                ORDER BY position ASC
            ");
            $stmt->execute([$meetingTypeId]);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'id or meeting_type_id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Handle reorder action
        if (isset($data['action']) && $data['action'] === 'reorder') {
            $meetingTypeId = (int)($data['meeting_type_id'] ?? 0);
            $order = $data['order'] ?? [];
            
            if (!$meetingTypeId || empty($order)) {
                http_response_code(400);
                echo json_encode(['error' => 'meeting_type_id and order are required']);
                exit;
            }
            
            // Verify all items belong to this meeting type
            $placeholders = implode(',', array_fill(0, count($order), '?'));
            $stmt = $db->prepare("
                SELECT id FROM agenda_templates 
                WHERE meeting_type_id = ? AND id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$meetingTypeId], $order));
            $validItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($validItems) !== count($order)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid item IDs or items do not belong to this meeting type']);
                exit;
            }
            
            // Update positions
            $db->beginTransaction();
            try {
                $updateStmt = $db->prepare("UPDATE agenda_templates SET position = ? WHERE id = ?");
                foreach ($order as $index => $itemId) {
                    $updateStmt->execute([$index, (int)$itemId]);
                }
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Template items reordered successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Failed to reorder template items: ' . $e->getMessage()]);
            }
            break;
        }
        
        // Normal template creation
        $meetingTypeId = (int)($data['meeting_type_id'] ?? 0);
        $title = $data['title'] ?? '';
        
        if (!$meetingTypeId || empty($title)) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_type_id and title are required']);
            exit;
        }
        
        // Get max position for new item
        $stmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 as new_position FROM agenda_templates WHERE meeting_type_id = ?");
        $stmt->execute([$meetingTypeId]);
        $result = $stmt->fetch();
        $position = (int)$result['new_position'];
        
        $stmt = $db->prepare("INSERT INTO agenda_templates (meeting_type_id, title, description, item_type, duration_minutes, position) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingTypeId,
            $title,
            $data['description'] ?? null,
            $data['item_type'] ?? 'Discussion',
            !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
            $position
        ]);
        
        $templateId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM agenda_templates WHERE id = ?");
        $stmt->execute([$templateId]);
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
        
        $fields = ['title', 'description', 'item_type', 'duration_minutes', 'position'];
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
        $sql = "UPDATE agenda_templates SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("SELECT * FROM agenda_templates WHERE id = ?");
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
        
        // Get the meeting_type_id and position of the item being deleted
        $stmt = $db->prepare("SELECT meeting_type_id, position FROM agenda_templates WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Template item not found']);
            exit;
        }
        
        // Delete the item
        $stmt = $db->prepare("DELETE FROM agenda_templates WHERE id = ?");
        $stmt->execute([$id]);
        
        // Renumber remaining items
        $stmt = $db->prepare("
            SELECT id, position 
            FROM agenda_templates 
            WHERE meeting_type_id = ? AND position > ?
            ORDER BY position ASC
        ");
        $stmt->execute([$item['meeting_type_id'], $item['position']]);
        $remainingItems = $stmt->fetchAll();
        
        foreach ($remainingItems as $remainingItem) {
            $newPosition = $remainingItem['position'] - 1;
            $updateStmt = $db->prepare("UPDATE agenda_templates SET position = ? WHERE id = ?");
            $updateStmt->execute([$newPosition, $remainingItem['id']]);
        }
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

