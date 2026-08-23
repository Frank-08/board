<?php
/**
 * Agenda Export to PDF/Print
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/agenda_helpers.php';
require_once __DIR__ . '/../includes/resolution_helpers.php';
require_once __DIR__ . '/../includes/export_helpers.php';

$meetingId = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : null;

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

// Get agenda items (resolutions/presenters attached separately to avoid row duplication)
$stmt = $db->prepare("
    SELECT ai.*
    FROM agenda_items ai
    WHERE ai.meeting_id = ?
    ORDER BY ai.position ASC, CASE WHEN ai.parent_id IS NULL THEN 0 ELSE 1 END ASC, ai.sub_position ASC
");
$stmt->execute([$meetingId]);
$agendaItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
$agendaItems = attachResolutionsToAgendaItems($db, $meetingId, $agendaItems);
$agendaItems = attachPresentersToAgendaItems($db, $meetingId, $agendaItems);

// Get attendees with their role in the meeting's committee
$stmt = $db->prepare("
    SELECT ma.*, bm.first_name, bm.last_name, bm.title,
        mtm.role, mtm.status as membership_status
    FROM meeting_attendees ma
    JOIN board_members bm ON ma.member_id = bm.id
    JOIN meetings m ON ma.meeting_id = m.id
    LEFT JOIN meeting_type_members mtm ON bm.id = mtm.member_id AND m.meeting_type_id = mtm.meeting_type_id
    WHERE ma.meeting_id = ?
    ORDER BY 
        FIELD(mtm.role, 'Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Ex-officio', 'Member'),
        bm.last_name ASC
");
$stmt->execute([$meetingId]);
$attendees = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/pdf.css">
    <style>
                @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
        }
        </style>
    <title>Meeting Agenda - <?php echo htmlspecialchars($meeting['title']); ?></title>
</head>
<body>
    <div class="no-print print-buttons">
        <button onclick="window.print()" class="btn">Print / Save as PDF</button>
        <a href="agenda_pdf.php?meeting_id=<?php echo $meetingId; ?>" class="btn" style="background-color: #28a745;">Download Agenda PDF</a>
        <a href="javascript:history.back()" class="btn btn-secondary">Back</a>
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
                 style="max-width:<?php echo defined('LOGO_WIDTH_HTML') ? LOGO_WIDTH_HTML : 60; ?>px; height:<?php echo defined('LOGO_HEIGHT') && LOGO_HEIGHT > 0 ? LOGO_HEIGHT : 'auto'; ?>px; max-height:250px;">
        </div>
        <?php endif; ?>
        <?php echo renderLetterheadBlock(ORGANIZATION_NAME, $meeting['meeting_type_name'], 'agenda'); ?>
    </div>

    <div class="meeting-info">
        <h2><?php echo htmlspecialchars($meeting['title']); ?></h2>
        <div class="info-row">
            <div class="info-label">Date &amp; Time:</div>
            <div class="info-value"><?php echo htmlspecialchars(formatMeetingDateTimeLine($meeting['scheduled_date'], $meeting['end_time'] ?? null)); ?></div>
        </div>
        <?php if ($meeting['location']): ?>
        <div class="info-row">
            <div class="info-label">Location:</div>
            <div class="info-value"><?php echo htmlspecialchars($meeting['location']); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($meeting['virtual_link']): ?>
        <div class="info-row">
            <div class="info-label">Virtual Link:</div>
            <div class="info-value"><?php echo htmlspecialchars($meeting['virtual_link']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($meeting['notes']): ?>
    <div class="agenda-section notes-section">
        <h3>Notes</h3>
        <div style="background-color: #fff8e1; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px;">
            <?php echo nl2br(htmlspecialchars($meeting['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="agenda-section">
        <h3>Agenda Items</h3>
        
        <?php if (count($agendaItems) > 0): ?>
            <?php foreach ($agendaItems as $item): ?>
            <?php $isChild = !empty($item['parent_id']); ?>
            <div class="agenda-item" <?php echo $isChild ? 'style="margin-left:22px;"' : ''; ?>>
                <div class="agenda-item-header">
                    <div style="display: flex; align-items: flex-start;">
                        <span class="agenda-item-number"><?php echo htmlspecialchars($item['item_number'] ?? '?'); ?>.</span>
                        <span class="agenda-item-title"><?php echo renderStarredPrefix($item) . htmlspecialchars($item['title']); ?></span>
                    </div>
                    <?php if ($item['item_type']): ?>
                    <span class="agenda-item-type"><?php echo htmlspecialchars($item['item_type']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($item['decision_method']) && $item['decision_method'] !== 'None'): ?>
                    <span class="agenda-item-type"><?php echo htmlspecialchars($item['decision_method']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="agenda-item-details">
                    <?php if ($item['description']): ?>
                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php echo renderResolvedClauseList($item['resolutions'] ?? [], 'agenda'); ?>

                    <?php $attribution = renderAttributionLine($item, 'future'); ?>
                    <?php if ($attribution): ?>
                    <p><?php echo $attribution; ?></p>
                    <?php elseif (!empty($item['presenters'])): ?>
                    <p><strong>Presenter:</strong> <?php echo htmlspecialchars(joinPresenterNames($item['presenters'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($item['duration_minutes']): ?>
                    <p><strong>Duration:</strong> <?php echo htmlspecialchars($item['duration_minutes']); ?> minutes</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No agenda items have been added yet.</p>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p><?php echo htmlspecialchars($meeting['meeting_type_name']); ?> - Together in Council</p>
    </div>
</body>
</html>

