<?php
/**
 * Campus Reports, Analytics & Print-Friendly Export
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Reports & Analytics - Admin Console';
$activeNav = 'reports';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

// Date range filters
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$whereClause = "1=1";
$params = [];

if (!empty($dateFrom)) {
    $whereClause .= " AND created_at >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if (!empty($dateTo)) {
    $whereClause .= " AND created_at <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

try {
    // 1. Overall Summary
    $summaryStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status IN ('Submitted', 'Under Review', 'Approved', 'Assigned', 'In Progress') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM complaints
        WHERE {$whereClause}
    ");
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch();

    $total = (int) ($summary['total'] ?? 0);
    $resolved = (int) ($summary['resolved'] ?? 0);
    $pending = (int) ($summary['pending'] ?? 0);
    $rejected = (int) ($summary['rejected'] ?? 0);

    // 2. Average Resolution Time (in hours)
    $avgTimeStmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours
        FROM complaints
        WHERE status = 'Resolved' AND resolved_at IS NOT NULL AND {$whereClause}
    ");
    $avgTimeStmt->execute($params);
    $avgHours = round((float) ($avgTimeStmt->fetchColumn() ?? 0), 1);

    // 3. Complaints by Category
    $catStmt = $pdo->prepare("
        SELECT cat.name, COUNT(c.id) as count
        FROM categories cat
        LEFT JOIN complaints c ON cat.id = c.category_id AND {$whereClause}
        GROUP BY cat.id
        ORDER BY count DESC
    ");
    $catStmt->execute($params);
    $categoryBreakdown = $catStmt->fetchAll();

    // 4. Complaints by Department
    $deptStmt = $pdo->prepare("
        SELECT d.name, COUNT(a.id) as count
        FROM departments d
        LEFT JOIN assignments a ON d.id = a.department_id
        LEFT JOIN complaints c ON a.complaint_id = c.id AND {$whereClause}
        GROUP BY d.id
        ORDER BY count DESC
    ");
    $deptStmt->execute($params);
    $deptBreakdown = $deptStmt->fetchAll();

    // 5. Complaints by Priority
    $prioStmt = $pdo->prepare("
        SELECT priority, COUNT(*) as count
        FROM complaints
        WHERE {$whereClause}
        GROUP BY priority
        ORDER BY FIELD(priority, 'High', 'Medium', 'Low')
    ");
    $prioStmt->execute($params);
    $priorityBreakdown = $prioStmt->fetchAll();

    // 6. Recent Detailed Records for printable table
    $recentStmt = $pdo->prepare("
        SELECT c.*, cat.name as category_name, u.name as student_name,
               d.name as dept_name, s.name as staff_name
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON c.user_id = u.id
        LEFT JOIN assignments a ON c.id = a.complaint_id
        LEFT JOIN departments d ON a.department_id = d.id
        LEFT JOIN users s ON a.staff_id = s.id
        WHERE {$whereClause}
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $recentStmt->execute($params);
    $reportRecords = $recentStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
    $total = $resolved = $pending = $rejected = 0;
    $avgHours = 0;
    $categoryBreakdown = $deptBreakdown = $priorityBreakdown = $reportRecords = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 no-print">
            <div>
                <h3 class="fw-bold text-dark mb-1">
                    <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Campus Maintenance Analytics & Reports
                </h3>
                <p class="text-secondary small mb-0">Generate comprehensive facility reports, review SLA turnaround times, and print official summaries.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print / Export Report
                </button>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Filter Card (Hidden in Print) -->
        <div class="app-card p-4 mb-4 no-print">
            <form method="GET" action="<?= url('admin/reports.php') ?>" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="dateFrom" class="form-label small fw-bold text-dark">Date From</label>
                    <input type="date" class="form-control" id="dateFrom" name="date_from" value="<?= e($dateFrom) ?>">
                </div>

                <div class="col-md-4">
                    <label for="dateTo" class="form-label small fw-bold text-dark">Date To</label>
                    <input type="date" class="form-control" id="dateTo" name="date_to" value="<?= e($dateTo) ?>">
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i> Filter Report
                    </button>
                    <a href="<?= url('admin/reports.php') ?>" class="btn btn-light border" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Official Report Header (Shown in Print) -->
        <div class="d-none d-print-block text-center mb-4">
            <h2 class="fw-bold text-dark mb-1">Campus Care Maintenance Report</h2>
            <p class="text-muted small mb-1">Campus Complaint Management and Tracking System</p>
            <div class="small text-secondary">
                Report Period: <?= $dateFrom ? formatDateTime($dateFrom, 'd M Y') : 'All Time' ?> to <?= $dateTo ? formatDateTime($dateTo, 'd M Y') : 'Present' ?> &bull; Generated: <?= date('d M Y, h:i A') ?>
            </div>
            <hr>
        </div>

        <!-- Executive Summary Cards -->
        <div class="row g-3 mb-4">
            
            <div class="col-6 col-md-3">
                <div class="app-card p-3 p-lg-4 text-center h-100">
                    <span class="text-secondary small fw-bold text-uppercase">Total Complaints</span>
                    <h2 class="fw-extrabold text-primary my-1"><?= $total ?></h2>
                    <small class="text-muted">Tickets in scope</small>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="app-card p-3 p-lg-4 text-center h-100">
                    <span class="text-secondary small fw-bold text-uppercase">Resolved</span>
                    <h2 class="fw-extrabold text-success my-1"><?= $resolved ?></h2>
                    <small class="text-success fw-semibold">
                        <?= $total > 0 ? round(($resolved / $total) * 100) : 0 ?>% resolution rate
                    </small>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="app-card p-3 p-lg-4 text-center h-100">
                    <span class="text-secondary small fw-bold text-uppercase">Pending Active</span>
                    <h2 class="fw-extrabold text-warning my-1"><?= $pending ?></h2>
                    <small class="text-muted">In queue or repair</small>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="app-card p-3 p-lg-4 text-center h-100">
                    <span class="text-secondary small fw-bold text-uppercase">Avg Turnaround</span>
                    <h2 class="fw-extrabold text-dark my-1"><?= $avgHours ?> hrs</h2>
                    <small class="text-muted">Creation to resolution</small>
                </div>
            </div>

        </div>

        <!-- Tables: By Category, Department, Priority -->
        <div class="row g-4 mb-4">
            
            <div class="col-md-4">
                <div class="app-card p-4 h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-tags text-primary me-2"></i>By Category</h6>
                    <ul class="list-group list-group-flush small">
                        <?php foreach (array_slice($categoryBreakdown, 0, 7) as $cat): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-dark"><?= e($cat['name']) ?></span>
                                <span class="badge bg-light text-dark border"><?= (int)$cat['count'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="col-md-4">
                <div class="app-card p-4 h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-buildings text-primary me-2"></i>By Department</h6>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($deptBreakdown as $dept): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-dark"><?= e($dept['name']) ?></span>
                                <span class="badge bg-light text-dark border"><?= (int)$dept['count'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="col-md-4">
                <div class="app-card p-4 h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-exclamation-triangle text-primary me-2"></i>By Priority</h6>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($priorityBreakdown as $p): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <span class="text-dark"><?= getPriorityBadge($p['priority']) ?></span>
                                <span class="badge bg-light text-dark border"><?= (int)$p['count'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Detailed Complaints Log for Report -->
        <div class="app-card p-4">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-table text-primary me-2"></i>Individual Complaint Records</h6>

            <div class="table-responsive">
                <table class="table-custom small">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Title & Location</th>
                            <th>Category</th>
                            <th>Student</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Date Filed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportRecords)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No records found for the selected period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reportRecords as $r): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= e($r['complaint_code']) ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($r['title']) ?></div>
                                        <small class="text-muted"><?= e($r['location']) ?></small>
                                    </td>
                                    <td><?= e($r['category_name']) ?></td>
                                    <td><?= e($r['student_name']) ?></td>
                                    <td><?= getPriorityBadge($r['priority']) ?></td>
                                    <td><?= getStatusBadge($r['status']) ?></td>
                                    <td>
                                        <?= !empty($r['dept_name']) ? e($r['dept_name']) . ' (' . e($r['staff_name'] ?? 'Staff') . ')' : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td><?= formatDateTime($r['created_at'], 'd M Y') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-none d-print-block mt-5 pt-4 text-end border-top">
                <div class="fw-bold">Authorized Signature</div>
                <div class="text-muted small">Campus Facilities Maintenance Officer</div>
            </div>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
