<?php
/**
 * Agenda Export to Combined PDF (Agenda + Attached PDFs)
 * 
 * This endpoint generates a single PDF containing:
 * 1. The agenda content (converted from HTML)
 * 2. All attached PDF documents appended
 * 
 * Requirements: TCPDF library for PDF generation
 * Optional: FPDI library for PDF merging (if available)
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

// Get PDF documents attached to agenda items
$agendaItemIds = array_filter(array_column($agendaItems, 'id'));
$pdfDocuments = [];
if (!empty($agendaItemIds)) {
    $placeholders = implode(',', array_fill(0, count($agendaItemIds), '?'));
    $stmt = $db->prepare("
        SELECT d.*
        FROM documents d
        WHERE d.agenda_item_id IN ($placeholders)
        ORDER BY d.agenda_item_id, d.created_at ASC
    ");
    $stmt->execute($agendaItemIds);
    $allDocuments = $stmt->fetchAll();
    
    foreach ($allDocuments as $doc) {
        $fileExtension = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
        if ($fileExtension === 'pdf' || $doc['mime_type'] === 'application/pdf') {
            $pdfDocuments[] = $doc;
        }
    }
}

// Format date functions
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

// Set up temporary directory and system commands
$tempDir = sys_get_temp_dir();

// Check for PDF merging tools (pdftk, ghostscript, or pdfunite)
$useSystemCommand = false;
$mergeCommand = null;

if (shell_exec('which pdftk')) {
    $useSystemCommand = true;
    $mergeCommand = 'pdftk';
} elseif (shell_exec('which gs')) {
    $useSystemCommand = true;
    $mergeCommand = 'gs';
} elseif (shell_exec('which pdfunite')) {
    $useSystemCommand = true;
    $mergeCommand = 'pdfunite';
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

// Build HTML content for agenda
$cssPath = __DIR__ . '/../assets/css/pdf.css';
$css = (file_exists($cssPath) ? file_get_contents($cssPath) : '');

// Add logo if configured and exists
$logoHtml = '';
if (defined('LOGO_PATH') && LOGO_PATH && file_exists(LOGO_PATH)) {
    $logoWidth = defined('LOGO_WIDTH') ? LOGO_WIDTH : 250;
    $logoHtml = '<div style="text-align:center; margin-bottom:15px;"><img src="' . LOGO_PATH . '" style="max-width:' . $logoWidth . 'px; height:50px;" alt="Logo"></div>';
}

// Build the HTML document
$html = '<!DOCTYPE html>';
$html .= '<html>';
$html .= '<head>';
$html .= '<meta charset="UTF-8">';
$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
$html .= '<title>Meeting Agenda</title>';
if ($css) {
    $html .= '<style>' . $css . '</style>';
}
$html .= '<style>';
$html .= 'body { font-family: Arial, sans-serif; margin: 20px; }';
$html .= '.header { text-align: center; margin-bottom: 30px; }';
$html .= '.header h1 { margin: 0 0 10px 0; color: #333; }';
$html .= '.organization { font-size: 18px; color: #666; margin-bottom: 20px; }';
$html .= '.meeting-info { margin-bottom: 30px; }';
$html .= '.meeting-info h2 { color: #333; margin: 0 0 15px 0; }';
$html .= '.meeting-info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }';
$html .= '.meeting-info-table tr { border-bottom: 1px solid #ddd; }';
$html .= '.info-label { font-weight: bold; width: 150px; padding: 8px; }';
$html .= '.info-value { padding: 8px; }';
$html .= '.agenda-section { margin-top: 30px; }';
$html .= '.agenda-section h3 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }';
$html .= '.agenda-table { width: 100%; border-collapse: collapse; }';
$html .= '.agenda-table tr { border-bottom: 1px solid #ddd; }';
$html .= '.item-number { font-weight: bold; color: #667eea; font-size: 14px; width: 20%; padding: 10px; }';
$html .= '.item-title { font-weight: bold; font-size: 14px; color: #333; width: 65%; padding: 10px; }';
$html .= '.item-type { width: 15%; padding: 10px; text-align: right; }';
$html .= '.item-type-badge { background-color: #667eea; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; white-space: nowrap; }';
$html .= '.item-detail { padding: 3px 10px; font-size: 12px; color: #555; }';
$html .= '.item-detail-row { padding: 8px 10px; }';
$html .= '.resolution-box { background-color: #c8e6c9; border-left: 4px solid #28a745; padding: 8px 10px; }';
$html .= '.resolution-box p { margin: 3px 0; font-size: 11px; }';
$html .= '</style>';
$html .= '</head>';
$html .= '<body>';
$html .= $logoHtml;
$html .= '<div class="header">';
$html .= '<h1>Meeting Agenda</h1>';
$html .= '<div class="organization">' . htmlspecialchars($meeting['meeting_type_name']) . '</div>';
$html .= '</div>';
$html .= '<div class="meeting-info">';
$html .= '<h2>' . htmlspecialchars($meeting['title']) . '</h2>';
$html .= '<table class="meeting-info-table">';
$html .= '<tr><td class="info-label">Date:</td><td class="info-value">' . formatDate($meeting['scheduled_date']) . '</td></tr>';
$html .= '<tr><td class="info-label">Time:</td><td class="info-value">' . formatTime($meeting['scheduled_date']) . '</td></tr>';
if ($meeting['location']) {
    $html .= '<tr><td class="info-label">Location:</td><td class="info-value">' . htmlspecialchars($meeting['location']) . '</td></tr>';
}
if ($meeting['virtual_link']) {
    $html .= '<tr><td class="info-label">Virtual Link:</td><td class="info-value">' . htmlspecialchars($meeting['virtual_link']) . '</td></tr>';
}
$html .= '</table>';
$html .= '</div>';

// Agenda items
$html .= '<div class="agenda-section">';
$html .= '<h3>Agenda Items</h3>';

if (count($agendaItems) > 0) {
    $html .= '<table class="agenda-table">';
    foreach ($agendaItems as $item) {
        $isChild = !empty($item['parent_id']);
        $paddingLeft = $isChild ? '30px' : '0px';
        
        // Item header row
        $html .= '<tr>';
        $html .= '<td class="item-number" style="padding-left: ' . $paddingLeft . ';">' . htmlspecialchars($item['item_number'] ?? '?') . '.</td>';
        $html .= '<td class="item-title">' . htmlspecialchars($item['title']) . '</td>';
        $html .= '<td class="item-type">';
        if (!empty($item['item_type'])) {
            $html .= '<span class="item-type-badge">' . htmlspecialchars($item['item_type']) . '</span>';
        }
        $html .= '</td>';
        $html .= '</tr>';
        
        // Description row
        if ($item['description']) {
            $html .= '<tr><td colspan="3" class="item-detail" style="padding-left: ' . $paddingLeft . ';">';
            $html .= '<strong>Description:</strong> ' . nl2br(htmlspecialchars($item['description']));
            $html .= '</td></tr>';
        }
        
        // Resolution row
        if (!empty($item['resolution_id'])) {
            $html .= '<tr><td colspan="3" class="item-detail-row resolution-box">';
            $html .= '<p style="margin: 0 0 3px 0; font-weight: bold;">Linked Resolution: ' . htmlspecialchars($item['resolution_title']) . '</p>';
            if (!empty($item['resolution_number'])) {
                $html .= '<p><strong>Resolution #:</strong> ' . htmlspecialchars($item['resolution_number']) . '</p>';
            }
            if (!empty($item['resolution_description'])) {
                $html .= '<p>' . nl2br(htmlspecialchars($item['resolution_description'])) . '</p>';
            }
            if (!empty($item['resolution_status'])) {
                $html .= '<p><strong>Status:</strong> ' . htmlspecialchars($item['resolution_status']) . '</p>';
            }
            if (!empty($item['vote_type'])) {
                $html .= '<p><strong>Vote Type:</strong> ' . htmlspecialchars($item['vote_type']) . '</p>';
            }
            $html .= '</td></tr>';
        }
        
        // Presenter row
        if ($item['presenter_first_name']) {
            $html .= '<tr><td colspan="3" class="item-detail" style="padding-left: ' . $paddingLeft . ';">';
            $html .= '<strong>Presenter:</strong> ' . htmlspecialchars($item['presenter_first_name'] . ' ' . $item['presenter_last_name']);
            if (!empty($item['presenter_role'])) {
                $html .= ' (' . htmlspecialchars($item['presenter_role']) . ')';
            }
            $html .= '</td></tr>';
        }
        
        // Duration row
        if ($item['duration_minutes']) {
            $html .= '<tr><td colspan="3" class="item-detail" style="padding-left: ' . $paddingLeft . ';">';
            $html .= '<strong>Duration:</strong> ' . htmlspecialchars($item['duration_minutes']) . ' minutes';
            $html .= '</td></tr>';
        }
    }
    $html .= '</table>';
} else {
    $html .= '<p>No agenda items have been added yet.</p>';
}

$html .= '</div>';
$html .= '</body></html>';

$uploadDir = rtrim(realpath(UPLOAD_DIR) ?: UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$tempAgendaPdf = $tempDir . '/agenda_' . $meetingId . '_' . time() . '.pdf';

if ($useTCPDF && class_exists('TCPDF')) {
    // Use TCPDF to render HTML to PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Together in Council');
    $pdf->SetAuthor($meeting['meeting_type_name']);
    $pdf->SetTitle('Meeting Agenda - ' . $meeting['title']);
    $pdf->SetSubject('Meeting Agenda');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add first page
    $pdf->AddPage();
    
    // Write HTML content using TCPDF's HTML renderer
    // This renders the HTML exactly as styled, maintaining layout and CSS
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Save to temporary file
    $pdf->Output($tempAgendaPdf, 'F');
} else {
    // Fallback: Redirect to HTML version if TCPDF not available
    header('Location: agenda.php?meeting_id=' . $meetingId);
    exit;
}

// Try to merge attached PDFs with the agenda PDF
if (!empty($pdfDocuments)) {
    // Collect all PDF file paths
    $pdfFiles = [$tempAgendaPdf];
    foreach ($pdfDocuments as $doc) {
        $filePath = null;
        $storedPath = $doc['file_path'];
        
        // Find the file using multiple strategies
        if (file_exists($uploadDir . $storedPath)) {
            $filePath = $uploadDir . $storedPath;
        } elseif (file_exists($uploadDir . basename($storedPath))) {
            $filePath = $uploadDir . basename($storedPath);
        } elseif (file_exists($storedPath)) {
            $filePath = $storedPath;
        }
        
        if ($filePath && file_exists($filePath)) {
            $pdfFiles[] = $filePath;
        }
    }
    
    // Try to merge using system commands
    if ($useSystemCommand && count($pdfFiles) > 1) {
        $mergedPdf = $tempDir . '/merged_' . $meetingId . '_' . time() . '.pdf';
        $success = false;
        
        if ($mergeCommand === 'pdftk') {
            $cmd = 'pdftk ' . implode(' ', array_map('escapeshellarg', $pdfFiles)) . ' cat output ' . escapeshellarg($mergedPdf) . ' 2>&1';
            exec($cmd, $output, $returnVar);
            $success = ($returnVar === 0 && file_exists($mergedPdf));
        } elseif ($mergeCommand === 'gs') {
            $cmd = 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($mergedPdf) . ' ' . implode(' ', array_map('escapeshellarg', $pdfFiles)) . ' 2>&1';
            exec($cmd, $output, $returnVar);
            $success = ($returnVar === 0 && file_exists($mergedPdf));
        } elseif ($mergeCommand === 'pdfunite') {
            $cmd = 'pdfunite ' . implode(' ', array_map('escapeshellarg', $pdfFiles)) . ' ' . escapeshellarg($mergedPdf) . ' 2>&1';
            exec($cmd, $output, $returnVar);
            $success = ($returnVar === 0 && file_exists($mergedPdf));
        }
        
        if ($success) {
            // Output merged PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="agenda_' . $meetingId . '_combined.pdf"');
            readfile($mergedPdf);
            // Cleanup
            @unlink($tempAgendaPdf);
            @unlink($mergedPdf);
            exit;
        }
    }
    
    // Try FPDI if system merge commands didn't work
    $fpdiPath = __DIR__ . '/../vendor/setasign/fpdi/src/autoload.php';
    $useFPDI = false;
    if (file_exists($fpdiPath)) {
        require_once($fpdiPath);
        $useFPDI = class_exists('setasign\Fpdi\Tcpdf\Fpdi');
    }
    
    if ($useFPDI) {
        // Use FPDI to merge agenda PDF with attached PDFs
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Import pages from all PDFs (agenda first, then attached)
        foreach ($pdfFiles as $file) {
            if (file_exists($file)) {
                try {
                    $pageCount = $pdf->setSourceFile($file);
                    for ($i = 1; $i <= $pageCount; $i++) {
                        $tplIdx = $pdf->importPage($i);
                        $pdf->AddPage();
                        $pdf->useTemplate($tplIdx);
                    }
                } catch (Exception $e) {
                    error_log("Error merging PDF: " . $e->getMessage());
                }
            }
        }
        
        // Cleanup temp file
        @unlink($tempAgendaPdf);
        $pdf->Output('agenda_' . $meetingId . '_combined.pdf', 'D');
        exit;
    }
    
    // If merging failed, output agenda PDF only
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="agenda_' . $meetingId . '.pdf"');
    readfile($tempAgendaPdf);
    @unlink($tempAgendaPdf);
    exit;
}

// Output agenda PDF only (no attachments to merge)
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="agenda_' . $meetingId . '.pdf"');
readfile($tempAgendaPdf);
@unlink($tempAgendaPdf);
exit;

