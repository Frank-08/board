<?php
/**
 * Action Items API Endpoint
 *
 * GET    ?id=X                      one item
 * GET    ?meeting_id=X              a meeting's items
 * GET    (filters)                  all items: status ('open' = Open + In
 *                                   Progress, or a status), meeting_type_id,
 *                                   assigned_member_id, mine=1, overdue=1
 * POST   {meeting_id, description, ...}            create one
 * POST   {meeting_id, items: [{description, ...}]} create several at once
 * PUT    {id, ...fields}            update. With update_own_action_items
 *                                   (not manage_action_items) only status and
 *                                   notes, and only on items assigned to the
 *                                   user's linked member.
 * DELETE {id}
 *
 * Action items aren't locked by approved minutes: following them up after
 * the meeting is the point.
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/action_item_helpers.php';

requireAuth();

function sendError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();
$currentUser = getCurrentUser();
$data = in_array($method, ['POST', 'PUT', 'DELETE'], true) ? json_decode(file_get_contents('php://input'), true) : null;
if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && !is_array($data)) {
    sendError(400, 'A JSON body is required');
}

try {
    switch ($method) {
    case 'GET':
        requirePermission('view_meetings');
        if (isset($_GET['id'])) {
            $rows = fetchActionItems($db, ['id' => (int)$_GET['id']]);
            if (!$rows) {
                sendError(404, 'Action item not found');
            }
            echo json_encode($rows[0]);
            break;
        }
        $filters = [
            'meeting_id' => (int)($_GET['meeting_id'] ?? 0),
            'status' => trim((string)($_GET['status'] ?? '')),
            'meeting_type_id' => (int)($_GET['meeting_type_id'] ?? 0),
            'assigned_member_id' => (int)($_GET['assigned_member_id'] ?? 0),
            'overdue' => !empty($_GET['overdue']),
        ];
        if (!empty($_GET['mine'])) {
            // Items assigned to the member this login is linked to
            $filters['assigned_member_id'] = (int)($currentUser['board_member_id'] ?? 0) ?: -1;
        }
        echo json_encode(fetchActionItems($db, $filters));
        break;

    case 'POST':
        requirePermission('manage_action_items');
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $stmt = $db->prepare('SELECT id FROM meetings WHERE id = ?');
        $stmt->execute([$meetingId]);
        if (!$meetingId || !$stmt->fetch()) {
            sendError(400, 'A valid meeting_id is required');
        }

        $inputs = isset($data['items']) && is_array($data['items']) ? $data['items'] : [$data];
        if (!$inputs) {
            sendError(400, 'items must not be empty');
        }
        $userId = (int)($currentUser['id'] ?? 0) ?: null;

        // Validate everything first so a bulk create is all-or-nothing
        $toInsert = [];
        foreach ($inputs as $i => $input) {
            if (!is_array($input)) {
                sendError(400, "Item $i is not an object");
            }
            $input += ['description' => ''];
            $result = normaliseActionItemFields($db, $meetingId, $input);
            if ($result['error']) {
                sendError(400, (count($inputs) > 1 ? "Item $i: " : '') . $result['error']);
            }
            $toInsert[] = $result['fields'];
        }

        $db->beginTransaction();
        $ids = [];
        foreach ($toInsert as $fields) {
            $ids[] = insertActionItem($db, $meetingId, $fields, $userId);
        }
        $db->commit();

        $created = array_map(fn($id) => fetchActionItems($db, ['id' => $id])[0], $ids);
        echo json_encode(isset($data['items']) ? $created : $created[0]);
        break;

    case 'PUT':
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM action_items WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            sendError(404, 'Action item not found');
        }

        if (!hasPermission('manage_action_items')) {
            $ownMemberId = (int)($currentUser['board_member_id'] ?? 0);
            $isOwn = $ownMemberId && (int)$existing['assigned_member_id'] === $ownMemberId;
            if (!$isOwn || !hasPermission('update_own_action_items')) {
                sendError(403, 'You can only update action items assigned to you');
            }
            $data = array_intersect_key($data, array_flip(['status', 'notes']));
        }

        $result = normaliseActionItemFields($db, (int)$existing['meeting_id'], $data);
        if ($result['error']) {
            sendError(400, $result['error']);
        }
        $fields = $result['fields'];
        if (!$fields) {
            sendError(400, 'No fields to update');
        }
        if (isset($fields['status']) && $fields['status'] !== $existing['status']) {
            $fields['completed_at'] = $fields['status'] === 'Done' ? date('Y-m-d H:i:s') : null;
        }

        $sets = implode(', ', array_map(fn($col) => "$col = ?", array_keys($fields)));
        $stmt = $db->prepare("UPDATE action_items SET $sets WHERE id = ?");
        $stmt->execute([...array_values($fields), $id]);
        echo json_encode(fetchActionItems($db, ['id' => $id])[0]);
        break;

    case 'DELETE':
        requirePermission('delete_action_items');
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM action_items WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            sendError(404, 'Action item not found');
        }
        echo json_encode(['success' => true]);
        break;

    default:
        sendError(405, 'Method not allowed');
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendError(500, publicErrorMessage($e, 'Server error'));
}
