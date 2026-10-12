<?php
/**
 * Campus Complaint Management and Tracking System
 * Modern Landing Page
 */

$pageTitle = 'Home - Campus Complaint Management & Tracking System';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// Dynamic Database Statistics
$totalComplaints = 0;
$resolvedComplaints = 0;
$pendingComplaints = 0;
$registeredUsers = 0;
$categories = [];

if (isset($pdo)) {
    try {
        $totalComplaints = (int) $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
        $resolvedComplaints = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Resolved'")->fetchColumn();
        $pendingComplaints = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status NOT IN ('Resolved', 'Rejected', 'Closed')")->fetchColumn();
        $registeredUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        
        $catStmt = $pdo->query("SELECT c.*, COUNT(cmp.id) as complaint_count FROM categories c LEFT JOIN complaints cmp ON c.id = cmp.category_id GROUP BY c.id ORDER BY complaint_count DESC LIMIT 8");
        $categories = $catStmt->fetchAll();
    } catch (Exception $e) {
        // Fallback gracefully if database is not yet ready
    }
}

// Category icon map helper
function getCategoryIcon(string $name): string {
    return match (strtolower($name)) {
        'electrical' => 'bi-lightning-charge',
        'plumbing' => 'bi-droplet-half',
        'internet/wi-fi', 'wi-fi', 'internet' => 'bi-wifi',
        'classroom' => 'bi-mortarboard',
        'laboratory' => 'bi-radioactive',
        'furniture' => 'bi-lamp',
        'cleaning' => 'bi-trash3',
        'restroom' => 'bi-badge-wc',
        'library' => 'bi-book',
        'canteen' => 'bi-cup-hot',
        'transportation' => 'bi-bus-front',
        'security' => 'bi-shield-shaded',
        default => 'bi-gear-wide-connected'
    };
}
?>

<!-- Hero Section -->
<section class="hero-gradient text-white py-5">
    <div class="container py-lg-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white bg-opacity-10 text-white small mb-3 border border-white border-opacity-10">
                    <span class="badge bg-primary rounded-pill">Campus Care</span>
                    <span>Modern Infrastructure & Facility Management</span>
                </div>
                <h1 class="display-4 fw-extrabold lh-tight mb-3">
                    Campus Complaint Management and Tracking System
                </h1>
                <p class="lead text-light opacity-90 mb-4 fs-5" style="max-width: 600px;">
                    &ldquo;Report campus problems, track their progress, and help us build a better campus.&rdquo;
                </p>
                
                <div class="d-flex flex-wrap gap-3">
                    <?php if (isLoggedIn()): ?>
                        <?php if (currentUserRole() === 'student'): ?>
                            <a href="<?= url('student/new-complaint.php') ?>" class="btn btn-primary btn-lg px-4 py-2.5 shadow-sm fw-semibold">
                                <i class="bi bi-plus-circle me-2"></i> Report a Complaint
                            </a>
                            <a href="<?= url('student/my-complaints.php') ?>" class="btn btn-outline-light btn-lg px-4 py-2.5 fw-semibold">
                                <i class="bi bi-clock-history me-2"></i> Track Complaint
                            </a>
                        <?php elseif (currentUserRole() === 'staff'): ?>
                            <a href="<?= url('staff/dashboard.php') ?>" class="btn btn-primary btn-lg px-4 py-2.5 shadow-sm fw-semibold">
                                <i class="bi bi-tools me-2"></i> Open Staff Portal
                            </a>
                        <?php else: ?>
                            <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-primary btn-lg px-4 py-2.5 shadow-sm fw-semibold">
                                <i class="bi bi-speedometer2 me-2"></i> Open Admin Console
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?= url('login.php?redirect=' . urlencode('student/new-complaint.php')) ?>" class="btn btn-primary btn-lg px-4 py-2.5 shadow fw-semibold">
                            <i class="bi bi-plus-circle me-2"></i> Report a Complaint
                        </a>
                        <a href="<?= url('login.php?redirect=' . urlencode('student/my-complaints.php')) ?>" class="btn btn-outline-light btn-lg px-4 py-2.5 fw-semibold">
                            <i class="bi bi-search me-2"></i> Track Complaint
                        </a>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center gap-4 mt-4 pt-3 border-top border-white border-opacity-10 text-light small">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-success fs-5"></i> Official Campus Portal
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-lightning-charge text-warning fs-5"></i> Rapid SLA Resolution
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="app-card p-4 p-md-5 text-dark shadow-lg border-0 position-relative">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Direct Incident Portal</h5>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Online</span>
                    </div>
                    <p class="text-secondary small mb-4">
                        Experiencing an issue with electricity, plumbing, Wi-Fi, or classroom equipment? Submit a ticket in less than 60 seconds.
                    </p>

                    <div class="list-group list-group-flush mb-4">
                        <div class="list-group-item px-0 py-2 d-flex align-items-center gap-3 border-0">
                            <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                                <i class="bi bi-camera"></i>
                            </div>
                            <span class="small fw-semibold text-secondary">Upload photos directly from your phone</span>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex align-items-center gap-3 border-0">
                            <div class="rounded-circle bg-warning-subtle text-warning p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <span class="small fw-semibold text-secondary">Real-time status updates and notifications</span>
                        </div>
                        <div class="list-group-item px-0 py-2 d-flex align-items-center gap-3 border-0">
                            <div class="rounded-circle bg-success-subtle text-success p-2 d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                                <i class="bi bi-star"></i>
                            </div>
                            <span class="small fw-semibold text-secondary">Rate resolution quality with 1–5 stars</span>
                        </div>
                    </div>

                    <?php if (!isLoggedIn()): ?>
                        <div class="d-grid gap-2">
                            <a href="<?= url('register.php') ?>" class="btn btn-primary fw-semibold py-2">
                                <i class="bi bi-person-plus me-1"></i> Register Student Account
                            </a>
                            <a href="<?= url('login.php') ?>" class="btn btn-light fw-semibold py-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Existing User Login
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="d-grid">
                            <a href="<?= $homeUrl ?>" class="btn btn-primary fw-semibold py-2">
                                <i class="bi bi-arrow-right-circle me-1"></i> Go to Your Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dynamic Live Statistics Section -->
<section id="stats" class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="row g-4 text-center">
            
            <div class="col-6 col-md-3">
                <div class="p-3">
                    <div class="card-stat-icon bg-primary-subtle text-primary mx-auto mb-3">
                        <i class="bi bi-folder2-open"></i>
                    </div>
                    <h2 class="fw-extrabold text-dark mb-1"><?= number_format($totalComplaints) ?></h2>
                    <span class="text-secondary fw-semibold small text-uppercase letter-spacing-1">Total Complaints</span>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="p-3">
                    <div class="card-stat-icon bg-success-subtle text-success mx-auto mb-3">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h2 class="fw-extrabold text-dark mb-1"><?= number_format($resolvedComplaints) ?></h2>
                    <span class="text-secondary fw-semibold small text-uppercase letter-spacing-1">Resolved Complaints</span>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="p-3">
                    <div class="card-stat-icon bg-warning-subtle text-warning-emphasis mx-auto mb-3">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h2 class="fw-extrabold text-dark mb-1"><?= number_format($pendingComplaints) ?></h2>
                    <span class="text-secondary fw-semibold small text-uppercase letter-spacing-1">Pending Complaints</span>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="p-3">
                    <div class="card-stat-icon bg-info-subtle text-info-emphasis mx-auto mb-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <h2 class="fw-extrabold text-dark mb-1"><?= number_format($registeredUsers) ?></h2>
                    <span class="text-secondary fw-semibold small text-uppercase letter-spacing-1">Registered Users</span>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- How It Works Section -->
<section id="how-it-works" class="py-5" style="background-color: var(--app-bg);">
    <div class="container py-4">
        
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill fw-semibold">Simple 4-Step Lifecycle</span>
            <h2 class="fw-bold text-dark mt-2">How It Works</h2>
            <p class="text-secondary mx-auto" style="max-width: 580px;">
                Our transparent campus workflow ensures every complaint is recorded, reviewed, resolved, and verified by students.
            </p>
        </div>

        <div class="row g-4">
            
            <div class="col-md-6 col-lg-3">
                <div class="app-card h-100 p-4 text-center app-card-hover">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold fs-4" style="width: 56px; height: 56px;">
                        1
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Submit Complaint</h5>
                    <p class="text-secondary small mb-0">
                        Students report problems, attach incident photos, and choose the campus category and location.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="app-card h-100 p-4 text-center app-card-hover">
                    <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold fs-4" style="width: 56px; height: 56px;">
                        2
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Admin Reviews</h5>
                    <p class="text-secondary small mb-0">
                        Administration inspects the ticket, assigns priority, and delegates to the relevant department and staff.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="app-card h-100 p-4 text-center app-card-hover">
                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold fs-4" style="width: 56px; height: 56px;">
                        3
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Staff Resolves</h5>
                    <p class="text-secondary small mb-0">
                        Maintenance technicians start work, fix the issue on site, and document resolution notes.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="app-card h-100 p-4 text-center app-card-hover">
                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-3 fw-bold fs-4" style="width: 56px; height: 56px;">
                        4
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Student Gives Feedback</h5>
                    <p class="text-secondary small mb-0">
                        Students receive completion notifications and rate the service quality with 1 to 5 stars.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Complaint Categories Section -->
<section id="categories" class="py-5 bg-white border-top">
    <div class="container py-4">
        
        <div class="text-center mb-5">
            <span class="badge bg-info-subtle text-info-emphasis px-3 py-1.5 rounded-pill fw-semibold">Coverage</span>
            <h2 class="fw-bold text-dark mt-2">Campus Complaint Categories</h2>
            <p class="text-secondary mx-auto" style="max-width: 580px;">
                Comprehensive facility support spanning academic buildings, hostels, libraries, and campus grounds.
            </p>
        </div>

        <div class="row g-4">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="app-card h-100 p-4 text-center app-card-hover border">
                            <div class="card-stat-icon bg-primary-subtle text-primary mx-auto mb-3">
                                <i class="bi <?= getCategoryIcon($cat['name']) ?>"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1"><?= e($cat['name']) ?></h6>
                            <p class="text-muted small text-truncate mb-2"><?= e($cat['description']) ?></p>
                            <span class="badge bg-secondary-subtle text-secondary small">
                                <?= (int)$cat['complaint_count'] ?> registered
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center text-muted py-4">
                    <p>Default categories will be loaded upon database setup.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<!-- Call to Action Banner -->
<section class="py-5" style="background: linear-gradient(135deg, #1E293B, #0F172A); color: white;">
    <div class="container text-center py-4">
        <h2 class="fw-extrabold mb-3">Ready to Report an Issue?</h2>
        <p class="lead text-light opacity-75 mb-4 mx-auto" style="max-width: 600px;">
            Help maintain safe, comfortable, and efficient learning facilities across our campus community.
        </p>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?= url('register.php') ?>" class="btn btn-primary btn-lg px-4 py-2.5 fw-semibold shadow">
                <i class="bi bi-person-plus me-1"></i> Register as Student
            </a>
            <a href="<?= url('login.php') ?>" class="btn btn-outline-light btn-lg px-4 py-2.5 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-1"></i> Log In
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
