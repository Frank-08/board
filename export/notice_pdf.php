<?php
/**
 * Notice of Meeting Export to PDF
 * 
 * This endpoint generates a PDF containing the meeting notice
 * 
 * Requirements: TCPDF library for PDF generation
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

// Format date functions
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

// Try to use TCPDF if available
$useTCPDF = false;
$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (file_exists($tcpdfPath)) {
    require_once($tcpdfPath);
    $useTCPDF = true;
} else {
    // Check for TCPDF in common locations
    $commonPaths = [
        __DIR__ . '/../tcpdf/tcpdf.php',
        __DIR__ . '/../libs/tcpdf/tcpdf.php',
        '/usr/share/php/tcpdf/tcpdf.php'
    ];
    foreach ($commonPaths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $useTCPDF = true;
            break;
        }
    }
}

if ($useTCPDF && class_exists('TCPDF')) {
    // Generate PDF using TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Governance Board Management System');
    $pdf->SetAuthor($meeting['meeting_type_name']);
    $pdf->SetTitle('Notice of Meeting - ' . $meeting['title']);
    $pdf->SetSubject('Meeting Notice');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add first page
    $pdf->AddPage();
    
    // Add logo if configured and exists
    $logoHtml = '';
    if (defined('LOGO_PATH') && LOGO_PATH && file_exists(LOGO_PATH)) {
        $logoWidth = defined('LOGO_WIDTH') ? LOGO_WIDTH : 60;
        $logoHeight = defined('LOGO_HEIGHT') ? LOGO_HEIGHT : 0;
        try {
            // Try to add logo as image in PDF
            // If height is 0, TCPDF will auto-calculate to maintain aspect ratio
            if ($logoHeight > 0) {
                $pdf->Image(LOGO_PATH, ($pdf->getPageWidth() - $logoWidth) / 2, 15, $logoWidth, $logoHeight, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $logoY = $logoHeight + 5;
            } else {
                // Auto-calculate height by passing empty string or 0
                $pdf->Image(LOGO_PATH, ($pdf->getPageWidth() - $logoWidth) / 2, 15, $logoWidth, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $logoY = 25; // Default spacing after logo
            }
            $pdf->SetY($logoY + 10);
        } catch (Exception $e) {
            // If image fails, add to HTML instead
            $logoHtml = '<div style="text-align:center; margin-bottom:15px;"><img src="file://' . str_replace('\\', '/', realpath(LOGO_PATH)) . '" style="max-width:' . $logoWidth . 'mm; height:auto;" alt="Logo"></div>';
            $pdf->SetY(15);
        }
    } else {
        $pdf->SetY(15);
    }
    
    // Build HTML content for notice
    $html = $logoHtml;
    $html .= '<h1 style="text-align:center; color:#667eea; font-size:24px; text-transform:uppercase; letter-spacing:2px;">NOTICE OF MEETING</h1>';
    $html .= '<div style="text-align:center; margin-bottom:20px; color:#666; font-size:14px;">' . htmlspecialchars(APP_NAME) . '</div>';
    
    $html .= '<div style="border:2px solid #667eea; padding:20px; margin-bottom:20px; border-radius:5px;">';
    $html .= '<h2 style="margin-top:0; font-size:20px; color:#333; border-bottom:2px solid #667eea; padding-bottom:10px;">' . htmlspecialchars($meeting['title']) . '</h2>';
    
    $html .= '<div style="margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #eee;">';
    $html .= '<strong style="color:#555; display:inline-block; width:150px;">Meeting Type:</strong>';
    $html .= '<span style="color:#333;">' . htmlspecialchars($meeting['meeting_type_name']) . '</span>';
    $html .= '</div>';
    
    $html .= '<div style="margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #eee;">';
    $html .= '<strong style="color:#555; display:inline-block; width:150px;">Date:</strong>';
    $html .= '<span style="color:#333;">' . formatDate($meeting['scheduled_date']) . '</span>';
    $html .= '</div>';
    
    $html .= '<div style="margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #eee;">';
    $html .= '<strong style="color:#555; display:inline-block; width:150px;">Time:</strong>';
    $html .= '<span style="color:#333;">' . formatTime($meeting['scheduled_date']) . '</span>';
    $html .= '</div>';
    
    if ($meeting['location']) {
        $html .= '<div style="margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #eee;">';
        $html .= '<strong style="color:#555; display:inline-block; width:150px;">Location:</strong>';
        $html .= '<span style="color:#333;">' . htmlspecialchars($meeting['location']) . '</span>';
        $html .= '</div>';
    }
    
    if ($meeting['virtual_link']) {
        $html .= '<div style="margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #eee;">';
        $html .= '<strong style="color:#555; display:inline-block; width:150px;">Virtual Meeting:</strong>';
        $html .= '<span style="color:#667eea;">' . htmlspecialchars($meeting['virtual_link']) . '</span>';
        $html .= '</div>';
    }
    
    $html .= '<div style="margin-bottom:12px; padding-bottom:10px;">';
    $html .= '<strong style="color:#555; display:inline-block; width:150px;">Status:</strong>';
    $html .= '<span style="color:#333;">' . htmlspecialchars($meeting['status']) . '</span>';
    $html .= '</div>';
    
    if ($meeting['notes']) {
        $html .= '<div style="margin-top:20px; padding-top:20px; border-top:2px solid #eee;">';
        $html .= '<h3 style="color:#667eea; margin-bottom:10px; font-size:16px;">Additional Information</h3>';
        $html .= '<p style="color:#333; font-size:12px;">' . nl2br(htmlspecialchars($meeting['notes'])) . '</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    // Footer
    $html .= '<div style="text-align:center; margin-top:30px; padding-top:15px; border-top:1px solid #ddd; color:#666; font-size:10px;">';
    $html .= '<p>This notice was generated on ' . date('F j, Y \a\t g:i A') . '</p>';
    $html .= '<p>' . htmlspecialchars(APP_NAME) . ' - Meeting Management System</p>';
    $html .= '</div>';
    
    // Write HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Output PDF
    $pdf->Output('notice_of_meeting_' . $meetingId . '.pdf', 'D');
    exit;
} else {
    // Fallback: redirect to HTML version
    header('Location: notice.php?meeting_id=' . $meetingId);
    exit;
}

