<?php
/**
 * Notice of Meeting Export to HTML/Print
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

// Format date
function formatDate($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('l, F j, Y');
}

function formatTime($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('g:i A');
}

function formatDateTime($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('l, F j, Y \a\t g:i A');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice of Meeting - <?php echo htmlspecialchars($meeting['title']); ?></title>
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
        
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #667eea;
            margin: 0 0 10px 0;
            font-size: 28px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .header .organization {
            font-size: 18px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .notice-content {
            background-color: #ffffff;
            padding: 15px;
            border: 2px solid #667eea;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .notice-content h2 {
            margin-top: 0;
            color: #333;
            font-size: 24px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 8px;
        }
        
        .info-row {
            margin-bottom: 8px;
            padding: 6px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 150px;
            vertical-align: top;
        }
        
        .info-value {
            display: inline-block;
            flex: 1;
            color: #333;
        }
        
        .virtual-link {
            color: #667eea;
            text-decoration: none;
            word-break: break-all;
        }
        
        .virtual-link:hover {
            text-decoration: underline;
        }
        
        .notes-section {
            margin-top: 15px;
            padding-top: 12px;
            border-top: 2px solid #eee;
        }
        
        .notes-section h3 {
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .print-buttons {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 8px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            background-color: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            border: none;
        }
        
        .btn:hover {
            background-color: #5568d3;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <?php if (defined('LOGO_URL') && LOGO_URL && file_exists(__DIR__ . '/../' . LOGO_URL)): ?>
            <img src="<?php echo '../' . LOGO_URL; ?>" alt="<?php echo APP_NAME; ?> Logo" style="max-width: <?php echo defined('LOGO_WIDTH_HTML') ? LOGO_WIDTH_HTML : 60; ?>px; height:<?php echo defined('LOGO_HEIGHT') && LOGO_HEIGHT > 0 ? LOGO_HEIGHT : 'auto'; ?>px; max-height:250px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1>Notice of Meeting</h1>
        <div class="organization"><?php echo htmlspecialchars(APP_NAME); ?></div>
    </div>

    <div class="print-buttons no-print">
        <button onclick="window.print()" class="btn">Print</button>
        <a href="notice_pdf.php?meeting_id=<?php echo $meetingId; ?>" class="btn btn-secondary" target="_blank">Download PDF</a>
        <a href="../meetings.php?id=<?php echo $meetingId; ?>" class="btn btn-secondary">Back to Meeting</a>
    </div>

    <div class="notice-content">
        <h2><?php echo htmlspecialchars($meeting['title']); ?></h2>
        
        <div class="info-row">
            <span class="info-label">Meeting Type:</span>
            <span class="info-value"><?php echo htmlspecialchars($meeting['meeting_type_name']); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Date:</span>
            <span class="info-value"><?php echo formatDate($meeting['scheduled_date']); ?></span>
        </div>
        
        <div class="info-row">
            <span class="info-label">Time:</span>
            <span class="info-value"><?php echo formatTime($meeting['scheduled_date']); ?></span>
        </div>
        
        <?php if ($meeting['location']): ?>
        <div class="info-row">
            <span class="info-label">Location:</span>
            <span class="info-value"><?php echo htmlspecialchars($meeting['location']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($meeting['virtual_link']): ?>
        <div class="info-row">
            <span class="info-label">Virtual Meeting:</span>
            <span class="info-value">
                <a href="<?php echo htmlspecialchars($meeting['virtual_link']); ?>" class="virtual-link" target="_blank">
                    <?php echo htmlspecialchars($meeting['virtual_link']); ?>
                </a>
            </span>
        </div>
        <?php endif; ?>
        
        <div class="info-row">
            <span class="info-label">Status:</span>
            <span class="info-value"><?php echo htmlspecialchars($meeting['status']); ?></span>
        </div>
        
        <?php if ($meeting['notes']): ?>
        <div class="notes-section">
            <h3>Additional Information</h3>
            <p><?php echo nl2br(htmlspecialchars($meeting['notes'])); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>This notice was generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p><?php echo htmlspecialchars(APP_NAME); ?></p>
    </div>
</body>
</html>

