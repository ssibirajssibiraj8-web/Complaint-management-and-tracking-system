<?php
/**
 * Student Profile & Security Settings
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'My Profile - Student Portal';
$activeNav = 'profile';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['student']);

$user = currentUser();
$studentId = (int) $user['id'];

$profileError = '';
$profileSuccess = '';
$pwError = '';
$pwSuccess = '';

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!validateCsrfToken()) {
        $profileError = 'Security token mismatch. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) {
            $profileError = 'Name cannot be empty.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $studentId]);
                
                // Update active session
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['phone'] = $phone;

                $profileSuccess = 'Profile details updated successfully!';
            } catch (PDOException $e) {
                $profileError = 'Error updating profile.';
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!validateCsrfToken()) {
        $pwError = 'Security token mismatch. Please try again.';
    } else {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (empty($currentPw) || empty($newPw) || empty($confirmPw)) {
            $pwError = 'All password fields are required.';
        } elseif (strlen($newPw) < 6) {
            $pwError = 'New password must be at least 6 characters.';
        } elseif ($newPw !== $confirmPw) {
            $pwError = 'New password and confirmation do not match.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$studentId]);
                $hash = $stmt->fetchColumn();

                if (!password_verify($currentPw, $hash)) {
                    $pwError = 'Current password entered is incorrect.';
                } else {
                    $newHash = password_hash($newPw, PASSWORD_BCRYPT);
                    $upStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upStmt->execute([$newHash, $studentId]);
                    $pwSuccess = 'Password changed successfully!';
                }
            } catch (PDOException $e) {
                $pwError = 'Failed to update password.';
            }
        }
    }
}

// Fetch fresh user data
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$studentId]);
$userData = $stmtUser->fetch();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                
                <div class="mb-4">
                    <h3 class="fw-bold text-dark mb-1">
                        <i class="bi bi-person-gear text-primary me-2"></i>My Profile & Account Settings
                    </h3>
                    <p class="text-secondary small mb-0">Manage your contact information and login credentials.</p>
                </div>

                <?php renderFlashMessages(); ?>

                <!-- Profile Info Form -->
                <div class="app-card p-4 p-md-5 mb-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person text-primary me-2"></i>Personal Details</h5>

                    <?php if ($profileError): ?>
                        <div class="alert alert-danger py-2 small mb-3"><?= e($profileError) ?></div>
                    <?php endif; ?>
                    <?php if ($profileSuccess): ?>
                        <div class="alert alert-success py-2 small mb-3"><?= e($profileSuccess) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?= url('student/profile.php') ?>">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">College Email</label>
                                <input type="email" class="form-control bg-light" value="<?= e($userData['email']) ?>" readonly disabled>
                                <div class="form-text small text-muted">Email address cannot be changed directly.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">User Role</label>
                                <input type="text" class="form-control bg-light text-capitalize" value="<?= e($userData['role']) ?>" readonly disabled>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="nameInput" class="form-label small fw-bold text-dark">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nameInput" name="name" value="<?= e($userData['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phoneInput" class="form-label small fw-bold text-dark">Phone Number</label>
                                <input type="tel" class="form-control" id="phoneInput" name="phone" value="<?= e($userData['phone']) ?>" placeholder="+91 98765 43210">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            Save Profile Changes
                        </button>
                    </form>
                </div>

                <!-- Password Change Form -->
                <div class="app-card p-4 p-md-5">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Change Password</h5>

                    <?php if ($pwError): ?>
                        <div class="alert alert-danger py-2 small mb-3"><?= e($pwError) ?></div>
                    <?php endif; ?>
                    <?php if ($pwSuccess): ?>
                        <div class="alert alert-success py-2 small mb-3"><?= e($pwSuccess) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?= url('student/profile.php') ?>">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="currentPw" class="form-label small fw-bold text-dark">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="currentPw" name="current_password" required>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="newPw" class="form-label small fw-bold text-dark">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="newPw" name="new_password" placeholder="At least 6 characters" required>
                            </div>
                            <div class="col-md-6">
                                <label for="confirmPw" class="form-label small fw-bold text-dark">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirmPw" name="confirm_password" placeholder="Re-enter new password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-primary px-4 fw-semibold">
                            Update Password
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
