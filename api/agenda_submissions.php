<?php
/**
 * Agenda Submissions API Endpoint
 *
 * Members propose an agenda item for an upcoming meeting with a SharePoint
 * link to their report (see assets/templates/ for the Word report template).
 * Clerks/Admins (manage_agenda) review the queue: accepting a submission
 * appends it to the meeting's agenda and links the report as a document;
 * declining it records a note back to the submitter.
 *
 * GET    ?open_meetings=1       meetings still accepting submissions
 * GET    ?notification_settings=1   reviewer's own email preference (manage_agenda)
 * GET    ?id=X                  one submission (own, or any with manage_agenda)
 * GET    [?meeting_id=X][&status=Pending]
 *                                own submissions, or all with manage_agenda
 * POST   {meeting_id, title, ...}           submit
 * PUT    {id, action: update|withdraw}      submitter, while Pending
 * PUT    {id, action: accept|decline, review_notes}   manage_agenda
 *        accept also takes optional placement:
 *          {mode: "end"}                       new item at the end (default)
 *          {mode: "start"}                     new item at the top
 *          {mode: "after", agenda_item_id: X}  new item straight after item X
 *          {mode: "under", agenda_item_id: X}  last sub-item of item X
 *        X must be a top-level item on the same meeting.
 * PUT    {action: notifications, enabled: bool}    manage_agenda: email me
 *                                                  when an item is submitted
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/agenda_helpers.php';
require_once __DIR__ . '/../includes/document_helpers.php';
require_once __DIR__ . '/../includes/submission_notifications.php';

// Require authentication for all requests
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();
$currentUser = getCurrentUser();
$currentUserId = (int)$currentUser['id'];
$canReview = hasPermission('manage_agenda');

if (!$canReview) {
    requirePermission('submit_agenda_item');
}

const SUBMISSION_ITEM_TYPES = ['Discussion', 'Action Item', 'Vote', 'Information', 'Presentation'];
const SUBMISSION_DECISION_METHODS = ['Consensus', 'Formal Majority', 'Referral', 'None'];
const SUBMISSION_REPORT_TYPES = ['Written', 'Verbal'];

function sendError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Fingerprint of a submission's content. Reviewers send back the version
 * they looked at when accepting, so a submitter can't swap the content
 * between the clerk reading it and clicking "Add to Agenda".
 */
function submissionVersion(array $s): string {
    return hash('sha256', json_encode([
        $s['title'] ?? null, $s['summary'] ?? null, $s['proposal'] ?? null, $s['item_type'] ?? null,
        $s['decision_method'] ?? null, $s['report_type'] ?? null, (string)($s['duration_minutes'] ?? ''),
        $s['sharepoint_url'] ?? null,
    ]));
}

function withSubmissionVersion($row) {
    if (is_array($row)) {
        $row['version'] = submissionVersion($row);
    }
    return $row;
}

function fetchSubmission(PDO $db, int $id) {
    $stmt = $db->prepare("
        SELECT s.*,
            m.title as meeting_title, m.scheduled_date as meeting_date, m.status as meeting_status,
            mt.name as meeting_type_name,
            u.username as submitted_by_username,
            bm.first_name as submitter_first_name, bm.last_name as submitter_last_name,
            ru.username as reviewed_by_username,
            ai.item_number as agenda_item_number
        FROM agenda_submissions s
        JOIN meetings m ON s.meeting_id = m.id
        JOIN meeting_types mt ON m.meeting_type_id = mt.id
        LEFT JOIN users u ON s.submitted_by = u.id
        LEFT JOIN board_members bm ON s.submitter_member_id = bm.id
        LEFT JOIN users ru ON s.reviewed_by = ru.id
        LEFT JOIN agenda_items ai ON s.agenda_item_id = ai.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    return withSubmissionVersion($stmt->fetch());
}

/**
 * Meetings that can still take submissions: scheduled and not yet started.
 */
function isMeetingOpenForSubmissions(PDO $db, int $meetingId): bool {
    $stmt = $db->prepare("SELECT 1 FROM meetings WHERE id = ? AND status = 'Scheduled' AND scheduled_date > NOW()");
    $stmt->execute([$meetingId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Validate and normalise the editable fields of a submission. Returns the
 * cleaned values, or exits with a 400.
 */
function validateSubmissionFields(array $data): array {
    $fields = [
        'title' => trim((string)($data['title'] ?? '')),
        'summary' => trim((string)($data['summary'] ?? '')),
        'proposal' => trim((string)($data['proposal'] ?? '')),
        'item_type' => $data['item_type'] ?? 'Discussion',
        'decision_method' => $data['decision_method'] ?? 'None',
        'report_type' => $data['report_type'] ?? 'Written',
        'duration_minutes' => !empty($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
        'sharepoint_url' => trim((string)($data['sharepoint_url'] ?? '')),
    ];

    if ($fields['title'] === '') {
        sendError(400, 'Title is required');
    }
    if (mb_strlen($fields['title']) > 255) {
        sendError(400, 'Title must be 255 characters or fewer');
    }
    if (!in_array($fields['item_type'], SUBMISSION_ITEM_TYPES, true)) {
        sendError(400, 'Invalid item_type');
    }
    if (!in_array($fields['decision_method'], SUBMISSION_DECISION_METHODS, true)) {
        sendError(400, 'Invalid decision_method');
    }
    if (!in_array($fields['report_type'], SUBMISSION_REPORT_TYPES, true)) {
        sendError(400, 'report_type must be Written or Verbal');
    }
    if ($fields['duration_minutes'] !== null && ($fields['duration_minutes'] < 1 || $fields['duration_minutes'] > 600)) {
        sendError(400, 'duration_minutes must be between 1 and 600');
    }
    if ($fields['decision_method'] !== 'None' && $fields['proposal'] === '') {
        sendError(400, 'A proposal is required when a decision is sought');
    }
    // A written report must be linked; a verbal report may optionally link supporting papers
    if ($fields['sharepoint_url'] !== '' || $fields['report_type'] === 'Written') {
        $normalised = normaliseSharePointUrl($fields['sharepoint_url']);
        if ($normalised === null) {
            sendError(400, sharePointUrlRequirement() . ($fields['report_type'] === 'Written' ? ' for a written report' : ''));
        }
        $fields['sharepoint_url'] = $normalised;
    }
    if ($fields['sharepoint_url'] === '') {
        $fields['sharepoint_url'] = null;
    }

    return $fields;
}

/**
 * Validate where a reviewer wants an accepted submission to go. Returns
 * ['mode' => ..., 'agenda_item_id' => int|null], or exits with a 400.
 */
function validatePlacement($placement): array {
    if ($placement === null || $placement === '' || $placement === []) {
        return ['mode' => 'end', 'agenda_item_id' => null];
    }
    if (!is_array($placement)) {
        sendError(400, 'placement must be an object');
    }
    $mode = $placement['mode'] ?? 'end';
    if (!in_array($mode, ['end', 'start', 'after', 'under'], true)) {
        sendError(400, 'placement.mode must be end, start, after or under');
    }
    $targetId = null;
    if ($mode === 'after' || $mode === 'under') {
        $targetId = (int)($placement['agenda_item_id'] ?? 0);
        if ($targetId <= 0) {
            sendError(400, 'placement.agenda_item_id is required for "' . $mode . '"');
        }
    }
    return ['mode' => $mode, 'agenda_item_id' => $targetId];
}

/**
 * Add an accepted submission to its meeting's agenda at the chosen place,
 * renumber the agenda (same scheme as api/agenda.php), and link its report
 * as a document. Must run inside a transaction.
 */
function acceptSubmission(PDO $db, array $submission, array $placement): int {
    $meetingId = (int)$submission['meeting_id'];

    // Lock the meeting row so concurrent agenda edits/accepts serialise
    $stmt = $db->prepare("SELECT meeting_type_id FROM meetings WHERE id = ? FOR UPDATE");
    $stmt->execute([$meetingId]);
    $meeting = $stmt->fetch();

    $parentId = null;
    $subPosition = 0;

    if ($placement['mode'] === 'after' || $placement['mode'] === 'under') {
        $stmt = $db->prepare("SELECT id, meeting_id, parent_id, position FROM agenda_items WHERE id = ? FOR UPDATE");
        $stmt->execute([$placement['agenda_item_id']]);
        $target = $stmt->fetch();
        if (!$target || (int)$target['meeting_id'] !== $meetingId) {
            throw new InvalidArgumentException('The chosen agenda item is not on this meeting\'s agenda. Reload the page and try again.');
        }
        if (!empty($target['parent_id'])) {
            // Agenda items only nest one level deep
            throw new InvalidArgumentException('Choose a main agenda item, not a sub-item.');
        }
    }

    switch ($placement['mode']) {
        case 'start':
            $db->prepare("UPDATE agenda_items SET position = position + 1 WHERE meeting_id = ?")->execute([$meetingId]);
            $position = 0;
            break;

        case 'after':
            // Sub-items share their parent's position, so this shifts them too
            $position = (int)$target['position'] + 1;
            $db->prepare("UPDATE agenda_items SET position = position + 1 WHERE meeting_id = ? AND position >= ?")
               ->execute([$meetingId, $position]);
            break;

        case 'under':
            $parentId = (int)$target['id'];
            $position = (int)$target['position'];
            $stmt = $db->prepare("SELECT COALESCE(MAX(sub_position), -1) + 1 FROM agenda_items WHERE parent_id = ?");
            $stmt->execute([$parentId]);
            $subPosition = (int)$stmt->fetchColumn();
            break;

        default: // end
            $stmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM agenda_items WHERE meeting_id = ?");
            $stmt->execute([$meetingId]);
            $position = (int)$stmt->fetchColumn();
    }

    $description = $submission['summary'] ?? '';
    if (!empty($submission['proposal'])) {
        $description = trim($description . "\n\nProposal: " . $submission['proposal']);
    }

    // item_number is filled in by renumberAgenda() below
    $stmt = $db->prepare("INSERT INTO agenda_items (meeting_id, title, description, item_type, decision_method, report_type, duration_minutes, position, sub_position, parent_id, item_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)");
    $stmt->execute([
        $meetingId,
        $submission['title'],
        $description !== '' ? $description : null,
        $submission['item_type'],
        $submission['decision_method'],
        $submission['report_type'],
        $submission['duration_minutes'],
        $position,
        $subPosition,
        $parentId,
    ]);
    $agendaItemId = (int)$db->lastInsertId();

    renumberAgenda($db, $meetingId);

    if (!empty($submission['submitter_member_id'])) {
        syncAgendaItemPresenters($db, $agendaItemId, [(int)$submission['submitter_member_id']]);
    }

    if (!empty($submission['sharepoint_url'])) {
        $stmt = $db->prepare("
            INSERT INTO documents (meeting_type_id, meeting_id, agenda_item_id, document_type, title, description, sharepoint_url, file_name, uploaded_by)
            VALUES (?, ?, ?, 'Report', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $meeting['meeting_type_id'],
            $meetingId,
            $agendaItemId,
            $submission['title'],
            'Submitted with agenda item request #' . $submission['id'],
            $submission['sharepoint_url'],
            extractFileNameFromUrl($submission['sharepoint_url']),
            $submission['submitter_member_id']
        ]);
    }

    return $agendaItemId;
}

switch ($method) {
    case 'GET':
        if (isset($_GET['open_meetings'])) {
            $stmt = $db->prepare("
                SELECT m.id, m.title, m.scheduled_date, m.location, mt.name as meeting_type_name
                FROM meetings m
                JOIN meeting_types mt ON m.meeting_type_id = mt.id
                WHERE m.status = 'Scheduled' AND m.scheduled_date > NOW()
                ORDER BY m.scheduled_date ASC
            ");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        } elseif (isset($_GET['notification_settings'])) {
            requirePermission('manage_agenda');
            $stmt = $db->prepare("SELECT notify_agenda_submissions, email FROM users WHERE id = ?");
            $stmt->execute([$currentUserId]);
            $row = $stmt->fetch() ?: [];
            echo json_encode([
                'notify_agenda_submissions' => !empty($row['notify_agenda_submissions']),
                'email' => $row['email'] ?? null,
                'email_configured' => smtpIsConfigured(),
            ]);
        } elseif (isset($_GET['id'])) {
            $submission = fetchSubmission($db, (int)$_GET['id']);
            if (!$submission || (!$canReview && (int)$submission['submitted_by'] !== $currentUserId)) {
                sendError(404, 'Submission not found');
            }
            echo json_encode($submission);
        } else {
            $where = [];
            $params = [];
            if (!$canReview || !empty($_GET['mine'])) {
                $where[] = 's.submitted_by = ?';
                $params[] = $currentUserId;
            }
            if (!empty($_GET['meeting_id'])) {
                $where[] = 's.meeting_id = ?';
                $params[] = (int)$_GET['meeting_id'];
            }
            if (!empty($_GET['status'])) {
                $where[] = 's.status = ?';
                $params[] = $_GET['status'];
            }
            $stmt = $db->prepare("
                SELECT s.*,
                    m.title as meeting_title, m.scheduled_date as meeting_date,
                    mt.name as meeting_type_name,
                    u.username as submitted_by_username,
                    bm.first_name as submitter_first_name, bm.last_name as submitter_last_name,
                    ai.item_number as agenda_item_number
                FROM agenda_submissions s
                JOIN meetings m ON s.meeting_id = m.id
                JOIN meeting_types mt ON m.meeting_type_id = mt.id
                LEFT JOIN users u ON s.submitted_by = u.id
                LEFT JOIN board_members bm ON s.submitter_member_id = bm.id
                LEFT JOIN agenda_items ai ON s.agenda_item_id = ai.id
                " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                ORDER BY FIELD(s.status, 'Pending', 'Accepted', 'Declined', 'Withdrawn'), m.scheduled_date ASC, s.created_at ASC
            ");
            $stmt->execute($params);
            echo json_encode(array_map('withSubmissionVersion', $stmt->fetchAll()));
        }
        break;

    case 'POST':
        requirePermission('submit_agenda_item');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            sendError(400, 'Request body must be valid JSON');
        }

        $meetingId = (int)($data['meeting_id'] ?? 0);
        if (!$meetingId || !isMeetingOpenForSubmissions($db, $meetingId)) {
            sendError(400, 'That meeting is not open for agenda submissions');
        }
        $fields = validateSubmissionFields($data);

        $stmt = $db->prepare("
            INSERT INTO agenda_submissions (meeting_id, submitted_by, submitter_member_id, title, summary, proposal, item_type, decision_method, report_type, duration_minutes, sharepoint_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $meetingId,
            $currentUserId,
            $currentUser['board_member_id'] ?? null,
            $fields['title'],
            $fields['summary'] !== '' ? $fields['summary'] : null,
            $fields['proposal'] !== '' ? $fields['proposal'] : null,
            $fields['item_type'],
            $fields['decision_method'],
            $fields['report_type'],
            $fields['duration_minutes'],
            $fields['sharepoint_url']
        ]);

        $created = fetchSubmission($db, (int)$db->lastInsertId());
        http_response_code(201);
        echo json_encode($created);

        // Send the response before talking to the SMTP server, so the
        // submitter isn't kept waiting on email delivery.
        if (function_exists('fastcgi_finish_request')) {
            session_write_close();
            fastcgi_finish_request();
        }
        notifyReviewersOfSubmission($db, $created);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $action = $data['action'] ?? '';

        if ($action === 'notifications') {
            requirePermission('manage_agenda');
            $enabled = !empty($data['enabled']);
            $stmt = $db->prepare("UPDATE users SET notify_agenda_submissions = ? WHERE id = ?");
            $stmt->execute([$enabled ? 1 : 0, $currentUserId]);
            echo json_encode(['notify_agenda_submissions' => $enabled]);
            break;
        }

        if (!$id) {
            sendError(400, 'ID is required');
        }

        $submission = fetchSubmission($db, $id);
        $isOwner = $submission && (int)$submission['submitted_by'] === $currentUserId;
        if (!$submission || (!$canReview && !$isOwner)) {
            sendError(404, 'Submission not found');
        }
        if ($submission['status'] !== 'Pending') {
            sendError(400, 'Only pending submissions can be changed');
        }

        switch ($action) {
            case 'update':
                if (!$isOwner) {
                    sendError(403, 'Only the submitter can edit a submission');
                }
                $fields = validateSubmissionFields($data);
                $stmt = $db->prepare("
                    UPDATE agenda_submissions
                    SET title = ?, summary = ?, proposal = ?, item_type = ?, decision_method = ?, report_type = ?, duration_minutes = ?, sharepoint_url = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $fields['title'],
                    $fields['summary'] !== '' ? $fields['summary'] : null,
                    $fields['proposal'] !== '' ? $fields['proposal'] : null,
                    $fields['item_type'],
                    $fields['decision_method'],
                    $fields['report_type'],
                    $fields['duration_minutes'],
                    $fields['sharepoint_url'],
                    $id
                ]);
                break;

            case 'withdraw':
                if (!$isOwner) {
                    sendError(403, 'Only the submitter can withdraw a submission');
                }
                $stmt = $db->prepare("UPDATE agenda_submissions SET status = 'Withdrawn' WHERE id = ? AND status = 'Pending'");
                $stmt->execute([$id]);
                break;

            case 'accept':
                requirePermission('manage_agenda');
                $placement = validatePlacement($data['placement'] ?? null);
                $db->beginTransaction();
                try {
                    // Re-check under lock so a double-click can't add the item twice
                    $stmt = $db->prepare("SELECT * FROM agenda_submissions WHERE id = ? AND status = 'Pending' FOR UPDATE");
                    $stmt->execute([$id]);
                    $locked = $stmt->fetch();
                    if (!$locked) {
                        $db->rollBack();
                        sendError(400, 'Only pending submissions can be changed');
                    }
                    $expected = (string)($data['version'] ?? '');
                    if ($expected === '' || !hash_equals(submissionVersion($locked), $expected)) {
                        $db->rollBack();
                        sendError(409, 'This submission was changed after you loaded it. Reload the page and review it again before adding it to the agenda.');
                    }

                    $agendaItemId = acceptSubmission($db, $locked, $placement);

                    $stmt = $db->prepare("
                        UPDATE agenda_submissions
                        SET status = 'Accepted', agenda_item_id = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                        WHERE id = ?
                    ");
                    $notes = trim((string)($data['review_notes'] ?? ''));
                    $stmt->execute([$agendaItemId, $notes !== '' ? $notes : null, $currentUserId, $id]);
                    $db->commit();
                } catch (InvalidArgumentException $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    sendError(400, $e->getMessage());
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    sendError(500, publicErrorMessage($e, 'Failed to accept submission'));
                }
                break;

            case 'decline':
                requirePermission('manage_agenda');
                $notes = trim((string)($data['review_notes'] ?? ''));
                if ($notes === '') {
                    sendError(400, 'Please give the submitter a reason for declining');
                }
                $stmt = $db->prepare("
                    UPDATE agenda_submissions
                    SET status = 'Declined', review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ? AND status = 'Pending'
                ");
                $stmt->execute([$notes, $currentUserId, $id]);
                break;

            default:
                sendError(400, 'action must be update, withdraw, accept or decline');
        }

        echo json_encode(fetchSubmission($db, $id));
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
