<?php
/**
 * Resolutions API Endpoint
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
if (in_array($method, ['POST', 'PUT'])) {
    requirePermission('create_resolution');
}
if ($method === 'DELETE') {
    requirePermission('delete_resolution');
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT r.*
                FROM resolutions r
                WHERE r.id = ?
            ");
            $stmt->execute([$id]);
            $resolution = $stmt->fetch();
            
            if (!$resolution) {
                http_response_code(404);
                echo json_encode(['error' => 'Resolution not found']);
                exit;
            }
            
            echo json_encode($resolution);
        } elseif (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT r.*
                FROM resolutions r
                WHERE r.meeting_id = ?
                ORDER BY r.created_at ASC
            ");
            $stmt->execute([$meetingId]);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'id or meeting_id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        
        if (!$meetingId || empty($title) || empty($description)) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id, title, and description are required']);
            exit;
        }
        
        $agendaItemId = null;
        
        // If no agenda_item_id provided, create one automatically
        if (empty($data['agenda_item_id'])) {
            // Get meeting date for item number format
            $stmt = $db->prepare("SELECT scheduled_date, shortcode FROM meetings WHERE id = ?");
            $stmt->execute([$meetingId]);
            $meeting = $stmt->fetch();
            
            if (!$meeting) {
                http_response_code(404);
                echo json_encode(['error' => 'Meeting not found']);
                exit;
            }
            
            // Support optional hierarchical parent_id for sub-items
            $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
            
            if ($parentId) {
                // Verify parent exists and belongs to this meeting
                $pstmt = $db->prepare("SELECT * FROM agenda_items WHERE id = ?");
                $pstmt->execute([$parentId]);
                $parent = $pstmt->fetch();
                if (!$parent || (int)$parent['meeting_id'] !== $meetingId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid parent_id or parent does not belong to meeting']);
                    exit;
                }

                // Compute next sub_position for this parent
                $spStmt = $db->prepare("SELECT COALESCE(MAX(sub_position), -1) + 1 as new_sub_position FROM agenda_items WHERE parent_id = ?");
                $spStmt->execute([$parentId]);
                $spResult = $spStmt->fetch();
                $subPosition = (int)$spResult['new_sub_position'];

                // Ensure parent has an item_number
                $parentItemNumber = $parent['item_number'] ?? null;
                if (empty($parentItemNumber)) {
                    // Fallback: compute parent item number based on meeting date and parent position
                    $meetingDate = new DateTime($meeting['scheduled_date']);
                    $year = $meetingDate->format('y');
                    $month = $meetingDate->format('n');
                    $shortcode = $meeting['shortcode'] ?? '';
                    $parentSeq = $parent['position'] + 1;
                    if (!empty($shortcode)) {
                        $parentItemNumber = sprintf('%s.%s.%s.%d', $shortcode, $year, $month, $parentSeq);
                    } else {
                        $parentItemNumber = sprintf('%s.%s.%d', $year, $month, $parentSeq);
                    }
                }

                $letter = chr(ord('a') + $subPosition);
                $itemNumber = $parentItemNumber . $letter;

                // Use parent's position so children are grouped with parent
                $position = (int)$parent['position'];

                $stmt = $db->prepare("INSERT INTO agenda_items (meeting_id, title, description, item_type, presenter_id, duration_minutes, position, sub_position, parent_id, item_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                try {
                    $stmt->execute([
                        $meetingId,
                        $title,
                        $description,
                        'Vote', // Resolutions are typically vote items
                        null, // presenter_id
                        null, // duration
                        $position,
                        $subPosition,
                        $parentId,
                        $itemNumber
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
                    exit;
                }
            } else {
                // Get max position for agenda items and ensure sequential numbering
                $stmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 as new_position FROM agenda_items WHERE meeting_id = ? AND parent_id IS NULL");
                $stmt->execute([$meetingId]);
                $result = $stmt->fetch();
                $position = (int)$result['new_position'];
                
                // Generate item number in format: YY.MM.SEQ or SHORTCODE.YY.MM.SEQ
                $meetingDate = new DateTime($meeting['scheduled_date']);
                $year = $meetingDate->format('y'); // Last 2 digits of year
                $month = $meetingDate->format('n'); // Month without leading zero (1-12)
                $sequence = $position + 1; // Position is 0-based, sequence is 1-based
                $shortcode = $meeting['shortcode'] ?? '';
                if (!empty($shortcode)) {
                    $itemNumber = sprintf('%s.%s.%s.%d', $shortcode, $year, $month, $sequence);
                } else {
                    $itemNumber = sprintf('%s.%s.%d', $year, $month, $sequence);
                }
                
                // Create agenda item for the resolution
                $stmt = $db->prepare("INSERT INTO agenda_items (meeting_id, title, description, item_type, presenter_id, duration_minutes, position, item_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                try {
                    $stmt->execute([
                        $meetingId,
                        $title,
                        $description,
                        'Vote', // Resolutions are typically vote items
                        null, // presenter_id
                        null, // duration
                        $position,
                        $itemNumber
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
                    exit;
                }
            }
            
            $agendaItemId = $db->lastInsertId();
        } else {
            $agendaItemId = (int)$data['agenda_item_id'];
            
            // Validate that the agenda_item_id belongs to the meeting
            $stmt = $db->prepare("SELECT meeting_id FROM agenda_items WHERE id = ?");
            $stmt->execute([$agendaItemId]);
            $agendaItem = $stmt->fetch();
            if (!$agendaItem || (int)$agendaItem['meeting_id'] !== $meetingId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid agenda_item_id or agenda item does not belong to meeting']);
                exit;
            }
        }
        
        $stmt = $db->prepare("INSERT INTO resolutions (meeting_id, agenda_item_id, resolution_number, title, description, vote_type, status, effective_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([
                $meetingId,
                $agendaItemId,
                $data['resolution_number'] ?? null,
                $title,
                $description,
                $data['vote_type'] ?? null,
                $data['status'] ?? 'Proposed',
                $data['effective_date'] ?? null
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error creating resolution: ' . $e->getMessage()]);
            exit;
        }
        
        $resolutionId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT r.*
            FROM resolutions r
            WHERE r.id = ?
        ");
        $stmt->execute([$resolutionId]);
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
        
        $fields = ['title', 'description', 'resolution_number', 
                   'vote_type', 'status', 'effective_date', 'agenda_item_id'];
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
        $sql = "UPDATE resolutions SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT r.*
            FROM resolutions r
            WHERE r.id = ?
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
        
        $stmt = $db->prepare("DELETE FROM resolutions WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

