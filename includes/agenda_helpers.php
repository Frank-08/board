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

