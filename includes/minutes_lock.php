<?php
/**
 * Approved-minutes integrity lock.
 *
 * Once a meeting's minutes are Approved, the record of that meeting is the
 * official minute and must not drift. Resolutions already enforce this (see
 * api/resolutions.php); this shared helper lets the agenda and attendance
 * endpoints enforce the same rule without duplicating the query.
 *
 * The register category on a resolution is the documented exception and is
 * handled in api/resolutions.php (set_category), not here.
 */

if (!function_exists('meetingMinutesApproved')) {
    /**
     * True if the given meeting has Approved minutes.
     */
    function meetingMinutesApproved(PDO $db, int $meetingId): bool {
        if ($meetingId <= 0) {
            return false;
        }
        $stmt = $db->prepare("SELECT 1 FROM minutes WHERE meeting_id = ? AND status = 'Approved' LIMIT 1");
        $stmt->execute([$meetingId]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('rejectIfMinutesApproved')) {
    /**
     * Send 409 and exit when the meeting's minutes are approved. $what names
     * the record for the error message, e.g. "The agenda" or "Attendance".
     */
    function rejectIfMinutesApproved(PDO $db, int $meetingId, string $what): void {
        if (meetingMinutesApproved($db, $meetingId)) {
            http_response_code(409);
            echo json_encode([
                'error' => $what . ' cannot be changed after the minutes are approved',
                'code'  => 'MINUTES_APPROVED',
            ]);
            exit;
        }
    }
}
