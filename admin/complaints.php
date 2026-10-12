<?php
/**
 * Admin Complaint Management Grid
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Manage Complaints - Admin Console';
$activeNav = 'complaints';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

$user = currentUser();
$adminId = (int) $user['id'];

// Handle Destructive Action: DELETE COMPLAINT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_complaint') {
    if (!validateCsrfToken()) {
        setFlash('danger', 'Security session mismatch. Please try again.');
    } else {
        $deleteId = (int) ($_POST['complaint_id'] ?? 0);
        if ($deleteId > 0) {
            try {
                // Fetch image paths to delete files from disk if necessary
                $stmtImg = $pdo->prepare("SELECT image, resolution_image, complaint_code FROM complaints WHERE id = ?");
                $stmtImg->execute([$deleteId]);
                $cmpData = $stmtImg->fetch();

                if ($cmpData) {
                    if (!empty($cmpData['image'])) {
                        @unlink(__DIR__ . '/../uploads/complaints/' . $cmpData['image']);
                    }
                    if (!empty($cmpData['resolution_image'])) {
                        @unlink(__DIR__ . '/../uploads/complaints/' . $cmpData['resolution_image']);
                    }

                    $delStmt = $pdo->prepare("DELETE FROM complaints WHERE id = ?");
                    $delStmt->execute([$deleteId]);

                    setFlash('success', "Complaint {$cmpData['complaint_code']} and related records were deleted successfully.");
                }
            } catch (PDOException $e) {
                error_log("Delete complaint error: " . $e->getMessage());
                setFlash('danger', 'Unable to delete complaint due to database constraints.');
            }
        }
        header('Location: ' . url('admin/complaints.php'));
        exit;
    }
}

// Handle Quick Action: APPROVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_approve') {
    if (validateCsrfToken()) {
        $appId = (int) ($_POST['complaint_id'] ?? 0);
        if ($appId > 0) {
            $stmt = $pdo->prepare("UPDATE complaints SET status = 'Approved', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$appId]);

            // Timeline & Notification
            $cRow = $pdo->query("SELECT user_id, complaint_code FROM complaints WHERE id = {$appId}")->fetch();
            logComplaintUpdate($pdo, $appId, $adminId, 'Submitted', 'Approved', 'Complaint reviewed and approved by administration.');
            createNotification($pdo, (int)$cRow['user_id'], 'Complaint Approved', "Your complaint {$cRow['complaint_code']} has been approved and queued for department assignment.", "student/complaint-details.php?id={$appId}");

            setFlash('success', "Complaint {$cRow['complaint_code']} approved.");
        }
        header('Location: ' . url('admin/complaints.php'));
        exit;
    }
}

// Handle Quick Action: REJECT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_reject') {
    if (validateCsrfToken()) {
        $rejId = (int) ($_POST['complaint_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Not approved according to campus maintenance policy.');
        if ($rejId > 0) {
            $stmt = $pdo->prepare("UPDATE complaints SET status = 'Rejected', resolution_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reason, $rejId]);

            $cRow = $pdo->query("SELECT user_id, complaint_code FROM complaints WHERE id = {$rejId}")->fetch();
            logComplaintUpdate($pdo, $rejId, $adminId, 'Submitted', 'Rejected', "Complaint rejected: {$reason}");
            createNotification($pdo, (int)$cRow['user_id'], 'Complaint Rejected', "Your complaint {$cRow['complaint_code']} was rejected. Reason: {$reason}", "student/complaint-details.php?id={$rejId}");

            setFlash('warning', "Complaint {$cRow['complaint_code']} marked as Rejected.");
        }
        header('Location: ' . url('admin/complaints.php'));
        exit;
    }
}

// Filters & Server Search
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$priority = trim($_GET['priority'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$sql = "
    SELECT c.*, cat.name as category_name, u.name as student_name, u.email as student_email,
           dept.name as assigned_dept_name, staff.name as assigned_staff_name
    FROM complaints c
    JOIN categories cat ON c.category_id = cat.id
    JOIN users u ON c.user_id = u.id
    LEFT JOIN assignments a ON c.id = a.complaint_id
    LEFT JOIN departments dept ON a.department_id = dept.id
    LEFT JOIN users staff ON a.staff_id = staff.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.complaint_code LIKE :search OR c.title LIKE :search OR c.location LIKE :search OR u.name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($status)) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $status;
}

if ($categoryId > 0) {
    $sql .= " AND c.category_id = :category_id";
    $params[':category_id'] = $categoryId;
}

if (!empty($priority)) {
    $sql .= " AND c.priority = :priority";
    $params[':priority'] = $priority;
}

if (!empty($dateFrom)) {
    $sql .= " AND c.created_at >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $sql .= " AND c.created_at <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$sql .= " ORDER BY c.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();
    $totalFound = count($complaints);

    // Fetch categories for filter dropdown
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Admin complaints filter error: " . $e->getMessage());
    $complaints = [];
    $categories = [];
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
                    <i class="bi bi-inbox text-primary me-2"></i>Campus Complaints Management
                </h3>
                <p class="text-secondary small mb-0">Search, review, prioritize, assign, and manage all campus complaint tickets.</p>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Server-Side Multi-Criteria Filter Bar -->
        <div class="app-card p-4 mb-4">
            <form method="GET" action="<?= url('admin/complaints.php') ?>" class="row g-3">
                
                <div class="col-md-4 col-lg-3">
                    <label for="searchInput" class="form-label small fw-bold text-dark">Keyword</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" name="search" value="<?= e($search) ?>" placeholder="Code, title, student, room...">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <label for="statusSelect" class="form-label small fw-bold text-dark">Status</label>
                    <select class="form-select" id="statusSelect" name="status">
                        <option value="">All Statuses</option>
                        <option value="Submitted" <?= ($status === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
                        <option value="Under Review" <?= ($status === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
                        <option value="Approved" <?= ($status === 'Approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="Assigned" <?= ($status === 'Assigned') ? 'selected' : '' ?>>Assigned</option>
                        <option value="In Progress" <?= ($status === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="Resolved" <?= ($status === 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                        <option value="Rejected" <?= ($status === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label for="categorySelect" class="form-label small fw-bold text-dark">Category</label>
                    <select class="form-select" id="categorySelect" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($categoryId === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label for="prioritySelect" class="form-label small fw-bold text-dark">Priority</label>
                    <select class="form-select" id="prioritySelect" name="priority">
                        <option value="">All Priorities</option>
                        <option value="Low" <?= ($priority === 'Low') ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($priority === 'Medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($priority === 'High') ? 'selected' : '' ?>>High</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label for="dateFrom" class="form-label small fw-bold text-dark">Date From</label>
                    <input type="date" class="form-control" id="dateFrom" name="date_from" value="<?= e($dateFrom) ?>">
                </div>

                <div class="col-6 col-md-2">
                    <label for="dateTo" class="form-label small fw-bold text-dark">Date To</label>
                    <input type="date" class="form-control" id="dateTo" name="date_to" value="<?= e($dateTo) ?>">
                </div>

                <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i> Apply
                    </button>
                    <a href="<?= url('admin/complaints.php') ?>" class="btn btn-light border" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>

            </form>
        </div>

        <!-- Complaints Table Card -->
        <div class="app-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="small text-muted fw-semibold">
                    Displaying <strong><?= $totalFound ?></strong> complaint<?= $totalFound === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (empty($complaints)): ?>
                <div class="empty-state-box">
                    <div class="empty-state-icon">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No complaints match your criteria</h5>
                    <p class="text-secondary small mb-3">Try clearing filters or search terms to see all complaints.</p>
                    <a href="<?= url('admin/complaints.php') ?>" class="btn btn-outline-primary btn-sm">Reset Filters</a>
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
                                <th>Status</th>
                                <th>Assignment</th>
                                <th>Reported</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('admin/complaint-details.php?id=' . $c['id']) ?>" class="fw-bold text-primary text-decoration-none">
                                            <?= e($c['complaint_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 200px;"><?= e($c['title']) ?></div>
                                        <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($c['student_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border small"><?= e($c['category_name']) ?></span>
                                        <div class="small text-secondary text-truncate mt-1" style="max-width: 150px;">
                                            <i class="bi bi-geo-alt me-1 text-danger"></i><?= e($c['location']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= getPriorityBadge($c['priority']) ?>
                                    </td>
                                    <td>
                                        <?= getStatusBadge($c['status']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($c['assigned_dept_name'])): ?>
                                            <small class="fw-semibold text-dark d-block"><?= e($c['assigned_dept_name']) ?></small>
                                            <small class="text-muted"><i class="bi bi-person-gear me-1"></i><?= e($c['assigned_staff_name'] ?? 'Staff') ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary small">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= formatDateTime($c['created_at'], 'd M Y, h:i A') ?></small>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border py-1 px-2.5" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 small">
                                                <li>
                                                    <a class="dropdown-item py-1.5" href="<?= url('admin/complaint-details.php?id=' . $c['id']) ?>">
                                                        <i class="bi bi-eye text-primary me-2"></i> View & Assign
                                                    </a>
                                                </li>

                                                <?php if ($c['status'] === 'Submitted'): ?>
                                                    <li>
                                                        <form method="POST" action="<?= url('admin/complaints.php') ?>">
                                                            <?= csrfInput() ?>
                                                            <input type="hidden" name="action" value="quick_approve">
                                                            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                                            <button type="submit" class="dropdown-item py-1.5 text-success">
                                                                <i class="bi bi-check-circle me-2"></i> Approve Complaint
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if (in_array($c['status'], ['Submitted', 'Under Review'])): ?>
                                                    <li>
                                                        <button type="button" class="dropdown-item py-1.5 text-warning" onclick="openRejectModal(<?= $c['id'] ?>, '<?= e($c['complaint_code']) ?>')">
                                                            <i class="bi bi-x-circle me-2"></i> Reject Complaint
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" action="<?= url('admin/complaints.php') ?>" onsubmit="return confirm('Are you sure you want to permanently delete complaint <?= e($c['complaint_code']) ?>? This action cannot be undone.');">
                                                        <?= csrfInput() ?>
                                                        <input type="hidden" name="action" value="delete_complaint">
                                                        <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                                                        <button type="submit" class="dropdown-item py-1.5 text-danger">
                                                            <i class="bi bi-trash3 me-2"></i> Delete Ticket
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
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

<!-- Modal: Reject Complaint with Explanation -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= url('admin/complaints.php') ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="quick_reject">
                <input type="hidden" name="complaint_id" id="rejectComplaintId" value="">

                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="rejectModalLabel">
                        <i class="bi bi-x-octagon text-danger me-2"></i>Reject Complaint <span id="rejectComplaintCode"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label small fw-bold text-dark">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectReason" name="reason" rows="3" placeholder="State clearly why this complaint cannot be serviced (e.g. invalid campus facility, handled by outside vendor, duplicate request)..." required></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">Reject Complaint</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRejectModal(id, code) {
    document.getElementById('rejectComplaintId').value = id;
    document.getElementById('rejectComplaintCode').textContent = code;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
