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

// Build a quick map of agenda items by id for lookups
$agendaItemsById = [];
foreach ($agendaItems as $ai) {
    $agendaItemsById[$ai['id']] = $ai;
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
    
    // Build HTML content for agenda (include minimal pdf.css if present)
    $pdfCssPath = __DIR__ . '/../assets/css/pdf.css';
    $pdfCss = '';
    if (file_exists($pdfCssPath)) {
        $pdfCss = file_get_contents($pdfCssPath);
    }

    $html = ($pdfCss ? '<style>' . $pdfCss . '</style>' : '') . $logoHtml;
    $html .= '<div class="header">';
    $html .= '<div class="organization">' . htmlspecialchars($meeting['meeting_type_name']) . '</div>';
    $html .= '<h1>Meeting Agenda</h1>';
    $html .= '</div>';

    $html .= '<div class="meeting-info">';
    $html .= '<h2>' . htmlspecialchars($meeting['title']) . '</h2>';
    $html .= '<div class="info-row"><div class="info-label">Meeting Type:</div><div class="info-value">' . htmlspecialchars($meeting['meeting_type_name']) . '</div></div>';
    $html .= '<div class="info-row"><div class="info-label">Date:</div><div class="info-value">' . formatDate($meeting['scheduled_date']) . '</div></div>';
    $html .= '<div class="info-row"><div class="info-label">Time:</div><div class="info-value">' . formatTime($meeting['scheduled_date']) . '</div></div>';
    if ($meeting['location']) {
        $html .= '<div class="info-row"><div class="info-label">Location:</div><div class="info-value">' . htmlspecialchars($meeting['location']) . '</div></div>';
    }
    if ($meeting['virtual_link']) {
        $html .= '<div class="info-row"><div class="info-label">Virtual Link:</div><div class="info-value">' . htmlspecialchars($meeting['virtual_link']) . '</div></div>';
    }
    $html .= '</div>';

    // Attendees (if any)
    if (!empty($attendees)) {
        $html .= '<div class="attendees-section"><h3>Attendees</h3>';
        $html .= '<table class="attendees">';
        $col = 0;
        foreach ($attendees as $att) {
            if ($col % 2 === 0) $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars(trim($att['first_name'] . ' ' . $att['last_name'])) . ($att['role'] ? ' (' . htmlspecialchars($att['role']) . ')' : '') . '</td>';
            if ($col % 2 === 1) $html .= '</tr>';
            $col++;
        }
        if ($col % 2 === 1) $html .= '<td></td></tr>';
        $html .= '</table></div>';
    }

    // Agenda items
    $html .= '<div class="agenda-section"><h3>Agenda Items</h3>';
    if (count($agendaItems) > 0) {
        foreach ($agendaItems as $item) {
            $isChild = !empty($item['parent_id']);
            $childClass = $isChild ? ' agenda-children' : '';
            $html .= '<div class="agenda-item' . $childClass . '">';
            $html .= '<div class="agenda-item-header"><span class="agenda-item-number">' . htmlspecialchars($item['item_number'] ?? '?') . '.</span><span class="agenda-item-title">' . htmlspecialchars($item['title']) . '</span></div>';
            if ($item['item_type']) { $html .= '<span class="agenda-item-type">' . htmlspecialchars($item['item_type']) . '</span>'; }
            $html .= '</div>';
            $html .= '<div class="agenda-item-details">';
            if ($item['description']) { $html .= '<p><strong>Description:</strong> ' . nl2br(htmlspecialchars($item['description'])) . '</p>'; }
            if ($item['resolution_id']) {
                $html .= '<div class="resolution">';
                $html .= '<p style="margin: 0 0 3px 0;"><strong>Linked Resolution:</strong> ' . htmlspecialchars($item['resolution_title']) . '</p>';
                if ($item['resolution_number']) { $html .= '<p style="margin: 3px 0;"><strong>Resolution #:</strong> ' . htmlspecialchars($item['resolution_number']) . '</p>'; }
                if ($item['resolution_description']) { $html .= '<p style="margin: 3px 0;">' . nl2br(htmlspecialchars($item['resolution_description'])) . '</p>'; }
                if ($item['resolution_status']) { $html .= '<p style="margin: 3px 0;"><strong>Resolution Status:</strong> ' . htmlspecialchars($item['resolution_status']) . '</p>'; }
                if ($item['vote_type']) { $html .= '<p style="margin: 3px 0;"><strong>Vote Type:</strong> ' . htmlspecialchars($item['vote_type']) . '</p>'; }
                $html .= '</div>';
            }
            if ($item['presenter_first_name']) { $html .= '<p><strong>Presenter:</strong> ' . htmlspecialchars($item['presenter_first_name'] . ' ' . $item['presenter_last_name']); if ($item['presenter_role']) { $html .= ' (' . htmlspecialchars($item['presenter_role']) . ')'; } $html .= '</p>'; }
            if ($item['duration_minutes']) { $html .= '<p><strong>Duration:</strong> ' . htmlspecialchars($item['duration_minutes']) . ' minutes</p>'; }
            $html .= '</div></div>';
        }
    } else {
        $html .= '<p>No agenda items have been added yet.</p>';
    }
    $html .= '</div>';

    // Attached PDFs (list metadata)
    $html .= '<div class="agenda-section"><h3>Attached PDF Documents</h3>';
    $anyPdf = false;
    foreach ($pdfDocuments as $doc) {
        $ai = isset($agendaItemsById[$doc['agenda_item_id']]) ? $agendaItemsById[$doc['agenda_item_id']] : null;
        $html .= '<div class="pdf-embed-container"><div class="pdf-embed-header">';
        $html .= '<div class="pdf-embed-title">' . htmlspecialchars($doc['title']) . '</div>';
        $html .= '<div class="pdf-embed-meta"><strong>From Agenda Item:</strong> ' . ($ai ? (htmlspecialchars($ai['item_number'] ?? '') . ($ai['item_number'] ? '. ' : '')) . htmlspecialchars($ai['title']) : 'N/A');
        // if ($doc['description']) { $html .= ' | ' . htmlspecialchars($doc['description']); }
        // $html .= ' | File: ' . htmlspecialchars($doc['file_name']) . ' (' . number_format($doc['file_size'] / 1024, 2) . ' KB)';
        $html .= '</div></div></div>';
        $anyPdf = true;
    }
    if (!$anyPdf) { $html .= '<p>No attached PDFs.</p>'; }
    $html .= '</div>';    
    // Write HTML content
    $pdf->writeHTML($html, true, false, true, false, '');
    
    $uploadDir = rtrim(realpath(UPLOAD_DIR) ?: UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    
    // Try to merge attached PDFs
    if (!empty($pdfDocuments)) {
        // First, save the agenda PDF to a temporary file
        $tempAgendaPdf = $tempDir . '/agenda_' . $meetingId . '_' . time() . '.pdf';
        $pdf->Output($tempAgendaPdf, 'F');
        
        // Collect all PDF file paths (prepend attachment metadata pages)
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
                // Create a small metadata PDF for this attachment to preserve context when merged
                try {
                    $metaPdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                    $metaPdf->SetCreator('Together in Council');
                    $metaPdf->SetAuthor($meeting['meeting_type_name']);
                    $metaPdf->SetTitle('Attachment: ' . $doc['file_name']);
                    $metaPdf->setPrintHeader(false);
                    $metaPdf->setPrintFooter(false);
                    $metaPdf->SetMargins(15, 15, 15);
                    $metaPdf->AddPage();

                    $ai = isset($agendaItemsById[$doc['agenda_item_id']]) ? $agendaItemsById[$doc['agenda_item_id']] : null;
                    $metaHtml = '<h2>Attachment: ' . htmlspecialchars($doc['title']) . '</h2>';
                    if ($ai) {
                        $metaHtml .= '<p><strong>From Agenda Item:</strong> ' . htmlspecialchars($ai['item_number'] ?? '') . ' ' . htmlspecialchars($ai['title']) . '</p>';
                    }
                    if ($doc['description']) {
                        $metaHtml .= '<p>' . nl2br(htmlspecialchars($doc['description'])) . '</p>';
                    }
                    $metaHtml .= '<p><strong>Filename:</strong> ' . htmlspecialchars($doc['file_name']) . ' (' . number_format($doc['file_size'] / 1024, 2) . ' KB)</p>';
                    $metaPdf->writeHTML($metaHtml, true, false, true, false, '');

                    $metaFile = $tempDir . '/attachment_meta_' . $doc['id'] . '_' . time() . '.pdf';
                    $metaPdf->Output($metaFile, 'F');

                    $pdfFiles[] = $metaFile;
                } catch (Exception $e) {
                    error_log("Failed creating meta PDF for attachment {$doc['id']}: " . $e->getMessage());
                }

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
                //readfile($mergedPdf);
                readfile($html)
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

