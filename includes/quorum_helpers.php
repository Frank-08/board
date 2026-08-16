<?php
/**
 * Quorum calculation helpers (UCA Manual for Meetings 5.1, 5.3).
 */

/**
 * Count attendees who count toward quorum (Present or Late).
 */
function countQuorumPresent(PDO $db, int $meetingId): int {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM meeting_attendees
        WHERE meeting_id = ? AND attendance_status IN ('Present', 'Late')
    ");
    $stmt->execute([$meetingId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Count active members for a meeting's meeting type.
 */
function countActiveMembers(PDO $db, int $meetingId): int {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM meeting_type_members mtm
        JOIN meetings m ON m.meeting_type_id = mtm.meeting_type_id
        WHERE m.id = ? AND mtm.status = 'Active'
    ");
    $stmt->execute([$meetingId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Determine required quorum for a meeting.
 * Uses quorum_required when set; otherwise defaults to ceil(50% of active members).
 */
function getQuorumRequired(PDO $db, array $meeting): int {
    $required = (int)($meeting['quorum_required'] ?? 0);
    if ($required > 0) {
        return $required;
    }
    $activeMembers = countActiveMembers($db, (int)$meeting['id']);
    if ($activeMembers <= 0) {
        return 0;
    }
    return (int)ceil($activeMembers * 0.5);
}

/**
 * Recalculate and persist quorum_met for a meeting.
 *
 * @return array{present: int, required: int, met: bool, active_members: int}
 */
function recalculateQuorum(PDO $db, int $meetingId): array {
    $stmt = $db->prepare("SELECT id, quorum_required FROM meetings WHERE id = ?");
    $stmt->execute([$meetingId]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$meeting) {
        return ['present' => 0, 'required' => 0, 'met' => false, 'active_members' => 0];
    }

    $present = countQuorumPresent($db, $meetingId);
    $activeMembers = countActiveMembers($db, $meetingId);
    $required = getQuorumRequired($db, $meeting);
    $met = $required === 0 ? true : ($present >= $required);

    $update = $db->prepare("UPDATE meetings SET quorum_met = ? WHERE id = ?");
    $update->execute([$met ? 1 : 0, $meetingId]);

    return [
        'present' => $present,
        'required' => $required,
        'met' => $met,
        'active_members' => $activeMembers,
    ];
}
