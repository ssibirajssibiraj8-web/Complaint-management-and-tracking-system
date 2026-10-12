<?php
/**
 * Student Feedback Reviews & Quality Assurance
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Student Feedback - Admin Console';
$activeNav = 'feedback';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

$ratingFilter = (int) ($_GET['rating'] ?? 0);

// Calculate Average Rating & Counts
$avgRating = 0;
$totalFeedback = 0;
$starCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

try {
    $statQuery = $pdo->query("
        SELECT 
            COUNT(*) as total,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as s5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as s4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as s3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as s2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as s1
        FROM feedback
    ")->fetch();

    $totalFeedback = (int) ($statQuery['total'] ?? 0);
    $avgRating = round((float) ($statQuery['avg_rating'] ?? 0), 1);
    $starCounts[5] = (int) ($statQuery['s5'] ?? 0);
    $starCounts[4] = (int) ($statQuery['s4'] ?? 0);
    $starCounts[3] = (int) ($statQuery['s3'] ?? 0);
    $starCounts[2] = (int) ($statQuery['s2'] ?? 0);
    $starCounts[1] = (int) ($statQuery['s1'] ?? 0);

    // Fetch Feedback records
    $sql = "
        SELECT f.*, u.name as student_name, u.email as student_email,
               c.complaint_code, c.title as complaint_title, c.location
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        JOIN complaints c ON f.complaint_id = c.id
    ";
    if ($ratingFilter >= 1 && $ratingFilter <= 5) {
        $sql .= " WHERE f.rating = " . $ratingFilter;
    }
    $sql .= " ORDER BY f.created_at DESC";

    $feedbackList = $pdo->query($sql)->fetchAll();

} catch (PDOException $e) {
    error_log("Feedback error: " . $e->getMessage());
    $feedbackList = [];
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
                    <i class="bi bi-star text-warning me-2"></i>Student Resolution Feedback
                </h3>
                <p class="text-secondary small mb-0">Evaluate student satisfaction ratings and comments on completed facility maintenance work.</p>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Overall Rating Analytics Card -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="app-card p-4 text-center h-100 d-flex flex-column justify-content-center">
                    <span class="text-secondary small fw-bold text-uppercase">Average Campus Rating</span>
                    <h1 class="display-3 fw-extrabold text-warning my-1"><?= $avgRating ?></h1>
                    <div class="star-display fs-4 mb-2">
                        <?php 
                            $roundedAvg = round($avgRating);
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $roundedAvg ? '★' : '<span class="empty">☆</span>';
                            }
                        ?>
                    </div>
                    <small class="text-muted">Based on <strong><?= $totalFeedback ?></strong> student review<?= $totalFeedback === 1 ? '' : 's' ?></small>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="app-card p-4 h-100">
                    <h6 class="fw-bold text-dark mb-3">Rating Breakdown</h6>
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                        <?php 
                            $cnt = $starCounts[$s];
                            $pct = $totalFeedback > 0 ? round(($cnt / $totalFeedback) * 100) : 0;
                        ?>
                        <div class="d-flex align-items-center gap-3 mb-2 small">
                            <span class="fw-bold text-dark" style="width: 55px;"><?= $s ?> Stars</span>
                            <div class="progress flex-grow-1" style="height: 10px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="text-muted text-end" style="width: 70px;"><?= $cnt ?> (<?= $pct ?>%)</span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Rating Filter Buttons -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?= url('admin/feedback.php') ?>" class="btn btn-sm <?= ($ratingFilter === 0) ? 'btn-primary' : 'btn-light border' ?> px-3">
                All Ratings (<?= $totalFeedback ?>)
            </a>
            <?php for ($s = 5; $s >= 1; $s--): ?>
                <a href="<?= url('admin/feedback.php?rating=' . $s) ?>" class="btn btn-sm <?= ($ratingFilter === $s) ? 'btn-primary' : 'btn-light border' ?> px-3">
                    <?= $s ?> Stars (<?= $starCounts[$s] ?>)
                </a>
            <?php endfor; ?>
        </div>

        <!-- Feedback Records List -->
        <div class="app-card p-4">
            <?php if (empty($feedbackList)): ?>
                <div class="empty-state-box">
                    <div class="empty-state-icon">
                        <i class="bi bi-star"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No feedback reviews found</h5>
                    <p class="text-secondary small mb-0">Student reviews will appear here once resolved complaints are rated.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($feedbackList as $fb): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="border rounded-3 p-3 bg-white h-100 d-flex flex-column shadow-xs">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="star-display fs-5">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?= $i <= $fb['rating'] ? '★' : '<span class="empty">☆</span>' ?>
                                        <?php endfor; ?>
                                    </span>
                                    <small class="text-muted"><?= formatDateTime($fb['created_at'], 'd M Y') ?></small>
                                </div>

                                <p class="text-dark small flex-grow-1 mb-3" style="line-height: 1.5; font-style: italic;">
                                    &ldquo;<?= e($fb['comment'] ?: 'No additional comment provided.') ?>&rdquo;
                                </p>

                                <div class="pt-2 border-top mt-auto small">
                                    <div class="fw-bold text-dark"><?= e($fb['student_name']) ?></div>
                                    <div class="text-muted text-truncate"><?= e($fb['student_email']) ?></div>
                                    <div class="mt-2">
                                        <a href="<?= url('admin/complaint-details.php?id=' . $fb['complaint_id']) ?>" class="fw-semibold text-primary text-decoration-none">
                                            <?= e($fb['complaint_code']) ?>: <?= e($fb['complaint_title']) ?> &rarr;
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
