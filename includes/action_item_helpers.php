<?php
/**
 * Action items arising from meetings: shared by api/action_items.php,
 * api/claude.php (suggestions) and export/minutes.php.
 */

const ACTION_ITEM_STATUSES = ['Open', 'In Progress', 'Done', 'Cancelled'];

/**
 * Action items matching the filters, with meeting / owner / linked agenda
 * item and resolution context. Open work first, then by due date (no due
 * date last), then meeting order.
 *
 * Filters (all optional): meeting_id; status (a status, or 'open' for
 * Open + In Progress); meeting_type_id; assigned_member_id; overdue (bool).
 */
function fetchActionItems(PDO $db, array $filters = []): array {
    $where = [];
    $params = [];

    if (!empty($filters['meeting_id'])) {
        $where[] = 'a.meeting_id = ?';
        $params[] = (int)$filters['meeting_id'];
    }
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'open') {
            $where[] = "a.status IN ('Open', 'In Progress')";
        } elseif (in_array($filters['status'], ACTION_ITEM_STATUSES, true)) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
    }
    if (!empty($filters['meeting_type_id'])) {
        $where[] = 'm.meeting_type_id = ?';
        $params[] = (int)$filters['meeting_type_id'];
    }
    if (!empty($filters['assigned_member_id'])) {
        $where[] = 'a.assigned_member_id = ?';
        $params[] = (int)$filters['assigned_member_id'];
    }
    if (!empty($filters['overdue'])) {
        $where[] = "a.status IN ('Open', 'In Progress') AND a.due_date < CURDATE()";
    }
    if (!empty($filters['id'])) {
        $where[] = 'a.id = ?';
        $params[] = (int)$filters['id'];
    }

    $stmt = $db->prepare("
        SELECT a.*,
            m.title AS meeting_title, m.scheduled_date AS meeting_date,
            m.meeting_type_id, mt.name AS meeting_type_name,
            bm.first_name AS assigned_first_name, bm.last_name AS assigned_last_name,
            ai.item_number AS agenda_item_number, ai.title AS agenda_item_title,
            r.resolution_number, r.title AS resolution_title,
            u.username AS created_by_username,
            (a.status IN ('Open', 'In Progress') AND a.due_date IS NOT NULL AND a.due_date < CURDATE()) AS is_overdue
        FROM action_items a
        JOIN meetings m ON a.meeting_id = m.id
        JOIN meeting_types mt ON m.meeting_type_id = mt.id
        LEFT JOIN board_members bm ON a.assigned_member_id = bm.id
        LEFT JOIN agenda_items ai ON a.agenda_item_id = ai.id
        LEFT JOIN resolutions r ON a.resolution_id = r.id
        LEFT JOIN users u ON a.created_by_user_id = u.id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY a.status IN ('Done', 'Cancelled') ASC,
            a.due_date IS NULL ASC, a.due_date ASC,
            m.scheduled_date ASC, ai.position ASC, a.id ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['is_overdue'] = (bool)$row['is_overdue'];
        $row['assigned_display'] = actionItemOwnerName($row);
    }
    return $rows;
}

/** "First Last", else the free-text owner, else '' (unassigned). */
function actionItemOwnerName(array $item): string {
    $member = trim(($item['assigned_first_name'] ?? '') . ' ' . ($item['assigned_last_name'] ?? ''));
    if ($member !== '') {
        return $member;
    }
    return trim((string)($item['assigned_to_name'] ?? ''));
}

/**
 * Validate and normalise action item fields from request input against the
 * meeting it belongs to. Only keys present in $data are returned, so the
 * result can drive both INSERT and partial UPDATE.
 *
 * @return array{fields: array, error: ?string}
 */
function normaliseActionItemFields(PDO $db, int $meetingId, array $data): array {
    $fields = [];

    if (array_key_exists('description', $data)) {
        $description = trim((string)$data['description']);
        if ($description === '') {
            return ['fields' => [], 'error' => 'description is required'];
        }
        $fields['description'] = $description;
    }

    if (array_key_exists('assigned_member_id', $data)) {
        $memberId = (int)($data['assigned_member_id'] ?? 0) ?: null;
        if ($memberId) {
            $stmt = $db->prepare('SELECT id FROM board_members WHERE id = ?');
            $stmt->execute([$memberId]);
            if (!$stmt->fetch()) {
                return ['fields' => [], 'error' => 'assigned_member_id is not a member'];
            }
        }
        $fields['assigned_member_id'] = $memberId;
    }

    if (array_key_exists('assigned_to_name', $data)) {
        $name = trim((string)($data['assigned_to_name'] ?? ''));
        $fields['assigned_to_name'] = $name === '' ? null : mb_substr($name, 0, 150);
    }

    if (array_key_exists('due_date', $data)) {
        $due = trim((string)($data['due_date'] ?? ''));
        if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            return ['fields' => [], 'error' => 'due_date must be YYYY-MM-DD'];
        }
        $fields['due_date'] = $due === '' ? null : $due;
    }

    if (array_key_exists('status', $data)) {
        if (!in_array($data['status'], ACTION_ITEM_STATUSES, true)) {
            return ['fields' => [], 'error' => 'status must be one of ' . implode(', ', ACTION_ITEM_STATUSES)];
        }
        $fields['status'] = $data['status'];
    }

    if (array_key_exists('notes', $data)) {
        $notes = trim((string)($data['notes'] ?? ''));
        $fields['notes'] = $notes === '' ? null : $notes;
    }

    // Linked agenda item / resolution must belong to the same meeting
    foreach (['agenda_item_id' => 'agenda_items', 'resolution_id' => 'resolutions'] as $key => $table) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $id = (int)($data[$key] ?? 0) ?: null;
        if ($id) {
            $stmt = $db->prepare("SELECT meeting_id FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['meeting_id'] !== $meetingId) {
                return ['fields' => [], 'error' => "$key does not belong to this meeting"];
            }
        }
        $fields[$key] = $id;
    }

    return ['fields' => $fields, 'error' => null];
}

/**
 * Insert one action item; $fields from normaliseActionItemFields().
 */
function insertActionItem(PDO $db, int $meetingId, array $fields, ?int $userId): int {
    $fields['meeting_id'] = $meetingId;
    $fields['created_by_user_id'] = $userId;
    if (($fields['status'] ?? 'Open') === 'Done') {
        $fields['completed_at'] = date('Y-m-d H:i:s');
    }
    $columns = array_keys($fields);
    $stmt = $db->prepare(
        'INSERT INTO action_items (' . implode(', ', $columns) . ') VALUES (' .
        implode(', ', array_fill(0, count($columns), '?')) . ')'
    );
    $stmt->execute(array_values($fields));
    return (int)$db->lastInsertId();
}
