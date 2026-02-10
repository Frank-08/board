<?php
/**
 * Resolutions API Endpoint
 */
// Start output buffering to prevent any output before JSON
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require authentication for all requests
requireAuth();

// Set error handler to catch any PHP errors
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        http_response_code(500);
        echo json_encode(['error' => 'PHP Error: ' . $message . ' in ' . $file . ' on line ' . $line]);
        exit;
    }
});

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDBConnection();

    // Check permissions for write operations
    if (in_array($method, ['POST', 'PUT'])) {
        requirePermission('create_resolution');
    }
    if ($method === 'DELETE') {
        requirePermission('delete_resolution');
    }

    /**
     * Convert a numeric position (0-based) to Excel-style column letter suffix
     * 0 → 'a', 1 → 'b', ..., 25 → 'z', 26 → 'aa', 27 → 'ab', etc.
     * 
     * @param int $number 0-based index
     * @return string Letter suffix (a-z, aa-az, ba-bz, etc.)
     */
    function numberToLetterSuffix($number) {
        $result = '';
        $num = $number;
        while ($num >= 0) {
            $result = chr(ord('a') + ($num % 26)) . $result;
            $num = intval($num / 26) - 1;
            if ($num < 0) break;
        }
        return $result;
    }

    function minutesAreApproved($db, $meetingId) {
        $stmt = $db->prepare("SELECT status FROM minutes WHERE meeting_id = ? LIMIT 1");
        $stmt->execute([(int)$meetingId]);
        $minutes = $stmt->fetch();
        return $minutes && $minutes['status'] === 'Approved';
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
                ob_end_clean();
                http_response_code(404);
                echo json_encode(['error' => 'Resolution not found']);
                exit;
            }
            
            ob_end_clean();
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
            ob_end_clean();
            echo json_encode($stmt->fetchAll());
        } else {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'id or meeting_id is required']);
        }
        if (empty($data['agenda_item_id'])) {
            // Get meeting date and shortcode from meeting_type for item number format
            $stmt = $db->prepare("
                $agendaItemId = null;
                if (!empty($data['agenda_item_id'])) {
                    $agendaItemId = (int)$data['agenda_item_id'];

                    // Validate that the agenda_item_id belongs to the meeting
                    $stmt = $db->prepare("SELECT meeting_id FROM agenda_items WHERE id = ?");
                    $stmt->execute([$agendaItemId]);
                    $agendaItem = $stmt->fetch();
                    if (!$agendaItem || (int)$agendaItem['meeting_id'] !== $meetingId) {
                        ob_end_clean();
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid agenda_item_id or agenda item does not belong to meeting']);
                        exit;
                    }
                }
                $data['resolution_number'] ?? null,
                $title,
                $description,
                $data['vote_type'] ?? null,
                $data['status'] ?? 'Proposed',
                $data['effective_date'] ?? null
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['error' => 'Database error creating resolution: ' . $e->getMessage()]);
            exit;
        }
        
        $resolutionId = $db->lastInsertId();
        if (!$resolutionId) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create resolution']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT r.*
            FROM resolutions r
            WHERE r.id = ?
        ");
        $stmt->execute([$resolutionId]);
        $resolution = $stmt->fetch();
        if (!$resolution) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to retrieve created resolution']);
            exit;
        }
        ob_end_clean();
        echo json_encode($resolution);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT meeting_id FROM resolutions WHERE id = ?");
        $stmt->execute([$id]);
        $resolution = $stmt->fetch();
        if (!$resolution) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['error' => 'Resolution not found']);
            exit;
        }
        if (minutesAreApproved($db, $resolution['meeting_id'])) {
            ob_end_clean();
            http_response_code(409);
            echo json_encode(['error' => 'Resolutions cannot be updated after minutes are approved']);
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
            ob_end_clean();
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
        ob_end_clean();
        echo json_encode($stmt->fetch());
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT meeting_id FROM resolutions WHERE id = ?");
        $stmt->execute([$id]);
        $resolution = $stmt->fetch();
        if (!$resolution) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['error' => 'Resolution not found']);
            exit;
        }
        if (minutesAreApproved($db, $resolution['meeting_id'])) {
            ob_end_clean();
            http_response_code(409);
            echo json_encode(['error' => 'Resolutions cannot be deleted after minutes are approved']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM resolutions WHERE id = ?");
        $stmt->execute([$id]);
        ob_end_clean();
        echo json_encode(['success' => true]);
        break;
        
    default:
        ob_end_clean();
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Fatal error: ' . $e->getMessage()]);
}

