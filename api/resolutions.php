<?php
/**
 * Resolutions API Endpoint
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT r.*,
                    mb.first_name as moved_first_name, mb.last_name as moved_last_name,
                    sb.first_name as seconded_first_name, sb.last_name as seconded_last_name
                FROM resolutions r
                LEFT JOIN board_members mb ON r.motion_moved_by = mb.id
                LEFT JOIN board_members sb ON r.motion_seconded_by = sb.id
                WHERE r.id = ?
            ");
            $stmt->execute([$id]);
            $resolution = $stmt->fetch();
            
            if (!$resolution) {
                http_response_code(404);
                echo json_encode(['error' => 'Resolution not found']);
                exit;
            }
            
            echo json_encode($resolution);
        } elseif (isset($_GET['meeting_id'])) {
            $meetingId = (int)$_GET['meeting_id'];
            $stmt = $db->prepare("
                SELECT r.*,
                    mb.first_name as moved_first_name, mb.last_name as moved_last_name,
                    sb.first_name as seconded_first_name, sb.last_name as seconded_last_name
                FROM resolutions r
                LEFT JOIN board_members mb ON r.motion_moved_by = mb.id
                LEFT JOIN board_members sb ON r.motion_seconded_by = sb.id
                WHERE r.meeting_id = ?
                ORDER BY r.created_at ASC
            ");
            $stmt->execute([$meetingId]);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'id or meeting_id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? '';
        
        if (!$meetingId || empty($title) || empty($description)) {
            http_response_code(400);
            echo json_encode(['error' => 'meeting_id, title, and description are required']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO resolutions (meeting_id, agenda_item_id, resolution_number, title, description, motion_moved_by, motion_seconded_by, vote_type, votes_for, votes_against, votes_abstain, status, effective_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $meetingId,
            !empty($data['agenda_item_id']) ? (int)$data['agenda_item_id'] : null,
            $data['resolution_number'] ?? null,
            $title,
            $description,
            !empty($data['motion_moved_by']) ? (int)$data['motion_moved_by'] : null,
            !empty($data['motion_seconded_by']) ? (int)$data['motion_seconded_by'] : null,
            $data['vote_type'] ?? null,
            $data['votes_for'] ?? 0,
            $data['votes_against'] ?? 0,
            $data['votes_abstain'] ?? 0,
            $data['status'] ?? 'Proposed',
            $data['effective_date'] ?? null
        ]);
        
        $resolutionId = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT r.*,
                mb.first_name as moved_first_name, mb.last_name as moved_last_name,
                sb.first_name as seconded_first_name, sb.last_name as seconded_last_name
            FROM resolutions r
            LEFT JOIN board_members mb ON r.motion_moved_by = mb.id
            LEFT JOIN board_members sb ON r.motion_seconded_by = sb.id
            WHERE r.id = ?
        ");
        $stmt->execute([$resolutionId]);
        echo json_encode($stmt->fetch());
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        $updates = [];
        $params = [];
        
        $fields = ['title', 'description', 'resolution_number', 'motion_moved_by', 'motion_seconded_by', 
                   'vote_type', 'votes_for', 'votes_against', 'votes_abstain', 'status', 'effective_date', 'agenda_item_id'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $id;
        $sql = "UPDATE resolutions SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT r.*,
                mb.first_name as moved_first_name, mb.last_name as moved_last_name,
                sb.first_name as seconded_first_name, sb.last_name as seconded_last_name
            FROM resolutions r
            LEFT JOIN board_members mb ON r.motion_moved_by = mb.id
            LEFT JOIN board_members sb ON r.motion_seconded_by = sb.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch());
        break;
        
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM resolutions WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

