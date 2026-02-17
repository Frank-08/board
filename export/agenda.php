<?php
/**
 * Agenda Export to PDF/Print
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

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

// Get agenda items with linked resolutions
$stmt = $db->prepare("
    SELECT ai.*, 
        bm.first_name as presenter_first_name, bm.last_name as presenter_last_name,
        mtm.role as presenter_role,
        r.id as resolution_id, r.title as resolution_title, r.resolution_number, r.description as resolution_description,
        r.status as resolution_status, r.vote_type
    FROM agenda_items ai
    LEFT JOIN board_members bm ON ai.presenter_id = bm.id
    LEFT JOIN meetings m ON ai.meeting_id = m.id
    LEFT JOIN meeting_type_members mtm ON bm.id = mtm.member_id AND m.meeting_type_id = mtm.meeting_type_id
    LEFT JOIN resolutions r ON ai.id = r.agenda_item_id
    WHERE ai.meeting_id = ?
    ORDER BY ai.position ASC, CASE WHEN ai.parent_id IS NULL THEN 0 ELSE 1 END ASC, ai.sub_position ASC
");
$stmt->execute([$meetingId]);
$agendaItems = $stmt->fetchAll();

// Get documents linked to agenda items
$agendaItemIds = array_filter(array_column($agendaItems, 'id'));
$documentsByAgendaItem = [];
if (!empty($agendaItemIds)) {
    $placeholders = implode(',', array_fill(0, count($agendaItemIds), '?'));
    $stmt = $db->prepare("
        SELECT d.*, d.agenda_item_id
        FROM documents d
        WHERE d.agenda_item_id IN ($placeholders)
        ORDER BY d.agenda_item_id, d.created_at ASC
    ");
    $stmt->execute($agendaItemIds);
    $allDocuments = $stmt->fetchAll();
    
    foreach ($allDocuments as $doc) {
        if (!isset($documentsByAgendaItem[$doc['agenda_item_id']])) {
            $documentsByAgendaItem[$doc['agenda_item_id']] = [];
        }
        $documentsByAgendaItem[$doc['agenda_item_id']][] = $doc;
    }
}

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/pdf.css">
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
        <a href="agenda_pdf.php?meeting_id=<?php echo $meetingId; ?>" class="btn" style="background-color: #28a745;">Download Combined PDF (with attachments)</a>
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
        <div class="organization"><?php echo htmlspecialchars($meeting['meeting_type_name']); ?></div>
        <h1>Meeting Agenda</h1>
    </div>

    <div class="meeting-info">
        <h2><?php echo htmlspecialchars($meeting['title']); ?></h2>
        <!-- <div class="info-row">
            <div class="info-label">Meeting Type:</div>
            <div class="info-value"><?php echo htmlspecialchars($meeting['meeting_type_name']); ?></div>
        </div> -->
        <div class="info-row">
            <div class="info-label">Date:</div>
            <div class="info-value"><?php echo formatDate($meeting['scheduled_date']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Time:</div>
            <div class="info-value"><?php echo formatTime($meeting['scheduled_date']); ?></div>
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
                        <span class="agenda-item-title"><?php echo htmlspecialchars($item['title']); ?></span>
                    </div>
                    <?php if ($item['item_type']): ?>
                    <span class="agenda-item-type"><?php echo htmlspecialchars($item['item_type']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="agenda-item-details">
                    <?php if ($item['description']): ?>
                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($item['resolution_id']): ?>
                    <div style="background: #e8f5e9; padding: 8px; border-radius: 4px; margin: 6px 0; border-left: 3px solid #28a745;">
                        <p style="margin: 0 0 3px 0;"><strong>📋 Linked Resolution:</strong> <?php echo htmlspecialchars($item['resolution_title']); ?></p>
                        <?php if ($item['resolution_number']): ?>
                        <p style="margin: 3px 0;"><strong>Resolution #:</strong> <?php echo htmlspecialchars($item['resolution_number']); ?></p>
                        <?php endif; ?>
                        <?php if ($item['resolution_description']): ?>
                        <p style="margin: 3px 0;"><?php echo nl2br(htmlspecialchars($item['resolution_description'])); ?></p>
                        <?php endif; ?>
                        <?php if ($item['resolution_status']): ?>
                        <p style="margin: 3px 0;"><strong>Resolution Status:</strong> <?php echo htmlspecialchars($item['resolution_status']); ?></p>
                        <?php endif; ?>
                        <?php if ($item['vote_type']): ?>
                        <p style="margin: 3px 0;"><strong>Vote Type:</strong> <?php echo htmlspecialchars($item['vote_type']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($item['presenter_first_name']): ?>
                    <p><strong>Presenter:</strong> <?php echo htmlspecialchars($item['presenter_first_name'] . ' ' . $item['presenter_last_name']); ?>
                        <?php if ($item['presenter_role']): ?>
                        (<?php echo htmlspecialchars($item['presenter_role']); ?>)
                        <?php endif; ?>
                    </p>
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

    <?php
    // Collect PDF documents attached to agenda items for display at the end
    $pdfDocuments = [];
    foreach ($agendaItems as $item) {
        if (isset($documentsByAgendaItem[$item['id']])) {
            foreach ($documentsByAgendaItem[$item['id']] as $doc) {
                // Only include PDF files
                $fileExtension = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                if ($fileExtension === 'pdf' || $doc['mime_type'] === 'application/pdf') {
                    $pdfDocuments[] = [
                        'document' => $doc,
                        'agenda_item' => $item
                    ];
                }
            }
        }
    }
    ?>
    
    <?php if (!empty($pdfDocuments)): ?>
    <div class="agenda-section">
        <h3>Attached PDF Documents</h3>
        <?php foreach ($pdfDocuments as $item): ?>
        <div class="pdf-embed-container">
            <div class="pdf-embed-header">
                <div class="pdf-embed-title"><?php echo htmlspecialchars($item['document']['title']); ?></div>
                <div class="pdf-embed-meta">
                    <strong>From Agenda Item:</strong> <?php echo htmlspecialchars($item['agenda_item']['item_number'] ?? '') . ($item['agenda_item']['item_number'] ? '. ' : ''); ?><?php echo htmlspecialchars($item['agenda_item']['title']); ?>
                    <?php if ($item['document']['description']): ?>
                    | <?php echo htmlspecialchars($item['document']['description']); ?>
                    <?php endif; ?>
                    | File: <?php echo htmlspecialchars($item['document']['file_name']); ?> 
                    (<?php echo number_format($item['document']['file_size'] / 1024, 2); ?> KB)
                </div>
            </div>
            <?php
            // Construct direct file path URL
            $filePath = $item['document']['file_path'];
            // Ensure we use just the filename if file_path contains a path
            $fileName = basename($filePath);
            // Construct absolute URL path starting with /board/uploads/
            $pdfUrl = '/uploads/' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');
            ?>
            <iframe 
                class="pdf-embed-iframe" 
                src="<?php echo $pdfUrl; ?>"
                title="<?php echo htmlspecialchars($item['document']['title']); ?>">
                <p>Your browser does not support PDFs. 
                <a href="<?php echo $pdfUrl; ?>" target="_blank">Click here to view the PDF</a>.</p>
            </iframe>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p><?php echo htmlspecialchars($meeting['meeting_type_name']); ?> - Together in Council</p>
    </div>
</body>
</html>

