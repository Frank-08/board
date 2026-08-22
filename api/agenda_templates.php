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
                ORDER BY position ASC, CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC, sub_position ASC
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
        
        // Handle reorder action (top-level items only)
        if (isset($data['action']) && $data['action'] === 'reorder') {
            $meetingTypeId = (int)($data['meeting_type_id'] ?? 0);
            $order = $data['order'] ?? [];

            if (!$meetingTypeId || empty($order)) {
                http_response_code(400);
                echo json_encode(['error' => 'meeting_type_id and order are required']);
                exit;
            }

            // Verify all items belong to this meeting type and are top-level
            $placeholders = implode(',', array_fill(0, count($order), '?'));
            $stmt = $db->prepare("
                SELECT id FROM agenda_templates
                WHERE meeting_type_id = ? AND parent_id IS NULL AND id IN ($placeholders)
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

        // Handle reorder_children action (reorders sub_position among one parent's children)
        if (isset($data['action']) && $data['action'] === 'reorder_children') {
            $parentId = (int)($data['parent_id'] ?? 0);
            $order = $data['order'] ?? [];

            if (!$parentId || empty($order)) {
                http_response_code(400);
                echo json_encode(['error' => 'parent_id and order are required']);
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($order), '?'));
            $stmt = $db->prepare("
                SELECT id FROM agenda_templates
                WHERE parent_id = ? AND id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$parentId], $order));
            $validItems = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($validItems) !== count($order)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid item IDs or items do not belong to this parent']);
                exit;
            }

            $db->beginTransaction();
            try {
                $updateStmt = $db->prepare("UPDATE agenda_templates SET sub_position = ? WHERE id = ?");
                foreach ($order as $index => $itemId) {
                    $updateStmt->execute([$index, (int)$itemId]);
                }
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Template child items reordered successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Failed to reorder template child items: ' . $e->getMessage()]);
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

        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        if ($parentId) {
            $pstmt = $db->prepare("SELECT * FROM agenda_templates WHERE id = ?");
            $pstmt->execute([$parentId]);
            $parent = $pstmt->fetch();
            if (!$parent || (int)$parent['meeting_type_id'] !== $meetingTypeId || !empty($parent['parent_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid parent_id, parent must be a top-level template item in the same meeting type']);
                exit;
            }

            $spStmt = $db->prepare("SELECT COALESCE(MAX(sub_position), -1) + 1 as new_sub_position FROM agenda_templates WHERE parent_id = ?");
            $spStmt->execute([$parentId]);
            $subPosition = (int)$spStmt->fetch()['new_sub_position'];
            $position = (int)$parent['position'];
        } else {
            // Get max position for new top-level item
            $stmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 as new_position FROM agenda_templates WHERE meeting_type_id = ? AND parent_id IS NULL");
            $stmt->execute([$meetingTypeId]);
            $result = $stmt->fetch();
            $position = (int)$result['new_position'];
            $subPosition = 0;
        }

        $stmt = $db->prepare("INSERT INTO agenda_templates (meeting_type_id, title, description, item_type, decision_method, duration_minutes, position, sub_position, parent_id, is_starred) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingTypeId,
            $title,
            $data['description'] ?? null,
            $data['item_type'] ?? 'Discussion',
            $data['decision_method'] ?? 'None',
            !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
            $position,
            $subPosition,
            $parentId,
            !empty($data['is_starred']) ? 1 : 0
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

        $fields = ['title', 'description', 'item_type', 'decision_method', 'duration_minutes', 'position', 'sub_position'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (array_key_exists('parent_id', $data)) {
            $updates[] = "parent_id = ?";
            $params[] = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        }
        if (array_key_exists('is_starred', $data)) {
            $updates[] = "is_starred = ?";
            $params[] = !empty($data['is_starred']) ? 1 : 0;
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
        
        // Get the meeting_type_id, parent_id, position, and sub_position of the item being deleted
        $stmt = $db->prepare("SELECT meeting_type_id, parent_id, position, sub_position FROM agenda_templates WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Template item not found']);
            exit;
        }

        // Delete the item (children cascade via FK)
        $stmt = $db->prepare("DELETE FROM agenda_templates WHERE id = ?");
        $stmt->execute([$id]);

        if (empty($item['parent_id'])) {
            // Renumber remaining top-level items
            $stmt = $db->prepare("
                SELECT id, position
                FROM agenda_templates
                WHERE meeting_type_id = ? AND parent_id IS NULL AND position > ?
                ORDER BY position ASC
            ");
            $stmt->execute([$item['meeting_type_id'], $item['position']]);
            $remainingItems = $stmt->fetchAll();

            foreach ($remainingItems as $remainingItem) {
                $newPosition = $remainingItem['position'] - 1;
                $updateStmt = $db->prepare("UPDATE agenda_templates SET position = ? WHERE id = ?");
                $updateStmt->execute([$newPosition, $remainingItem['id']]);
            }
        } else {
            // Renumber remaining siblings under the same parent
            $stmt = $db->prepare("
                SELECT id, sub_position
                FROM agenda_templates
                WHERE parent_id = ? AND sub_position > ?
                ORDER BY sub_position ASC
            ");
            $stmt->execute([$item['parent_id'], $item['sub_position']]);
            $remainingItems = $stmt->fetchAll();

            foreach ($remainingItems as $remainingItem) {
                $newSubPosition = $remainingItem['sub_position'] - 1;
                $updateStmt = $db->prepare("UPDATE agenda_templates SET sub_position = ? WHERE id = ?");
                $updateStmt->execute([$newSubPosition, $remainingItem['id']]);
            }
        }

        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

