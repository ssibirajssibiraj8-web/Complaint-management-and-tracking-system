<?php
/**
 * Submit New Complaint Form
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Report Complaint - Student Portal';
$activeNav = 'new-complaint';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['student']);

$user = currentUser();
$studentId = (int) $user['id'];

$error = '';
$title = '';
$categoryId = '';
$location = '';
$priority = 'Medium';
$description = '';

// Fetch all available categories
try {
    $catStmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $priority = trim($_POST['priority'] ?? 'Medium');
        $description = trim($_POST['description'] ?? '');

        // Validations
        if (empty($title) || empty($location) || empty($description) || $categoryId <= 0) {
            $error = 'Please fill in all required fields (Title, Category, Campus Location, and Description).';
        } elseif (!in_array($priority, ['Low', 'Medium', 'High'], true)) {
            $error = 'Please select a valid priority level.';
        } else {
            // Process Image Upload
            $uploadedImage = null;
            try {
                if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadedImage = handleImageUpload($_FILES['complaint_image'], 'uploads/complaints/');
                }

                // Generate Sequential Complaint Code
                $complaintCode = generateComplaintCode($pdo);

                // Insert into complaints table
                $insertStmt = $pdo->prepare("
                    INSERT INTO complaints 
                    (complaint_code, user_id, category_id, title, description, location, priority, image, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Submitted', NOW())
                ");
                $insertStmt->execute([
                    $complaintCode,
                    $studentId,
                    $categoryId,
                    $title,
                    $description,
                    $location,
                    $priority,
                    $uploadedImage
                ]);

                $complaintId = (int) $pdo->lastInsertId();

                // 1. Log initial timeline update
                logComplaintUpdate(
                    $pdo,
                    $complaintId,
                    $studentId,
                    null,
                    'Submitted',
                    'Complaint registered by student ' . $user['name'] . '.'
                );

                // 2. Dispatch notification to student
                createNotification(
                    $pdo,
                    $studentId,
                    'Complaint Submitted',
                    "Your complaint {$complaintCode} has been submitted successfully and is awaiting administrative review.",
                    "student/complaint-details.php?id={$complaintId}"
                );

                // 3. Dispatch notification to campus admins
                $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
                foreach ($admins as $admin) {
                    createNotification(
                        $pdo,
                        (int) $admin['id'],
                        'New Complaint Submitted',
                        "New ticket {$complaintCode} reported by {$user['name']}: {$title}.",
                        "admin/complaint-details.php?id={$complaintId}"
                    );
                }

                setFlash('success', "Complaint {$complaintCode} submitted successfully.");
                header('Location: ' . url('student/complaint-details.php?id=' . $complaintId));
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">
                            <i class="bi bi-plus-circle text-primary me-2"></i>Report a Campus Complaint
                        </h3>
                        <p class="text-secondary small mb-0">Fill in the details below to request maintenance or repair service.</p>
                    </div>
                    <a href="<?= url('student/my-complaints.php') ?>" class="btn btn-light btn-sm border">
                        <i class="bi bi-arrow-left me-1"></i> Back to List
                    </a>
                </div>

                <?php renderFlashMessages(); ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <div class="app-card p-4 p-md-5">
                    <form method="POST" action="<?= url('student/new-complaint.php') ?>" enctype="multipart/form-data" novalidate>
                        <?= csrfInput() ?>

                        <!-- Title -->
                        <div class="mb-4">
                            <label for="titleInput" class="form-label fw-bold text-dark small">Complaint Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg fs-6" id="titleInput" name="title" value="<?= e($title) ?>" placeholder="e.g. Ceiling fan not working, Wi-Fi connectivity lost, Water tap leaking" required autofocus>
                            <div class="form-text small text-muted">Provide a concise, specific summary of the problem.</div>
                        </div>

                        <!-- Category & Priority Row -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="categorySelect" class="form-label fw-bold text-dark small">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="categorySelect" name="category_id" required>
                                    <option value="">-- Choose Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ((string)$categoryId === (string)$cat['id']) ? 'selected' : '' ?>>
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="prioritySelect" class="form-label fw-bold text-dark small">Priority Level <span class="text-danger">*</span></label>
                                <select class="form-select" id="prioritySelect" name="priority" required>
                                    <option value="Low" <?= ($priority === 'Low') ? 'selected' : '' ?>>Low (Routine maintenance / Minor fault)</option>
                                    <option value="Medium" <?= ($priority === 'Medium') ? 'selected' : '' ?>>Medium (Standard classroom / lab disruption)</option>
                                    <option value="High" <?= ($priority === 'High') ? 'selected' : '' ?>>High (Safety hazard / Major outage)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Campus Location -->
                        <div class="mb-4">
                            <label for="locationInput" class="form-label fw-bold text-dark small">Campus Location <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt"></i></span>
                                <input type="text" class="form-control" id="locationInput" name="location" value="<?= e($location) ?>" placeholder="e.g. Block A - Room 203, Library 2nd Floor, Boys Hostel Block C" required>
                            </div>
                            <div class="form-text small text-muted">Include block, floor, and room number so maintenance technicians can quickly find the site.</div>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label for="descriptionTextarea" class="form-label fw-bold text-dark small">Detailed Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="descriptionTextarea" name="description" rows="5" placeholder="Explain what happened, since when the issue started, and any specific observations..." required><?= e($description) ?></textarea>
                        </div>

                        <!-- Image Upload -->
                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <label for="complaintImage" class="form-label fw-bold text-dark small mb-1">
                                <i class="bi bi-camera me-1 text-primary"></i> Upload Issue Photo <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input class="form-control" type="file" id="complaintImage" name="complaint_image" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text small text-muted">
                                Allowed formats: <strong>JPG, JPEG, PNG, WEBP</strong>. Maximum file size: <strong>5MB</strong>.
                            </div>

                            <!-- Interactive Image Preview -->
                            <div id="imagePreviewContainer" class="mt-3 d-none">
                                <div class="small fw-semibold text-secondary mb-1">Selected Photo Preview:</div>
                                <img id="imagePreviewImg" src="#" alt="Preview" class="img-thumbnail rounded-3 shadow-xs" style="max-height: 220px; object-fit: contain;">
                            </div>
                        </div>

                        <!-- Submission Buttons -->
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                            <a href="<?= url('student/dashboard.php') ?>" class="btn btn-light px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary px-5 py-2.5 fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Submit Complaint
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
