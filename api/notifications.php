<?php
/**
 * In-App Notification API Endpoint
 * Campus Complaint Management and Tracking System
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = currentUser();
$userId = (int) $user['id'];
$action = $_GET['action'] ?? '';

try {
    if ($action === 'mark_read') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            echo json_encode(['success' => true]);
            exit;
        }
    } elseif ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'get_count') {
        $count = getUnreadNotificationsCount($pdo, $userId);
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
