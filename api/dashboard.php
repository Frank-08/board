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

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

// Require authentication
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Support both committee_id (legacy) and meeting_type_id - make optional for system-wide view
$meetingTypeId = isset($_GET['meeting_type_id']) ? (int)$_GET['meeting_type_id'] : (isset($_GET['committee_id']) ? (int)$_GET['committee_id'] : null);

// Get statistics
$stats = [];

// Active members count
$stmt = $db->prepare("SELECT COUNT(DISTINCT member_id) as count FROM meeting_type_members WHERE status = 'Active'" . ($meetingTypeId ? " AND meeting_type_id = ?" : ""));
$params = $meetingTypeId ? [$meetingTypeId] : [];
$stmt->execute($params);
$stats['active_members'] = (int)$stmt->fetch()['count'];

// Upcoming meetings (next 30 days)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM meetings WHERE status = 'Scheduled' AND scheduled_date >= NOW() AND scheduled_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)" . ($meetingTypeId ? " AND meeting_type_id = ?" : ""));
$stmt->execute($params);
$stats['upcoming_meetings'] = (int)$stmt->fetch()['count'];

// Recent meetings (last 30 days)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM meetings WHERE status = 'Completed' AND scheduled_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . ($meetingTypeId ? " AND meeting_type_id = ?" : ""));
$stmt->execute($params);
$stats['recent_meetings'] = (int)$stmt->fetch()['count'];

// Pending resolutions
$stmt = $db->prepare("SELECT COUNT(*) as count FROM resolutions WHERE status = 'Proposed'" . ($meetingTypeId ? " AND meeting_id IN (SELECT id FROM meetings WHERE meeting_type_id = ?)" : ""));
$stmt->execute($params);
$stats['pending_resolutions'] = (int)$stmt->fetch()['count'];

// Draft minutes
$stmt = $db->prepare("SELECT COUNT(*) as count FROM minutes WHERE status = 'Draft'" . ($meetingTypeId ? " AND meeting_id IN (SELECT id FROM meetings WHERE meeting_type_id = ?)" : ""));
$stmt->execute($params);
$stats['draft_minutes'] = (int)$stmt->fetch()['count'];

// Get recent meetings
$stmt = $db->prepare("SELECT m.*, mt.name as meeting_type_name FROM meetings m JOIN meeting_types mt ON m.meeting_type_id = mt.id" . ($meetingTypeId ? " WHERE m.meeting_type_id = ?" : "") . " ORDER BY m.scheduled_date DESC LIMIT 10");
$stmt->execute($params);
$stats['recent_meetings_list'] = $stmt->fetchAll();

// Get upcoming meetings
$stmt = $db->prepare("SELECT m.*, mt.name as meeting_type_name FROM meetings m JOIN meeting_types mt ON m.meeting_type_id = mt.id WHERE m.status = 'Scheduled' AND m.scheduled_date >= NOW()" . ($meetingTypeId ? " AND m.meeting_type_id = ?" : "") . " ORDER BY m.scheduled_date ASC LIMIT 10");
$stmt->execute($params);
$stats['upcoming_meetings_list'] = $stmt->fetchAll();

echo json_encode($stats);

