<?php
/**
 * Staff Technician Dashboard
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Staff Dashboard - Maintenance Portal';
$activeNav = 'dashboard';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['staff']);

$user = currentUser();
$staffId = (int) $user['id'];

// Dynamic Statistics for this staff technician
$assignedCount = 0;
$inProgressCount = 0;
$resolvedCount = 0;
$highPriorityCount = 0;

try {
    // Total assigned (currently active)
    $stmtCounts = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN c.status = 'Assigned' THEN 1 ELSE 0 END) as assigned_cnt,
            SUM(CASE WHEN c.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_cnt,
            SUM(CASE WHEN c.status = 'Resolved' THEN 1 ELSE 0 END) as resolved_cnt,
            SUM(CASE WHEN c.priority = 'High' AND c.status IN ('Assigned', 'In Progress') THEN 1 ELSE 0 END) as high_prio_cnt
        FROM assignments a
        JOIN complaints c ON a.complaint_id = c.id
        WHERE a.staff_id = ?
    ");
    $stmtCounts->execute([$staffId]);
    $counts = $stmtCounts->fetch();

    $assignedCount = (int) ($counts['assigned_cnt'] ?? 0);
    $inProgressCount = (int) ($counts['in_progress_cnt'] ?? 0);
    $resolvedCount = (int) ($counts['resolved_cnt'] ?? 0);
    $highPriorityCount = (int) ($counts['high_prio_cnt'] ?? 0);

    // Fetch active tasks for staff (Assigned or In Progress)
    $stmtActive = $pdo->prepare("
        SELECT c.*, cat.name as category_name, u.name as student_name, u.phone as student_phone,
               d.name as department_name, a.assigned_at
        FROM assignments a
        JOIN complaints c ON a.complaint_id = c.id
        JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON c.user_id = u.id
        JOIN departments d ON a.department_id = d.id
        WHERE a.staff_id = ? AND c.status IN ('Assigned', 'In Progress')
        ORDER BY FIELD(c.priority, 'High', 'Medium', 'Low'), c.created_at ASC
    ");
    $stmtActive->execute([$staffId]);
    $activeTasks = $stmtActive->fetchAll();

} catch (PDOException $e) {
    error_log("Staff dashboard error: " . $e->getMessage());
    $activeTasks = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <?php renderFlashMessages(); ?>

        <!-- Welcome Banner -->
        <div class="app-card p-4 p-lg-5 mb-4 border-0" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); color: white;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 fw-bold small">
                        <i class="bi bi-tools me-1"></i> Maintenance Operations
                    </span>
                    <h2 class="fw-bold mb-2">Staff Portal: <?= e($user['name']) ?></h2>
                    <p class="mb-3 text-light opacity-75 lead fs-6" style="max-width: 600px;">
                        Manage on-site facility tickets assigned to you by administration. Start work, update progress notes, and document resolutions.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= url('staff/complaints.php') ?>" class="btn btn-primary fw-semibold px-3 py-2">
                            <i class="bi bi-card-checklist me-1"></i> View All Assigned (<?= $assignedCount + $inProgressCount ?>)
                        </a>
                        <a href="<?= url('staff/complaints.php?status=Resolved') ?>" class="btn btn-outline-light fw-semibold px-3 py-2">
                            <i class="bi bi-check-circle me-1"></i> View Resolved History (<?= $resolvedCount ?>)
                        </a>
                    </div>
                </div>
                <div class="col-md-4 d-none d-md-block text-end">
                    <div class="d-inline-flex p-4 rounded-circle bg-white bg-opacity-10 text-warning fs-1">
                        <i class="bi bi-wrench-adjustable-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Metric Cards -->
        <div class="row g-3 mb-4">
            
            <div class="col-6 col-lg-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Assigned</span>
                        <div class="card-stat-icon bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-person-gear"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $assignedCount ?></h3>
                    <small class="text-muted">Awaiting your action</small>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">In Progress</span>
                        <div class="card-stat-icon bg-info-subtle text-info">
                            <i class="bi bi-gear-wide-connected"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $inProgressCount ?></h3>
                    <small class="text-muted">Work currently active</small>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">High Priority</span>
                        <div class="card-stat-icon bg-danger-subtle text-danger">
                            <i class="bi bi-exclamation-diamond-fill"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $highPriorityCount ?></h3>
                    <small class="text-danger fw-semibold">Urgent attention needed</small>
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
                    <h3 class="fw-extrabold text-dark mb-0"><?= $resolvedCount ?></h3>
                    <small class="text-muted">Completed repairs</small>
                </div>
            </div>

        </div>

        <!-- Active Tasks Table -->
        <div class="app-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-list-task text-primary me-2"></i>Active Assigned Workload
                </h5>
                <span class="badge bg-light text-secondary border">
                    <?= count($activeTasks) ?> tasks requiring attention
                </span>
            </div>

            <?php if (empty($activeTasks)): ?>
                <div class="empty-state-box">
                    <div class="empty-state-icon">
                        <i class="bi bi-check2-circle text-success"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No pending tasks assigned</h5>
                    <p class="text-secondary small mb-3">You have cleared all active assignments! New tasks will appear here when assigned by administration.</p>
                    <a href="<?= url('staff/complaints.php?status=Resolved') ?>" class="btn btn-outline-primary btn-sm">
                        View Resolved History
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Complaint ID</th>
                                <th>Title & Student</th>
                                <th>Category & Location</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeTasks as $task): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('staff/complaint-details.php?id=' . $task['id']) ?>" class="fw-bold text-primary text-decoration-none">
                                            <?= e($task['complaint_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 240px;"><?= e($task['title']) ?></div>
                                        <small class="text-muted">
                                            <i class="bi bi-person me-1"></i><?= e($task['student_name']) ?>
                                            <?php if (!empty($task['student_phone'])): ?>
                                                (<?= e($task['student_phone']) ?>)
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border small"><?= e($task['category_name']) ?></span>
                                        <div class="small text-secondary text-truncate mt-1" style="max-width: 180px;">
                                            <i class="bi bi-geo-alt me-1 text-danger"></i><?= e($task['location']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= getPriorityBadge($task['priority']) ?>
                                    </td>
                                    <td>
                                        <?= getStatusBadge($task['status']) ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= formatDateTime($task['assigned_at'], 'd M Y, h:i A') ?></small>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('staff/complaint-details.php?id=' . $task['id']) ?>" class="btn btn-sm btn-primary px-3 py-1 fw-semibold">
                                            Open Task &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
