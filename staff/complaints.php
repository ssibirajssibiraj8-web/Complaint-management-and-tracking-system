<?php
/**
 * Staff Complaints Listing & Workload Filter
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Assigned Complaints - Staff Portal';
$activeNav = 'complaints';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['staff']);

$user = currentUser();
$staffId = (int) $user['id'];

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');

$sql = "
    SELECT c.*, cat.name as category_name, u.name as student_name, u.phone as student_phone,
           a.assigned_at
    FROM assignments a
    JOIN complaints c ON a.complaint_id = c.id
    JOIN categories cat ON c.category_id = cat.id
    JOIN users u ON c.user_id = u.id
    WHERE a.staff_id = :staff_id
";
$params = [':staff_id' => $staffId];

if (!empty($search)) {
    $sql .= " AND (c.complaint_code LIKE :search OR c.title LIKE :search OR c.location LIKE :search OR u.name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($priorityFilter)) {
    $sql .= " AND c.priority = :priority";
    $params[':priority'] = $priorityFilter;
}

$sql .= " ORDER BY CASE WHEN c.status = 'In Progress' THEN 1 WHEN c.status = 'Assigned' THEN 2 ELSE 3 END, c.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    $totalFound = count($tasks);
} catch (PDOException $e) {
    error_log("Staff complaints error: " . $e->getMessage());
    $tasks = [];
    $totalFound = 0;
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
                    <i class="bi bi-tools text-primary me-2"></i>My Assigned Maintenance Tasks
                </h3>
                <p class="text-secondary small mb-0">Track all campus facility issues assigned to you for inspection and repair.</p>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Quick Status Filter Pills -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?= url('staff/complaints.php') ?>" class="btn btn-sm <?= empty($statusFilter) ? 'btn-primary' : 'btn-light border' ?> px-3">
                All Assigned Tasks
            </a>
            <a href="<?= url('staff/complaints.php?status=Assigned') ?>" class="btn btn-sm <?= ($statusFilter === 'Assigned') ? 'btn-primary' : 'btn-light border' ?> px-3">
                <i class="bi bi-person-gear me-1"></i> Awaiting Start (Assigned)
            </a>
            <a href="<?= url('staff/complaints.php?status=In+Progress') ?>" class="btn btn-sm <?= ($statusFilter === 'In Progress') ? 'btn-primary' : 'btn-light border' ?> px-3">
                <i class="bi bi-tools me-1"></i> Work In Progress
            </a>
            <a href="<?= url('staff/complaints.php?status=Resolved') ?>" class="btn btn-sm <?= ($statusFilter === 'Resolved') ? 'btn-primary' : 'btn-light border' ?> px-3">
                <i class="bi bi-check-circle-fill me-1"></i> Resolved History
            </a>
        </div>

        <!-- Search Form -->
        <div class="app-card p-4 mb-4">
            <form method="GET" action="<?= url('staff/complaints.php') ?>" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="searchInput" class="form-label small fw-bold text-dark">Search Keyword</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" name="search" value="<?= e($search) ?>" placeholder="Complaint code, title, student, room...">
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <label for="statusSelect" class="form-label small fw-bold text-dark">Status</label>
                    <select class="form-select" id="statusSelect" name="status">
                        <option value="">All Statuses</option>
                        <option value="Assigned" <?= ($statusFilter === 'Assigned') ? 'selected' : '' ?>>Assigned</option>
                        <option value="In Progress" <?= ($statusFilter === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="Resolved" <?= ($statusFilter === 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label for="prioritySelect" class="form-label small fw-bold text-dark">Priority</label>
                    <select class="form-select" id="prioritySelect" name="priority">
                        <option value="">All Priorities</option>
                        <option value="Low" <?= ($priorityFilter === 'Low') ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($priorityFilter === 'Medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($priorityFilter === 'High') ? 'selected' : '' ?>>High</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <a href="<?= url('staff/complaints.php') ?>" class="btn btn-light border" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>

        <!-- Task Table -->
        <div class="app-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="small text-muted fw-semibold">
                    Found <strong><?= $totalFound ?></strong> assigned task<?= $totalFound === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (empty($tasks)): ?>
                <div class="empty-state-box">
                    <div class="empty-state-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No matching tasks found</h5>
                    <p class="text-secondary small mb-3">Try clearing search filters or check your active assignments.</p>
                    <a href="<?= url('staff/complaints.php') ?>" class="btn btn-outline-primary btn-sm">Reset All Filters</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Complaint Code</th>
                                <th>Title & Student</th>
                                <th>Category & Location</th>
                                <th>Priority</th>
                                <th>Current Status</th>
                                <th>Assigned Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('staff/complaint-details.php?id=' . $t['id']) ?>" class="fw-bold text-primary text-decoration-none">
                                            <?= e($t['complaint_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 240px;"><?= e($t['title']) ?></div>
                                        <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($t['student_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border small"><?= e($t['category_name']) ?></span>
                                        <div class="small text-secondary text-truncate mt-1" style="max-width: 170px;">
                                            <i class="bi bi-geo-alt me-1 text-danger"></i><?= e($t['location']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= getPriorityBadge($t['priority']) ?>
                                    </td>
                                    <td>
                                        <?= getStatusBadge($t['status']) ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= formatDateTime($t['assigned_at'], 'd M Y, h:i A') ?></small>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('staff/complaint-details.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary px-3 py-1">
                                            View & Update &rarr;
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
