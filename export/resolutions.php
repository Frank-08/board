<?php
/**
 * Resolutions Register Export
 *
 * Every resolution across all meetings (filtered as on resolutions.php),
 * grouped under headings by category / meeting type / year / status /
 * decision method, as a printable HTML page (?format=html, default - use
 * the browser's Print / Save as PDF) or a Word download (?format=word).
 *
 * Query params: group_by, format, plus the register filters read by
 * readResolutionRegisterFilters() (meeting_type_id, status, category,
 * from, to, q).
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/resolution_helpers.php';

// requireLogin() redirects relative to the current directory, which would
// point at export/login.php from here.
if (!isLoggedIn()) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
if (!hasPermission('view_resolutions')) {
    http_response_code(403);
    die('You do not have permission to view resolutions');
}

$db = getDBConnection();

$filters = readResolutionRegisterFilters($_GET);
$groupBy = $_GET['group_by'] ?? 'category';
if (!isset(RESOLUTION_REGISTER_GROUPINGS[$groupBy])) {
    $groupBy = 'category';
}
$format = ($_GET['format'] ?? 'html') === 'word' ? 'word' : 'html';

$rows = fetchResolutionRegister($db, $filters);
$groups = groupResolutionRegister($rows, $groupBy);

// Describe the filters applied, for the document's subtitle
$filterParts = [];
if ($filters['meeting_type_id']) {
    $stmt = $db->prepare("SELECT name FROM meeting_types WHERE id = ?");
    $stmt->execute([$filters['meeting_type_id']]);
    $name = $stmt->fetchColumn();
    if ($name) {
        $filterParts[] = $name;
    }
}
if ($filters['status'] === 'adopted') {
    $filterParts[] = 'Adopted resolutions (Consensus or Agreement)';
} elseif ($filters['status'] !== '') {
    $filterParts[] = 'Status: ' . $filters['status'];
}
if ($filters['category'] === RESOLUTION_UNCATEGORISED_FILTER) {
    $filterParts[] = 'Uncategorised only';
} elseif ($filters['category'] !== '') {
    $filterParts[] = 'Category: ' . $filters['category'];
}
$fmtDate = function (?string $d, string $pattern = 'j F Y'): string {
    return $d ? (new DateTime($d))->format($pattern) : '';
};
if ($filters['from'] && $filters['to']) {
    $filterParts[] = $fmtDate($filters['from']) . ' – ' . $fmtDate($filters['to']);
} elseif ($filters['from']) {
    $filterParts[] = 'From ' . $fmtDate($filters['from']);
} elseif ($filters['to']) {
    $filterParts[] = 'Up to ' . $fmtDate($filters['to']);
}
if ($filters['q'] !== '') {
    $filterParts[] = 'Matching "' . $filters['q'] . '"';
}
$groupLabel = RESOLUTION_REGISTER_GROUPINGS[$groupBy];
$subtitle = $groupBy === 'none' ? '' : 'Grouped by ' . strtolower($groupLabel);

$docTitle = 'Resolutions Register';
$e = function ($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
};

if ($format === 'word') {
    $filename = 'Resolutions_Register_' . date('Y-m-d') . '.doc';
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
}
$isWord = $format === 'word';

/** Anchor id for a group heading (Word honours these for the contents links). */
$anchor = function (int $i): string {
    return 'group-' . ($i + 1);
};
?>
<?php if ($isWord): ?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<?php else: ?>
<!DOCTYPE html>
<html lang="en">
<?php endif; ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($docTitle); ?></title>
    <?php if ($isWord): ?>
    <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->
    <?php endif; ?>
    <style>
        @page { margin: 2cm; }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .group { page-break-inside: auto; }
            .resolution { page-break-inside: avoid; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 20px;
            line-height: 1.4;
            color: #222;
            background: #fff;
        }
        .letterhead { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .letterhead-org { font-size: 16pt; font-weight: bold; letter-spacing: 0.5px; margin: 0 0 4px 0; text-transform: uppercase; }
        .letterhead-doctype { font-size: 14pt; font-weight: bold; margin: 0; }
        .letterhead-meta { font-size: 10pt; color: #555; margin: 6px 0 0 0; }
        h2.contents-heading { font-size: 13pt; margin: 0 0 6px 0; }
        .contents { margin: 0 0 24px 0; padding-left: 20px; }
        .contents li { margin: 2px 0; }
        .contents a { color: #222; text-decoration: none; }
        h2.group-heading { font-size: 14pt; border-bottom: 1px solid #999; padding-bottom: 4px; margin: 28px 0 12px 0; }
        .group-count { font-weight: normal; color: #666; font-size: 10pt; }
        .resolution { margin: 0 0 14px 0; padding: 0 0 10px 0; border-bottom: 1px solid #e5e5e5; }
        .resolution-heading { font-weight: bold; margin: 0 0 2px 0; }
        .resolution-context { font-size: 9.5pt; color: #555; margin: 0 0 6px 0; }
        .resolution-text { margin: 0 0 4px 0; }
        .resolution-meta { font-size: 9.5pt; color: #444; margin: 0; }
        .resolution p { margin: 3px 0; }
        .empty { font-style: italic; color: #666; }
        .footer { margin-top: 30px; font-size: 9pt; color: #777; text-align: center; }
        .actions { text-align: center; margin-bottom: 20px; }
        .actions a, .actions button { display: inline-block; padding: 8px 16px; margin: 0 4px; border: none; border-radius: 4px; background: #667eea; color: #fff; text-decoration: none; font-size: 14px; cursor: pointer; }
        h2 { font-family: Arial, sans-serif; }
    </style>
    <?php if ($isWord): ?>
    <style>
        /* Word applies a <div>'s border to every paragraph inside it, so
           borders go on single elements (or an <hr>) instead. */
        .letterhead, .resolution { border: none; }
        .letterhead { padding-bottom: 0; }
        h2.group-heading { margin-left: 0; }
    </style>
    <?php endif; ?>
</head>
<body>
<?php if (!$isWord): ?>
    <div class="actions no-print">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <?php $wordQuery = $_GET; $wordQuery['format'] = 'word'; ?>
        <a href="?<?php echo $e(http_build_query($wordQuery)); ?>">Download Word document</a>
    </div>
<?php endif; ?>

    <div class="letterhead">
        <p class="letterhead-org"><?php echo $e(ORGANIZATION_NAME); ?></p>
        <p class="letterhead-doctype"><?php echo $e(strtoupper($docTitle)); ?></p>
        <?php if ($subtitle || $filterParts): ?>
        <p class="letterhead-meta"><?php echo $e(implode(' · ', array_filter(array_merge([$subtitle], $filterParts)))); ?></p>
        <?php endif; ?>
        <p class="letterhead-meta"><?php echo count($rows); ?> resolution<?php echo count($rows) === 1 ? '' : 's'; ?> · Prepared <?php echo $e(date('j F Y')); ?></p>
    </div>
    <?php if ($isWord): ?><hr><?php endif; ?>

<?php if (empty($rows)): ?>
    <p class="empty">No resolutions match the selected filters.</p>
<?php else: ?>
    <?php if ($groupBy !== 'none' && count($groups) > 1): ?>
    <h2 class="contents-heading">Contents</h2>
    <ol class="contents">
        <?php foreach ($groups as $i => $group): ?>
        <li><a href="#<?php echo $anchor($i); ?>"><?php echo $e($group['label']); ?></a> (<?php echo count($group['resolutions']); ?>)</li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <?php foreach ($groups as $i => $group): ?>
    <div class="group">
        <?php if ($groupBy !== 'none'): ?>
        <h2 class="group-heading" id="<?php echo $anchor($i); ?>"><a name="<?php echo $anchor($i); ?>"></a><?php echo $e($group['label']); ?>
            <span class="group-count">(<?php echo count($group['resolutions']); ?>)</span></h2>
        <?php endif; ?>

        <?php foreach ($group['resolutions'] as $res): ?>
        <?php
            $headingParts = [];
            if (!empty($res['resolution_number'])) {
                $headingParts[] = $res['resolution_number'];
            }
            if (!empty($res['title'])) {
                $headingParts[] = $res['title'];
            } elseif (!empty($res['agenda_item_title'])) {
                $headingParts[] = $res['agenda_item_title'];
            }
            $heading = $headingParts ? implode(' — ', $headingParts) : 'Resolution';

            $context = [$res['meeting_type_name'] . ': ' . $res['meeting_title'], $fmtDate($res['meeting_date'])];
            if (!empty($res['agenda_item_number'])) {
                $context[] = 'Item ' . $res['agenda_item_number'];
            }
            // Category is the heading when grouped by it; otherwise show it here
            if ($groupBy !== 'category' && !empty($res['category'])) {
                $context[] = 'Category: ' . $res['category'];
            }

            $meta = [];
            if ($groupBy !== 'status') {
                $meta[] = 'Status: ' . $res['status'];
            }
            if ($groupBy !== 'decision_method' && !empty($res['decision_method'])) {
                $meta[] = 'Decision method: ' . $res['decision_method'];
            }
            if (!empty($res['effective_date'])) {
                $meta[] = 'Effective: ' . $fmtDate($res['effective_date']);
            }

            // Clerk notes are working notes, not part of the register
            $voteRes = $res;
            unset($voteRes['clerk_notes']);
        ?>
        <div class="resolution">
            <p class="resolution-heading"><?php echo $e($heading); ?></p>
            <p class="resolution-context"><?php echo $e(implode(' · ', array_filter($context))); ?></p>
            <p class="resolution-text"><?php echo nl2br($e(formatResolutionOutcomeText($res))); ?></p>
            <?php if ($res['decision_method'] === 'Referral' && (!empty($res['referral_body']) || !empty($res['referral_scope']))): ?>
            <p class="resolution-meta"><strong>Referred to:</strong> <?php echo $e($res['referral_body'] ?? ''); ?><?php echo !empty($res['referral_scope']) ? ' — ' . $e($res['referral_scope']) : ''; ?></p>
            <?php endif; ?>
            <?php if ($meta): ?>
            <p class="resolution-meta"><?php echo $e(implode(' · ', $meta)); ?></p>
            <?php endif; ?>
            <div class="resolution-meta"><?php echo renderResolutionVoteDetails($voteRes); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

    <div class="footer"><?php echo $e(APP_NAME); ?> · Resolutions Register generated <?php echo $e(date('j F Y g:i a')); ?></div>
</body>
</html>
