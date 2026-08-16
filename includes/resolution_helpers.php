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

    if ($meetingId && in_array($status, RESOLUTION_FINAL_STATUSES, true)) {
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
