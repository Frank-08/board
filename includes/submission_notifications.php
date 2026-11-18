<?php
/**
 * Email reviewers when a new agenda item is submitted.
 *
 * Recipients are active users who can review submissions (their role has
 * manage_agenda) and have opted in with users.notify_agenda_submissions,
 * toggled from the Review Queue on submit_agenda.php. The submitter is never
 * emailed about their own submission.
 *
 * If NTFY_TOPIC_URL is set in config/config.php, a push notification also
 * goes to that ntfy topic (whoever subscribes to it, regardless of the
 * per-user email setting).
 *
 * Sending is best effort: a missing SMTP configuration or a failed send is
 * logged and never fails the submission itself.
 */

require_once __DIR__ . '/../config/email.php';

/**
 * Users who should be told about a new submission.
 *
 * @return array<int, array{id:int, username:string, email:string}>
 */
function submissionNotificationRecipients(PDO $db, int $excludeUserId): array {
    $reviewerRoles = PERMISSIONS['manage_agenda'] ?? [];
    if (!$reviewerRoles) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($reviewerRoles), '?'));
    $stmt = $db->prepare("
        SELECT id, username, email
        FROM users
        WHERE is_active = TRUE
          AND notify_agenda_submissions = TRUE
          AND role IN ($placeholders)
          AND id <> ?
    ");
    $stmt->execute(array_merge($reviewerRoles, [$excludeUserId]));

    return array_values(array_filter($stmt->fetchAll(), function ($u) {
        return filter_var($u['email'], FILTER_VALIDATE_EMAIL) !== false;
    }));
}

function smtpIsConfigured(): bool {
    return defined('SMTP_HOST') && SMTP_HOST !== ''
        && defined('SMTP_USERNAME') && SMTP_USERNAME !== ''
        && defined('SMTP_PASSWORD') && SMTP_PASSWORD !== ''
        && defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL !== '';
}

function ntfyTopicUrl(): string {
    $url = defined('NTFY_TOPIC_URL') ? trim((string)NTFY_TOPIC_URL) : '';
    return preg_match('#^https?://[^\s/]+/[A-Za-z0-9_-]+$#', $url) ? $url : '';
}

/**
 * Push a short notice to the configured ntfy topic. Only the title, meeting
 * and submitter go out - not the summary or proposal - because a public
 * ntfy.sh topic is readable by anyone who guesses its name.
 */
function pushSubmissionToNtfy(array $submission, string $submitter, string $meetingLabel, string $reviewUrl): void {
    $url = ntfyTopicUrl();
    if ($url === '') {
        if (defined('NTFY_TOPIC_URL') && trim((string)NTFY_TOPIC_URL) !== '') {
            error_log('NTFY_TOPIC_URL is not a valid ntfy topic URL (expected https://host/topic)');
        }
        return;
    }

    $body = $submission['title'] . "\n" . $meetingLabel . "\nFrom " . $submitter;
    // Header values must be single-line ASCII; user text only goes in the body
    $headers = [
        'Content-Type: text/plain; charset=utf-8',
        'Title: New agenda item submitted',
        'Tags: memo',
        'Click: ' . preg_replace('/[^\x21-\x7E]/', '', $reviewUrl),
    ];
    $token = defined('NTFY_ACCESS_TOKEN') ? preg_replace('/[^A-Za-z0-9_-]/', '', (string)NTFY_ACCESS_TOKEN) : '';
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $context);
    $status = isset($http_response_header[0]) ? $http_response_header[0] : 'no response';
    if ($response === false || !preg_match('#^HTTP/\S+\s+2\d\d#', $status)) {
        error_log('Agenda submission #' . $submission['id'] . ': ntfy push failed (' . $status . ')');
    }
}

/**
 * Push to ntfy (if configured) and email every opted-in reviewer about a
 * new submission. $submission is a row from fetchSubmission() in
 * api/agenda_submissions.php.
 */
function notifyReviewersOfSubmission(PDO $db, array $submission): void {
    try {
        $submitter = trim(($submission['submitter_first_name'] ?? '') . ' ' . ($submission['submitter_last_name'] ?? ''));
        if ($submitter === '') {
            $submitter = $submission['submitted_by_username'] ?? 'A member';
        }
        $meetingDate = !empty($submission['meeting_date'])
            ? (new DateTime($submission['meeting_date']))->format('j M Y')
            : '';
        $meetingLabel = $submission['meeting_title'] . ($meetingDate !== '' ? " ($meetingDate)" : '');
        $decision = ($submission['decision_method'] ?? 'None') === 'None' ? 'For information' : $submission['decision_method'];
        $reviewUrl = rtrim(BASE_URL, '/') . '/submit_agenda.php?meeting_id=' . (int)$submission['meeting_id'];

        pushSubmissionToNtfy($submission, $submitter, $meetingLabel, $reviewUrl);

        $recipients = submissionNotificationRecipients($db, (int)($submission['submitted_by'] ?? 0));
        if (!$recipients) {
            return;
        }
        if (!smtpIsConfigured()) {
            error_log('Agenda submission #' . $submission['id'] . ': ' . count($recipients)
                . ' reviewer(s) want an email but SMTP is not configured in config/config.php');
            return;
        }

        $subject = 'New agenda item submitted: ' . mb_substr($submission['title'], 0, 120) . ' - ' . APP_NAME;

        $h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
        $rows = [
            'Meeting' => $meetingLabel,
            'Submitted by' => $submitter,
            'Item type' => $submission['item_type'] ?? '',
            'Decision sought' => $decision,
            'Report' => ($submission['report_type'] ?? '')
                . (!empty($submission['duration_minutes']) ? ' · ' . (int)$submission['duration_minutes'] . ' min requested' : ''),
        ];

        $htmlRows = '';
        $textRows = '';
        foreach ($rows as $label => $value) {
            $htmlRows .= '<tr><td style="padding:4px 12px 4px 0;color:#666;vertical-align:top;">' . $h($label)
                . '</td><td style="padding:4px 0;">' . $h($value) . '</td></tr>';
            $textRows .= $label . ': ' . $value . "\n";
        }

        $htmlBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
            . '<body style="font-family:Arial,sans-serif;line-height:1.5;color:#333;">'
            . '<div style="max-width:600px;margin:0 auto;padding:20px;">'
            . '<h2 style="margin-top:0;">New agenda item submitted</h2>'
            . '<p style="font-size:16px;"><strong>' . $h($submission['title']) . '</strong></p>'
            . '<table style="border-collapse:collapse;margin-bottom:16px;">' . $htmlRows . '</table>'
            . (!empty($submission['summary']) ? '<p><strong>Summary</strong><br>' . nl2br($h($submission['summary'])) . '</p>' : '')
            . (!empty($submission['proposal']) ? '<p><strong>Proposal</strong><br>' . nl2br($h($submission['proposal'])) . '</p>' : '')
            . '<p><a href="' . $h($reviewUrl) . '" style="display:inline-block;padding:10px 24px;background:#2c5282;color:#fff;text-decoration:none;border-radius:6px;">Review submission</a></p>'
            . '<p style="font-size:12px;color:#666;border-top:1px solid #ddd;padding-top:12px;">'
            . 'You are receiving this because you turned on submission emails in ' . $h(APP_NAME)
            . '. Turn them off from the Review Queue on the Submit Item page.</p>'
            . '</div></body></html>';

        $textBody = "New agenda item submitted\n\n"
            . $submission['title'] . "\n\n"
            . $textRows
            . (!empty($submission['summary']) ? "\nSummary:\n" . $submission['summary'] . "\n" : '')
            . (!empty($submission['proposal']) ? "\nProposal:\n" . $submission['proposal'] . "\n" : '')
            . "\nReview it here: " . $reviewUrl . "\n\n"
            . "---\nYou are receiving this because you turned on submission emails in " . APP_NAME
            . ". Turn them off from the Review Queue on the Submit Item page.\n";

        foreach ($recipients as $recipient) {
            $result = sendSMTPEmail($recipient['email'], $subject, $htmlBody, $textBody);
            if (empty($result['success'])) {
                error_log('Agenda submission #' . $submission['id'] . ': notification to user #'
                    . $recipient['id'] . ' failed: ' . ($result['message'] ?? 'unknown error'));
            }
        }
    } catch (Throwable $e) {
        error_log('Agenda submission #' . ($submission['id'] ?? '?') . ': notification failed: ' . $e->getMessage());
    }
}
