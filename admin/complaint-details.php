<?php
/**
 * Admin Complaint Deep-Dive & Assignment Interface
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Complaint Details & Assignment - Admin Console';
$activeNav = 'complaints';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

$user = currentUser();
$adminId = (int) $user['id'];
$complaintId = (int) ($_GET['id'] ?? 0);

if ($complaintId <= 0) {
    setFlash('danger', 'Invalid complaint ID.');
    header('Location: ' . url('admin/complaints.php'));
    exit;
}

// Fetch Complaint Details
try {
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name as category_name,
               u.name as student_name, u.email as student_email, u.phone as student_phone,
               resolver.name as resolver_name
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON c.user_id = u.id
        LEFT JOIN users resolver ON c.resolved_by = resolver.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        setFlash('danger', 'Complaint record not found.');
        header('Location: ' . url('admin/complaints.php'));
        exit;
    }

    // Handle Action 1: ASSIGN DEPARTMENT & STAFF
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_complaint') {
        if (!validateCsrfToken()) {
            setFlash('danger', 'Security session expired. Please try again.');
        } else {
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $staffId = (int) ($_POST['staff_id'] ?? 0);
            $priority = trim($_POST['priority'] ?? $complaint['priority']);

            if ($departmentId <= 0 || $staffId <= 0) {
                setFlash('danger', 'Please select both a department and an active staff member.');
            } else {
                // Fetch staff and dept names
                $deptRow = $pdo->query("SELECT name FROM departments WHERE id = {$departmentId}")->fetch();
                $staffRow = $pdo->query("SELECT name FROM users WHERE id = {$staffId} AND role = 'staff'")->fetch();

                if (!$deptRow || !$staffRow) {
                    setFlash('danger', 'Selected department or staff member is invalid.');
                } else {
                    // 1. Record / Update Assignment
                    $stmtAssignCheck = $pdo->prepare("SELECT id FROM assignments WHERE complaint_id = ?");
                    $stmtAssignCheck->execute([$complaintId]);
                    $existingAssignId = $stmtAssignCheck->fetchColumn();

                    if ($existingAssignId) {
                        $upAssign = $pdo->prepare("UPDATE assignments SET department_id = ?, staff_id = ?, assigned_by = ?, assigned_at = NOW() WHERE id = ?");
                        $upAssign->execute([$departmentId, $staffId, $adminId, $existingAssignId]);
                    } else {
                        $inAssign = $pdo->prepare("INSERT INTO assignments (complaint_id, department_id, staff_id, assigned_by, assigned_at) VALUES (?, ?, ?, ?, NOW())");
                        $inAssign->execute([$complaintId, $departmentId, $staffId, $adminId]);
                    }

                    // 2. Update Complaint Status & Priority
                    $oldStatus = $complaint['status'];
                    $upCmp = $pdo->prepare("UPDATE complaints SET status = 'Assigned', priority = ?, updated_at = NOW() WHERE id = ?");
                    $upCmp->execute([$priority, $complaintId]);

                    // 3. Log Timeline Event
                    logComplaintUpdate(
                        $pdo,
                        $complaintId,
                        $adminId,
                        $oldStatus,
                        'Assigned',
                        "Assigned to {$deptRow['name']} and technician {$staffRow['name']} with {$priority} priority."
                    );

                    // 4. Notify Staff Member
                    createNotification(
                        $pdo,
                        $staffId,
                        'New Maintenance Task Assigned',
                        "You have been assigned to complaint {$complaint['complaint_code']}: {$complaint['title']}.",
                        "staff/complaint-details.php?id={$complaintId}"
                    );

                    // 5. Notify Student
                    createNotification(
                        $pdo,
                        (int) $complaint['user_id'],
                        'Complaint Assigned to Technician',
                        "Your complaint {$complaint['complaint_code']} has been assigned to {$deptRow['name']} (Staff: {$staffRow['name']}).",
                        "student/complaint-details.php?id={$complaintId}"
                    );

                    setFlash('success', 'Complaint assigned successfully.');
                    header('Location: ' . url('admin/complaint-details.php?id=' . $complaintId));
                    exit;
                }
            }
        }
    }

    // Handle Action 2: STATUS OVERRIDE
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
        if (validateCsrfToken()) {
            $newStatus = trim($_POST['status'] ?? '');
            $comment = trim($_POST['comment'] ?? '');

            $validStatuses = ['Submitted', 'Under Review', 'Approved', 'Assigned', 'In Progress', 'Resolved', 'Rejected', 'Closed'];
            if (in_array($newStatus, $validStatuses, true)) {
                $oldStatus = $complaint['status'];
                $upStmt = $pdo->prepare("UPDATE complaints SET status = ?, updated_at = NOW() WHERE id = ?");
                $upStmt->execute([$newStatus, $complaintId]);

                logComplaintUpdate($pdo, $complaintId, $adminId, $oldStatus, $newStatus, $comment ?: "Status updated to {$newStatus} by Administrator.");

                createNotification(
                    $pdo,
                    (int) $complaint['user_id'],
                    "Complaint Status Updated to {$newStatus}",
                    "Your complaint {$complaint['complaint_code']} is now {$newStatus}." . ($comment ? " Note: {$comment}" : ""),
                    "student/complaint-details.php?id={$complaintId}"
                );

                setFlash('success', "Status updated to {$newStatus}.");
                header('Location: ' . url('admin/complaint-details.php?id=' . $complaintId));
                exit;
            }
        }
    }

    // Fetch existing assignment
    $stmtAssign = $pdo->prepare("
        SELECT a.*, d.name as department_name, s.name as staff_name, s.phone as staff_phone, s.email as staff_email
        FROM assignments a
        JOIN departments d ON a.department_id = d.id
        JOIN users s ON a.staff_id = s.id
        WHERE a.complaint_id = ?
        ORDER BY a.id DESC LIMIT 1
    ");
    $stmtAssign->execute([$complaintId]);
    $currentAssignment = $stmtAssign->fetch();

    // Fetch dynamic timeline
    $stmtTimeline = $pdo->prepare("
        SELECT u.*, actor.name as actor_name, actor.role as actor_role
        FROM complaint_updates u
        LEFT JOIN users actor ON u.user_id = actor.id
        WHERE u.complaint_id = ?
        ORDER BY u.created_at ASC
    ");
    $stmtTimeline->execute([$complaintId]);
    $timeline = $stmtTimeline->fetchAll();

    // Fetch student feedback if available
    $stmtFeedback = $pdo->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
    $stmtFeedback->execute([$complaintId]);
    $feedback = $stmtFeedback->fetch();

    // Fetch departments and active staff members for assignment form
    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
    $staffMembers = $pdo->query("SELECT id, name, email FROM users WHERE role = 'staff' AND status = 'active' ORDER BY name ASC")->fetchAll();

} catch (PDOException $e) {
    error_log("Admin complaint details error: " . $e->getMessage());
    die("Database error occurred.");
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= url('admin/dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= url('admin/complaints.php') ?>" class="text-decoration-none">Complaints</a></li>
                        <li class="breadcrumb-item active"><?= e($complaint['complaint_code']) ?></li>
                    </ol>
                </nav>
                <h3 class="fw-bold text-dark mb-0">
                    Complaint <span class="text-primary"><?= e($complaint['complaint_code']) ?></span>
                </h3>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('admin/complaints.php') ?>" class="btn btn-light btn-sm border">
                    <i class="bi bi-arrow-left me-1"></i> Back to List
                </a>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <div class="row g-4">
            
            <!-- Left Column: Details, Photos, Assignment Form, Override -->
            <div class="col-lg-8">
                
                <!-- Main Information Card -->
                <div class="app-card p-4 p-md-5 mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-3 border-bottom">
                        <div>
                            <h4 class="fw-bold text-dark mb-1"><?= e($complaint['title']) ?></h4>
                            <span class="badge bg-light text-secondary border small"><?= e($complaint['category_name']) ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?= getPriorityBadge($complaint['priority']) ?>
                            <?= getStatusBadge($complaint['status']) ?>
                        </div>
                    </div>

                    <!-- Student & Location Information -->
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <div class="text-muted mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Location:</div>
                                <div class="fw-bold text-dark fs-6"><?= e($complaint['location']) ?></div>
                                <div class="text-muted mt-2">Reported: <strong><?= formatDateTime($complaint['created_at']) ?></strong></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted mb-1"><i class="bi bi-person text-primary me-1"></i> Student Info:</div>
                                <div class="fw-bold text-dark"><?= e($complaint['student_name']) ?></div>
                                <div class="text-secondary"><?= e($complaint['student_email']) ?></div>
                                <?php if (!empty($complaint['student_phone'])): ?>
                                    <div class="text-secondary mt-1"><i class="bi bi-telephone me-1"></i><?= e($complaint['student_phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mb-2">Problem Description</h6>
                    <div class="p-3 bg-white rounded-3 mb-4 border text-dark" style="white-space: pre-wrap; line-height: 1.6;"><?= e($complaint['description']) ?></div>

                    <!-- Uploaded Issue Image -->
                    <?php if (!empty($complaint['image'])): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-image text-primary me-1"></i> Attached Issue Photo</h6>
                            <a href="<?= url('uploads/complaints/' . $complaint['image']) ?>" target="_blank">
                                <img src="<?= url('uploads/complaints/' . $complaint['image']) ?>" alt="Issue Photo" class="img-thumbnail" style="max-height: 250px;">
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Resolution Information (If Resolved) -->
                    <?php if ($complaint['status'] === 'Resolved'): ?>
                        <div class="p-4 rounded-3 bg-success-subtle border border-success-subtle mb-4">
                            <h6 class="fw-bold text-success mb-2"><i class="bi bi-check-circle-fill me-1"></i> Resolved by Staff</h6>
                            <p class="small text-dark mb-2"><strong>Notes:</strong> <?= nl2br(e($complaint['resolution_notes'])) ?></p>
                            <div class="d-flex flex-wrap gap-4 small text-secondary mt-2">
                                <div>Resolved By: <strong class="text-dark"><?= e($complaint['resolver_name'] ?? 'Staff') ?></strong></div>
                                <div>Date: <strong class="text-dark"><?= formatDateTime($complaint['resolved_at']) ?></strong></div>
                            </div>
                            <?php if (!empty($complaint['resolution_image'])): ?>
                                <div class="mt-3">
                                    <div class="small fw-semibold mb-1">Resolution Proof:</div>
                                    <a href="<?= url('uploads/complaints/' . $complaint['resolution_image']) ?>" target="_blank">
                                        <img src="<?= url('uploads/complaints/' . $complaint['resolution_image']) ?>" alt="Proof" class="img-thumbnail" style="max-height: 160px;">
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Assignment Interface Card -->
                <div class="app-card p-4 p-md-5 mb-4">
                    <h5 class="fw-bold text-dark mb-3">
                        <i class="bi bi-person-gear text-primary me-2"></i>Department & Staff Assignment
                    </h5>
                    <p class="text-secondary small mb-4">
                        Direct this complaint to a specialized campus department and assign a specific staff technician to investigate and fix the problem.
                    </p>

                    <?php if ($currentAssignment): ?>
                        <div class="alert alert-info py-2.5 small mb-4 d-flex align-items-center justify-content-between">
                            <div>
                                Currently assigned to: <strong><?= e($currentAssignment['department_name']) ?></strong> &bull; Technician: <strong><?= e($currentAssignment['staff_name']) ?></strong> (<?= formatDateTime($currentAssignment['assigned_at']) ?>)
                            </div>
                            <span class="badge bg-primary">Assigned</span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= url('admin/complaint-details.php?id=' . $complaintId) ?>">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="assign_complaint">

                        <div class="row g-3 mb-4">
                            <div class="col-md-5">
                                <label for="assignDept" class="form-label small fw-bold text-dark">Department <span class="text-danger">*</span></label>
                                <select class="form-select" id="assignDept" name="department_id" required>
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= ($currentAssignment && (int)$currentAssignment['department_id'] === (int)$d['id']) ? 'selected' : '' ?>>
                                            <?= e($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="assignStaff" class="form-label small fw-bold text-dark">Staff Technician <span class="text-danger">*</span></label>
                                <select class="form-select" id="assignStaff" name="staff_id" required>
                                    <option value="">-- Select Technician --</option>
                                    <?php foreach ($staffMembers as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= ($currentAssignment && (int)$currentAssignment['staff_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                                            <?= e($s['name']) ?> (<?= e($s['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="assignPriority" class="form-label small fw-bold text-dark">Priority</label>
                                <select class="form-select" id="assignPriority" name="priority">
                                    <option value="Low" <?= ($complaint['priority'] === 'Low') ? 'selected' : '' ?>>Low</option>
                                    <option value="Medium" <?= ($complaint['priority'] === 'Medium') ? 'selected' : '' ?>>Medium</option>
                                    <option value="High" <?= ($complaint['priority'] === 'High') ? 'selected' : '' ?>>High</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                            <i class="bi bi-send-check me-1"></i> <?= $currentAssignment ? 'Re-assign Complaint' : 'Assign Complaint' ?>
                        </button>
                    </form>
                </div>

                <!-- Status Override Card -->
                <div class="app-card p-4 mb-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-sliders text-secondary me-2"></i>Status Override Control</h6>
                    <form method="POST" action="<?= url('admin/complaint-details.php?id=' . $complaintId) ?>" class="row g-3 align-items-end">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="update_status">

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Change Status</label>
                            <select class="form-select" name="status">
                                <?php foreach (['Submitted', 'Under Review', 'Approved', 'Assigned', 'In Progress', 'Resolved', 'Rejected', 'Closed'] as $st): ?>
                                    <option value="<?= $st ?>" <?= ($complaint['status'] === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Admin Remark</label>
                            <input type="text" class="form-control" name="comment" placeholder="Optional remark explaining status change...">
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-dark w-100 fw-semibold">Update</button>
                        </div>
                    </form>
                </div>

                <!-- Student Feedback Card if rated -->
                <?php if ($feedback): ?>
                    <div class="app-card p-4 border">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-star-fill text-warning me-2"></i>Student Rating & Review</h6>
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="star-display fs-5">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= $i <= $feedback['rating'] ? '★' : '<span class="empty">☆</span>' ?>
                                    <?php endfor; ?>
                                </span>
                                <span class="badge bg-success-subtle text-success small"><?= $feedback['rating'] ?> Stars</span>
                            </div>
                            <p class="small text-dark mb-1">&ldquo;<?= e($feedback['comment']) ?>&rdquo;</p>
                            <small class="text-muted">Recorded on <?= formatDateTime($feedback['created_at']) ?></small>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Column: Dynamic Timeline -->
            <div class="col-lg-4">
                <div class="app-card p-4">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-clock-history text-primary me-2"></i>Status Progression
                    </h5>
                    <p class="text-muted small mb-3">Live timeline updates</p>

                    <div class="complaint-timeline">
                        <?php foreach ($timeline as $index => $step): ?>
                            <?php 
                                $isLast = ($index === count($timeline) - 1);
                                $itemClass = $isLast ? 'active' : 'completed';
                            ?>
                            <div class="timeline-item <?= $itemClass ?>">
                                <div class="timeline-dot">
                                    <?php if ($isLast): ?>
                                        <i class="bi bi-dot text-white fs-4"></i>
                                    <?php else: ?>
                                        <i class="bi bi-check text-white"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold small text-dark"><?= getStatusBadge($step['new_status']) ?></span>
                                        <small class="text-muted" style="font-size: 0.72rem;"><?= formatDateTime($step['created_at'], 'd M, h:i A') ?></small>
                                    </div>
                                    <?php if (!empty($step['comment'])): ?>
                                        <p class="small text-secondary mb-1"><?= e($step['comment']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($step['actor_name'])): ?>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">
                                            By <?= e($step['actor_name']) ?> (<?= e(ucfirst($step['actor_role'] ?? 'user')) ?>)
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
