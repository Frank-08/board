<?php
/**
 * Shared helpers for agenda item queries and resolution attachment.
 */

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
        SELECT id, agenda_item_id, resolution_number, title, description,
            status, decision_method, vote_type, effective_date, created_at
        FROM resolutions
        WHERE meeting_id = ? AND agenda_item_id IS NOT NULL
        ORDER BY created_at ASC
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
 * Render linked resolution boxes for agenda export templates.
 *
 * @param array $item Agenda item with resolutions array attached
 * @return string
 */
function renderExportResolutionBoxes(array $item): string {
    $resolutions = $item['resolutions'] ?? [];
    if (empty($resolutions)) {
        return '';
    }

    $html = '';
    foreach ($resolutions as $res) {
        $html .= '<div style="background: #e8f5e9; padding: 8px; border-radius: 4px; margin: 6px 0; border-left: 3px solid #28a745;">';
        $html .= '<p style="margin: 0 0 3px 0;"><strong>Linked Resolution:</strong> '
            . htmlspecialchars($res['title'] ?? 'Resolution') . '</p>';
        if (!empty($res['resolution_number'])) {
            $html .= '<p style="margin: 3px 0;"><strong>Resolution #:</strong> '
                . htmlspecialchars($res['resolution_number']) . '</p>';
        }
        if (!empty($res['description'])) {
            $html .= '<p style="margin: 3px 0;">' . nl2br(htmlspecialchars($res['description'])) . '</p>';
        }
        if (!empty($res['status'])) {
            $html .= '<p style="margin: 3px 0;"><strong>Resolution Status:</strong> '
                . htmlspecialchars($res['status']) . '</p>';
        }
        if (!empty($res['decision_method'])) {
            $html .= '<p style="margin: 3px 0;"><strong>Decision Method:</strong> '
                . htmlspecialchars($res['decision_method']) . '</p>';
        }
        if (!empty($res['vote_type'])) {
            $html .= '<p style="margin: 3px 0;"><strong>Vote Type:</strong> '
                . htmlspecialchars($res['vote_type']) . '</p>';
        }
        $html .= '</div>';
    }

    return $html;
}
