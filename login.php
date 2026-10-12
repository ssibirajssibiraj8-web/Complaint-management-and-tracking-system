<?php
/**
 * User Authentication - Login Page
 * Campus Complaint Management and Tracking System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already authenticated
if (isLoggedIn()) {
    $role = currentUserRole();
    if ($role === 'admin') header('Location: ' . url('admin/dashboard.php'));
    elseif ($role === 'staff') header('Location: ' . url('staff/dashboard.php'));
    else header('Location: ' . url('student/dashboard.php'));
    exit;
}

$error = '';
$email = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = trim($_POST['redirect'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        $error = 'Your account has been deactivated. Please contact campus administration.';
                    } else {
                        // Successful login
                        loginUser($user);
                        setFlash('success', 'Welcome back, ' . $user['name'] . '!');

                        // Handle safe internal redirect
                        if (!empty($redirect) && str_starts_with($redirect, BASE_URL)) {
                            header('Location: ' . $redirect);
                            exit;
                        }

                        // Role-based destination
                        $destination = match($user['role']) {
                            'admin' => url('admin/dashboard.php'),
                            'staff' => url('staff/dashboard.php'),
                            default => url('student/dashboard.php')
                        };
                        header('Location: ' . $destination);
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'A system error occurred while processing your request. Please try again.';
            }
        }
    }
}

$pageTitle = 'Login - Campus Complaint System';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="py-5" style="background-color: var(--app-bg); min-height: 80vh;">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                
                <div class="text-center mb-4">
                    <div class="brand-badge mx-auto mb-2" style="width: 50px; height: 50px; font-size: 1.6rem;">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h3 class="fw-bold text-dark">Sign In to Campus Care</h3>
                    <p class="text-secondary small">Access student services, staff maintenance, or administrative portal</p>
                </div>

                <?php renderFlashMessages(); ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div><?= e($error) ?></div>
                    </div>
                <?php endif; ?>

                <div class="app-card p-4 p-md-5 mb-4 shadow-sm">
                    <form method="POST" action="<?= url('login.php') ?>" novalidate>
                        <?= csrfInput() ?>
                        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                        <div class="mb-3">
                            <label for="emailInput" class="form-label fw-semibold small text-dark">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control border-start-0 ps-1" id="emailInput" name="email" value="<?= e($email) ?>" placeholder="name@college.edu" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="passwordInput" class="form-label fw-semibold small text-dark mb-0">Password</label>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control border-start-0 ps-1" id="passwordInput" name="password" placeholder="Enter your password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="small text-secondary mb-0">
                            Don't have an account? 
                            <a href="<?= url('register.php') ?>" class="text-primary fw-semibold text-decoration-none">Register as Student</a>
                        </p>
                    </div>
                </div>

                <!-- Viva Demonstration Helper: 1-Click Fast Credentials Fill -->
                <div class="app-card p-3 bg-white">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-bold text-muted text-uppercase" style="letter-spacing: 0.05em;">
                            <i class="bi bi-lightning-charge text-warning"></i> Demo Quick Fill:
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary small">For Viva / Testing</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" onclick="quickFill('student@demo.com', 'student123')">
                            Student
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning text-dark flex-grow-1" onclick="quickFill('staff@demo.com', 'staff123')">
                            Staff
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger flex-grow-1" onclick="quickFill('admin@demo.com', 'admin123')">
                            Admin
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function quickFill(email, password) {
    document.getElementById('emailInput').value = email;
    document.getElementById('passwordInput').value = password;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
