<?php
/**
 * Minutes Agenda Comments API Endpoint
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
        if (isset($_GET['minutes_id'])) {
            $minutesId = (int)$_GET['minutes_id'];
            $stmt = $db->prepare("
                SELECT mac.*, ai.title as agenda_item_title, ai.position
                FROM minutes_agenda_comments mac
                JOIN agenda_items ai ON mac.agenda_item_id = ai.id
                WHERE mac.minutes_id = ?
                ORDER BY ai.position ASC
            ");
            $stmt->execute([$minutesId]);
            echo json_encode($stmt->fetchAll());
        } elseif (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $db->prepare("
                SELECT mac.*, ai.title as agenda_item_title
                FROM minutes_agenda_comments mac
                JOIN agenda_items ai ON mac.agenda_item_id = ai.id
                WHERE mac.id = ?
            ");
            $stmt->execute([$id]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                http_response_code(404);
                echo json_encode(['error' => 'Comment not found']);
                exit;
            }
            
            echo json_encode($comment);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'minutes_id or id is required']);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $minutesId = (int)($data['minutes_id'] ?? 0);
        $agendaItemId = (int)($data['agenda_item_id'] ?? 0);
        $comment = $data['comment'] ?? '';
        
        if (!$minutesId || !$agendaItemId || empty($comment)) {
            http_response_code(400);
            echo json_encode(['error' => 'minutes_id, agenda_item_id, and comment are required']);
            exit;
        }
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle upsert
        $stmt = $db->prepare("
            INSERT INTO minutes_agenda_comments (minutes_id, agenda_item_id, comment)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$minutesId, $agendaItemId, $comment]);
        
        $commentId = $db->lastInsertId();
        if ($commentId == 0) {
            // If it was an update, get the existing ID
            $stmt = $db->prepare("SELECT id FROM minutes_agenda_comments WHERE minutes_id = ? AND agenda_item_id = ?");
            $stmt->execute([$minutesId, $agendaItemId]);
            $result = $stmt->fetch();
            $commentId = $result['id'];
        }
        
        $stmt = $db->prepare("
            SELECT mac.*, ai.title as agenda_item_title, ai.position
            FROM minutes_agenda_comments mac
            JOIN agenda_items ai ON mac.agenda_item_id = ai.id
            WHERE mac.id = ?
        ");
        $stmt->execute([$commentId]);
        echo json_encode($stmt->fetch());
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $comment = $data['comment'] ?? '';
        
        if (!$id || empty($comment)) {
            http_response_code(400);
            echo json_encode(['error' => 'id and comment are required']);
            exit;
        }
        
        $stmt = $db->prepare("UPDATE minutes_agenda_comments SET comment = ? WHERE id = ?");
        $stmt->execute([$comment, $id]);
        
        $stmt = $db->prepare("
            SELECT mac.*, ai.title as agenda_item_title, ai.position
            FROM minutes_agenda_comments mac
            JOIN agenda_items ai ON mac.agenda_item_id = ai.id
            WHERE mac.id = ?
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
        
        $stmt = $db->prepare("DELETE FROM minutes_agenda_comments WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

