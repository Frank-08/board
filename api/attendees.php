<?php
/**
 * Meeting Attendees API Endpoint
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
require_once __DIR__ . '/../includes/quorum_helpers.php';

// Require authentication for all requests
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

// Check permissions for write operations
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    requirePermission('manage_attendees');
}

const ATTENDEE_SELECT = "
    SELECT ma.*, bm.first_name, bm.last_name, bm.email, bm.phone, bm.title,
        mtm.role, mtm.status as membership_status,
        CASE WHEN ma.member_id IS NOT NULL THEN CONCAT(bm.first_name, ' ', bm.last_name) ELSE ma.attendee_name END AS display_name,
        (ma.member_id IS NULL) AS is_general
    FROM meeting_attendees ma
    LEFT JOIN board_members bm ON ma.member_id = bm.id
    JOIN meetings m ON ma.meeting_id = m.id
    LEFT JOIN meeting_type_members mtm ON bm.id = mtm.member_id AND m.meeting_type_id = mtm.meeting_type_id
";

const MAX_BULK_ATTENDEES = 150;

function meetingAllowsGeneralAttendees(PDO $db, int $meetingId): bool {
    $stmt = $db->prepare("
        SELECT mt.general_attendance_enabled
        FROM meetings m JOIN meeting_types mt ON m.meeting_type_id = mt.id
        WHERE m.id = ?
    ");
    $stmt->execute([$meetingId]);
    return (bool)$stmt->fetchColumn();
}

function normalizeAttendeeName(string $name): string {
    return trim(preg_replace('/\s+/', ' ', $name));
}

switch ($method) {
    case 'GET':
        if (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare(ATTENDEE_SELECT . "
                WHERE ma.meeting_id = ?
                ORDER BY
                    (ma.member_id IS NULL) ASC,
                    FIELD(mtm.role, 'Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Ex-officio', 'Member'),
                    COALESCE(bm.last_name, ma.attendee_name) ASC
            ");
            $stmt->execute([$meetingId]);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id is required']);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        // Bulk import of general attendees (e.g. pasted from a sign-in sheet).
        if (($data['action'] ?? '') === 'bulk_add') {
            $meetingId = (int)($data['meeting_id'] ?? 0);
            $names = $data['names'] ?? null;

            if (!$meetingId || !is_array($names) || count($names) === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'meeting_id and a non-empty names array are required']);
                exit;
            }
            if (count($names) > MAX_BULK_ATTENDEES) {
                http_response_code(400);
                echo json_encode(['error' => 'Too many names in one batch (max ' . MAX_BULK_ATTENDEES . ')']);
                exit;
            }
            if (!meetingAllowsGeneralAttendees($db, $meetingId)) {
                http_response_code(403);
                echo json_encode(['error' => 'This meeting type does not allow general attendees']);
                exit;
            }

            $status = $data['default_attendance_status'] ?? 'Present';
            $validStatuses = ['Present', 'Absent', 'Apology', 'Excused', 'Late'];
            if (!in_array($status, $validStatuses, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid default_attendance_status']);
                exit;
            }

            $stmt = $db->prepare("SELECT attendee_name FROM meeting_attendees WHERE meeting_id = ? AND attendee_name IS NOT NULL");
            $stmt->execute([$meetingId]);
            $existing = array_map('mb_strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $seen = [];
            $created = [];
            $skippedDuplicates = [];
            $skippedInvalid = [];

            $db->beginTransaction();
            try {
                $insert = $db->prepare("INSERT INTO meeting_attendees (meeting_id, attendee_name, attendance_status) VALUES (?, ?, ?)");
                foreach ($names as $rawName) {
                    if (!is_string($rawName)) {
                        $skippedInvalid[] = $rawName;
                        continue;
                    }
                    $name = normalizeAttendeeName($rawName);
                    if ($name === '' || mb_strlen($name) > 200) {
                        $skippedInvalid[] = $rawName;
                        continue;
                    }
                    $key = mb_strtolower($name);
                    if (in_array($key, $existing, true) || isset($seen[$key])) {
                        $skippedDuplicates[] = $name;
                        continue;
                    }
                    $seen[$key] = true;
                    $insert->execute([$meetingId, $name, $status]);
                    $created[] = [
                        'id' => (int)$db->lastInsertId(),
                        'attendee_name' => $name,
                        'display_name' => $name,
                        'is_general' => true,
                        'attendance_status' => $status,
                    ];
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Bulk import failed: ' . $e->getMessage()]);
                exit;
            }

            $quorum = recalculateQuorum($db, $meetingId);
            echo json_encode([
                'created' => $created,
                'skipped_duplicates' => $skippedDuplicates,
                'skipped_invalid' => $skippedInvalid,
                'quorum' => $quorum,
            ]);
            break;
        }

        $meetingId = (int)($data['meeting_id'] ?? 0);
        $memberId = !empty($data['member_id']) ? (int)$data['member_id'] : null;
        $attendeeName = isset($data['attendee_name']) ? normalizeAttendeeName($data['attendee_name']) : '';

        if (!$meetingId || (!$memberId && $attendeeName === '')) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id and either member_id or attendee_name are required']);
            exit;
        }
        if ($memberId && $attendeeName !== '') {
            http_response_code(400);
            echo json_encode(['error' => 'Provide member_id or attendee_name, not both']);
            exit;
        }

        if ($attendeeName !== '') {
            if (mb_strlen($attendeeName) > 200) {
                http_response_code(400);
                echo json_encode(['error' => 'Attendee name is too long']);
                exit;
            }
            if (!meetingAllowsGeneralAttendees($db, $meetingId)) {
                http_response_code(403);
                echo json_encode(['error' => 'This meeting type does not allow general attendees']);
                exit;
            }
            $stmt = $db->prepare("SELECT id FROM meeting_attendees WHERE meeting_id = ? AND LOWER(attendee_name) = LOWER(?)");
            $stmt->execute([$meetingId, $attendeeName]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Attendee already exists for this meeting']);
                exit;
            }
        } else {
            // Check if already exists
            $stmt = $db->prepare("SELECT id FROM meeting_attendees WHERE meeting_id = ? AND member_id = ?");
            $stmt->execute([$meetingId, $memberId]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Attendee already exists for this meeting']);
                exit;
            }
        }

        $stmt = $db->prepare("INSERT INTO meeting_attendees (meeting_id, member_id, attendee_name, attendance_status, arrival_time, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingId,
            $memberId,
            $attendeeName !== '' ? $attendeeName : null,
            $data['attendance_status'] ?? 'Absent',
            $data['arrival_time'] ?? null,
            $data['notes'] ?? null
        ]);

        $attendeeId = $db->lastInsertId();
        recalculateQuorum($db, $meetingId);

        $stmt = $db->prepare(ATTENDEE_SELECT . " WHERE ma.id = ?");
        $stmt->execute([$attendeeId]);
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

        $stmt = $db->prepare("SELECT meeting_id, member_id, attendee_name FROM meeting_attendees WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        if (!$current) {
            http_response_code(404);
            echo json_encode(['error' => 'Attendee not found']);
            exit;
        }

        $updates = [];
        $params = [];

        // Handle member_id / attendee_name together - exactly one may be set,
        // switching between them clears the other (lets a clerk reclassify a
        // mis-entered attendee between "formal member" and "general").
        if (isset($data['member_id']) && !empty($data['member_id'])) {
            $newMemberId = (int)$data['member_id'];
            if ((int)$current['member_id'] !== $newMemberId) {
                $stmt = $db->prepare("SELECT id FROM meeting_attendees WHERE meeting_id = ? AND member_id = ? AND id != ?");
                $stmt->execute([$current['meeting_id'], $newMemberId, $id]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['error' => 'This member is already an attendee for this meeting']);
                    exit;
                }
            }
            $updates[] = "member_id = ?";
            $params[] = $newMemberId;
            $updates[] = "attendee_name = NULL";
        } elseif (isset($data['attendee_name']) && normalizeAttendeeName($data['attendee_name']) !== '') {
            $newName = normalizeAttendeeName($data['attendee_name']);
            if (mb_strlen($newName) > 200) {
                http_response_code(400);
                echo json_encode(['error' => 'Attendee name is too long']);
                exit;
            }
            if (!meetingAllowsGeneralAttendees($db, (int)$current['meeting_id'])) {
                http_response_code(403);
                echo json_encode(['error' => 'This meeting type does not allow general attendees']);
                exit;
            }
            $stmt = $db->prepare("SELECT id FROM meeting_attendees WHERE meeting_id = ? AND LOWER(attendee_name) = LOWER(?) AND id != ?");
            $stmt->execute([$current['meeting_id'], $newName, $id]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Attendee already exists for this meeting']);
                exit;
            }
            $updates[] = "attendee_name = ?";
            $params[] = $newName;
            $updates[] = "member_id = NULL";
        }

        $fields = ['attendance_status', 'arrival_time', 'notes'];
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
        $sql = "UPDATE meeting_attendees SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        recalculateQuorum($db, (int)$current['meeting_id']);

        $stmt = $db->prepare(ATTENDEE_SELECT . " WHERE ma.id = ?");
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

        $stmt = $db->prepare("SELECT meeting_id FROM meeting_attendees WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch();

        $stmt = $db->prepare("DELETE FROM meeting_attendees WHERE id = ?");
        $stmt->execute([$id]);

        if ($current) {
            recalculateQuorum($db, (int)$current['meeting_id']);
        }
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
