<?php
/**
 * Shared helpers for agenda item queries and resolution attachment.
 */

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

/**
 * Format a top-level agenda item number: SHORTCODE.YY.MM.SEQ (or YY.MM.SEQ if no shortcode).
 *
 * @param string $shortcode Meeting type shortcode (may be empty)
 * @param string $year 2-digit year
 * @param string $month 2-digit month
 * @param int $sequence 1-based sequence
 * @return string
 */
function formatAgendaItemNumber($shortcode, $year, $month, $sequence) {
    if (!empty($shortcode)) {
        return sprintf('%s.%s.%s.%02d', $shortcode, $year, $month, $sequence);
    }
    return sprintf('%s.%s.%02d', $year, $month, $sequence);
}

/**
 * Rebuild position, sub_position and item_number for every agenda item in a meeting
 * from the current ordering, so numbers are contiguous and sub-items carry their
 * parent's number plus a letter suffix.
 *
 * Top-level items keep their relative order (position, then id); sub-items keep
 * their order within their parent (sub_position, then id). Sub-items whose parent
 * is missing are promoted to top-level.
 *
 * @param PDO $db
 * @param int $meetingId
 */
function renumberAgenda(PDO $db, int $meetingId): void {
    $stmt = $db->prepare("
        SELECT m.scheduled_date, mt.shortcode
        FROM meetings m
        JOIN meeting_types mt ON m.meeting_type_id = mt.id
        WHERE m.id = ?
    ");
    $stmt->execute([$meetingId]);
    $meeting = $stmt->fetch();
    if (!$meeting) {
        return;
    }

    $meetingDate = new DateTime($meeting['scheduled_date']);
    $year = $meetingDate->format('y');
    $month = $meetingDate->format('m');
    $shortcode = $meeting['shortcode'] ?? '';

    $stmt = $db->prepare("
        SELECT id, parent_id, position, sub_position
        FROM agenda_items
        WHERE meeting_id = ?
        ORDER BY position ASC, CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END ASC, sub_position ASC, id ASC
    ");
    $stmt->execute([$meetingId]);
    $rows = $stmt->fetchAll();

    $ids = [];
    foreach ($rows as $row) {
        $ids[(int)$row['id']] = true;
    }

    $topLevel = [];
    $children = [];
    foreach ($rows as $row) {
        $parentId = $row['parent_id'] ? (int)$row['parent_id'] : null;
        if ($parentId && isset($ids[$parentId])) {
            $children[$parentId][] = (int)$row['id'];
        } else {
            $topLevel[] = (int)$row['id'];
        }
    }

    $updateTopStmt = $db->prepare("UPDATE agenda_items SET position = ?, sub_position = 0, item_number = ?, parent_id = NULL WHERE id = ?");
    $updateChildStmt = $db->prepare("UPDATE agenda_items SET position = ?, sub_position = ?, item_number = ? WHERE id = ?");

    foreach ($topLevel as $position => $itemId) {
        $itemNumber = formatAgendaItemNumber($shortcode, $year, $month, $position + 1);
        $updateTopStmt->execute([$position, $itemNumber, $itemId]);

        foreach ($children[$itemId] ?? [] as $subPos => $childId) {
            $updateChildStmt->execute([$position, $subPos, $itemNumber . numberToLetterSuffix($subPos), $childId]);
        }
    }
}

/**
 * Attach linked resolutions to agenda items (one row per item, no JOIN duplication).
 *
 * @param PDO $db
 * @param int $meetingId
 * @param array $items
 * @return array
 */
function attachResolutionsToAgendaItems(PDO $db, int $meetingId, array $items): array {
    if (empty($items)) {
        return $items;
    }

    $stmt = $db->prepare("
        SELECT r.id, r.agenda_item_id, r.resolution_number, r.title, r.description,
            r.status, r.decision_method, r.vote_type, r.effective_date, r.created_at, r.position,
            r.motion_moved_by, r.motion_seconded_by, r.votes_for, r.votes_against, r.votes_abstain,
            r.casting_vote_used, r.referral_body, r.referral_scope, r.clerk_notes,
            mover.first_name AS mover_first_name, mover.last_name AS mover_last_name,
            seconder.first_name AS seconder_first_name, seconder.last_name AS seconder_last_name
        FROM resolutions r
        LEFT JOIN board_members mover ON r.motion_moved_by = mover.id
        LEFT JOIN board_members seconder ON r.motion_seconded_by = seconder.id
        WHERE r.meeting_id = ? AND r.agenda_item_id IS NOT NULL
        ORDER BY r.position ASC, r.created_at ASC
    ");
    $stmt->execute([$meetingId]);
    $byAgendaItemId = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $agendaItemId = (int)$row['agenda_item_id'];
        if (!isset($byAgendaItemId[$agendaItemId])) {
            $byAgendaItemId[$agendaItemId] = [];
        }
        $byAgendaItemId[$agendaItemId][] = $row;
    }

    foreach ($items as &$item) {
        $itemId = (int)$item['id'];
        $item['resolutions'] = $byAgendaItemId[$itemId] ?? [];
        applyFirstResolutionFlatFields($item);
    }
    unset($item);

    return $items;
}

/**
 * Copy the first linked resolution into legacy flat fields for backward compatibility.
 *
 * @param array $item
 * @return void
 */
function applyFirstResolutionFlatFields(array &$item): void {
    $item['resolution_id'] = null;
    $item['resolution_title'] = null;
    $item['resolution_description'] = null;
    $item['resolution_number'] = null;
    $item['resolution_status'] = null;
    $item['resolution_vote_type'] = null;
    $item['resolution_effective_date'] = null;

    if (empty($item['resolutions'])) {
        return;
    }

    $first = $item['resolutions'][0];
    $item['resolution_id'] = $first['id'];
    $item['resolution_title'] = $first['title'];
    $item['resolution_description'] = $first['description'];
    $item['resolution_number'] = $first['resolution_number'];
    $item['resolution_status'] = $first['status'];
    $item['resolution_vote_type'] = $first['vote_type'];
    $item['resolution_effective_date'] = $first['effective_date'];
}

/**
 * Attach the list of presenters to each agenda item (a joint presentation
 * has more than one), ordered by agenda_item_presenters.position.
 *
 * @param PDO $db
 * @param int $meetingId
 * @param array $items
 * @return array
 */
function attachPresentersToAgendaItems(PDO $db, int $meetingId, array $items): array {
    if (empty($items)) {
        return $items;
    }

    $stmt = $db->prepare("
        SELECT aip.agenda_item_id, bm.id AS member_id, bm.first_name, bm.last_name, bm.title
        FROM agenda_item_presenters aip
        JOIN agenda_items ai ON aip.agenda_item_id = ai.id
        JOIN board_members bm ON aip.member_id = bm.id
        WHERE ai.meeting_id = ?
        ORDER BY aip.agenda_item_id ASC, aip.position ASC
    ");
    $stmt->execute([$meetingId]);
    $byAgendaItemId = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $itemId = (int)$row['agenda_item_id'];
        $byAgendaItemId[$itemId][] = [
            'id' => (int)$row['member_id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'title' => $row['title'],
        ];
    }

    foreach ($items as &$item) {
        $item['presenters'] = $byAgendaItemId[(int)$item['id']] ?? [];
    }
    unset($item);

    return $items;
}

/**
 * Replace the full set of presenters for one agenda item (delete then
 * re-insert, in the given order) - simplest correct semantics for a
 * "these are now the presenters" write from the UI, without needing to
 * diff old vs new.
 *
 * @param PDO $db
 * @param int $agendaItemId
 * @param array $memberIds
 * @return void
 */
function syncAgendaItemPresenters(PDO $db, int $agendaItemId, array $memberIds): void {
    $deleteStmt = $db->prepare("DELETE FROM agenda_item_presenters WHERE agenda_item_id = ?");
    $deleteStmt->execute([$agendaItemId]);

    if (empty($memberIds)) {
        return;
    }

    $insertStmt = $db->prepare("INSERT INTO agenda_item_presenters (agenda_item_id, member_id, position) VALUES (?, ?, ?)");
    $position = 0;
    $seen = [];
    foreach ($memberIds as $memberId) {
        $memberId = (int)$memberId;
        if (!$memberId || isset($seen[$memberId])) {
            continue;
        }
        $seen[$memberId] = true;
        $insertStmt->execute([$agendaItemId, $memberId, $position]);
        $position++;
    }
}

/**
 * Attach the list of members who left the room during each agenda item
 * (recorded while taking minutes - see agenda_item_departures).
 *
 * @param PDO $db
 * @param int $meetingId
 * @param array $items
 * @return array
 */
function attachDeparturesToAgendaItems(PDO $db, int $meetingId, array $items): array {
    if (empty($items)) {
        return $items;
    }

    $stmt = $db->prepare("
        SELECT d.id, d.agenda_item_id, d.reason, d.returned,
            bm.id AS member_id, bm.first_name, bm.last_name
        FROM agenda_item_departures d
        JOIN agenda_items ai ON d.agenda_item_id = ai.id
        JOIN board_members bm ON d.member_id = bm.id
        WHERE ai.meeting_id = ?
        ORDER BY d.agenda_item_id ASC, d.created_at ASC
    ");
    $stmt->execute([$meetingId]);
    $byAgendaItemId = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $itemId = (int)$row['agenda_item_id'];
        $byAgendaItemId[$itemId][] = $row;
    }

    foreach ($items as &$item) {
        $item['departures'] = $byAgendaItemId[(int)$item['id']] ?? [];
    }
    unset($item);

    return $items;
}

