<?php
/**
 * Resolution validation helpers (UCA Manual for Meetings).
 */

const RESOLUTION_FINAL_STATUSES = ['Consensus', 'Agreement', 'Failed', 'Withdrawn', 'Lapsed'];

/**
 * Validate resolution data before create/update.
 *
 * @return array{valid: bool, error: ?string, warning: ?string}
 */
function validateResolutionData(PDO $db, array $data, ?int $resolutionId = null): array {
    $status = $data['status'] ?? 'Proposed';
    $decisionMethod = $data['decision_method'] ?? 'Consensus';
    $meetingId = (int)($data['meeting_id'] ?? 0);

    if (!$meetingId && $resolutionId) {
        $stmt = $db->prepare("SELECT meeting_id FROM resolutions WHERE id = ?");
        $stmt->execute([$resolutionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $meetingId = $row ? (int)$row['meeting_id'] : 0;
    }

    // Quorum only gates Formal Majority (voted) decisions - a Consensus or
    // Referral decision doesn't require a formal vote count, so quorum
    // doesn't apply to it.
    if ($meetingId && $decisionMethod === 'Formal Majority' && in_array($status, RESOLUTION_FINAL_STATUSES, true)) {
        $stmt = $db->prepare("SELECT quorum_met FROM meetings WHERE id = ?");
        $stmt->execute([$meetingId]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($meeting && !(bool)$meeting['quorum_met'] && empty($data['override_quorum'])) {
            return [
                'valid' => false,
                'error' => 'Quorum has not been met. Final outcomes cannot be recorded unless quorum is met or an admin override is applied.',
                'warning' => null,
            ];
        }
    }

    if ($decisionMethod === 'Formal Majority' && in_array($status, ['Consensus', 'Agreement', 'Failed'], true)) {
        $movedBy = $data['motion_moved_by'] ?? null;
        $secondedBy = $data['motion_seconded_by'] ?? null;
        $votesFor = isset($data['votes_for']) ? (int)$data['votes_for'] : null;
        $votesAgainst = isset($data['votes_against']) ? (int)$data['votes_against'] : null;

        if ($status === 'Lapsed' || $status === 'Withdrawn') {
            if (empty($secondedBy)) {
                return ['valid' => true, 'error' => null, 'warning' => 'Motion lapsed or withdrawn — not recorded in minutes body per Manual 7.2.'];
            }
        } elseif (in_array($status, ['Consensus', 'Agreement', 'Failed'], true)) {
            if (empty($movedBy) || empty($secondedBy)) {
                return [
                    'valid' => false,
                    'error' => 'Formal majority decisions require both mover and seconder.',
                    'warning' => null,
                ];
            }
            if ($votesFor === null && $votesAgainst === null) {
                return [
                    'valid' => false,
                    'error' => 'Formal majority decisions require vote tallies (for and/or against).',
                    'warning' => null,
                ];
            }
        }
    }

    $notes = strtolower($data['clerk_notes'] ?? '');
    $warning = null;
    if (str_contains($notes, 'proxy') || str_contains($notes, 'absentee vote')) {
        $warning = 'Reminder: proxy and absentee voting are not permitted (Manual 4.5).';
    }

    return ['valid' => true, 'error' => null, 'warning' => $warning];
}

/**
 * Format resolution outcome text for minutes export.
 */
function formatResolutionOutcomeText(array $res): string {
    $status = $res['status'] ?? 'Proposed';
    $desc = $res['description'] ?? '';

    switch ($status) {
        case 'Consensus':
            return 'RESOLVED by consensus that ' . $desc;
        case 'Agreement':
            return 'AGREEMENT recorded that ' . $desc;
        case 'Failed':
            return 'The motion was LOST: ' . $desc;
        case 'Withdrawn':
            return 'The motion was withdrawn.';
        case 'Lapsed':
            return 'The motion lapsed (not seconded) and was not recorded.';
        default:
            return $desc;
    }
}

/**
 * Render vote details for export.
 */
function renderResolutionVoteDetails(array $res): string {
    $html = '';
    if (!empty($res['vote_type'])) {
        $html .= '<p style="margin: 3px 0;"><strong>Vote method:</strong> ' . htmlspecialchars($res['vote_type']) . '</p>';
    }
    if ($res['decision_method'] === 'Formal Majority' || !empty($res['votes_for']) || !empty($res['votes_against'])) {
        $parts = [];
        if (isset($res['votes_for'])) {
            $parts[] = 'For: ' . (int)$res['votes_for'];
        }
        if (isset($res['votes_against'])) {
            $parts[] = 'Against: ' . (int)$res['votes_against'];
        }
        if (isset($res['votes_abstain']) && $res['votes_abstain'] !== null) {
            $parts[] = 'Abstain: ' . (int)$res['votes_abstain'];
        }
        if (!empty($parts)) {
            $html .= '<p style="margin: 3px 0;"><strong>Vote:</strong> ' . htmlspecialchars(implode(', ', $parts)) . '</p>';
        }
        if (!empty($res['casting_vote_used'])) {
            $html .= '<p style="margin: 3px 0;"><em>Chair exercised casting vote on a tied decision.</em></p>';
        }
    }
    if (!empty($res['clerk_notes'])) {
        $html .= '<p style="margin: 3px 0;"><strong>Clerk notes:</strong> ' . nl2br(htmlspecialchars($res['clerk_notes'])) . '</p>';
    }
    return $html;
}

/**
 * Ways the resolutions register (resolutions.php / export/resolutions.php)
 * can be categorised. Key => heading label.
 */
const RESOLUTION_REGISTER_GROUPINGS = [
    'category' => 'Category',
    'meeting_type' => 'Meeting type',
    'year' => 'Year',
    'status' => 'Status',
    'decision_method' => 'Decision method',
    'none' => 'No grouping',
];

/** Category filter value meaning "only resolutions with no category". */
const RESOLUTION_UNCATEGORISED_FILTER = '__none__';

/** Heading used for resolutions with no category. */
const RESOLUTION_UNCATEGORISED_LABEL = 'Uncategorised';

/**
 * Normalise a category from request input: trimmed, NULL when blank,
 * capped at the column width.
 */
function normaliseResolutionCategory($category): ?string {
    if ($category === null) {
        return null;
    }
    $category = trim((string)$category);
    if ($category === '') {
        return null;
    }
    return mb_substr($category, 0, 100);
}

/**
 * Read register filters from a request array ($_GET), keeping only known keys.
 *
 * @return array{meeting_type_id: int, status: string, category: string, from: string, to: string, q: string}
 */
function readResolutionRegisterFilters(array $input): array {
    $date = function ($v) {
        $v = trim((string)($v ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    };
    return [
        'meeting_type_id' => (int)($input['meeting_type_id'] ?? 0),
        'status' => trim((string)($input['status'] ?? '')),
        'category' => trim((string)($input['category'] ?? '')),
        'from' => $date($input['from'] ?? ''),
        'to' => $date($input['to'] ?? ''),
        'q' => trim((string)($input['q'] ?? '')),
    ];
}

/**
 * Every resolution across all meetings matching the filters, with its
 * meeting, meeting type and agenda item context, in meeting/agenda order.
 *
 * Filters (all optional): meeting_type_id; status (a status value, or
 * 'adopted' for Consensus + Agreement); category (a category, or
 * RESOLUTION_UNCATEGORISED_FILTER); from / to (Y-m-d, on the meeting date);
 * q (text search over number, title, description).
 */
function fetchResolutionRegister(PDO $db, array $filters): array {
    $where = [];
    $params = [];

    if (!empty($filters['meeting_type_id'])) {
        $where[] = 'm.meeting_type_id = ?';
        $params[] = (int)$filters['meeting_type_id'];
    }
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'adopted') {
            $where[] = "r.status IN ('Consensus', 'Agreement')";
        } else {
            $where[] = 'r.status = ?';
            $params[] = $filters['status'];
        }
    }
    if (($filters['category'] ?? '') !== '') {
        if ($filters['category'] === RESOLUTION_UNCATEGORISED_FILTER) {
            $where[] = "(r.category IS NULL OR r.category = '')";
        } else {
            $where[] = 'r.category = ?';
            $params[] = $filters['category'];
        }
    }
    if (!empty($filters['from'])) {
        $where[] = 'm.scheduled_date >= ?';
        $params[] = $filters['from'] . ' 00:00:00';
    }
    if (!empty($filters['to'])) {
        $where[] = 'm.scheduled_date <= ?';
        $params[] = $filters['to'] . ' 23:59:59';
    }
    if (($filters['q'] ?? '') !== '') {
        $like = '%' . addcslashes($filters['q'], '\\%_') . '%';
        $where[] = '(r.resolution_number LIKE ? OR r.title LIKE ? OR r.description LIKE ?)';
        array_push($params, $like, $like, $like);
    }

    $sql = "
        SELECT r.*,
            m.title AS meeting_title, m.scheduled_date AS meeting_date,
            m.meeting_type_id, mt.name AS meeting_type_name,
            ai.item_number AS agenda_item_number, ai.title AS agenda_item_title
        FROM resolutions r
        JOIN meetings m ON r.meeting_id = m.id
        JOIN meeting_types mt ON m.meeting_type_id = mt.id
        LEFT JOIN agenda_items ai ON r.agenda_item_id = ai.id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY m.scheduled_date ASC, m.id ASC,
            ai.id IS NULL ASC, ai.position ASC, ai.sub_position ASC,
            r.position ASC, r.id ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Categories currently in use, alphabetical, for suggestions/filters.
 *
 * @return string[]
 */
function fetchResolutionCategories(PDO $db): array {
    $stmt = $db->query("
        SELECT DISTINCT category FROM resolutions
        WHERE category IS NOT NULL AND category <> ''
        ORDER BY category ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Group register rows under headings for the chosen categorisation.
 * Category groups are alphabetical with Uncategorised last; year groups are
 * chronological; other groups keep first-seen order. Rows keep their order.
 *
 * @return array<int, array{label: string, resolutions: array}>
 */
function groupResolutionRegister(array $rows, string $groupBy): array {
    if ($groupBy === 'none' || !isset(RESOLUTION_REGISTER_GROUPINGS[$groupBy])) {
        return $rows ? [['label' => 'All resolutions', 'resolutions' => $rows]] : [];
    }

    $groups = [];
    foreach ($rows as $row) {
        switch ($groupBy) {
            case 'category':
                $label = trim((string)($row['category'] ?? '')) ?: RESOLUTION_UNCATEGORISED_LABEL;
                break;
            case 'meeting_type':
                $label = $row['meeting_type_name'] ?? '';
                break;
            case 'year':
                $label = substr((string)$row['meeting_date'], 0, 4);
                break;
            default:
                $label = (string)($row[$groupBy] ?? '');
        }
        // Case-insensitive, like the column's collation (so "finance" and
        // "Finance" are one group); the first spelling seen is the heading.
        $key = mb_strtolower((string)$label);
        if (!isset($groups[$key])) {
            $groups[$key] = ['label' => (string)$label, 'resolutions' => []];
        }
        $groups[$key]['resolutions'][] = $row;
    }
    $groups = array_values($groups);

    if ($groupBy === 'category') {
        usort($groups, function ($a, $b) {
            if ($a['label'] === RESOLUTION_UNCATEGORISED_LABEL) return 1;
            if ($b['label'] === RESOLUTION_UNCATEGORISED_LABEL) return -1;
            return strcasecmp($a['label'], $b['label']);
        });
    } elseif ($groupBy === 'year') {
        usort($groups, fn($a, $b) => strcmp($a['label'], $b['label']));
    }

    return $groups;
}
