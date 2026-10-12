<?php
/**
 * Staff Technician Directory & Workload Monitor
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Staff Technicians - Admin Console';
$activeNav = 'staff';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

// Fetch all staff members with active workload and resolved counts
try {
    $stmt = $pdo->query("
        SELECT 
            u.*,
            COUNT(DISTINCT CASE WHEN c.status IN ('Assigned', 'In Progress') THEN a.complaint_id END) as active_tasks,
            COUNT(DISTINCT CASE WHEN c.status = 'Resolved' THEN a.complaint_id END) as resolved_tasks
        FROM users u
        LEFT JOIN assignments a ON u.id = a.staff_id
        LEFT JOIN complaints c ON a.complaint_id = c.id
        WHERE u.role = 'staff'
        GROUP BY u.id
        ORDER BY u.name ASC
    ");
    $staffList = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Staff directory error: " . $e->getMessage());
    $staffList = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">
                    <i class="bi bi-person-badge text-primary me-2"></i>Campus Maintenance Technicians
                </h3>
                <p class="text-secondary small mb-0">View staff workloads, performance metrics, and assign new team members.</p>
            </div>
            <a href="<?= url('admin/users.php') ?>" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-person-plus me-1"></i> Register New Staff Account
            </a>
        </div>

        <?php renderFlashMessages(); ?>

        <div class="row g-4">
            <?php if (empty($staffList)): ?>
                <div class="col-12">
                    <div class="app-card p-5 text-center">
                        <div class="empty-state-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h5 class="fw-bold text-dark">No staff technicians found</h5>
                        <p class="text-secondary small mb-3">Create staff accounts in User Management to assign them complaints.</p>
                        <a href="<?= url('admin/users.php') ?>" class="btn btn-primary btn-sm">Go to User Accounts</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($staffList as $s): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="app-card p-4 h-100 app-card-hover border">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center fs-5" style="width: 48px; height: 48px;">
                                        <?= strtoupper(substr($s['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-dark mb-0"><?= e($s['name']) ?></h5>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle small">
                                            Staff Technician
                                        </span>
                                    </div>
                                </div>
                                <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($s['status']) ?>
                                </span>
                            </div>

                            <div class="small text-secondary mb-3">
                                <div><i class="bi bi-envelope me-1 text-muted"></i> <?= e($s['email']) ?></div>
                                <?php if (!empty($s['phone'])): ?>
                                    <div><i class="bi bi-telephone me-1 text-muted"></i> <?= e($s['phone']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="row g-2 p-2 bg-light rounded-3 text-center mb-3">
                                <div class="col-6">
                                    <span class="d-block fw-bold fs-5 text-warning"><?= (int)$s['active_tasks'] ?></span>
                                    <span class="text-muted" style="font-size: 0.72rem; text-transform: uppercase;">Active Tasks</span>
                                </div>
                                <div class="col-6 border-start">
                                    <span class="d-block fw-bold fs-5 text-success"><?= (int)$s['resolved_tasks'] ?></span>
                                    <span class="text-muted" style="font-size: 0.72rem; text-transform: uppercase;">Completed</span>
                                </div>
                            </div>

                            <div class="d-grid">
                                <a href="<?= url('admin/complaints.php?search=' . urlencode($s['name'])) ?>" class="btn btn-sm btn-outline-primary fw-semibold">
                                    View Assigned Complaints &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
