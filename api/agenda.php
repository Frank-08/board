<?php
/**
 * Agenda Items API Endpoint
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
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name,
                    r.id as resolution_id, r.title as resolution_title, r.resolution_number, r.status as resolution_status
                FROM agenda_items ai
                LEFT JOIN board_members bm ON ai.presenter_id = bm.id
                LEFT JOIN resolutions r ON ai.id = r.agenda_item_id
                WHERE ai.id = ?
            ");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                http_response_code(404);
                echo json_encode(['error' => 'Agenda item not found']);
                exit;
            }
            
            echo json_encode($item);
        } elseif (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name,
                    r.id as resolution_id, r.title as resolution_title, r.resolution_number, r.status as resolution_status
                FROM agenda_items ai
                LEFT JOIN board_members bm ON ai.presenter_id = bm.id
                LEFT JOIN resolutions r ON ai.id = r.agenda_item_id
                WHERE ai.meeting_id = ?
                ORDER BY ai.position ASC, ai.sub_position ASC
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
        
        // Handle reorder action
        if (isset($data['action']) && $data['action'] === 'reorder') {
            $meetingId = (int)($data['meeting_id'] ?? 0);
            $order = $data['order'] ?? [];
            
            if (!$meetingId || empty($order)) {
                http_response_code(400);
                echo json_encode(['error' => 'meeting_id and order are required']);
                exit;
            }
            
            // Get meeting date and shortcode for item number format
            $stmt = $db->prepare("
                SELECT m.scheduled_date, mt.shortcode 
                FROM meetings m
                JOIN meeting_types mt ON m.meeting_type_id = mt.id
                WHERE m.id = ?
            ");
            $stmt->execute([$meetingId]);
            $meeting = $stmt->fetch();
            
            if (!$meeting) {
                http_response_code(404);
                echo json_encode(['error' => 'Meeting not found']);
                exit;
            }
            
            // Verify all items belong to this meeting
            $placeholders = implode(',', array_fill(0, count($order), '?'));
            $stmt = $db->prepare("
                SELECT id FROM agenda_items 
                WHERE meeting_id = ? AND id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$meetingId], $order));
            $validItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($validItems) !== count($order)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid item IDs or items do not belong to this meeting']);
                exit;
            }
            
            // Start transaction
            $db->beginTransaction();
            
            try {
                $meetingDate = new DateTime($meeting['scheduled_date']);
                $year = $meetingDate->format('y');
                $month = $meetingDate->format('n');
                $shortcode = $meeting['shortcode'] ?? '';
                
                // Pre-fetch all parent-child relationships
                $placeholders = implode(',', array_fill(0, count($order), '?'));
                $stmt = $db->prepare("
                    SELECT id, parent_id 
                    FROM agenda_items 
                    WHERE meeting_id = ? AND id IN ($placeholders)
                ");
                $stmt->execute(array_merge([$meetingId], $order));
                $parentRelations = [];
                while ($row = $stmt->fetch()) {
                    $parentRelations[(int)$row['id']] = $row['parent_id'] ? (int)$row['parent_id'] : null;
                }
                
                // Update positions and item numbers, keeping children with their parent
                $updateTopStmt = $db->prepare("UPDATE agenda_items SET position = ?, sub_position = 0, item_number = ?, parent_id = NULL WHERE id = ?");
                $updateChildStmt = $db->prepare("UPDATE agenda_items SET position = ?, sub_position = ?, item_number = ?, parent_id = ? WHERE id = ?");

                $topPos = -1;
                $parentPositions = [];
                $parentItemNumbers = [];
                $parentChildCounters = [];
                $processed = [];

                foreach ($order as $index => $itemId) {
                    $itemId = (int)$itemId;
                    if (isset($processed[$itemId])) {
                        continue; // Already processed
                    }
                    
                    $parentId = isset($parentRelations[$itemId]) ? $parentRelations[$itemId] : null;

                    // If this is a child and parent hasn't been processed yet, process parent first
                    if ($parentId && !isset($processed[$parentId])) {
                        // Find parent in order array and process it first
                        $parentIndex = array_search($parentId, $order);
                        if ($parentIndex !== false) {
                            // Parent is later in array, but we need it now - process it
                            $parentIdToProcess = (int)$order[$parentIndex];
                            $parentParentId = isset($parentRelations[$parentIdToProcess]) ? $parentRelations[$parentIdToProcess] : null;
                            
                            if (empty($parentParentId)) {
                                // Parent is top-level
                                $topPos++;
                                $position = $topPos;
                                $sequence = $position + 1;
                                if (!empty($shortcode)) {
                                    $topItemNumber = sprintf('%s.%s.%s.%d', $shortcode, $year, $month, $sequence);
                                } else {
                                    $topItemNumber = sprintf('%s.%s.%d', $year, $month, $sequence);
                                }

                                $parentPositions[$parentIdToProcess] = $position;
                                $parentItemNumbers[$parentIdToProcess] = $topItemNumber;
                                $parentChildCounters[$parentIdToProcess] = 0;

                                $updateTopStmt->execute([$position, $topItemNumber, $parentIdToProcess]);
                                $processed[$parentIdToProcess] = true;
                            } else {
                                // Parent is also a child - recursively process its parent first
                                // For now, treat it as top-level to avoid infinite recursion
                                $topPos++;
                                $position = $topPos;
                                $sequence = $position + 1;
                                if (!empty($shortcode)) {
                                    $topItemNumber = sprintf('%s.%s.%s.%d', $shortcode, $year, $month, $sequence);
                                } else {
                                    $topItemNumber = sprintf('%s.%s.%d', $year, $month, $sequence);
                                }

                                $parentPositions[$parentIdToProcess] = $position;
                                $parentItemNumbers[$parentIdToProcess] = $topItemNumber;
                                $parentChildCounters[$parentIdToProcess] = 0;

                                $updateTopStmt->execute([$position, $topItemNumber, $parentIdToProcess]);
                                $processed[$parentIdToProcess] = true;
                            }
                        } else if ($parentIndex === false) {
                            // Parent not in order array - this shouldn't happen, but handle gracefully
                            // Treat child as top-level for now
                            $parentId = null;
                        }
                    }

                    if (empty($parentId)) {
                        // top-level item
                        $topPos++;
                        $position = $topPos;
                        $sequence = $position + 1;
                        if (!empty($shortcode)) {
                            $topItemNumber = sprintf('%s.%s.%s.%d', $shortcode, $year, $month, $sequence);
                        } else {
                            $topItemNumber = sprintf('%s.%s.%d', $year, $month, $sequence);
                        }

                        $parentPositions[$itemId] = $position;
                        $parentItemNumbers[$itemId] = $topItemNumber;
                        $parentChildCounters[$itemId] = 0;

                        $updateTopStmt->execute([$position, $topItemNumber, $itemId]);
                        $processed[$itemId] = true;
                    } else {
                        // child item: parent should now be processed
                        if (!isset($parentItemNumbers[$parentId])) {
                            // Parent not processed yet - this is an error condition
                            // This shouldn't happen, but if it does, treat as top-level to avoid breaking
                            $topPos++;
                            $position = $topPos;
                            $sequence = $position + 1;
                            if (!empty($shortcode)) {
                                $topItemNumber = sprintf('%s.%s.%s.%d', $shortcode, $year, $month, $sequence);
                            } else {
                                $topItemNumber = sprintf('%s.%s.%d', $year, $month, $sequence);
                            }

                            $parentPositions[$itemId] = $position;
                            $parentItemNumbers[$itemId] = $topItemNumber;
                            $parentChildCounters[$itemId] = 0;

                            $updateTopStmt->execute([$position, $topItemNumber, $itemId]);
                            $processed[$itemId] = true;
                            continue;
                        }

                        $subPos = $parentChildCounters[$parentId];
                        $letter = chr(ord('a') + $subPos);
                        $itemNumber = $parentItemNumbers[$parentId] . $letter;
                        $position = $parentPositions[$parentId];

                        $parentChildCounters[$parentId] = $subPos + 1;

                        $updateChildStmt->execute([$position, $subPos, $itemNumber, $parentId, $itemId]);
                        $processed[$itemId] = true;
                    }
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Agenda items reordered successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Failed to reorder agenda items: ' . $e->getMessage()]);
            }
            break;
        }
        
        // Normal item creation
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $title = $data['title'] ?? '';
        
        if (!$meetingId || empty($title)) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id and title are required']);
            exit;
        }
        
        // Get meeting date and shortcode for item number format
        $stmt = $db->prepare("
            SELECT m.scheduled_date, mt.shortcode 
            FROM meetings m
            JOIN meeting_types mt ON m.meeting_type_id = mt.id
            WHERE m.id = ?
        ");
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
            $stmt->execute([
                $meetingId,
                $title,
                $data['description'] ?? null,
                $data['item_type'] ?? 'Discussion',
                !empty($data['presenter_id']) ? (int)$data['presenter_id'] : null,
                !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
                $position,
                $subPosition,
                $parentId,
                $itemNumber
            ]);
        } else {
            // Get max position and ensure sequential numbering (0-based, so max + 1)
            $stmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 as new_position FROM agenda_items WHERE meeting_id = ? AND parent_id IS NULL");
            $stmt->execute([$meetingId]);
            $result = $stmt->fetch();
            $position = (int)$result['new_position'];

            // Generate item number in format: SHORTCODE.YY.MM.SEQ (or YY.MM.SEQ if no shortcode)
            $meetingDate = new DateTime($meeting['scheduled_date']);
            $year = $meetingDate->format('y'); // Last 2 digits of year
            $month = $meetingDate->format('n'); // Month without leading zero (1-12)
            $sequence = $position + 1; // Position is 0-based, sequence is 1-based
            $shortcode = $meeting['shortcode'] ?? ''; // Get shortcode from meeting_type
            if (!empty($shortcode)) {
                $itemNumber = sprintf('%s.%s.%s.%d', $shortcode, $year, $month, $sequence);
            } else {
                $itemNumber = sprintf('%s.%s.%d', $year, $month, $sequence);
            }

            $stmt = $db->prepare("INSERT INTO agenda_items (meeting_id, title, description, item_type, presenter_id, duration_minutes, position, item_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $meetingId,
                $title,
                $data['description'] ?? null,
                $data['item_type'] ?? 'Discussion',
                !empty($data['presenter_id']) ? (int)$data['presenter_id'] : null,
                !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
                $position,
                $itemNumber
            ]);
        }
        
        $itemId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name
            FROM agenda_items ai
            LEFT JOIN board_members bm ON ai.presenter_id = bm.id
            WHERE ai.id = ?
        ");
        $stmt->execute([$itemId]);
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
        
        $fields = ['title', 'description', 'item_type', 'presenter_id', 'duration_minutes', 'position', 'outcome'];
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
        $sql = "UPDATE agenda_items SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT ai.*, bm.first_name as presenter_first_name, bm.last_name as presenter_last_name
            FROM agenda_items ai
            LEFT JOIN board_members bm ON ai.presenter_id = bm.id
            WHERE ai.id = ?
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
        
        // Get the meeting_id, position, meeting date, and shortcode of the item being deleted
        $stmt = $db->prepare("
            SELECT ai.meeting_id, ai.position, m.scheduled_date, mt.shortcode 
            FROM agenda_items ai
            JOIN meetings m ON ai.meeting_id = m.id
            JOIN meeting_types mt ON m.meeting_type_id = mt.id
            WHERE ai.id = ?
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Agenda item not found']);
            exit;
        }
        
        // Delete the item
        $stmt = $db->prepare("DELETE FROM agenda_items WHERE id = ?");
        $stmt->execute([$id]);
        
        // Renumber remaining items to ensure sequential numbering and update item numbers
        $stmt = $db->prepare("
            SELECT id, position 
            FROM agenda_items 
            WHERE meeting_id = ? AND position > ?
            ORDER BY position ASC
        ");
        $stmt->execute([$item['meeting_id'], $item['position']]);
        $remainingItems = $stmt->fetchAll();
        
        // Update positions and item numbers
        $meetingDate = new DateTime($item['scheduled_date']);
        $year = $meetingDate->format('y');
        $month = $meetingDate->format('n');
        $shortcode = $item['shortcode'] ?? '';
        
        foreach ($remainingItems as $remainingItem) {
            $newPosition = $remainingItem['position'] - 1;
            $newSequence = $newPosition + 1;
            if (!empty($shortcode)) {
                $newItemNumber = sprintf('%s.%s.%s.%d', $shortcode, $year, $month, $newSequence);
            } else {
                $newItemNumber = sprintf('%s.%s.%d', $year, $month, $newSequence);
            }
            
            $updateStmt = $db->prepare("
                UPDATE agenda_items 
                SET position = ?, item_number = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$newPosition, $newItemNumber, $remainingItem['id']]);
        }
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

