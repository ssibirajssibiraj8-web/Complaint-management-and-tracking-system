<?php
/**
 * Campus Complaint Management and Tracking System
 * One-Click Database Installer & Setup Utility
 */

define('SETUP_MODE', true);
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';
$isInstalled = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    try {
        // 1. Connect to MySQL Server (without selecting database)
        $dsnNoDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
        $pdoServer = new PDO($dsnNoDb, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // 2. Read database.sql file
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("database.sql file was not found at: {$sqlFile}");
        }

        $sqlContent = file_get_contents($sqlFile);
        if (empty($sqlContent)) {
            throw new Exception("database.sql is empty.");
        }

        // 3. Execute the SQL script
        $pdoServer->exec($sqlContent);

        // 4. Connect to the created database and update passwords using PHP's native password_hash
        $pdoDb = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
        $staffHash = password_hash('staff123', PASSWORD_BCRYPT);
        $studentHash = password_hash('student123', PASSWORD_BCRYPT);

        $updateStmt = $pdoDb->prepare("UPDATE users SET password = ? WHERE email = ?");
        $updateStmt->execute([$adminHash, 'admin@demo.com']);
        $updateStmt->execute([$staffHash, 'staff@demo.com']);
        $updateStmt->execute([$staffHash, 'sarah.it@demo.com']);
        $updateStmt->execute([$staffHash, 'michael.elec@demo.com']);
        $updateStmt->execute([$studentHash, 'student@demo.com']);
        $updateStmt->execute([$studentHash, 'alex.student@demo.com']);

        // 5. Ensure upload directory exists
        $uploadDir = __DIR__ . '/uploads/complaints/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $isInstalled = true;
        $message = "Database 'campus_complaint_system' and all demonstration records have been successfully initialized!";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    // Check if already installed
    try {
        $dsnTest = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $testPdo = new PDO($dsnTest, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
        $res = $testPdo->query("SELECT COUNT(*) FROM users");
        if ($res !== false) {
            $isInstalled = true;
        }
    } catch (Exception $e) {
        // Not installed yet
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Installer | Campus Care</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <div class="text-center mb-4">
                    <div class="brand-badge mx-auto mb-2" style="width: 54px; height: 54px; font-size: 1.8rem;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h2 class="fw-bold text-dark">Campus Care Installer</h2>
                    <p class="text-secondary">Campus Complaint Management & Tracking System Setup</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-octagon-fill fs-4 me-3 flex-shrink-0"></i>
                        <div class="w-100">
                            <div class="fw-bold">Installation Error</div>
                            <div><?= htmlspecialchars($error) ?></div>
                            <?php if (strpos($error, 'Access denied') !== false): ?>
                                <div class="mt-2 pt-2 border-top border-danger-subtle">
                                    <p class="small mb-2"><strong>Tip:</strong> Your MySQL server has a root password set. Enter it here:</p>
                                    <a href="configure.php" class="btn btn-sm btn-dark"><i class="bi bi-gear-fill me-1"></i>Configure MySQL Password &rarr;</a>
                                </div>
                            <?php else: ?>
                                <small class="text-muted mt-1 d-block">Make sure MySQL is running in your services or XAMPP Control Panel.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-success shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-check-circle-fill fs-4 me-3 flex-shrink-0"></i>
                        <div>
                            <div class="fw-bold">Success!</div>
                            <div><?= htmlspecialchars($message) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="app-card p-4 p-md-5 mb-4">
                    <h4 class="fw-bold mb-3"><i class="bi bi-database-gear text-primary me-2"></i>Database Environment Configuration</h4>
                    <p class="text-secondary small mb-4">
                        This wizard will initialize the <strong>campus_complaint_system</strong> MySQL database, construct tables, set up foreign keys, and seed realistic demo complaints, staff members, categories, and student accounts.
                    </p>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered small">
                            <tbody>
                                <tr>
                                    <th class="bg-light text-muted w-25">Host</th>
                                    <td><code><?= htmlspecialchars(DB_HOST) ?>:<?= htmlspecialchars(DB_PORT) ?></code></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">Database Name</th>
                                    <td><code><?= htmlspecialchars(DB_NAME) ?></code></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">Default User</th>
                                    <td><code><?= htmlspecialchars(DB_USER) ?></code> (Standard XAMPP default)</td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">Base URL</th>
                                    <td><code><?= htmlspecialchars(BASE_URL) ?></code></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">Status</th>
                                    <td>
                                        <?php if ($isInstalled): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5"><i class="bi bi-check-circle me-1"></i>Database Ready & Seeded</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1.5"><i class="bi bi-exclamation-triangle me-1"></i>Not Initialized</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="setup.php">
                        <input type="hidden" name="action" value="install">
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <button type="submit" class="btn btn-primary px-4 py-2">
                                <i class="bi bi-lightning-charge-fill me-1"></i> <?= $isInstalled ? 'Re-install / Reset Database' : 'Initialize Database Now' ?>
                            </button>
                            
                            <?php if ($isInstalled): ?>
                                <a href="<?= url('login.php') ?>" class="btn btn-success px-4 py-2">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Login &rarr;
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Demonstration Credentials Card -->
                <div class="app-card p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-key-fill text-warning me-2"></i>Default Demo Login Credentials</h5>
                    <p class="text-secondary small mb-3">Pre-configured accounts for testing and viva demonstration:</p>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-light">
                                <span class="badge bg-danger-subtle text-danger mb-2">Admin Role</span>
                                <div class="fw-bold small text-dark">Demo Admin</div>
                                <div class="small text-secondary text-truncate">admin@demo.com</div>
                                <div class="small text-muted mt-1">Pass: <code>admin123</code></div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-light">
                                <span class="badge bg-warning-subtle text-warning-emphasis mb-2">Staff Role</span>
                                <div class="fw-bold small text-dark">Demo Staff</div>
                                <div class="small text-secondary text-truncate">staff@demo.com</div>
                                <div class="small text-muted mt-1">Pass: <code>staff123</code></div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 bg-light">
                                <span class="badge bg-primary-subtle text-primary mb-2">Student Role</span>
                                <div class="fw-bold small text-dark">Demo Student</div>
                                <div class="small text-secondary text-truncate">student@demo.com</div>
                                <div class="small text-muted mt-1">Pass: <code>student123</code></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="<?= url('index.php') ?>" class="text-decoration-none small text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Back to Campus Care Home Page
                    </a>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
