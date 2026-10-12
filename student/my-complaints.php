<?php
/**
 * Student's Complaints Listing & Server-side Filter/Search
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'My Complaints - Student Portal';
$activeNav = 'my-complaints';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['student']);

$user = currentUser();
$studentId = (int) $user['id'];

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = (int) ($_GET['category'] ?? 0);
$priorityFilter = trim($_GET['priority'] ?? '');
$sort = ($_GET['sort'] ?? '') === 'oldest' ? 'ASC' : 'DESC';

// Fetch all categories for filter dropdown
$categories = [];
try {
    $catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {}

// Build server-side PDO query with prepared parameters
$sql = "
    SELECT c.*, cat.name as category_name
    FROM complaints c
    JOIN categories cat ON c.category_id = cat.id
    WHERE c.user_id = :user_id
";
$params = [':user_id' => $studentId];

if (!empty($search)) {
    $sql .= " AND (c.complaint_code LIKE :search OR c.title LIKE :search OR c.location LIKE :search OR c.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $sql .= " AND c.status = :status";
    $params[':status'] = $statusFilter;
}

if ($categoryFilter > 0) {
    $sql .= " AND c.category_id = :category_id";
    $params[':category_id'] = $categoryFilter;
}

if (!empty($priorityFilter)) {
    $sql .= " AND c.priority = :priority";
    $params[':priority'] = $priorityFilter;
}

$sql .= " ORDER BY c.created_at " . $sort;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();
    $totalFound = count($complaints);
} catch (PDOException $e) {
    error_log("Complaint search error: " . $e->getMessage());
    $complaints = [];
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
                    <i class="bi bi-card-checklist text-primary me-2"></i>My Reported Complaints
                </h3>
                <p class="text-secondary small mb-0">Search, filter, and track all complaints submitted under your account.</p>
            </div>
            <a href="<?= url('student/new-complaint.php') ?>" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> New Complaint
            </a>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Search and Filters Form -->
        <div class="app-card p-4 mb-4">
            <form method="GET" action="<?= url('student/my-complaints.php') ?>" class="row g-3 align-items-end">
                
                <!-- Search text -->
                <div class="col-md-4 col-lg-3">
                    <label for="searchInput" class="form-label small fw-bold text-dark">Search Keyword</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" name="search" value="<?= e($search) ?>" placeholder="Code, title, room...">
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-2">
                    <label for="statusSelect" class="form-label small fw-bold text-dark">Status</label>
                    <select class="form-select" id="statusSelect" name="status">
                        <option value="">All Statuses</option>
                        <option value="Submitted" <?= ($statusFilter === 'Submitted') ? 'selected' : '' ?>>Submitted</option>
                        <option value="Under Review" <?= ($statusFilter === 'Under Review') ? 'selected' : '' ?>>Under Review</option>
                        <option value="Approved" <?= ($statusFilter === 'Approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="Assigned" <?= ($statusFilter === 'Assigned') ? 'selected' : '' ?>>Assigned</option>
                        <option value="In Progress" <?= ($statusFilter === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                        <option value="Resolved" <?= ($statusFilter === 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                        <option value="Rejected" <?= ($statusFilter === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <!-- Category Filter -->
                <div class="col-6 col-md-2">
                    <label for="categorySelect" class="form-label small fw-bold text-dark">Category</label>
                    <select class="form-select" id="categorySelect" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($categoryFilter === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priority Filter -->
                <div class="col-6 col-md-2">
                    <label for="prioritySelect" class="form-label small fw-bold text-dark">Priority</label>
                    <select class="form-select" id="prioritySelect" name="priority">
                        <option value="">All Priorities</option>
                        <option value="Low" <?= ($priorityFilter === 'Low') ? 'selected' : '' ?>>Low</option>
                        <option value="Medium" <?= ($priorityFilter === 'Medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="High" <?= ($priorityFilter === 'High') ? 'selected' : '' ?>>High</option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div class="col-6 col-md-2 col-lg-1">
                    <label for="sortSelect" class="form-label small fw-bold text-dark">Sort</label>
                    <select class="form-select" id="sortSelect" name="sort">
                        <option value="newest" <?= ($sort === 'DESC') ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= ($sort === 'ASC') ? 'selected' : '' ?>>Oldest</option>
                    </select>
                </div>

                <!-- Filter Actions -->
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="<?= url('student/my-complaints.php') ?>" class="btn btn-light border" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>

            </form>
        </div>

        <!-- Complaints Listing Results -->
        <div class="app-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="small text-muted fw-semibold">
                    Found <strong><?= $totalFound ?></strong> complaint<?= $totalFound === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (empty($complaints)): ?>
                <div class="empty-state-box">
                    <div class="empty-state-icon">
                        <i class="bi bi-search"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No matching complaints found</h5>
                    <p class="text-secondary small mb-3">Try adjusting your search criteria or clear the filters to view all complaints.</p>
                    <a href="<?= url('student/my-complaints.php') ?>" class="btn btn-outline-primary btn-sm me-2">Clear Filters</a>
                    <a href="<?= url('student/new-complaint.php') ?>" class="btn btn-primary btn-sm">Report New Complaint</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Complaint Code</th>
                                <th>Title & Category</th>
                                <th>Campus Location</th>
                                <th>Priority</th>
                                <th>Current Status</th>
                                <th>Date Filed</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('student/complaint-details.php?id=' . $c['id']) ?>" class="fw-bold text-primary text-decoration-none">
                                            <?= e($c['complaint_code']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 250px;">
                                            <?= e($c['title']) ?>
                                        </div>
                                        <span class="badge bg-light text-secondary border small mt-1"><?= e($c['category_name']) ?></span>
                                    </td>
                                    <td>
                                        <div class="small text-secondary text-truncate" style="max-width: 180px;">
                                            <i class="bi bi-geo-alt me-1 text-muted"></i><?= e($c['location']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= getPriorityBadge($c['priority']) ?>
                                    </td>
                                    <td>
                                        <?= getStatusBadge($c['status']) ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= formatDateTime($c['created_at'], 'd M Y, h:i A') ?></small>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('student/complaint-details.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary px-3 py-1">
                                            <i class="bi bi-eye me-1"></i> View Details
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
