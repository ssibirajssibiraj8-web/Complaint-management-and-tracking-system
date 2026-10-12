<?php
/**
 * Complaint Details & Visual Dynamic Timeline
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Complaint Details - Student Portal';
$activeNav = 'my-complaints';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['student']);

$user = currentUser();
$studentId = (int) $user['id'];
$complaintId = (int) ($_GET['id'] ?? 0);

if ($complaintId <= 0) {
    setFlash('danger', 'Invalid complaint identifier.');
    header('Location: ' . url('student/my-complaints.php'));
    exit;
}

try {
    // 1. Fetch complaint with category and resolver details
    $stmt = $pdo->prepare("
        SELECT c.*, cat.name as category_name, cat.description as category_desc,
               resolver.name as resolver_name
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN users resolver ON c.resolved_by = resolver.id
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$complaintId, $studentId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        setFlash('danger', 'Complaint not found or you do not have permission to view it.');
        header('Location: ' . url('student/my-complaints.php'));
        exit;
    }

    // 2. Fetch assignment details if any
    $stmtAssign = $pdo->prepare("
        SELECT a.*, d.name as department_name, s.name as staff_name, s.phone as staff_phone, s.email as staff_email
        FROM assignments a
        JOIN departments d ON a.department_id = d.id
        JOIN users s ON a.staff_id = s.id
        WHERE a.complaint_id = ?
        ORDER BY a.id DESC LIMIT 1
    ");
    $stmtAssign->execute([$complaintId]);
    $assignment = $stmtAssign->fetch();

    // 3. Fetch dynamic timeline updates
    $stmtUpdates = $pdo->prepare("
        SELECT u.*, actor.name as actor_name, actor.role as actor_role
        FROM complaint_updates u
        LEFT JOIN users actor ON u.user_id = actor.id
        WHERE u.complaint_id = ?
        ORDER BY u.created_at ASC
    ");
    $stmtUpdates->execute([$complaintId]);
    $timeline = $stmtUpdates->fetchAll();

    // 4. Fetch feedback if already provided
    $stmtFeedback = $pdo->prepare("
        SELECT * FROM feedback WHERE complaint_id = ? AND user_id = ? LIMIT 1
    ");
    $stmtFeedback->execute([$complaintId, $studentId]);
    $feedback = $stmtFeedback->fetch();

} catch (PDOException $e) {
    error_log("Complaint details error: " . $e->getMessage());
    die("A database error occurred.");
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        
        <!-- Header & Breadcrumb -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= url('student/dashboard.php') ?>" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= url('student/my-complaints.php') ?>" class="text-decoration-none">My Complaints</a></li>
                        <li class="breadcrumb-item active"><?= e($complaint['complaint_code']) ?></li>
                    </ol>
                </nav>
                <h3 class="fw-bold text-dark mb-0">
                    Complaint <span class="text-primary"><?= e($complaint['complaint_code']) ?></span>
                </h3>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('student/my-complaints.php') ?>" class="btn btn-light btn-sm border">
                    <i class="bi bi-arrow-left me-1"></i> Back to Complaints
                </a>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <div class="row g-4">
            
            <!-- Left Column: Complaint Details, Assignment, Resolution & Feedback -->
            <div class="col-lg-8">
                
                <!-- Main Details Card -->
                <div class="app-card p-4 p-md-5 mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-3 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-5 fw-bold text-dark"><?= e($complaint['title']) ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?= getPriorityBadge($complaint['priority']) ?>
                            <?= getStatusBadge($complaint['status']) ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-4 text-secondary small">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-tags text-primary fs-6"></i>
                                <span>Category: <strong class="text-dark"><?= e($complaint['category_name']) ?></strong></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-calendar3 text-primary fs-6"></i>
                                <span>Reported on: <strong class="text-dark"><?= formatDateTime($complaint['created_at']) ?></strong></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-geo-alt-fill text-danger fs-6"></i>
                                <span>Location: <strong class="text-dark"><?= e($complaint['location']) ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mb-2">Problem Description</h6>
                    <div class="p-3 bg-light rounded-3 mb-4 text-dark" style="white-space: pre-wrap; line-height: 1.6;"><?= e($complaint['description']) ?></div>

                    <!-- Uploaded Image -->
                    <?php if (!empty($complaint['image'])): ?>
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-image me-1 text-primary"></i> Attached Issue Photo</h6>
                            <div class="p-2 border rounded-3 d-inline-block bg-white shadow-xs">
                                <a href="<?= url('uploads/complaints/' . $complaint['image']) ?>" target="_blank" title="Click to view full photo">
                                    <img src="<?= url('uploads/complaints/' . $complaint['image']) ?>" alt="Complaint Photo" class="img-fluid rounded" style="max-height: 280px; object-fit: contain;">
                                </a>
                                <div class="text-center mt-1">
                                    <small class="text-muted"><i class="bi bi-arrows-fullscreen me-1"></i>Click to view full size</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Assignment Card Section -->
                    <?php if ($assignment): ?>
                        <div class="p-3 rounded-3 bg-info-subtle border border-info-subtle mb-4">
                            <h6 class="fw-bold text-info-emphasis mb-2">
                                <i class="bi bi-person-gear me-1"></i> Technical Assignment Details
                            </h6>
                            <div class="row g-2 small text-dark">
                                <div class="col-md-6">
                                    <div>Department: <strong><?= e($assignment['department_name']) ?></strong></div>
                                    <div>Assigned Staff: <strong><?= e($assignment['staff_name']) ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div>Assigned On: <strong><?= formatDateTime($assignment['assigned_at']) ?></strong></div>
                                    <?php if (!empty($assignment['staff_phone'])): ?>
                                        <div>Contact: <strong><?= e($assignment['staff_phone']) ?></strong></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Resolution Information (If Resolved) -->
                    <?php if ($complaint['status'] === 'Resolved'): ?>
                        <div class="p-4 rounded-3 bg-success-subtle border border-success-subtle mb-4">
                            <div class="d-flex align-items-center gap-2 mb-2 text-success">
                                <i class="bi bi-check-circle-fill fs-5"></i>
                                <h6 class="fw-bold mb-0">Complaint Resolved</h6>
                            </div>
                            <p class="small text-dark mb-2">
                                <strong>Resolution Notes:</strong><br>
                                <?= nl2br(e($complaint['resolution_notes'] ?? 'The technical fault was addressed and resolved.')) ?>
                            </p>
                            <div class="d-flex flex-wrap gap-4 small text-secondary mt-3 pt-2 border-top border-success-subtle">
                                <div>Resolved By: <strong class="text-dark"><?= e($complaint['resolver_name'] ?? 'Assigned Staff') ?></strong></div>
                                <div>Resolved Date: <strong class="text-dark"><?= formatDateTime($complaint['resolved_at']) ?></strong></div>
                            </div>

                            <?php if (!empty($complaint['resolution_image'])): ?>
                                <div class="mt-3">
                                    <div class="small fw-bold text-dark mb-1">Resolution Proof Photo:</div>
                                    <a href="<?= url('uploads/complaints/' . $complaint['resolution_image']) ?>" target="_blank">
                                        <img src="<?= url('uploads/complaints/' . $complaint['resolution_image']) ?>" alt="Resolution Photo" class="img-thumbnail" style="max-height: 200px;">
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Feedback Section -->
                <?php if ($complaint['status'] === 'Resolved'): ?>
                    <div class="app-card p-4 p-md-5 mb-4 border">
                        <h5 class="fw-bold text-dark mb-2">
                            <i class="bi bi-star-fill text-warning me-2"></i>Student Resolution Feedback
                        </h5>
                        <p class="text-secondary small mb-4">
                            Help us maintain quality services across campus by rating how effectively this maintenance request was addressed.
                        </p>

                        <?php if ($feedback): ?>
                            <!-- Feedback already submitted -->
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="star-display fs-5">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?= $i <= $feedback['rating'] ? '★' : '<span class="empty">☆</span>' ?>
                                        <?php endfor; ?>
                                    </span>
                                    <span class="badge bg-success-subtle text-success small">Feedback Submitted</span>
                                </div>
                                <p class="text-dark mb-1" style="font-style: italic;">&ldquo;<?= e($feedback['comment']) ?>&rdquo;</p>
                                <small class="text-muted">Submitted on <?= formatDateTime($feedback['created_at']) ?></small>
                            </div>
                        <?php else: ?>
                            <!-- Submit Feedback Form -->
                            <form method="POST" action="<?= url('student/feedback.php') ?>">
                                <?= csrfInput() ?>
                                <input type="hidden" name="complaint_id" value="<?= $complaintId ?>">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark d-block">Overall Satisfaction Rating <span class="text-danger">*</span></label>
                                    <div class="rating-stars">
                                        <input type="radio" id="star5" name="rating" value="5" required>
                                        <label for="star5" title="5 Stars - Excellent">★</label>
                                        
                                        <input type="radio" id="star4" name="rating" value="4">
                                        <label for="star4" title="4 Stars - Good">★</label>
                                        
                                        <input type="radio" id="star3" name="rating" value="3">
                                        <label for="star3" title="3 Stars - Satisfactory">★</label>
                                        
                                        <input type="radio" id="star2" name="rating" value="2">
                                        <label for="star2" title="2 Stars - Needs Improvement">★</label>
                                        
                                        <input type="radio" id="star1" name="rating" value="1">
                                        <label for="star1" title="1 Star - Poor">★</label>
                                    </div>
                                    <div class="form-text small text-muted">Select 1 (poor) to 5 (excellent) stars.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="feedbackComment" class="form-label small fw-bold text-dark">Comments / Suggestions</label>
                                    <textarea class="form-control" id="feedbackComment" name="comment" rows="3" placeholder="e.g. The technician arrived very promptly and fixed the fan properly..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-warning text-dark fw-bold px-4 py-2">
                                    <i class="bi bi-send-check me-1"></i> Submit Feedback
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Right Column: Visual Dynamic Timeline -->
            <div class="col-lg-4">
                <div class="app-card p-4">
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-clock-history text-primary me-2"></i>Status Progression
                    </h5>
                    <p class="text-muted small mb-3">Live timeline updates from staff and administration</p>

                    <div class="complaint-timeline">
                        <?php foreach ($timeline as $index => $step): ?>
                            <?php 
                                $isLast = ($index === count($timeline) - 1);
                                $isCompleted = true;
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
                                        <span class="fw-bold small text-dark">
                                            <?= getStatusBadge($step['new_status']) ?>
                                        </span>
                                        <small class="text-muted" style="font-size: 0.72rem;">
                                            <?= formatDateTime($step['created_at'], 'd M, h:i A') ?>
                                        </small>
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
