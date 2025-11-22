<?php
/**
 * Dashboard API Endpoint - Provides summary statistics
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$organizationId = isset($_GET['organization_id']) ? (int)$_GET['organization_id'] : null;

if (!$organizationId) {
    http_response_code(400);
    echo json_encode(['error' => 'organization_id is required']);
    exit;
}

// Get statistics
$stats = [];

// Active members count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM board_members WHERE organization_id = ? AND status = 'Active'");
$stmt->execute([$organizationId]);
$stats['active_members'] = (int)$stmt->fetch()['count'];

// Upcoming meetings (next 30 days)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM meetings WHERE organization_id = ? AND status = 'Scheduled' AND scheduled_date >= NOW() AND scheduled_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$organizationId]);
$stats['upcoming_meetings'] = (int)$stmt->fetch()['count'];

// Recent meetings (last 30 days)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM meetings WHERE organization_id = ? AND status = 'Completed' AND scheduled_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$organizationId]);
$stats['recent_meetings'] = (int)$stmt->fetch()['count'];

// Pending resolutions
$stmt = $db->prepare("SELECT COUNT(*) as count FROM resolutions r JOIN meetings m ON r.meeting_id = m.id WHERE m.organization_id = ? AND r.status = 'Proposed'");
$stmt->execute([$organizationId]);
$stats['pending_resolutions'] = (int)$stmt->fetch()['count'];

// Draft minutes
$stmt = $db->prepare("SELECT COUNT(*) as count FROM minutes m JOIN meetings mt ON m.meeting_id = mt.id WHERE mt.organization_id = ? AND m.status = 'Draft'");
$stmt->execute([$organizationId]);
$stats['draft_minutes'] = (int)$stmt->fetch()['count'];

// Get recent meetings
$stmt = $db->prepare("SELECT * FROM meetings WHERE organization_id = ? ORDER BY scheduled_date DESC LIMIT 5");
$stmt->execute([$organizationId]);
$stats['recent_meetings_list'] = $stmt->fetchAll();

// Get upcoming meetings
$stmt = $db->prepare("SELECT * FROM meetings WHERE organization_id = ? AND status = 'Scheduled' AND scheduled_date >= NOW() ORDER BY scheduled_date ASC LIMIT 5");
$stmt->execute([$organizationId]);
$stats['upcoming_meetings_list'] = $stmt->fetchAll();

echo json_encode($stats);

