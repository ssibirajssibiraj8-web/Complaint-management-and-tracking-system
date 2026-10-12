<?php
/**
 * Student Dashboard
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Student Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['student']);

$user = currentUser();
$studentId = (int) $user['id'];

// Fetch Dynamic Statistics for logged in student
$totalComplaints = 0;
$pendingComplaints = 0;
$inProgressComplaints = 0;
$resolvedComplaints = 0;

try {
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('Submitted', 'Under Review', 'Approved', 'Assigned') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved
        FROM complaints 
        WHERE user_id = ?
    ");
    $stmtStats->execute([$studentId]);
    $stats = $stmtStats->fetch();

    $totalComplaints = (int) ($stats['total'] ?? 0);
    $pendingComplaints = (int) ($stats['pending'] ?? 0);
    $inProgressComplaints = (int) ($stats['in_progress'] ?? 0);
    $resolvedComplaints = (int) ($stats['resolved'] ?? 0);

    // Fetch Recent Complaints
    $stmtRecent = $pdo->prepare("
        SELECT c.*, cat.name as category_name 
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmtRecent->execute([$studentId]);
    $recentComplaints = $stmtRecent->fetchAll();

    // Fetch Recent In-App Notifications
    $recentNotifs = getUserNotifications($pdo, $studentId, 4);

} catch (PDOException $e) {
    error_log("Student dashboard error: " . $e->getMessage());
    $recentComplaints = [];
    $recentNotifs = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <?php renderFlashMessages(); ?>

        <!-- Welcome Banner -->
        <div class="app-card p-4 p-lg-5 mb-4 border-0" style="background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); color: white;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-3 py-1 mb-2 small">
                        <i class="bi bi-mortarboard me-1"></i> Student Portal
                    </span>
                    <h2 class="fw-bold mb-2">Welcome, <?= e($user['name']) ?>!</h2>
                    <p class="mb-3 text-white text-opacity-90 lead fs-6" style="max-width: 600px;">
                        Have you noticed a facility issue in your classroom, lab, hostel, or campus grounds? Submit a ticket and our maintenance technicians will handle it.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= url('student/new-complaint.php') ?>" class="btn btn-light fw-semibold text-primary px-3 py-2 shadow-sm">
                            <i class="bi bi-plus-circle me-1"></i> Report a Complaint
                        </a>
                        <a href="<?= url('student/my-complaints.php') ?>" class="btn btn-outline-light fw-semibold px-3 py-2">
                            <i class="bi bi-search me-1"></i> Track My Complaints
                        </a>
                    </div>
                </div>
                <div class="col-md-4 d-none d-md-block text-end">
                    <div class="d-inline-flex p-4 rounded-circle bg-white bg-opacity-10 text-white fs-1">
                        <i class="bi bi-building-gear"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Metric Cards -->
        <div class="row g-3 mb-4">
            
            <div class="col-6 col-lg-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Total Reported</span>
                        <div class="card-stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-folder2-open"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $totalComplaints ?></h3>
                    <small class="text-muted">Lifetime complaints</small>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Pending</span>
                        <div class="card-stat-icon bg-secondary-subtle text-secondary">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $pendingComplaints ?></h3>
                    <small class="text-muted">Awaiting assignment</small>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">In Progress</span>
                        <div class="card-stat-icon bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-tools"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $inProgressComplaints ?></h3>
                    <small class="text-muted">Technician working</small>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Resolved</span>
                        <div class="card-stat-icon bg-success-subtle text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $resolvedComplaints ?></h3>
                    <small class="text-muted">Successfully closed</small>
                </div>
            </div>

        </div>

        <div class="row g-4">
            
            <!-- Recent Complaints Table -->
            <div class="col-lg-8">
                <div class="app-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-clock-history text-primary me-2"></i> Recent Complaints
                        </h5>
                        <a href="<?= url('student/my-complaints.php') ?>" class="small text-primary fw-semibold text-decoration-none">
                            View All (<?= $totalComplaints ?>) &rarr;
                        </a>
                    </div>

                    <?php if (empty($recentComplaints)): ?>
                        <div class="empty-state-box">
                            <div class="empty-state-icon">
                                <i class="bi bi-inbox"></i>
                            </div>
                            <h6 class="fw-bold text-dark">No complaints reported yet</h6>
                            <p class="text-secondary small mb-3">If you spot any broken equipment or maintenance issues, let us know.</p>
                            <a href="<?= url('student/new-complaint.php') ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> Submit First Complaint
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>Complaint ID</th>
                                        <th>Title & Category</th>
                                        <th>Location</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentComplaints as $cmp): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-primary"><?= e($cmp['complaint_code']) ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;"><?= e($cmp['title']) ?></div>
                                                <small class="text-muted"><?= e($cmp['category_name']) ?></small>
                                            </td>
                                            <td>
                                                <small class="text-secondary text-truncate d-block" style="max-width: 160px;"><?= e($cmp['location']) ?></small>
                                            </td>
                                            <td>
                                                <?= getPriorityBadge($cmp['priority']) ?>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($cmp['status']) ?>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= formatDateTime($cmp['created_at'], 'd M Y') ?></small>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?= url('student/complaint-details.php?id=' . $cmp['id']) ?>" class="btn btn-sm btn-light border px-2.5 py-1">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Quick Actions & Status Updates -->
            <div class="col-lg-4">
                
                <!-- Quick Actions Card -->
                <div class="app-card p-4 mb-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-lightning-charge text-warning me-2"></i>Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="<?= url('student/new-complaint.php') ?>" class="btn btn-outline-primary text-start d-flex align-items-center justify-content-between p-2.5 rounded-3">
                            <span><i class="bi bi-plus-circle me-2"></i> Report Complaint</span>
                            <i class="bi bi-chevron-right small text-muted"></i>
                        </a>
                        <a href="<?= url('student/my-complaints.php') ?>" class="btn btn-outline-secondary text-start d-flex align-items-center justify-content-between p-2.5 rounded-3">
                            <span><i class="bi bi-card-checklist me-2"></i> My Complaints</span>
                            <i class="bi bi-chevron-right small text-muted"></i>
                        </a>
                        <a href="<?= url('student/my-complaints.php?status=In+Progress') ?>" class="btn btn-outline-warning text-dark text-start d-flex align-items-center justify-content-between p-2.5 rounded-3">
                            <span><i class="bi bi-hourglass-split me-2"></i> Track In-Progress</span>
                            <i class="bi bi-chevron-right small text-muted"></i>
                        </a>
                        <a href="<?= url('student/profile.php') ?>" class="btn btn-outline-dark text-start d-flex align-items-center justify-content-between p-2.5 rounded-3">
                            <span><i class="bi bi-person-gear me-2"></i> My Profile & Settings</span>
                            <i class="bi bi-chevron-right small text-muted"></i>
                        </a>
                    </div>
                </div>

                <!-- Recent Notifications Card -->
                <div class="app-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bell text-primary me-2"></i>Recent Updates</h6>
                        <a href="<?= url('student/notifications.php') ?>" class="small text-decoration-none">All</a>
                    </div>

                    <?php if (empty($recentNotifs)): ?>
                        <div class="text-center py-3 text-muted small">
                            <i class="bi bi-bell-slash d-block fs-4 mb-1"></i>
                            No notifications yet
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentNotifs as $notif): ?>
                                <div class="list-group-item px-0 py-2.5 border-0 border-bottom">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small fw-bold text-dark"><?= e($notif['title']) ?></span>
                                        <small class="text-muted" style="font-size: 0.7rem;"><?= timeAgo($notif['created_at']) ?></small>
                                    </div>
                                    <p class="small text-secondary mb-1 mt-0.5"><?= e($notif['message']) ?></p>
                                    <?php if (!empty($notif['link'])): ?>
                                        <a href="<?= url($notif['link']) ?>" class="small text-primary text-decoration-none fw-semibold">Check details &rarr;</a>
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
