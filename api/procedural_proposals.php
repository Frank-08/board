<?php
/**
 * Procedural Proposals API Endpoint
 *
 * Records procedural motions raised during a meeting (points of order,
 * adjournment, previous question, etc. — UCA Manual for Meetings §5.15-5.16, §7.6-7.10).
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

const PROPOSAL_TYPES = [
    'UseOfProcedures', 'OrderOfDay', 'Adjournment', 'PrivateSitting',
    'Referral', 'DecisionNow', 'WithdrawMotion', 'PreviousQuestion',
    'Closure', 'Reconsideration', 'PointOfOrder'
];

const PROPOSAL_OUTCOMES = ['Carried', 'Lost', 'Lapsed', 'RuledOn', 'Pending'];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDBConnection();

    // Check permissions for write operations
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        requirePermission('manage_procedural_proposals');
    }

    function minutesAreApprovedForProposal($db, $meetingId) {
        $stmt = $db->prepare("SELECT status FROM minutes WHERE meeting_id = ? LIMIT 1");
        $stmt->execute([(int)$meetingId]);
        $minutes = $stmt->fetch();
        return $minutes && $minutes['status'] === 'Approved';
    }

    switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("SELECT * FROM procedural_proposals WHERE id = ?");
            $stmt->execute([$id]);
            $proposal = $stmt->fetch();

            if (!$proposal) {
                ob_end_clean();
                http_response_code(404);
                echo json_encode(['error' => 'Procedural proposal not found']);
                exit;
            }

            ob_end_clean();
            echo json_encode($proposal);
        } elseif (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT pp.*,
                    proposer.first_name AS proposed_by_first_name, proposer.last_name AS proposed_by_last_name,
                    seconder.first_name AS seconded_by_first_name, seconder.last_name AS seconded_by_last_name
                FROM procedural_proposals pp
                LEFT JOIN board_members proposer ON pp.proposed_by = proposer.id
                LEFT JOIN board_members seconder ON pp.seconded_by = seconder.id
                WHERE pp.meeting_id = ?
                ORDER BY pp.recorded_at ASC, pp.id ASC
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
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $proposalType = $data['proposal_type'] ?? '';

        if (!$meetingId || empty($proposalType)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id and proposal_type are required']);
            exit;
        }

        if (!in_array($proposalType, PROPOSAL_TYPES, true)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid proposal_type']);
            exit;
        }

        $outcome = $data['outcome'] ?? 'Pending';
        if (!in_array($outcome, PROPOSAL_OUTCOMES, true)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid outcome']);
            exit;
        }

        if (minutesAreApprovedForProposal($db, $meetingId)) {
            ob_end_clean();
            http_response_code(409);
            echo json_encode(['error' => 'Procedural proposals cannot be added after minutes are approved']);
            exit;
        }

        $agendaItemId = null;
        if (!empty($data['agenda_item_id'])) {
            $agendaItemId = (int)$data['agenda_item_id'];
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

        $resolutionId = null;
        if (!empty($data['resolution_id'])) {
            $resolutionId = (int)$data['resolution_id'];
            $stmt = $db->prepare("SELECT meeting_id FROM resolutions WHERE id = ?");
            $stmt->execute([$resolutionId]);
            $resolution = $stmt->fetch();
            if (!$resolution || (int)$resolution['meeting_id'] !== $meetingId) {
                ob_end_clean();
                http_response_code(400);
                echo json_encode(['error' => 'Invalid resolution_id or resolution does not belong to meeting']);
                exit;
            }
        }

        $stmt = $db->prepare("
            INSERT INTO procedural_proposals
                (meeting_id, agenda_item_id, resolution_id, proposal_type, proposed_by, seconded_by, outcome, requires_leave, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        try {
            $stmt->execute([
                $meetingId,
                $agendaItemId,
                $resolutionId,
                $proposalType,
                !empty($data['proposed_by']) ? (int)$data['proposed_by'] : null,
                !empty($data['seconded_by']) ? (int)$data['seconded_by'] : null,
                $outcome,
                !empty($data['requires_leave']) ? 1 : 0,
                $data['notes'] ?? null
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['error' => 'Database error creating procedural proposal: ' . $e->getMessage()]);
            exit;
        }

        $proposalId = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM procedural_proposals WHERE id = ?");
        $stmt->execute([$proposalId]);
        ob_end_clean();
        echo json_encode($stmt->fetch());
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

        $stmt = $db->prepare("SELECT meeting_id FROM procedural_proposals WHERE id = ?");
        $stmt->execute([$id]);
        $proposal = $stmt->fetch();
        if (!$proposal) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['error' => 'Procedural proposal not found']);
            exit;
        }
        if (minutesAreApprovedForProposal($db, $proposal['meeting_id'])) {
            ob_end_clean();
            http_response_code(409);
            echo json_encode(['error' => 'Procedural proposals cannot be updated after minutes are approved']);
            exit;
        }

        if (isset($data['proposal_type']) && !in_array($data['proposal_type'], PROPOSAL_TYPES, true)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid proposal_type']);
            exit;
        }
        if (isset($data['outcome']) && !in_array($data['outcome'], PROPOSAL_OUTCOMES, true)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['error' => 'Invalid outcome']);
            exit;
        }
        if (array_key_exists('requires_leave', $data)) {
            $data['requires_leave'] = !empty($data['requires_leave']) ? 1 : 0;
        }

        $updates = [];
        $params = [];

        $fields = ['agenda_item_id', 'resolution_id', 'proposal_type', 'proposed_by',
                   'seconded_by', 'outcome', 'requires_leave', 'notes'];
        foreach ($fields as $field) {
            if (isset($data[$field]) || array_key_exists($field, $data)) {
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
        $sql = "UPDATE procedural_proposals SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $stmt = $db->prepare("SELECT * FROM procedural_proposals WHERE id = ?");
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

        $stmt = $db->prepare("SELECT meeting_id FROM procedural_proposals WHERE id = ?");
        $stmt->execute([$id]);
        $proposal = $stmt->fetch();
        if (!$proposal) {
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['error' => 'Procedural proposal not found']);
            exit;
        }
        if (minutesAreApprovedForProposal($db, $proposal['meeting_id'])) {
            ob_end_clean();
            http_response_code(409);
            echo json_encode(['error' => 'Procedural proposals cannot be deleted after minutes are approved']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM procedural_proposals WHERE id = ?");
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
