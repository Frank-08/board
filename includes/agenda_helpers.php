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

