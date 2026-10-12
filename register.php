<?php
/**
 * Student Registration Page
 * Campus Complaint Management and Tracking System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . url('student/dashboard.php'));
    exit;
}

$error = '';
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            try {
                // Check for duplicate email
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    $error = 'An account with this email address already exists. Please sign in instead.';
                } else {
                    // Hash password securely
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    
                    // Insert student account
                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (name, email, phone, password, role, status, created_at)
                        VALUES (?, ?, ?, ?, 'student', 'active', NOW())
                    ");
                    $insertStmt->execute([$name, $email, $phone, $hashedPassword]);
                    $newUserId = (int) $pdo->lastInsertId();

                    // Send welcome notification
                    createNotification(
                        $pdo,
                        $newUserId,
                        'Welcome to Campus Care!',
                        'Your student account has been created. You can now submit facility complaints and track their status in real time.',
                        'student/dashboard.php'
                    );

                    // Fetch user and auto-login
                    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmtUser->execute([$newUserId]);
                    $user = $stmtUser->fetch();

                    loginUser($user);
                    setFlash('success', 'Registration successful! Welcome to Campus Care, ' . $user['name'] . '.');
                    header('Location: ' . url('student/dashboard.php'));
                    exit;
                }
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = 'A database error occurred during registration. Please try again.';
            }
        }
    }
}

$pageTitle = 'Student Registration - Campus Complaint System';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="py-5" style="background-color: var(--app-bg); min-height: 80vh;">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                
                <div class="text-center mb-4">
                    <div class="brand-badge mx-auto mb-2" style="width: 50px; height: 50px; font-size: 1.6rem;">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h3 class="fw-bold text-dark">Create Student Account</h3>
                    <p class="text-secondary small">Register to report and track campus facility issues</p>
                </div>

                <?php renderFlashMessages(); ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <div class="app-card p-4 p-md-5 mb-4 shadow-sm">
                    <form method="POST" action="<?= url('register.php') ?>" novalidate>
                        <?= csrfInput() ?>

                        <div class="mb-3">
                            <label for="nameInput" class="form-label fw-semibold small text-dark">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control border-start-0 ps-1" id="nameInput" name="name" value="<?= e($name) ?>" placeholder="e.g. John Doe" required autofocus>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="emailInput" class="form-label fw-semibold small text-dark">College Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 ps-1" id="emailInput" name="email" value="<?= e($email) ?>" placeholder="john@college.edu" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="phoneInput" class="form-label fw-semibold small text-dark">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                                    <input type="tel" class="form-control border-start-0 ps-1" id="phoneInput" name="phone" value="<?= e($phone) ?>" placeholder="+91 98765 43210">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="passwordInput" class="form-label fw-semibold small text-dark">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-1" id="passwordInput" name="password" placeholder="At least 6 characters" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirmPasswordInput" class="form-label fw-semibold small text-dark">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-check"></i></span>
                                    <input type="password" class="form-control border-start-0 ps-1" id="confirmPasswordInput" name="confirm_password" placeholder="Re-enter password" required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm">
                            <i class="bi bi-check-circle me-1"></i> Register Student Account
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="small text-secondary mb-0">
                            Already registered? 
                            <a href="<?= url('login.php') ?>" class="text-primary fw-semibold text-decoration-none">Sign In here</a>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
