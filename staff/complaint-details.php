<?php
/**
 * Staff Complaint Details & Workflow Action Processor
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Task Details - Staff Portal';
$activeNav = 'complaints';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['staff']);

$user = currentUser();
$staffId = (int) $user['id'];
$complaintId = (int) ($_GET['id'] ?? 0);

if ($complaintId <= 0) {
    setFlash('danger', 'Invalid complaint identifier.');
    header('Location: ' . url('staff/complaints.php'));
    exit;
}

// 1. Verify staff assignment
try {
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name as category_name, 
               u.name as student_name, u.email as student_email, u.phone as student_phone,
               d.name as department_name, a.assigned_at
        FROM assignments a
        JOIN complaints c ON a.complaint_id = c.id
        JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON c.user_id = u.id
        JOIN departments d ON a.department_id = d.id
        WHERE a.staff_id = ? AND c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$staffId, $complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        setFlash('danger', 'Task not found or you are not assigned to this complaint.');
        header('Location: ' . url('staff/complaints.php'));
        exit;
    }

    // Handle Workflow Action: START WORK
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_work') {
        if (!validateCsrfToken()) {
            setFlash('danger', 'Security session mismatch. Please try again.');
        } else {
            $updateStmt = $pdo->prepare("UPDATE complaints SET status = 'In Progress', updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$complaintId]);

            // Log timeline event
            logComplaintUpdate(
                $pdo,
                $complaintId,
                $staffId,
                'Assigned',
                'In Progress',
                "Technician {$user['name']} has arrived on site and started maintenance work."
            );

            // Notify student
            createNotification(
                $pdo,
                (int) $complaint['user_id'],
                'Technician Started Work',
                "Technician {$user['name']} has commenced work on your complaint {$complaint['complaint_code']}.",
                "student/complaint-details.php?id={$complaintId}"
            );

            setFlash('success', 'Work status updated to In Progress.');
            header('Location: ' . url('staff/complaint-details.php?id=' . $complaintId));
            exit;
        }
    }

    // Handle Workflow Action: RESOLVE COMPLAINT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_complaint') {
        if (!validateCsrfToken()) {
            setFlash('danger', 'Security session mismatch. Please try again.');
        } else {
            $notes = trim($_POST['resolution_notes'] ?? '');
            
            if (empty($notes)) {
                setFlash('danger', 'Please enter resolution notes describing what was repaired or fixed.');
            } else {
                $resolutionImage = null;
                try {
                    if (isset($_FILES['resolution_image']) && $_FILES['resolution_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $resolutionImage = handleImageUpload($_FILES['resolution_image'], 'uploads/complaints/');
                    }

                    $stmtResolve = $pdo->prepare("
                        UPDATE complaints 
                        SET status = 'Resolved',
                            resolution_notes = ?,
                            resolution_image = COALESCE(?, resolution_image),
                            resolved_at = NOW(),
                            resolved_by = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtResolve->execute([$notes, $resolutionImage, $staffId, $complaintId]);

                    // Log timeline event
                    logComplaintUpdate(
                        $pdo,
                        $complaintId,
                        $staffId,
                        $complaint['status'],
                        'Resolved',
                        "Maintenance completed: " . $notes
                    );

                    // Notify student
                    createNotification(
                        $pdo,
                        (int) $complaint['user_id'],
                        'Complaint Resolved',
                        "Your complaint {$complaint['complaint_code']} has been marked as Resolved. Please review and provide your feedback.",
                        "student/complaint-details.php?id={$complaintId}"
                    );

                    // Notify admins
                    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
                    foreach ($admins as $admin) {
                        createNotification(
                            $pdo,
                            (int) $admin['id'],
                            'Complaint Resolved by Staff',
                            "Technician {$user['name']} marked {$complaint['complaint_code']} as Resolved.",
                            "admin/complaint-details.php?id={$complaintId}"
                        );
                    }

                    setFlash('success', 'Complaint marked as resolved.');
                    header('Location: ' . url('staff/complaint-details.php?id=' . $complaintId));
                    exit;

                } catch (Exception $e) {
                    setFlash('danger', $e->getMessage());
                }
            }
        }
    }

    // Fetch timeline events
    $stmtTimeline = $pdo->prepare("
        SELECT u.*, actor.name as actor_name, actor.role as actor_role
        FROM complaint_updates u
        LEFT JOIN users actor ON u.user_id = actor.id
        WHERE u.complaint_id = ?
        ORDER BY u.created_at ASC
    ");
    $stmtTimeline->execute([$complaintId]);
    $timeline = $stmtTimeline->fetchAll();

    // Fetch student feedback if any
    $stmtFeedback = $pdo->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
    $stmtFeedback->execute([$complaintId]);
    $feedback = $stmtFeedback->fetch();

} catch (PDOException $e) {
    error_log("Staff task details error: " . $e->getMessage());
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
                        <li class="breadcrumb-item"><a href="<?= url('staff/dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= url('staff/complaints.php') ?>" class="text-decoration-none">Tasks</a></li>
                        <li class="breadcrumb-item active"><?= e($complaint['complaint_code']) ?></li>
                    </ol>
                </nav>
                <h3 class="fw-bold text-dark mb-0">
                    Task Details: <span class="text-primary"><?= e($complaint['complaint_code']) ?></span>
                </h3>
            </div>
            <a href="<?= url('staff/complaints.php') ?>" class="btn btn-light btn-sm border">
                <i class="bi bi-arrow-left me-1"></i> Back to Tasks
            </a>
        </div>

        <?php renderFlashMessages(); ?>

        <div class="row g-4">
            
            <div class="col-lg-8">
                
                <!-- Main Task Info Card -->
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

                    <!-- Contact & Location Box -->
                    <div class="p-3 bg-light rounded-3 mb-4 border">
                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <div class="text-muted mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Campus Location:</div>
                                <div class="fw-bold text-dark fs-6"><?= e($complaint['location']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted mb-1"><i class="bi bi-person text-primary me-1"></i> Student Contact:</div>
                                <div class="fw-bold text-dark"><?= e($complaint['student_name']) ?></div>
                                <div class="text-secondary">
                                    <?= e($complaint['student_email']) ?>
                                    <?php if (!empty($complaint['student_phone'])): ?>
                                        &bull; <a href="tel:<?= e($complaint['student_phone']) ?>" class="text-decoration-none fw-semibold"><?= e($complaint['student_phone']) ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mb-2">Student Problem Description</h6>
                    <div class="p-3 bg-white rounded-3 mb-4 border text-dark" style="white-space: pre-wrap; line-height: 1.6;"><?= e($complaint['description']) ?></div>

                    <?php if (!empty($complaint['image'])): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-image text-primary me-1"></i> Student Uploaded Photo:</h6>
                            <a href="<?= url('uploads/complaints/' . $complaint['image']) ?>" target="_blank">
                                <img src="<?= url('uploads/complaints/' . $complaint['image']) ?>" alt="Issue Photo" class="img-thumbnail" style="max-height: 250px;">
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Workflow Actions Banner -->
                    <div class="border-top pt-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-play-circle text-primary me-2"></i>Task Resolution Actions</h6>
                        
                        <div class="d-flex flex-wrap gap-3">
                            <?php if ($complaint['status'] === 'Assigned'): ?>
                                <form method="POST" action="<?= url('staff/complaint-details.php?id=' . $complaintId) ?>">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="start_work">
                                    <button type="submit" class="btn btn-warning text-dark fw-bold px-4 py-2 shadow-sm">
                                        <i class="bi bi-tools me-1"></i> Start Work (Change to In Progress)
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($complaint['status'], ['Assigned', 'In Progress'])): ?>
                                <button type="button" class="btn btn-success fw-bold px-4 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#resolveModal">
                                    <i class="bi bi-check-circle-fill me-1"></i> Resolve Complaint
                                </button>
                            <?php elseif ($complaint['status'] === 'Resolved'): ?>
                                <div class="alert alert-success d-flex align-items-center mb-0 w-100 py-2">
                                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                                    <div>
                                        <strong>Task Completed.</strong> You resolved this complaint on <?= formatDateTime($complaint['resolved_at']) ?>.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Display Resolution Notes if resolved -->
                    <?php if ($complaint['status'] === 'Resolved'): ?>
                        <div class="mt-4 p-3 bg-light rounded-3 border">
                            <h6 class="fw-bold text-success mb-2"><i class="bi bi-journal-check me-1"></i> Resolution Summary</h6>
                            <p class="small text-dark mb-2"><?= nl2br(e($complaint['resolution_notes'])) ?></p>
                            <?php if (!empty($complaint['resolution_image'])): ?>
                                <div class="mt-2">
                                    <div class="small fw-semibold text-muted mb-1">Attached Completion Proof:</div>
                                    <a href="<?= url('uploads/complaints/' . $complaint['resolution_image']) ?>" target="_blank">
                                        <img src="<?= url('uploads/complaints/' . $complaint['resolution_image']) ?>" alt="Resolution Proof" class="img-thumbnail" style="max-height: 180px;">
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Student Feedback Card if resolved and rated -->
                <?php if ($feedback): ?>
                    <div class="app-card p-4 mb-4 border">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-star-fill text-warning me-2"></i>Student Rating Received</h6>
                        <div class="p-3 bg-light rounded-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="star-display fs-5">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= $i <= $feedback['rating'] ? '★' : '<span class="empty">☆</span>' ?>
                                    <?php endfor; ?>
                                </span>
                                <span class="badge bg-success-subtle text-success small"><?= $feedback['rating'] ?> / 5 Stars</span>
                            </div>
                            <p class="small text-dark mb-1">&ldquo;<?= e($feedback['comment']) ?>&rdquo;</p>
                            <small class="text-muted">Reviewed on <?= formatDateTime($feedback['created_at']) ?></small>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Column: Dynamic Timeline -->
            <div class="col-lg-4">
                <div class="app-card p-4">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-clock-history text-primary me-2"></i>Work Timeline
                    </h5>
                    <p class="text-muted small mb-3">Live chronological history</p>

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

<!-- Modal: Resolve Complaint -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= url('staff/complaint-details.php?id=' . $complaintId) ?>" enctype="multipart/form-data">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="resolve_complaint">

                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="resolveModalLabel">
                        <i class="bi bi-check-circle text-success me-2"></i>Complete & Resolve Complaint
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="resolutionNotes" class="form-label small fw-bold text-dark">Resolution Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="resolutionNotes" name="resolution_notes" rows="4" placeholder="e.g. Fan capacitor was replaced and mounting brackets were secured. Tested running smoothly." required></textarea>
                        <div class="form-text small text-muted">Detail the specific actions taken, parts replaced, or repairs completed.</div>
                    </div>

                    <div class="mb-3">
                        <label for="resolutionImage" class="form-label small fw-bold text-dark">Proof of Repair Photo <span class="text-muted fw-normal">(Optional)</span></label>
                        <input class="form-control" type="file" id="resolutionImage" name="resolution_image" accept=".jpg,.jpeg,.png,.webp">
                        <div class="form-text small text-muted">Upload an image showing the repaired equipment or completed work (JPG, PNG, WEBP, max 5MB).</div>
                    </div>
                </div>

                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        <i class="bi bi-check2-circle me-1"></i> Submit Resolution
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
