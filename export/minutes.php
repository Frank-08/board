<?php
/**
 * Meeting Minutes Export to PDF/Print
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$meetingId = isset($_GET['meeting_id']) ? (int)$_GET['meeting_id'] : 0;

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

// Get agenda items with comments and resolutions
$stmt = $db->prepare("
    SELECT ai.*, 
        bm.first_name as presenter_first_name, bm.last_name as presenter_last_name,
        mtm.role as presenter_role,
        mac.comment as minutes_comment,
        r.resolution_number, r.title as resolution_title, r.status as resolution_status
    FROM agenda_items ai
    LEFT JOIN board_members bm ON ai.presenter_id = bm.id
    LEFT JOIN meetings m ON ai.meeting_id = m.id
    LEFT JOIN meeting_type_members mtm ON bm.id = mtm.member_id AND m.meeting_type_id = mtm.meeting_type_id
    LEFT JOIN minutes_agenda_comments mac ON ai.id = mac.agenda_item_id AND mac.minutes_id = ?
    LEFT JOIN resolutions r ON ai.id = r.agenda_item_id
    WHERE ai.meeting_id = ?
    ORDER BY ai.position ASC
");
$stmt->execute([$minutes['id'], $meetingId]);
$agendaItems = $stmt->fetchAll();

// Get attendees with their role in the meeting's meeting type
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

// Format date
function formatDate($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('F j, Y');
}

function formatTime($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('g:i A');
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
        .status-passed { background: #28a745; color: #fff; }
        .status-failed { background: #dc3545; color: #fff; }
        .status-tabled { background: #ffc107; color: #000; }
        .status-withdrawn { background: #6c757d; color: #fff; }
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
                 style="max-width:<?php echo defined('LOGO_WIDTH_HTML') ? LOGO_WIDTH : 60; ?>px; height:<?php echo defined('LOGO_HEIGHT') && LOGO_HEIGHT > 0 ? LOGO_HEIGHT : 'auto'; ?>px; max-height:80px;">
        </div>
        <?php endif; ?>
        <h1>Meeting Minutes</h1>
        <p><?php echo htmlspecialchars($meeting['meeting_type_name']); ?></p>
    </div>
    
    <div class="meeting-info">
        <p><strong>Meeting:</strong> <?php echo htmlspecialchars($meeting['title']); ?></p>
        <p><strong>Date:</strong> <?php echo formatDate($meeting['scheduled_date']); ?></p>
        <p><strong>Time:</strong> <?php echo formatTime($meeting['scheduled_date']); ?></p>
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
        <div class="attendee-list">
            <?php foreach ($attendees as $attendee): ?>
            <div class="attendee-item">
                <strong><?php echo htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']); ?></strong>
                <?php if (!empty($attendee['role'])): ?>
                <span style="color: #666; font-size: 12px;"><?php echo htmlspecialchars($attendee['role']); ?></span>
                <?php endif; ?>
                <?php if ($attendee['title']): ?>
                <br><span style="color: #999; font-size: 11px;"><?php echo htmlspecialchars($attendee['title']); ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (count($agendaItems) > 0): ?>
    <div class="section">
        <h2>Agenda Items & Discussion</h2>
        <?php foreach ($agendaItems as $item): ?>
        <div class="agenda-item">
            <h4>
                <?php echo htmlspecialchars($item['item_number'] ?? '') . ($item['item_number'] ? '. ' : ''); ?>
                <?php echo htmlspecialchars($item['title']); ?>
                <?php if ($item['resolution_number']): ?>
                <span style="color: #007bff; font-weight: normal; margin-left: 10px;">(Resolution #<?php echo htmlspecialchars($item['resolution_number']); ?>)</span>
                <?php endif; ?>
                <?php if ($item['resolution_status']): ?>
                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $item['resolution_status'])); ?>" style="margin-left: 8px; padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: bold;">
                    <?php echo htmlspecialchars($item['resolution_status']); ?>
                </span>
                <?php endif; ?>
            </h4>
            <?php if ($item['description']): ?>
            <div class="item-description"><?php echo nl2br(htmlspecialchars($item['description'])); ?></div>
            <?php endif; ?>
            <?php if ($item['presenter_first_name']): ?>
            <p style="font-size: 14px; color: #666; margin: 8px 0;">
                <strong>Presenter:</strong> <?php echo htmlspecialchars($item['presenter_first_name'] . ' ' . $item['presenter_last_name']); ?>
                <?php if ($item['presenter_role']): ?>
                (<?php echo htmlspecialchars($item['presenter_role']); ?>)
                <?php endif; ?>
            </p>
            <?php endif; ?>
            <?php if (!empty($item['minutes_comment'])): ?>
            <div class="agenda-comment">
                <strong>Discussion/Comments:</strong>
                <div><?php echo nl2br(htmlspecialchars($item['minutes_comment'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
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


