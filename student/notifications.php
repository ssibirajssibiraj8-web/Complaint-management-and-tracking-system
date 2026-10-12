<?php
/**
 * Student Notifications Center
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Notifications - Student Portal';
$activeNav = 'notifications';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['student']);

$user = currentUser();
$studentId = (int) $user['id'];

// Handle Mark All as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    if (validateCsrfToken()) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$studentId]);
        setFlash('success', 'All notifications marked as read.');
        header('Location: ' . url('student/notifications.php'));
        exit;
    }
}

// Fetch all notifications for this student
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$studentId]);
    $notifications = $stmt->fetchAll();
    $unreadCount = getUnreadNotificationsCount($pdo, $studentId);
} catch (PDOException $e) {
    $notifications = [];
    $unreadCount = 0;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">
                            <i class="bi bi-bell text-primary me-2"></i>Notifications Center
                        </h3>
                        <p class="text-secondary small mb-0">Stay informed about status progressions on your reported complaints.</p>
                    </div>

                    <?php if ($unreadCount > 0): ?>
                        <form method="POST" action="<?= url('student/notifications.php') ?>">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-check2-all me-1"></i> Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php renderFlashMessages(); ?>

                <div class="app-card p-4">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state-box">
                            <div class="empty-state-icon">
                                <i class="bi bi-bell-slash"></i>
                            </div>
                            <h6 class="fw-bold text-dark">No notifications yet</h6>
                            <p class="text-secondary small mb-0">You will receive updates here whenever the administration or technicians update your complaints.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $n): ?>
                                <div class="list-group-item p-3 border-bottom <?= $n['is_read'] ? '' : 'bg-light border-start border-primary border-4' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold text-dark"><?= e($n['title']) ?></span>
                                            <?php if (!$n['is_read']): ?>
                                                <span class="badge bg-primary-subtle text-primary rounded-pill small" style="font-size: 0.68rem;">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= formatDateTime($n['created_at']) ?></small>
                                    </div>
                                    <p class="text-secondary small mb-2"><?= e($n['message']) ?></p>
                                    <?php if (!empty($n['link'])): ?>
                                        <a href="<?= url($n['link']) ?>" class="btn btn-sm btn-light border px-2.5 py-1 text-primary fw-semibold small">
                                            Open Complaint Details &rarr;
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
