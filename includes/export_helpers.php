<?php
/**
 * Rendering helpers shared by the agenda/minutes export templates
 * (export/agenda.php, export/agenda_pdf.php, export/minutes.php) so the
 * Word-style letterhead, numbering, resolution-clause list, attribution
 * line, and starred-item prefix are only implemented once.
 */

/**
 * Build the letterhead block: org + meeting type name, a
 * DRAFT AGENDA / DRAFT MINUTES / MINUTES line, and (matching the Word
 * document, which has no separate "Date & Time:" info panel) the
 * date/time line centered directly underneath.
 *
 * @param string $orgName e.g. ORGANIZATION_NAME
 * @param string $meetingTypeName e.g. "Standing Committee"
 * @param string $docType 'agenda' or 'minutes'
 * @param string|null $minutesStatus minutes.status, only relevant when $docType === 'minutes'
 * @param string|null $dateTimeLine pre-formatted via formatMeetingDateTimeLine(), e.g. "Sunday 26 July 2026 2.00pm - 5.00pm"
 * @return string HTML
 */
function renderLetterheadBlock(string $orgName, string $meetingTypeName, string $docType, ?string $minutesStatus = null, ?string $dateTimeLine = null): string {
    $orgLine = strtoupper(trim($orgName . ' ' . $meetingTypeName));

    if ($docType === 'minutes') {
        $isFinal = in_array($minutesStatus, ['Approved', 'Published'], true);
        $docLine = $isFinal ? 'MINUTES' : 'DRAFT MINUTES';
    } else {
        $docLine = 'DRAFT AGENDA';
    }

    $html = '<div class="letterhead">';
    $html .= '<p class="letterhead-org">' . htmlspecialchars($orgLine) . '</p>';
    $html .= '<p class="letterhead-doctype">' . htmlspecialchars($docLine) . '</p>';
    if (!empty($dateTimeLine)) {
        $html .= '<p class="letterhead-datetime">' . htmlspecialchars($dateTimeLine) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * "Sunday 26 July 2026 2.00pm – 5.00pm", omitting the end time when unset.
 *
 * @param string $scheduledDate DATETIME string
 * @param string|null $endTime TIME string (HH:MM:SS) or null
 * @return string
 */
function formatMeetingDateTimeLine(string $scheduledDate, ?string $endTime): string {
    $start = new DateTime($scheduledDate);
    $line = $start->format('l j F Y') . ' ' . formatWordStyleTime($start);

    if (!empty($endTime)) {
        $end = DateTime::createFromFormat('H:i:s', substr($endTime, 0, 8)) ?: DateTime::createFromFormat('H:i', $endTime);
        if ($end) {
            $line .= ' – ' . formatWordStyleTime($end);
        }
    }

    return $line;
}

/**
 * "2.00pm" style time (dot separator, no space before am/pm), matching the
 * Word document convention rather than PHP's default "2:00 PM".
 */
function formatWordStyleTime(DateTime $time): string {
    return $time->format('g') . '.' . $time->format('i') . strtolower($time->format('a'));
}

/**
 * Returns '*** ' when the item is starred, '' otherwise.
 */
function renderStarredPrefix(array $item): string {
    return !empty($item['is_starred']) ? '*** ' : '';
}

/**
 * "{Presenter}, {will speak to/spoke to} a {written/verbal} report".
 * Empty string when presenter or report_type is not set.
 *
 * @param array $item Agenda item with presenter_first_name/presenter_last_name/report_type
 * @param string $tense 'future' (agenda) or 'past' (minutes)
 */
function renderAttributionLine(array $item, string $tense): string {
    if (empty($item['presenter_id']) || empty($item['report_type'])) {
        return '';
    }

    $name = trim(($item['presenter_title'] ?? '') . ' ' . ($item['presenter_first_name'] ?? '') . ' ' . ($item['presenter_last_name'] ?? ''));
    if ($name === '') {
        return '';
    }

    $verb = $tense === 'past' ? 'spoke to' : 'will speak to';
    $reportType = strtolower($item['report_type']);

    return htmlspecialchars($name) . ', ' . $verb . ' a ' . htmlspecialchars($reportType) . ' report';
}

/**
 * Render every resolution linked to one agenda item as a single lettered
 * clause list under one heading, replacing the old one-box-per-resolution
 * rendering. Requires includes/resolution_helpers.php and
 * includes/agenda_helpers.php (numberToLetterSuffix) to already be loaded.
 *
 * @param array $resolutions Rows from attachResolutionsToAgendaItems()
 * @param string $mode 'agenda' or 'minutes'
 * @return string HTML
 */
function renderResolvedClauseList(array $resolutions, string $mode): string {
    if (empty($resolutions)) {
        return '';
    }

    $heading = $mode === 'minutes'
        ? 'The Standing Committee resolved:'
        : 'It is proposed that the Standing Committee Resolve:';

    $html = '<div class="resolved-clause-list">';
    $html .= '<p class="resolved-clause-heading">' . htmlspecialchars($heading) . '</p>';
    $html .= '<ol type="a" class="resolved-clause-items">';

    foreach ($resolutions as $index => $res) {
        $status = $res['status'] ?? 'Proposed';
        $isSpecialOutcome = $mode === 'minutes' && in_array($status, ['Failed', 'Withdrawn', 'Lapsed'], true);

        $clauseText = $isSpecialOutcome
            ? formatResolutionOutcomeText($res)
            : ($res['description'] ?? '');

        $html .= '<li>' . nl2br(htmlspecialchars(trim($clauseText)));

        if ($mode === 'minutes') {
            $voteDetails = renderResolutionVoteDetails($res);
            if (!empty($voteDetails)) {
                $html .= $voteDetails;
            }
        }

        $html .= '</li>';
    }

    $html .= '</ol>';
    $html .= '</div>';
    return $html;
}
