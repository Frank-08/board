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
 * GET    ?id=X                  one submission (own, or any with manage_agenda)
 * GET    [?meeting_id=X][&status=Pending]
 *                                own submissions, or all with manage_agenda
 * POST   {meeting_id, title, ...}           submit
 * PUT    {id, action: update|withdraw}      submitter, while Pending
 * PUT    {id, action: accept|decline, review_notes}   manage_agenda
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/agenda_helpers.php';
require_once __DIR__ . '/../includes/document_helpers.php';

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
    return $stmt->fetch();
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
        if (!isValidSharePointUrl($fields['sharepoint_url'])) {
            sendError(400, 'A valid HTTPS SharePoint URL is required for a written report');
        }
    }
    if ($fields['sharepoint_url'] === '') {
        $fields['sharepoint_url'] = null;
    }

    return $fields;
}

/**
 * Append an accepted submission to the end of its meeting's agenda, numbered
 * the same way as api/agenda.php, and link its report as a document.
 */
function acceptSubmission(PDO $db, array $submission): int {
    $meetingId = (int)$submission['meeting_id'];

    $stmt = $db->prepare("
        SELECT m.scheduled_date, m.meeting_type_id, mt.shortcode
        FROM meetings m
        JOIN meeting_types mt ON m.meeting_type_id = mt.id
        WHERE m.id = ?
        FOR UPDATE
    ");
    $stmt->execute([$meetingId]);
    $meeting = $stmt->fetch();

    $stmt = $db->prepare("SELECT COALESCE(MAX(position), -1) + 1 as new_position FROM agenda_items WHERE meeting_id = ? AND parent_id IS NULL");
    $stmt->execute([$meetingId]);
    $position = (int)$stmt->fetch()['new_position'];

    $meetingDate = new DateTime($meeting['scheduled_date']);
    $year = $meetingDate->format('y');
    $month = $meetingDate->format('m');
    $sequence = $position + 1;
    $shortcode = $meeting['shortcode'] ?? '';
    if (!empty($shortcode)) {
        $itemNumber = sprintf('%s.%s.%s.%02d', $shortcode, $year, $month, $sequence);
    } else {
        $itemNumber = sprintf('%s.%s.%02d', $year, $month, $sequence);
    }

    $description = $submission['summary'] ?? '';
    if (!empty($submission['proposal'])) {
        $description = trim($description . "\n\nProposal: " . $submission['proposal']);
    }

    $stmt = $db->prepare("INSERT INTO agenda_items (meeting_id, title, description, item_type, decision_method, report_type, duration_minutes, position, item_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $meetingId,
        $submission['title'],
        $description !== '' ? $description : null,
        $submission['item_type'],
        $submission['decision_method'],
        $submission['report_type'],
        $submission['duration_minutes'],
        $position,
        $itemNumber
    ]);
    $agendaItemId = (int)$db->lastInsertId();

    if (!empty($submission['submitter_member_id'])) {
        syncAgendaItemPresenters($db, $agendaItemId, [(int)$submission['submitter_member_id']]);
    }

    if (!empty($submission['sharepoint_url'])) {
        $stmt = $db->prepare("
            INSERT INTO documents (meeting_type_id, meeting_id, agenda_item_id, document_type, title, description, sharepoint_url, file_path, file_name, file_size, mime_type, uploaded_by)
            VALUES (?, ?, ?, 'Report', ?, ?, ?, NULL, ?, NULL, NULL, ?)
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
            echo json_encode($stmt->fetchAll());
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

        http_response_code(201);
        echo json_encode(fetchSubmission($db, (int)$db->lastInsertId()));
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $action = $data['action'] ?? '';
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

                    $agendaItemId = acceptSubmission($db, $locked);

                    $stmt = $db->prepare("
                        UPDATE agenda_submissions
                        SET status = 'Accepted', agenda_item_id = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                        WHERE id = ?
                    ");
                    $notes = trim((string)($data['review_notes'] ?? ''));
                    $stmt->execute([$agendaItemId, $notes !== '' ? $notes : null, $currentUserId, $id]);
                    $db->commit();
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    sendError(500, 'Failed to accept submission: ' . $e->getMessage());
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
