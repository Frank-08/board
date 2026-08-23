<?php
/**
 * Agenda Item Departures API Endpoint
 *
 * Records members who left the room during a specific agenda item (e.g. a
 * declared conflict of interest), entered while taking minutes.
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

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    requirePermission('manage_minutes');
}

function minutesAreApprovedForDeparture($db, $meetingId) {
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
                SELECT d.*, bm.first_name, bm.last_name
                FROM agenda_item_departures d
                JOIN board_members bm ON d.member_id = bm.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            $departure = $stmt->fetch();

            if (!$departure) {
                http_response_code(404);
                echo json_encode(['error' => 'Departure not found']);
                exit;
            }

            echo json_encode($departure);
        } elseif (isset($_GET['agenda_item_id'])) {
            $agendaItemId = (int)$_GET['agenda_item_id'];
            $stmt = $db->prepare("
                SELECT d.*, bm.first_name, bm.last_name
                FROM agenda_item_departures d
                JOIN board_members bm ON d.member_id = bm.id
                WHERE d.agenda_item_id = ?
                ORDER BY d.created_at ASC
            ");
            $stmt->execute([$agendaItemId]);
            echo json_encode($stmt->fetchAll());
        } elseif (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT d.*, bm.first_name, bm.last_name
                FROM agenda_item_departures d
                JOIN agenda_items ai ON d.agenda_item_id = ai.id
                JOIN board_members bm ON d.member_id = bm.id
                WHERE ai.meeting_id = ?
                ORDER BY d.agenda_item_id ASC, d.created_at ASC
            ");
            $stmt->execute([$meetingId]);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'id, agenda_item_id, or meeting_id is required']);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $agendaItemId = (int)($data['agenda_item_id'] ?? 0);
        $memberId = (int)($data['member_id'] ?? 0);

        if (!$agendaItemId || !$memberId) {
            http_response_code(400);
            echo json_encode(['error' => 'agenda_item_id and member_id are required']);
            exit;
        }

        $stmt = $db->prepare("SELECT meeting_id FROM agenda_items WHERE id = ?");
        $stmt->execute([$agendaItemId]);
        $agendaItem = $stmt->fetch();
        if (!$agendaItem) {
            http_response_code(404);
            echo json_encode(['error' => 'Agenda item not found']);
            exit;
        }
        if (minutesAreApprovedForDeparture($db, $agendaItem['meeting_id'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Departures cannot be added after minutes are approved']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO agenda_item_departures (agenda_item_id, member_id, reason, returned) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([
                $agendaItemId,
                $memberId,
                $data['reason'] ?? null,
                !empty($data['returned']) ? 1 : 0
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error recording departure: ' . $e->getMessage()]);
            exit;
        }

        $departureId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT d.*, bm.first_name, bm.last_name
            FROM agenda_item_departures d
            JOIN board_members bm ON d.member_id = bm.id
            WHERE d.id = ?
        ");
        $stmt->execute([$departureId]);
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

        $stmt = $db->prepare("
            SELECT d.agenda_item_id, ai.meeting_id
            FROM agenda_item_departures d
            JOIN agenda_items ai ON d.agenda_item_id = ai.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $departure = $stmt->fetch();
        if (!$departure) {
            http_response_code(404);
            echo json_encode(['error' => 'Departure not found']);
            exit;
        }
        if (minutesAreApprovedForDeparture($db, $departure['meeting_id'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Departures cannot be updated after minutes are approved']);
            exit;
        }

        $updates = [];
        $params = [];

        if (isset($data['reason']) || array_key_exists('reason', $data)) {
            $updates[] = "reason = ?";
            $params[] = $data['reason'];
        }
        if (array_key_exists('returned', $data)) {
            $updates[] = "returned = ?";
            $params[] = !empty($data['returned']) ? 1 : 0;
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }

        $params[] = $id;
        $sql = "UPDATE agenda_item_departures SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $stmt = $db->prepare("
            SELECT d.*, bm.first_name, bm.last_name
            FROM agenda_item_departures d
            JOIN board_members bm ON d.member_id = bm.id
            WHERE d.id = ?
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

        $stmt = $db->prepare("
            SELECT ai.meeting_id
            FROM agenda_item_departures d
            JOIN agenda_items ai ON d.agenda_item_id = ai.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $departure = $stmt->fetch();
        if (!$departure) {
            http_response_code(404);
            echo json_encode(['error' => 'Departure not found']);
            exit;
        }
        if (minutesAreApprovedForDeparture($db, $departure['meeting_id'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Departures cannot be deleted after minutes are approved']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM agenda_item_departures WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
