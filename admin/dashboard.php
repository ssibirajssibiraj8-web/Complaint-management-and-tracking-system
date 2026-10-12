<?php
/**
 * Admin Executive Dashboard & Visual Analytics
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Admin Dashboard - Campus Care';
$activeNav = 'dashboard';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

// Fetch All Dynamic Database Counts
$totalUsers = 0;
$totalStudents = 0;
$totalStaff = 0;
$totalComplaints = 0;
$submittedCount = 0;
$underReviewCount = 0;
$inProgressCount = 0;
$resolvedCount = 0;
$rejectedCount = 0;

// Chart datasets
$categoryLabels = [];
$categoryCounts = [];
$statusLabels = ['Submitted', 'Under Review', 'Approved', 'Assigned', 'In Progress', 'Resolved', 'Rejected'];
$statusCounts = [];
$priorityLabels = ['Low', 'Medium', 'High'];
$priorityCounts = [0, 0, 0];
$monthlyLabels = [];
$monthlyCounts = [];

try {
    // 1. User counters
    $stmtUsers = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff
        FROM users
    ");
    $uStats = $stmtUsers->fetch();
    $totalUsers = (int) ($uStats['total'] ?? 0);
    $totalStudents = (int) ($uStats['students'] ?? 0);
    $totalStaff = (int) ($uStats['staff'] ?? 0);

    // 2. Complaint status counters
    $stmtCmp = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM complaints
    ");
    $cStats = $stmtCmp->fetch();
    $totalComplaints = (int) ($cStats['total'] ?? 0);
    $submittedCount = (int) ($cStats['submitted'] ?? 0);
    $underReviewCount = (int) ($cStats['under_review'] ?? 0);
    $inProgressCount = (int) ($cStats['in_progress'] ?? 0);
    $resolvedCount = (int) ($cStats['resolved'] ?? 0);
    $rejectedCount = (int) ($cStats['rejected'] ?? 0);

    // 3. Category Distribution Chart Data
    $catQuery = $pdo->query("
        SELECT cat.name, COUNT(c.id) as total
        FROM categories cat
        LEFT JOIN complaints c ON cat.id = c.category_id
        GROUP BY cat.id
        ORDER BY total DESC
        LIMIT 6
    ");
    while ($row = $catQuery->fetch()) {
        $categoryLabels[] = $row['name'];
        $categoryCounts[] = (int) $row['total'];
    }

    // 4. Status Breakdown Chart Data
    foreach ($statusLabels as $st) {
        $stQuery = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE status = ?");
        $stQuery->execute([$st]);
        $statusCounts[] = (int) $stQuery->fetchColumn();
    }

    // 5. Priority Distribution Chart Data
    $prioQuery = $pdo->query("
        SELECT 
            SUM(CASE WHEN priority = 'Low' THEN 1 ELSE 0 END) as p_low,
            SUM(CASE WHEN priority = 'Medium' THEN 1 ELSE 0 END) as p_med,
            SUM(CASE WHEN priority = 'High' THEN 1 ELSE 0 END) as p_high
        FROM complaints
    ")->fetch();
    $priorityCounts = [
        (int) ($prioQuery['p_low'] ?? 0),
        (int) ($prioQuery['p_med'] ?? 0),
        (int) ($prioQuery['p_high'] ?? 0)
    ];

    // 6. Monthly trend (Past 6 months)
    for ($i = 5; $i >= 0; $i--) {
        $monthStr = date('M Y', strtotime("-$i months"));
        $startOfMonth = date('Y-m-01 00:00:00', strtotime("-$i months"));
        $endOfMonth = date('Y-m-t 23:59:59', strtotime("-$i months"));

        $mQuery = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE created_at BETWEEN ? AND ?");
        $mQuery->execute([$startOfMonth, $endOfMonth]);
        
        $monthlyLabels[] = $monthStr;
        $monthlyCounts[] = (int) $mQuery->fetchColumn();
    }

    // 7. Recent Pending Complaints
    $recentPending = $pdo->query("
        SELECT c.*, cat.name as category_name, u.name as student_name
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON c.user_id = u.id
        WHERE c.status IN ('Submitted', 'Under Review')
        ORDER BY c.created_at DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $recentPending = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">
                    <i class="bi bi-speedometer2 text-primary me-2"></i>Campus Operations Console
                </h3>
                <p class="text-secondary small mb-0">Overview of campus infrastructure requests, technician workloads, and resolution metrics.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('admin/complaints.php') ?>" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                    <i class="bi bi-inbox me-1"></i> Manage Complaints
                </a>
                <a href="<?= url('admin/reports.php') ?>" class="btn btn-light border px-3 py-2 fw-semibold">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i> Full Reports
                </a>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Primary KPI Cards (Users & Overall Complaints) -->
        <div class="row g-3 mb-4">
            
            <div class="col-6 col-md-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Total Users</span>
                        <div class="card-stat-icon bg-primary-subtle text-primary">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $totalUsers ?></h3>
                    <small class="text-muted"><?= $totalStudents ?> Students, <?= $totalStaff ?> Staff</small>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Total Complaints</span>
                        <div class="card-stat-icon bg-info-subtle text-info-emphasis">
                            <i class="bi bi-folder2-open"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $totalComplaints ?></h3>
                    <small class="text-muted">All registered campus tickets</small>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Awaiting Action</span>
                        <div class="card-stat-icon bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $submittedCount + $underReviewCount ?></h3>
                    <small class="text-warning-emphasis fw-semibold"><?= $submittedCount ?> new submissions</small>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="app-card p-3 p-lg-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Resolved</span>
                        <div class="card-stat-icon bg-success-subtle text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-0"><?= $resolvedCount ?></h3>
                    <small class="text-success fw-semibold">
                        <?= $totalComplaints > 0 ? round(($resolvedCount / $totalComplaints) * 100) : 0 ?>% resolution rate
                    </small>
                </div>
            </div>

        </div>

        <!-- 4 Visual Analytics Charts using Chart.js -->
        <div class="row g-4 mb-4">
            
            <!-- 1. Monthly Trends Chart -->
            <div class="col-lg-8">
                <div class="app-card p-4 h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up text-primary me-2"></i>Monthly Complaint Registrations</h6>
                    <div style="height: 260px;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 2. Complaints by Category (Doughnut) -->
            <div class="col-lg-4">
                <div class="app-card p-4 h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart text-primary me-2"></i>Top Complaint Categories</h6>
                    <div style="height: 260px; position: relative;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 3. Complaints by Status (Horizontal Bar) -->
            <div class="col-lg-6">
                <div class="app-card p-4 h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-steps text-primary me-2"></i>Complaints by Status Lifecycle</h6>
                    <div style="height: 240px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 4. Complaints by Priority (Pie Chart) -->
            <div class="col-lg-6">
                <div class="app-card p-4 h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-exclamation-triangle text-primary me-2"></i>Priority Distribution</h6>
                    <div style="height: 240px; position: relative;">
                        <canvas id="priorityChart"></canvas>
                    </div>
                </div>
            </div>

        </div>

        <!-- Action Items: Complaints Awaiting Review & Assignment -->
        <div class="app-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-bell text-warning me-2"></i>Complaints Requiring Administrative Review
                </h5>
                <a href="<?= url('admin/complaints.php?status=Submitted') ?>" class="small text-primary fw-semibold text-decoration-none">
                    View All Pending &rarr;
                </a>
            </div>

            <?php if (empty($recentPending)): ?>
                <div class="empty-state-box py-4">
                    <div class="empty-state-icon">
                        <i class="bi bi-check2-all text-success"></i>
                    </div>
                    <h6 class="fw-bold text-dark">All complaints reviewed!</h6>
                    <p class="text-secondary small mb-0">No tickets are currently waiting for admin review or department assignment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Title & Student</th>
                                <th>Category & Location</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Reported</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPending as $p): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('admin/complaint-details.php?id=' . $p['id']) ?>" class="fw-bold text-primary text-decoration-none">
                                            <?= e($p['complaint_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;"><?= e($p['title']) ?></div>
                                        <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($p['student_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border small"><?= e($p['category_name']) ?></span>
                                        <div class="small text-secondary text-truncate mt-1" style="max-width: 170px;">
                                            <i class="bi bi-geo-alt me-1 text-danger"></i><?= e($p['location']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= getPriorityBadge($p['priority']) ?>
                                    </td>
                                    <td>
                                        <?= getStatusBadge($p['status']) ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= formatDateTime($p['created_at'], 'd M, h:i A') ?></small>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('admin/complaint-details.php?id=' . $p['id']) ?>" class="btn btn-sm btn-primary px-3 py-1">
                                            Review & Assign &rarr;
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

<!-- Chart.js Scripts Initialization -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Monthly Trend Bar Chart
    const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthlyLabels) ?>,
                datasets: [{
                    label: 'Complaints Logged',
                    data: <?= json_encode($monthlyCounts) ?>,
                    backgroundColor: '#2563EB',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // 2. Category Doughnut Chart
    const categoryCtx = document.getElementById('categoryChart')?.getContext('2d');
    if (categoryCtx) {
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($categoryLabels) ?>,
                datasets: [{
                    data: <?= json_encode($categoryCounts) ?>,
                    backgroundColor: ['#2563EB', '#3B82F6', '#60A5FA', '#93C5FD', '#F59E0B', '#10B981'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
            }
        });
    }

    // 3. Status Breakdown Horizontal Bar Chart
    const statusCtx = document.getElementById('statusChart')?.getContext('2d');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($statusLabels) ?>,
                datasets: [{
                    label: 'Count',
                    data: <?= json_encode($statusCounts) ?>,
                    backgroundColor: ['#94A3B8', '#38BDF8', '#6366F1', '#FBBF24', '#F59E0B', '#22C55E', '#EF4444'],
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 1 } },
                    y: { grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // 4. Priority Pie Chart
    const priorityCtx = document.getElementById('priorityChart')?.getContext('2d');
    if (priorityCtx) {
        new Chart(priorityCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($priorityLabels) ?>,
                datasets: [{
                    data: <?= json_encode($priorityCounts) ?>,
                    backgroundColor: ['#16A34A', '#F59E0B', '#DC2626'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
