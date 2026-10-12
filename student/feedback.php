<?php
/**
 * Submit Feedback Handler
 * Campus Complaint Management and Tracking System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['student']);

$user = currentUser();
$studentId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('student/my-complaints.php'));
    exit;
}

if (!validateCsrfToken()) {
    setFlash('danger', 'Security session token expired. Please try again.');
    header('Location: ' . url('student/my-complaints.php'));
    exit;
}

$complaintId = (int) ($_POST['complaint_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($complaintId <= 0 || $rating < 1 || $rating > 5) {
    setFlash('danger', 'Please provide a valid rating between 1 and 5 stars.');
    header('Location: ' . url('student/complaint-details.php?id=' . $complaintId));
    exit;
}

try {
    // 1. Verify complaint belongs to student and is in Resolved state
    $stmtCheck = $pdo->prepare("SELECT id, complaint_code, status FROM complaints WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$complaintId, $studentId]);
    $complaint = $stmtCheck->fetch();

    if (!$complaint) {
        setFlash('danger', 'Complaint not found or unauthorized.');
        header('Location: ' . url('student/my-complaints.php'));
        exit;
    }

    if ($complaint['status'] !== 'Resolved') {
        setFlash('warning', 'Feedback can only be provided after a complaint has been resolved.');
        header('Location: ' . url('student/complaint-details.php?id=' . $complaintId));
        exit;
    }

    // 2. Check if feedback already submitted
    $stmtExisting = $pdo->prepare("SELECT id FROM feedback WHERE complaint_id = ?");
    $stmtExisting->execute([$complaintId]);
    if ($stmtExisting->fetch()) {
        setFlash('info', 'You have already submitted feedback for this complaint.');
        header('Location: ' . url('student/complaint-details.php?id=' . $complaintId));
        exit;
    }

    // 3. Insert feedback
    $stmtInsert = $pdo->prepare("
        INSERT INTO feedback (complaint_id, user_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmtInsert->execute([$complaintId, $studentId, $rating, $comment]);

    // 4. Notify admins of new feedback
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
    foreach ($admins as $admin) {
        createNotification(
            $pdo,
            (int) $admin['id'],
            'New Student Feedback',
            "{$user['name']} gave {$rating} stars on {$complaint['complaint_code']}.",
            "admin/feedback.php"
        );
    }

    setFlash('success', 'Thank you for your feedback! Your review helps us improve campus services.');
    header('Location: ' . url('student/complaint-details.php?id=' . $complaintId));
    exit;

} catch (PDOException $e) {
    error_log("Feedback submission error: " . $e->getMessage());
    setFlash('danger', 'An error occurred while recording your feedback.');
    header('Location: ' . url('student/complaint-details.php?id=' . $complaintId));
    exit;
}
