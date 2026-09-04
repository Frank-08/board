<?php
/**
 * Meeting Minutes Export to PDF/Print
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/agenda_helpers.php';
require_once __DIR__ . '/../includes/resolution_helpers.php';
require_once __DIR__ . '/../includes/export_helpers.php';

$meetingId = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;
date_default_timezone_set('Australia/Sydney');

if (!$meetingId) {
    die('Meeting ID is required');
}

$db = getDBConnection();

// Get meeting details
$stmt = $db->prepare("SELECT m.*, mt.name as meeting_type_name, mt.description as meeting_type_description FROM meetings m JOIN meeting_types mt ON m.meeting_type_id = mt.id WHERE m.id = ?");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    die('Meeting not found');
}

// Get minutes
$stmt = $db->prepare("
    SELECT m.*, 
        pb.first_name as prepared_first_name, pb.last_name as prepared_last_name,
        ab.first_name as approved_first_name, ab.last_name as approved_last_name
    FROM minutes m
    LEFT JOIN board_members pb ON m.prepared_by = pb.id
    LEFT JOIN board_members ab ON m.approved_by = ab.id
    WHERE m.meeting_id = ?
");
$stmt->execute([$meetingId]);
$minutes = $stmt->fetch();

if (!$minutes) {
    die('No minutes found for this meeting');
}

// Get agenda items with comments (resolutions/presenters/departures attached separately to avoid row duplication)
$stmt = $db->prepare("
    SELECT ai.*,
        mac.comment as minutes_comment
    FROM agenda_items ai
    LEFT JOIN minutes_agenda_comments mac ON ai.id = mac.agenda_item_id AND mac.minutes_id = ?
    WHERE ai.meeting_id = ?
    ORDER BY ai.position ASC, CASE WHEN ai.parent_id IS NULL THEN 0 ELSE 1 END ASC, ai.sub_position ASC
");
$stmt->execute([$minutes['id'], $meetingId]);
$agendaItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
$agendaItems = attachResolutionsToAgendaItems($db, $meetingId, $agendaItems);
$agendaItems = attachPresentersToAgendaItems($db, $meetingId, $agendaItems);
$agendaItems = attachDeparturesToAgendaItems($db, $meetingId, $agendaItems);

// Get procedural proposals recorded for this meeting (points of order, adjournment, etc.)
$stmt = $db->prepare("
    SELECT pp.*,
        proposer.first_name AS proposed_by_first_name, proposer.last_name AS proposed_by_last_name,
        seconder.first_name AS seconded_by_first_name, seconder.last_name AS seconded_by_last_name,
        ai.title AS agenda_item_title, ai.item_number AS agenda_item_number,
        r.title AS resolution_title
    FROM procedural_proposals pp
    LEFT JOIN board_members proposer ON pp.proposed_by = proposer.id
    LEFT JOIN board_members seconder ON pp.seconded_by = seconder.id
    LEFT JOIN agenda_items ai ON pp.agenda_item_id = ai.id
    LEFT JOIN resolutions r ON pp.resolution_id = r.id
    WHERE pp.meeting_id = ?
    ORDER BY pp.recorded_at ASC, pp.id ASC
");
$stmt->execute([$meetingId]);
$proceduralProposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by where they actually happened in the meeting flow, relative to the
// agenda item they're anchored to: 'During' renders inline within that
// item's card; 'Before'/'After' render in the gap just before/after it, so a
// proposal raised between two items (rather than during either one's
// discussion) is still shown at the exact point it happened, in agenda
// order, rather than lumped into a single end-of-document list.
$duringProceduralProposalsByAgendaItem = [];
$beforeProceduralProposalsByAgendaItem = [];
$afterProceduralProposalsByAgendaItem = [];
$unlinkedProceduralProposals = [];
foreach ($proceduralProposals as $pp) {
    if (empty($pp['agenda_item_id'])) {
        $unlinkedProceduralProposals[] = $pp;
        continue;
    }
    $anchorId = (int)$pp['agenda_item_id'];
    $position = $pp['agenda_position'] ?? 'During';
    if ($position === 'Before') {
        $beforeProceduralProposalsByAgendaItem[$anchorId][] = $pp;
    } elseif ($position === 'After') {
        $afterProceduralProposalsByAgendaItem[$anchorId][] = $pp;
    } else {
        $duringProceduralProposalsByAgendaItem[$anchorId][] = $pp;
    }
}

$proceduralProposalLabels = [
    'UseOfProcedures' => 'Use of Procedures',
    'OrderOfDay' => 'Order of the Day',
    'Adjournment' => 'Adjournment',
    'PrivateSitting' => 'Private Sitting',
    'Referral' => 'Referral',
    'DecisionNow' => 'Determining the Need for a Decision Now',
    'WithdrawMotion' => 'Withdraw Motion',
    'PreviousQuestion' => 'The Previous Question',
    'Closure' => 'Closure (vote be now taken)',
    'Reconsideration' => 'Reconsideration',
    'PointOfOrder' => 'Point of Order'
];

/**
 * Render a single procedural proposal as a callout. When $showAgendaItem is
 * true, the linked agenda item is named in the callout (used for the
 * "General" section); inline callouts under an agenda item omit it since
 * it's already clear from context.
 */
function renderProceduralProposalCallout(array $pp, array $proceduralProposalLabels, bool $showAgendaItem = false): string {
    $typeLabel = $proceduralProposalLabels[$pp['proposal_type']] ?? $pp['proposal_type'];
    $html = '<div class="resolution-callout" style="background: #f4f4f4; border-left-color: #6c757d;">';
    $html .= '<p class="callout-title" style="color: #495057;">Procedural Proposal</p>';
    $html .= '<div class="callout-body"><p class="callout-text" style="font-style: normal;"><strong>' . htmlspecialchars($typeLabel) . '</strong>';
    $html .= ' <span class="status-badge status-' . strtolower($pp['outcome']) . '">' . htmlspecialchars($pp['outcome']) . '</span></p></div>';
    if ($showAgendaItem && !empty($pp['agenda_item_title'])) {
        $html .= '<p style="margin: 6px 0 0 0; font-size: 13px;"><strong>Agenda Item:</strong> '
            . htmlspecialchars(($pp['agenda_item_number'] ? $pp['agenda_item_number'] . '. ' : '') . $pp['agenda_item_title']) . '</p>';
    }
    if (!empty($pp['resolution_title'])) {
        $html .= '<p style="margin: 6px 0 0 0; font-size: 13px;"><strong>Motion/Resolution:</strong> '
            . htmlspecialchars($pp['resolution_title']) . '</p>';
    }
    $proposedByName = !empty($pp['proposed_by_first_name'])
        ? $pp['proposed_by_first_name'] . ' ' . $pp['proposed_by_last_name']
        : ($pp['proposed_by_name'] ?? null);
    $secondedByName = !empty($pp['seconded_by_first_name'])
        ? $pp['seconded_by_first_name'] . ' ' . $pp['seconded_by_last_name']
        : ($pp['seconded_by_name'] ?? null);
    if (!empty($proposedByName)) {
        $html .= '<p style="margin: 6px 0 0 0; font-size: 13px;"><strong>Proposed by:</strong> '
            . htmlspecialchars($proposedByName);
        if (!empty($secondedByName)) {
            $html .= ', seconded by ' . htmlspecialchars($secondedByName);
        }
        $html .= '</p>';
    }
    if (!empty($pp['requires_leave'])) {
        $html .= '<p style="margin: 6px 0 0 0; font-size: 13px;"><em>Required leave of the council.</em></p>';
    }
    if (!empty($pp['notes'])) {
        $html .= '<p style="margin: 6px 0 0 0; font-size: 13px;">' . nl2br(htmlspecialchars($pp['notes'])) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

// Get attendees with their role in the meeting's meeting type. member_id is
// NULL for a general attendee (see meeting_attendees.attendee_name) - LEFT
// JOIN so they still appear, with display_name falling back to their name.
$stmt = $db->prepare("
    SELECT ma.*, bm.first_name, bm.last_name, bm.title,
        mtm.role, mtm.status as membership_status,
        CASE WHEN ma.member_id IS NOT NULL THEN CONCAT(bm.first_name, ' ', bm.last_name) ELSE ma.attendee_name END AS display_name
    FROM meeting_attendees ma
    LEFT JOIN board_members bm ON ma.member_id = bm.id
    JOIN meetings m ON ma.meeting_id = m.id
    LEFT JOIN meeting_type_members mtm ON bm.id = mtm.member_id AND m.meeting_type_id = mtm.meeting_type_id
    WHERE ma.meeting_id = ?
    ORDER BY
        FIELD(ma.attendance_status, 'Present', 'Apology', 'Absent', 'Excused', 'Late') ASC,
        FIELD(mtm.role, 'Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Ex-officio', 'Member'),
        COALESCE(bm.last_name, ma.attendee_name) ASC
");
$stmt->execute([$meetingId]);
$attendees = $stmt->fetchAll();

$attendanceOrder = ['Present', 'Apology', 'Absent', 'Excused', 'Late', 'Other'];
$attendanceLabels = [
    'Apology' => 'Apologies'
];
$attendeesByStatus = array_fill_keys($attendanceOrder, []);
foreach ($attendees as $attendee) {
    $status = $attendee['attendance_status'] ?? '';
    if (!isset($attendeesByStatus[$status])) {
        $status = 'Other';
    }
    $attendeesByStatus[$status][] = $attendee;
}

function formatDateTime($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('F j, Y g:i A');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Minutes - <?php echo htmlspecialchars($meeting['title']); ?></title>
    <style>
        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
            @page {
                margin: 1cm;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
            line-height: 1.4;
            color: #333;
        }
        
        .no-print {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 5px;
        }
        
        .no-print button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .no-print button:hover {
            background: #0056b3;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: #333;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .meeting-info {
            margin-bottom: 15px;
            padding: 12px;
            background: #f9f9f9;
            border-left: 4px solid #007bff;
        }
        
        .meeting-info p {
            margin: 5px 0;
        }
        
        .section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .section h2 {
            color: #333;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .section h3 {
            color: #555;
            margin-top: 12px;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .attendee-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        
        .attendee-item {
            padding: 6px;
            background: #f9f9f9;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .attendee-item strong {
            display: block;
            margin-bottom: 3px;
        }
        
        .agenda-item {
            margin-bottom: 12px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        
        .agenda-item h4 {
            margin: 0 0 6px 0;
            color: #333;
            font-size: 16px;
        }
        
        .agenda-item .item-description {
            color: #666;
            margin: 6px 0;
            font-style: italic;
        }

        .procedural-gap {
            margin: 8px 0;
            padding-left: 10px;
            border-left: 3px dashed #bbb;
        }

        .resolution-callout {
            background: #e6f2ff;
            border-left: 4px solid #007bff;
            border-radius: 4px;
            padding: 10px;
            margin: 8px 0;
        }

        .resolution-callout .callout-title {
            margin: 0 0 4px 0;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #0056b3;
            text-transform: uppercase;
        }

        .resolution-callout .callout-lead {
            margin: 0 0 6px 0;
            font-size: 13px;
            font-weight: 600;
            color: #0056b3;
        }

        .resolution-callout .callout-body {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .resolution-callout .callout-number {
            min-width: 80px;
            padding: 4px 8px;
            border: 1px solid #007bff;
            border-radius: 4px;
            background: #f6faff;
            font-weight: bold;
            color: #0056b3;
            text-align: center;
            font-size: 12px;
        }

        .resolution-callout .callout-text {
            margin: 0;
            color: #333;
            font-style: italic;
        }
        
        .agenda-comment {
            margin-top: 8px;
            padding: 8px;
            background: #f0f8ff;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
        
        .agenda-comment strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        .minutes-content {
            white-space: pre-wrap;
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .action-items {
            margin-top: 10px;
            padding: 10px;
            background: #fff8dc;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }
        
        .action-items ul {
            margin: 6px 0;
            padding-left: 20px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #333;
            font-size: 12px;
            color: #666;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-draft { background: #ffc107; color: #000; }
        .status-review { background: #17a2b8; color: #fff; }
        .status-approved { background: #28a745; color: #fff; }
        .status-published { background: #6c757d; color: #fff; }
        
        /* Resolution status badges */
        .status-proposed { background: #17a2b8; color: #fff; }
        .status-consensus { background: #28a745; color: #fff; }
        .status-agreement { background: #28a745; color: #fff; }
        .status-failed { background: #dc3545; color: #fff; }
        .status-withdrawn { background: #6c757d; color: #fff; }
        .status-lapsed { background: #6c757d; color: #fff; }

        /* Procedural proposal outcome badges */
        .status-pending { background: #ffc107; color: #000; }
        .status-carried { background: #28a745; color: #fff; }
        .status-lost { background: #dc3545; color: #fff; }
        .status-ruledon { background: #17a2b8; color: #fff; }

        .letterhead {
            text-align: center;
        }

        .letterhead-org {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
        }

        .letterhead-doctype {
            font-size: 16px;
            font-weight: bold;
            color: #666;
            margin: 0;
        }

        .resolved-clause-list {
            margin: 8px 0;
            padding: 10px;
            background: #e6f2ff;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }

        .resolved-clause-heading {
            margin: 0 0 6px 0;
            font-weight: bold;
            color: #0056b3;
        }

        .resolved-clause-items {
            margin: 0;
            padding-left: 22px;
            color: #333;
        }

        .resolved-clause-items li {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>
    
    <div class="header">
        <?php 
        $logoPath = defined('LOGO_PATH') && LOGO_PATH ? LOGO_PATH : '';
        $logoUrl = defined('LOGO_URL') && LOGO_URL ? LOGO_URL : '';
        $logoExists = ($logoPath && file_exists($logoPath)) || ($logoUrl && file_exists(__DIR__ . '/../' . $logoUrl));
        if ($logoExists && $logoUrl): ?>
        <div style="text-align:center; margin-bottom:15px;">
            <img src="../<?php echo htmlspecialchars($logoUrl); ?>" 
                 alt="Logo" 
                 style="max-width:<?php echo defined('LOGO_WIDTH_HTML') ? LOGO_WIDTH : 60; ?>px; height:<?php echo defined('LOGO_HEIGHT') && LOGO_HEIGHT > 0 ? LOGO_HEIGHT : 'auto'; ?>px; max-height:250px;">
        </div>
        <?php endif; ?>
        <?php echo renderLetterheadBlock(ORGANIZATION_NAME, $meeting['meeting_type_name'], 'minutes', $minutes['status']); ?>
    </div>

    <div class="meeting-info">
        <p><strong>Meeting:</strong> <?php echo htmlspecialchars($meeting['title']); ?></p>
        <p><strong>Date &amp; Time:</strong> <?php echo htmlspecialchars(formatMeetingDateTimeLine($meeting['scheduled_date'], $meeting['end_time'] ?? null)); ?></p>
        <?php if ($meeting['location']): ?>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($meeting['location']); ?></p>
        <?php endif; ?>
        <p><strong>Minutes Status:</strong> 
            <span class="status-badge status-<?php echo strtolower($minutes['status']); ?>">
                <?php echo htmlspecialchars($minutes['status']); ?>
            </span>
        </p>
    </div>
    
    <?php if (count($attendees) > 0): ?>
    <div class="section">
        <h2>Attendees</h2>
        <?php foreach ($attendanceOrder as $status): ?>
            <?php if (!empty($attendeesByStatus[$status])): ?>
            <h3><?php echo htmlspecialchars($attendanceLabels[$status] ?? $status); ?></h3>
            <div class="attendee-list">
                <?php foreach ($attendeesByStatus[$status] as $attendee): ?>
                <div class="attendee-item">
                    <strong><?php echo htmlspecialchars($attendee['display_name']); ?></strong>
                    <?php if (!empty($attendee['role'])): ?>
                    <span style="color: #666; font-size: 12px;"><?php echo htmlspecialchars($attendee['role']); ?></span>
                    <?php endif; ?>
                    <?php if ($attendee['title']): ?>
                    <br><span style="color: #999; font-size: 11px;"><?php echo htmlspecialchars($attendee['title']); ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (count($agendaItems) > 0 || count($unlinkedProceduralProposals) > 0): ?>
    <div class="section">
        <h2>Agenda Items & Discussion</h2>
        <?php foreach ($agendaItems as $agendaIndex => $item): ?>
        <?php if ($agendaIndex === 0): ?>
        <?php foreach ($beforeProceduralProposalsByAgendaItem[(int)$item['id']] ?? [] as $pp): ?>
        <div class="procedural-gap"><?php echo renderProceduralProposalCallout($pp, $proceduralProposalLabels); ?></div>
        <?php endforeach; ?>
        <?php endif; ?>
        <div class="agenda-item">
            <h4>
                <?php echo htmlspecialchars($item['item_number'] ?? '') . ($item['item_number'] ? '. ' : ''); ?>
                <?php echo renderStarredPrefix($item) . htmlspecialchars($item['title']); ?>
                <?php foreach ($item['resolutions'] ?? [] as $res): ?>
                <?php if (!empty($res['resolution_number'])): ?>
                <span style="color: #007bff; font-weight: normal; margin-left: 10px;">(Resolution #<?php echo htmlspecialchars($res['resolution_number']); ?>)</span>
                <?php endif; ?>
                <?php if (!empty($res['status'])): ?>
                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $res['status'])); ?>" style="margin-left: 8px; padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: bold;">
                    <?php echo htmlspecialchars($res['status']); ?>
                </span>
                <?php endif; ?>
                <?php endforeach; ?>
            </h4>
            <?php if (!empty($item['description'])): ?>
            <div class="item-description"><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>
            <?php endif; ?>
            <?php echo renderResolvedClauseList($item['resolutions'] ?? [], 'minutes', $meeting['meeting_type_name']); ?>
            <?php foreach ($duringProceduralProposalsByAgendaItem[(int)$item['id']] ?? [] as $pp): ?>
            <?php echo renderProceduralProposalCallout($pp, $proceduralProposalLabels); ?>
            <?php endforeach; ?>
            <?php $attribution = renderAttributionLine($item, 'past'); ?>
            <?php if ($attribution): ?>
            <p style="font-size: 14px; color: #666; margin: 8px 0;"><?php echo $attribution; ?></p>
            <?php elseif (!empty($item['presenters'])): ?>
            <p style="font-size: 14px; color: #666; margin: 8px 0;">
                <strong>Presenter:</strong> <?php echo htmlspecialchars(joinPresenterNames($item['presenters'])); ?>
            </p>
            <?php endif; ?>
            <?php echo renderDeparturesNote($item['departures'] ?? []); ?>
            <?php if (!empty($item['minutes_comment'])): ?>
            <div class="agenda-comment">
                <strong>Discussion/Comments:</strong>
                <div><?php echo nl2br(htmlspecialchars($item['minutes_comment'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php foreach ($afterProceduralProposalsByAgendaItem[(int)$item['id']] ?? [] as $pp): ?>
        <div class="procedural-gap"><?php echo renderProceduralProposalCallout($pp, $proceduralProposalLabels); ?></div>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (count($unlinkedProceduralProposals) > 0): ?>
        <div class="agenda-item" style="background: #fafafa;">
            <h4>General Procedural Proposals</h4>
            <div class="item-description">Procedural motions with no specific point in the agenda recorded.</div>
            <?php foreach ($unlinkedProceduralProposals as $pp): ?>
            <?php echo renderProceduralProposalCallout($pp, $proceduralProposalLabels); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($minutes['action_items']): ?>
    <div class="section">
        <h2>Action Items</h2>
        <div class="action-items">
            <?php echo nl2br(htmlspecialchars($minutes['action_items'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <?php if ($minutes['prepared_first_name']): ?>
        <p><strong>Prepared by:</strong> <?php echo htmlspecialchars($minutes['prepared_first_name'] . ' ' . $minutes['prepared_last_name']); ?></p>
        <?php endif; ?>
        <?php if ($minutes['approved_first_name']): ?>
        <p><strong>Approved by:</strong> <?php echo htmlspecialchars($minutes['approved_first_name'] . ' ' . $minutes['approved_last_name']); ?></p>
        <?php endif; ?>
        <?php if ($minutes['approved_at']): ?>
        <p><strong>Approved on:</strong> <?php echo formatDateTime($minutes['approved_at']); ?></p>
        <?php endif; ?>
        <?php if ($minutes['next_meeting_date']): ?>
        <p><strong>Next Meeting:</strong> <?php echo formatDateTime($minutes['next_meeting_date']); ?></p>
        <?php endif; ?>
        <p><strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?></p>
    </div>
</body>
</html>


