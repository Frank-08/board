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
require_once __DIR__ . '/../includes/resolution_helpers.php';

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
                ORDER BY r.agenda_item_id ASC, r.position ASC, r.created_at ASC
            ");
            $stmt->execute([$meetingId]);
            ob_end_clean();
            echo json_encode($stmt->fetchAll());
        } else {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'id or meeting_id is required']);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        // Handle reorder action: reorders the lettered clauses (resolutions)
        // linked to one agenda item.
        if (isset($data['action']) && $data['action'] === 'reorder') {
            $agendaItemId = (int)($data['agenda_item_id'] ?? 0);
            $order = $data['order'] ?? [];

            if (!$agendaItemId || empty($order)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['error' => 'agenda_item_id and order are required']);
                exit;
            }

            $stmt = $db->prepare("SELECT meeting_id FROM agenda_items WHERE id = ?");
            $stmt->execute([$agendaItemId]);
            $agendaItem = $stmt->fetch();
            if (!$agendaItem) {
                ob_end_clean();
                http_response_code(404);
                echo json_encode(['error' => 'Agenda item not found']);
                exit;
            }
            if (minutesAreApproved($db, $agendaItem['meeting_id'])) {
                ob_end_clean();
                http_response_code(409);
                echo json_encode(['error' => 'Resolutions cannot be reordered after minutes are approved']);
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($order), '?'));
            $stmt = $db->prepare("SELECT id FROM resolutions WHERE agenda_item_id = ? AND id IN ($placeholders)");
            $stmt->execute(array_merge([$agendaItemId], $order));
            $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($validIds) !== count($order)) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['error' => 'Invalid resolution IDs or resolutions do not belong to this agenda item']);
                exit;
            }

            $db->beginTransaction();
            try {
                $updateStmt = $db->prepare("UPDATE resolutions SET position = ? WHERE id = ?");
                foreach ($order as $index => $resolutionId) {
                    $updateStmt->execute([$index, (int)$resolutionId]);
                }
                $db->commit();
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Resolution clauses reordered successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                ob_end_clean();
                http_response_code(500);
                echo json_encode(['error' => 'Failed to reorder resolution clauses: ' . $e->getMessage()]);
            }
            break;
        }

        $meetingId = (int)($data['meeting_id'] ?? 0);
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? '';

        if (!$meetingId || empty($description)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id and description are required']);
            exit;
        }

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

        $validation = validateResolutionData($db, $data);
        if (!$validation['valid']) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => $validation['error']]);
            exit;
        }

        // New clause goes at the end of the lettered list for its agenda item
        // (or, for an unlinked resolution, the end of the meeting's list).
        if ($agendaItemId) {
            $posStmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM resolutions WHERE agenda_item_id = ?");
            $posStmt->execute([$agendaItemId]);
        } else {
            $posStmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM resolutions WHERE meeting_id = ? AND agenda_item_id IS NULL");
            $posStmt->execute([$meetingId]);
        }
        $position = (int)$posStmt->fetchColumn();

        $stmt = $db->prepare("INSERT INTO resolutions (
            meeting_id, agenda_item_id, resolution_number, title, description, decision_method,
            motion_moved_by, motion_seconded_by, votes_for, votes_against, votes_abstain,
            casting_vote_used, referral_body, referral_scope, clerk_notes,
            vote_type, status, effective_date, position
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([
                $meetingId,
                $agendaItemId,
                $data['resolution_number'] ?? null,
                $title,
                $description,
                $data['decision_method'] ?? 'Consensus',
                !empty($data['motion_moved_by']) ? (int)$data['motion_moved_by'] : null,
                !empty($data['motion_seconded_by']) ? (int)$data['motion_seconded_by'] : null,
                isset($data['votes_for']) && $data['votes_for'] !== '' ? (int)$data['votes_for'] : null,
                isset($data['votes_against']) && $data['votes_against'] !== '' ? (int)$data['votes_against'] : null,
                isset($data['votes_abstain']) && $data['votes_abstain'] !== '' ? (int)$data['votes_abstain'] : null,
                !empty($data['casting_vote_used']) ? 1 : 0,
                $data['referral_body'] ?? null,
                $data['referral_scope'] ?? null,
                $data['clerk_notes'] ?? null,
                $data['vote_type'] ?? null,
                $data['status'] ?? 'Proposed',
                $data['effective_date'] ?? null,
                $position
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
        if (!empty($validation['warning'])) {
            $resolution['_warning'] = $validation['warning'];
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

        $validation = validateResolutionData($db, $data, $id);
        if (!$validation['valid']) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => $validation['error']]);
            exit;
        }

        $updates = [];
        $params = [];

        $fields = ['title', 'description', 'resolution_number', 'decision_method',
                   'vote_type', 'status', 'effective_date', 'agenda_item_id',
                   'motion_moved_by', 'motion_seconded_by', 'votes_for', 'votes_against',
                   'votes_abstain', 'referral_body', 'referral_scope', 'clerk_notes'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (array_key_exists('casting_vote_used', $data)) {
            $updates[] = "casting_vote_used = ?";
            $params[] = !empty($data['casting_vote_used']) ? 1 : 0;
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
        $updated = $stmt->fetch();
        if (!empty($validation['warning']) && $updated) {
            $updated['_warning'] = $validation['warning'];
        }
        ob_end_clean();
        echo json_encode($updated);
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

