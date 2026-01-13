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

// Try to use system commands for PDF merging (pdftk, ghostscript, or pdfunite)
$useSystemCommand = false;
$mergeCommand = null;
$tempDir = sys_get_temp_dir();

// Check for pdftk
if (shell_exec('which pdftk')) {
    $useSystemCommand = true;
    $mergeCommand = 'pdftk';
} 
// Check for ghostscript (gs command)
elseif (shell_exec('which gs')) {
    $useSystemCommand = true;
    $mergeCommand = 'gs';
}
// Check for pdfunite (poppler-utils)
elseif (shell_exec('which pdfunite')) {
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

if ($useTCPDF && class_exists('TCPDF')) {
    // Generate PDF using TCPDF
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
    
    // Add logo if configured and exists
    $logoHtml = '';
    if (defined('LOGO_PATH') && LOGO_PATH && file_exists(LOGO_PATH)) {
        $logoWidth = defined('LOGO_WIDTH') ? LOGO_WIDTH : 60;
        $logoHeight = defined('LOGO_HEIGHT') ? LOGO_HEIGHT : 0;
        try {
            // Try to add logo as image in PDF
            $pdf->Image(LOGO_PATH, $pdf->getPageWidth() - $logoWidth - 15, 15, $logoWidth, $logoHeight);
            $logoY = $logoHeight > 0 ? $logoHeight + 5 : 25;
            $pdf->SetY($logoY);
        } catch (Exception $e) {
            // If image fails, add to HTML instead
            $logoHtml = '<div style="text-align:center; margin-bottom:15px;"><img src="' . LOGO_PATH . '" style="max-width:' . $logoWidth . 'mm; height:auto;" alt="Logo"></div>';
        }
    }
    
    // Build HTML content for agenda
    $cssPath = __DIR__ . '/../assets/css/agenda.css';
    $css = (file_exists($cssPath) ? file_get_contents($cssPath) : '');
    $html = ($css ? '<style>' . $css . '</style>' : '') . $logoHtml;
    $html .= $logoHtml;
    $html .= '<h1 style="text-align:center; color:#667eea; font-size:24px;">Meeting Agenda</h1>';
    $html .= '<div style="text-align:center; margin-bottom:20px; color:#666;">' . htmlspecialchars($meeting['meeting_type_name']) . '</div>';
    
    $html .= '<div style="background:#f5f5f5; padding:15px; margin-bottom:20px; border-radius:5px;">';
    $html .= '<h2 style="margin-top:0; font-size:18px;">' . htmlspecialchars($meeting['title']) . '</h2>';
    $html .= '<p><strong>Meeting Type:</strong> ' . htmlspecialchars($meeting['meeting_type_name']) . '</p>';
    $html .= '<p><strong>Date:</strong> ' . formatDate($meeting['scheduled_date']) . '</p>';
    $html .= '<p><strong>Time:</strong> ' . formatTime($meeting['scheduled_date']) . '</p>';
    if ($meeting['location']) {
        $html .= '<p><strong>Location:</strong> ' . htmlspecialchars($meeting['location']) . '</p>';
    }
    if ($meeting['virtual_link']) {
        $html .= '<p><strong>Virtual Link:</strong> ' . htmlspecialchars($meeting['virtual_link']) . '</p>';
    }
    $html .= '</div>';
    
    // Agenda items
    $html .= '<h3 style="color:#667eea; border-bottom:2px solid #667eea; padding-bottom:5px;">Agenda Items</h3>';
    foreach ($agendaItems as $item) {
        $isChild = !empty($item['parent_id']);
        $childStyle = $isChild ? 'margin-left:20px;' : '';
        $html .= '<div style="' . $childStyle . 'margin-bottom:15px; padding:10px; background:#f9f9f9; border-left:4px solid #667eea;">';
        $html .= '<h4 style="margin:0 0 10px 0; font-size:14px;">';
        if ($item['item_number']) {
            $html .= htmlspecialchars($item['item_number']) . '. ';
        }
        $html .= htmlspecialchars($item['title']) . '</h4>';
        if ($item['description']) {
            $html .= '<p style="margin:5px 0; font-size:12px;">' . nl2br(htmlspecialchars($item['description'])) . '</p>';
        }
        if ($item['presenter_first_name']) {
            $html .= '<p style="margin:5px 0; font-size:11px; color:#666;"><strong>Presenter:</strong> ' . 
                     htmlspecialchars($item['presenter_first_name'] . ' ' . $item['presenter_last_name']) . '</p>';
        }
        if ($item['duration_minutes']) {
            $html .= '<p style="margin:5px 0; font-size:11px; color:#666;"><strong>Duration:</strong> ' . 
                     htmlspecialchars($item['duration_minutes']) . ' minutes</p>';
        }
        $html .= '</div>';
    }
    
    // Write HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    $uploadDir = rtrim(realpath(UPLOAD_DIR) ?: UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    
    // Try to merge attached PDFs
    if (!empty($pdfDocuments)) {
        // First, save the agenda PDF to a temporary file
        $tempAgendaPdf = $tempDir . '/agenda_' . $meetingId . '_' . time() . '.pdf';
        $pdf->Output($tempAgendaPdf, 'F');
        
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
        
        // Try FPDI if system commands didn't work
        $fpdiPath = __DIR__ . '/../vendor/setasign/fpdi/src/autoload.php';
        $useFPDI = false;
        if (file_exists($fpdiPath)) {
            require_once($fpdiPath);
            $useFPDI = class_exists('setasign\Fpdi\Tcpdf\Fpdi');
        }
        
        if ($useFPDI) {
            // Recreate PDF with FPDI support
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(TRUE, 15);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Import pages from attached PDFs
            foreach ($pdfFiles as $idx => $file) {
                if ($idx === 0) continue; // Skip agenda PDF (already added)
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
        
        // If merging failed, output agenda PDF with note
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="agenda_' . $meetingId . '.pdf"');
        readfile($tempAgendaPdf);
        @unlink($tempAgendaPdf);
        exit;
    }
    
    // Output PDF (no attachments to merge)
    $pdf->Output('agenda_' . $meetingId . '.pdf', 'D');
    exit;
} else {
    // Fallback: Redirect to HTML version if TCPDF not available
    header('Location: agenda.php?meeting_id=' . $meetingId);
    exit;
}

