<?php
/**
 * User Accounts Management (CRUD)
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'User Management - Admin Console';
$activeNav = 'users';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

$user = currentUser();

// 1. Handle Action: CREATE USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    if (!validateCsrfToken()) {
        setFlash('danger', 'Security session expired. Please try again.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'student');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            setFlash('danger', 'Name, email, and initial password are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'Invalid email format.');
        } elseif (!in_array($role, ['student', 'staff', 'admin'], true)) {
            setFlash('danger', 'Invalid role selected.');
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    setFlash('danger', 'A user with this email address already exists.');
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                    $ins->execute([$name, $email, $phone, $hashed, $role]);
                    setFlash('success', "New {$role} user '{$name}' created successfully.");
                }
            } catch (PDOException $e) {
                setFlash('danger', 'Database error creating user.');
            }
        }
    }
    header('Location: ' . url('admin/users.php'));
    exit;
}

// 2. Handle Action: TOGGLE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (validateCsrfToken()) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId > 0 && $targetId !== (int)$user['id']) {
            $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id = ?");
            $stmt->execute([$targetId]);
            setFlash('info', 'User status updated successfully.');
        } else {
            setFlash('warning', 'You cannot deactivate your own administrative account.');
        }
    }
    header('Location: ' . url('admin/users.php'));
    exit;
}

// 3. Handle Action: EDIT USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    if (validateCsrfToken()) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $newPw = $_POST['new_password'] ?? '';

        if ($targetId > 0 && !empty($name) && in_array($role, ['student', 'staff', 'admin'], true)) {
            try {
                if (!empty($newPw)) {
                    $hashed = password_hash($newPw, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $role, $hashed, $targetId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, role = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $role, $targetId]);
                }
                setFlash('success', 'User details updated successfully.');
            } catch (PDOException $e) {
                setFlash('danger', 'Error updating user.');
            }
        }
    }
    header('Location: ' . url('admin/users.php'));
    exit;
}

// 4. Handle Action: DELETE USER (With Foreign Key Safety Check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    if (validateCsrfToken()) {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        if ($targetId === (int)$user['id']) {
            setFlash('danger', 'You cannot delete your own account.');
        } else {
            try {
                // Check if user has active assignments as staff
                $hasAssignments = (int) $pdo->query("SELECT COUNT(*) FROM assignments WHERE staff_id = {$targetId}")->fetchColumn();
                if ($hasAssignments > 0) {
                    setFlash('danger', 'Cannot delete this staff member because they have assigned complaints. Please reassign the complaints first or deactivate the account instead.');
                } else {
                    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmtDel->execute([$targetId]);
                    setFlash('success', 'User deleted successfully.');
                }
            } catch (PDOException $e) {
                setFlash('danger', 'Cannot delete user because related records exist. Consider deactivating this user instead.');
            }
        }
    }
    header('Location: ' . url('admin/users.php'));
    exit;
}

// Fetch Users with Search & Filters
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($roleFilter)) {
    $sql .= " AND role = :role";
    $params[':role'] = $roleFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND status = :status";
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $userList = $stmt->fetchAll();
    $totalUsers = count($userList);
} catch (PDOException $e) {
    $userList = [];
    $totalUsers = 0;
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
                    <i class="bi bi-people text-primary me-2"></i>Campus User Management
                </h3>
                <p class="text-secondary small mb-0">Create, search, update credentials, activate/deactivate, and manage accounts.</p>
            </div>
            <button type="button" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="bi bi-person-plus me-1"></i> Add New User
            </button>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Search & Filter Bar -->
        <div class="app-card p-4 mb-4">
            <form method="GET" action="<?= url('admin/users.php') ?>" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="searchInput" class="form-label small fw-bold text-dark">Search Users</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" name="search" value="<?= e($search) ?>" placeholder="Search by name, email, phone...">
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <label for="roleSelect" class="form-label small fw-bold text-dark">Role</label>
                    <select class="form-select" id="roleSelect" name="role">
                        <option value="">All Roles</option>
                        <option value="student" <?= ($roleFilter === 'student') ? 'selected' : '' ?>>Student</option>
                        <option value="staff" <?= ($roleFilter === 'staff') ? 'selected' : '' ?>>Staff Technician</option>
                        <option value="admin" <?= ($roleFilter === 'admin') ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label for="statusSelect" class="form-label small fw-bold text-dark">Status</label>
                    <select class="form-select" id="statusSelect" name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($statusFilter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-light border" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="app-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="small text-muted fw-semibold">
                    Found <strong><?= $totalUsers ?></strong> registered user<?= $totalUsers === 1 ? '' : 's' ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name & Contact</th>
                            <th>College Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registration Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userList as $u): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-muted">#<?= $u['id'] ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($u['name']) ?></div>
                                    <?php if (!empty($u['phone'])): ?>
                                        <small class="text-secondary"><i class="bi bi-telephone me-1"></i><?= e($u['phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-secondary small"><?= e($u['email']) ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $roleBadge = match($u['role']) {
                                            'admin' => 'bg-danger-subtle text-danger border-danger-subtle',
                                            'staff' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                            default => 'bg-primary-subtle text-primary border-primary-subtle'
                                        };
                                    ?>
                                    <span class="badge <?= $roleBadge ?> border px-2.5 py-1 text-uppercase" style="font-size: 0.72rem;">
                                        <?= e($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check-circle me-1"></i>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                            <i class="bi bi-dash-circle me-1"></i>Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= formatDateTime($u['created_at'], 'd M Y') ?></small>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- Edit User Button -->
                                        <button type="button" class="btn btn-sm btn-light border p-1.5" title="Edit User" onclick='openEditModal(<?= json_encode($u) ?>)'>
                                            <i class="bi bi-pencil-square text-primary"></i>
                                        </button>

                                        <!-- Toggle Status Button -->
                                        <?php if ($u['id'] !== (int)$user['id']): ?>
                                            <form method="POST" action="<?= url('admin/users.php') ?>" class="d-inline">
                                                <?= csrfInput() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-light border p-1.5" title="<?= $u['status'] === 'active' ? 'Deactivate User' : 'Activate User' ?>">
                                                    <i class="bi <?= $u['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?> fs-6"></i>
                                                </button>
                                            </form>

                                            <!-- Delete User Button -->
                                            <form method="POST" action="<?= url('admin/users.php') ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete user <?= e($u['name']) ?>?');">
                                                <?= csrfInput() ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-light border p-1.5" title="Delete User">
                                                    <i class="bi bi-trash3 text-danger"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </main>
</div>

<!-- Modal: Create New User -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= url('admin/users.php') ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="create_user">

                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="createUserModalLabel">
                        <i class="bi bi-person-plus text-primary me-2"></i>Create New Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="createName" class="form-label small fw-bold text-dark">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="createName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="createEmail" class="form-label small fw-bold text-dark">College Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="createEmail" name="email" placeholder="name@college.edu" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="createPhone" class="form-label small fw-bold text-dark">Phone Number</label>
                            <input type="tel" class="form-control" id="createPhone" name="phone" placeholder="+91 98765 43210">
                        </div>
                        <div class="col-md-6">
                            <label for="createRole" class="form-label small fw-bold text-dark">Account Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="createRole" name="role" required>
                                <option value="staff" selected>Staff Technician</option>
                                <option value="admin">Administrator</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createPassword" class="form-label small fw-bold text-dark">Initial Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="createPassword" name="password" placeholder="At least 6 characters" required>
                    </div>
                </div>

                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= url('admin/users.php') ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId" value="">

                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="editUserModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit User Account
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editName" class="form-label small fw-bold text-dark">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="editEmail" class="form-label small fw-bold text-dark">Email (Read-only)</label>
                        <input type="email" class="form-control bg-light" id="editEmail" readonly disabled>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="editPhone" class="form-label small fw-bold text-dark">Phone Number</label>
                            <input type="tel" class="form-control" id="editPhone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="editRole" class="form-label small fw-bold text-dark">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="editRole" name="role" required>
                                <option value="student">Student</option>
                                <option value="staff">Staff Technician</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editNewPassword" class="form-label small fw-bold text-dark">Reset Password <span class="text-muted fw-normal">(Leave blank to keep unchanged)</span></label>
                        <input type="password" class="form-control" id="editNewPassword" name="new_password" placeholder="Enter new password if changing">
                    </div>
                </div>

                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(userData) {
    document.getElementById('editUserId').value = userData.id;
    document.getElementById('editName').value = userData.name;
    document.getElementById('editEmail').value = userData.email;
    document.getElementById('editPhone').value = userData.phone || '';
    document.getElementById('editRole').value = userData.role;
    document.getElementById('editNewPassword').value = '';
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
