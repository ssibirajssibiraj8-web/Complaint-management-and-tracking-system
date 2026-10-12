<?php
/**
 * Campus Department Management (CRUD)
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Departments - Admin Console';
$activeNav = 'departments';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

// 1. Handle Action: CREATE DEPARTMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_dept') {
    if (!validateCsrfToken()) {
        setFlash('danger', 'Security session mismatch. Please try again.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (empty($name)) {
            setFlash('danger', 'Department name is required.');
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
                $check->execute([$name]);
                if ($check->fetch()) {
                    setFlash('danger', 'A department with this name already exists.');
                } else {
                    $ins = $pdo->prepare("INSERT INTO departments (name, description, created_at) VALUES (?, ?, NOW())");
                    $ins->execute([$name, $desc]);
                    setFlash('success', "Department '{$name}' created successfully.");
                }
            } catch (PDOException $e) {
                setFlash('danger', 'Database error creating department.');
            }
        }
    }
    header('Location: ' . url('admin/departments.php'));
    exit;
}

// 2. Handle Action: EDIT DEPARTMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_dept') {
    if (validateCsrfToken()) {
        $id = (int) ($_POST['dept_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($id > 0 && !empty($name)) {
            try {
                $up = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
                $up->execute([$name, $desc, $id]);
                setFlash('success', "Department '{$name}' updated successfully.");
            } catch (PDOException $e) {
                setFlash('danger', 'Failed to update department.');
            }
        }
    }
    header('Location: ' . url('admin/departments.php'));
    exit;
}

// 3. Handle Action: DELETE DEPARTMENT (With Relationship Check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dept') {
    if (validateCsrfToken()) {
        $id = (int) ($_POST['dept_id'] ?? 0);
        if ($id > 0) {
            try {
                // Check if any assignments link to this department
                $assignCount = (int) $pdo->query("SELECT COUNT(*) FROM assignments WHERE department_id = {$id}")->fetchColumn();
                if ($assignCount > 0) {
                    setFlash('danger', "Cannot delete this department because {$assignCount} complaints have been assigned to it. Remove or reassign those complaints first.");
                } else {
                    $del = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                    $del->execute([$id]);
                    setFlash('success', 'Department deleted successfully.');
                }
            } catch (PDOException $e) {
                setFlash('danger', 'Cannot delete department due to database integrity constraints.');
            }
        }
    }
    header('Location: ' . url('admin/departments.php'));
    exit;
}

// Fetch all departments with assigned complaints count
try {
    $stmt = $pdo->query("
        SELECT d.*, COUNT(a.id) as total_assignments
        FROM departments d
        LEFT JOIN assignments a ON d.id = a.department_id
        GROUP BY d.id
        ORDER BY d.name ASC
    ");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
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
                    <i class="bi bi-buildings text-primary me-2"></i>Campus Departments
                </h3>
                <p class="text-secondary small mb-0">Manage technical departments responsible for campus maintenance and facility repairs.</p>
            </div>
            <button type="button" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                <i class="bi bi-plus-circle me-1"></i> Add Department
            </button>
        </div>

        <?php renderFlashMessages(); ?>

        <div class="row g-4">
            <?php foreach ($departments as $dept): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="app-card p-4 h-100 d-flex flex-column border">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <h5 class="fw-bold text-dark mb-0"><?= e($dept['name']) ?></h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                <?= (int)$dept['total_assignments'] ?> assigned
                            </span>
                        </div>
                        <p class="text-secondary small flex-grow-1 mb-3">
                            <?= e($dept['description'] ?: 'No specific description provided.') ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                            <small class="text-muted">Created <?= formatDateTime($dept['created_at'], 'd M Y') ?></small>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-light border p-1.5" title="Edit Department" onclick='openEditDeptModal(<?= json_encode($dept) ?>)'>
                                    <i class="bi bi-pencil-square text-primary"></i>
                                </button>
                                
                                <form method="POST" action="<?= url('admin/departments.php') ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete department <?= e($dept['name']) ?>?');">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="delete_dept">
                                    <input type="hidden" name="dept_id" value="<?= $dept['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-light border p-1.5" title="Delete Department">
                                        <i class="bi bi-trash3 text-danger"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<!-- Modal: Add Department -->
<div class="modal fade" id="addDeptModal" tabindex="-1" aria-labelledby="addDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= url('admin/departments.php') ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="create_dept">

                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="addDeptModalLabel">
                        <i class="bi bi-buildings text-primary me-2"></i>Add New Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="deptName" class="form-label small fw-bold text-dark">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="deptName" name="name" placeholder="e.g. Electrical Department, IT Services" required>
                    </div>

                    <div class="mb-3">
                        <label for="deptDesc" class="form-label small fw-bold text-dark">Description</label>
                        <textarea class="form-control" id="deptDesc" name="description" rows="3" placeholder="Scope of services and repair duties..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Create Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal fade" id="editDeptModal" tabindex="-1" aria-labelledby="editDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= url('admin/departments.php') ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="edit_dept">
                <input type="hidden" name="dept_id" id="editDeptId" value="">

                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="editDeptModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editDeptName" class="form-label small fw-bold text-dark">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editDeptName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="editDeptDesc" class="form-label small fw-bold text-dark">Description</label>
                        <textarea class="form-control" id="editDeptDesc" name="description" rows="3"></textarea>
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
function openEditDeptModal(dept) {
    document.getElementById('editDeptId').value = dept.id;
    document.getElementById('editDeptName').value = dept.name;
    document.getElementById('editDeptDesc').value = dept.description || '';
    new bootstrap.Modal(document.getElementById('editDeptModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
