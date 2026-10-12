<?php
/**
 * Complaint Categories Management (CRUD)
 * Campus Complaint Management and Tracking System
 */

$pageTitle = 'Categories - Admin Console';
$activeNav = 'categories';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin']);

// 1. Handle Action: CREATE CATEGORY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_cat') {
    if (!validateCsrfToken()) {
        setFlash('danger', 'Security session mismatch. Please try again.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (empty($name)) {
            setFlash('danger', 'Category name is required.');
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $check->execute([$name]);
                if ($check->fetch()) {
                    setFlash('danger', 'A category with this name already exists.');
                } else {
                    $ins = $pdo->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
                    $ins->execute([$name, $desc]);
                    setFlash('success', "Category '{$name}' created successfully.");
                }
            } catch (PDOException $e) {
                setFlash('danger', 'Database error creating category.');
            }
        }
    }
    header('Location: ' . url('admin/categories.php'));
    exit;
}

// 2. Handle Action: EDIT CATEGORY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_cat') {
    if (validateCsrfToken()) {
        $id = (int) ($_POST['cat_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($id > 0 && !empty($name)) {
            try {
                $up = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $up->execute([$name, $desc, $id]);
                setFlash('success', "Category '{$name}' updated successfully.");
            } catch (PDOException $e) {
                setFlash('danger', 'Failed to update category.');
            }
        }
    }
    header('Location: ' . url('admin/categories.php'));
    exit;
}

// 3. Handle Action: DELETE CATEGORY (With Safe Check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_cat') {
    if (validateCsrfToken()) {
        $id = (int) ($_POST['cat_id'] ?? 0);
        if ($id > 0) {
            try {
                // Check if any complaints use this category
                $count = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE category_id = {$id}")->fetchColumn();
                if ($count > 0) {
                    setFlash('danger', "Cannot delete this category because {$count} complaints are currently associated with it. Please reassign those complaints first.");
                } else {
                    $del = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $del->execute([$id]);
                    setFlash('success', 'Category deleted successfully.');
                }
            } catch (PDOException $e) {
                setFlash('danger', 'Cannot delete category due to database relationships.');
            }
        }
    }
    header('Location: ' . url('admin/categories.php'));
    exit;
}

// Fetch all categories with complaint count
try {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(cmp.id) as complaint_count
        FROM categories c
        LEFT JOIN complaints cmp ON c.id = cmp.category_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
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
                    <i class="bi bi-tags text-primary me-2"></i>Complaint Categories
                </h3>
                <p class="text-secondary small mb-0">Configure facility categories available for students when filing tickets.</p>
            </div>
            <button type="button" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#addCatModal">
                <i class="bi bi-plus-circle me-1"></i> Add Category
            </button>
        </div>

        <?php renderFlashMessages(); ?>

        <div class="row g-4">
            <?php foreach ($categories as $cat): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="app-card p-4 h-100 d-flex flex-column border">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <h5 class="fw-bold text-dark mb-0"><?= e($cat['name']) ?></h5>
                            <span class="badge bg-secondary-subtle text-secondary small">
                                <?= (int)$cat['complaint_count'] ?> registered
                            </span>
                        </div>
                        <p class="text-secondary small flex-grow-1 mb-3">
                            <?= e($cat['description'] ?: 'No description provided.') ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                            <small class="text-muted">ID #<?= $cat['id'] ?></small>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-light border p-1.5" title="Edit Category" onclick='openEditCatModal(<?= json_encode($cat) ?>)'>
                                    <i class="bi bi-pencil-square text-primary"></i>
                                </button>
                                
                                <form method="POST" action="<?= url('admin/categories.php') ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete category <?= e($cat['name']) ?>?');">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="delete_cat">
                                    <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-light border p-1.5" title="Delete Category">
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

<!-- Modal: Add Category -->
<div class="modal fade" id="addCatModal" tabindex="-1" aria-labelledby="addCatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= url('admin/categories.php') ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="create_cat">

                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="addCatModalLabel">
                        <i class="bi bi-tags text-primary me-2"></i>Add Complaint Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="catName" class="form-label small fw-bold text-dark">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="catName" name="name" placeholder="e.g. Air Conditioning, Campus Lighting" required>
                    </div>

                    <div class="mb-3">
                        <label for="catDesc" class="form-label small fw-bold text-dark">Description</label>
                        <textarea class="form-control" id="catDesc" name="description" rows="3" placeholder="Brief description of issues covered by this category..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Category -->
<div class="modal fade" id="editCatModal" tabindex="-1" aria-labelledby="editCatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= url('admin/categories.php') ?>">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="edit_cat">
                <input type="hidden" name="cat_id" id="editCatId" value="">

                <div class="modal-header border-bottom bg-light">
                    <h5 class="modal-title fw-bold text-dark" id="editCatModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editCatName" class="form-label small fw-bold text-dark">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editCatName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="editCatDesc" class="form-label small fw-bold text-dark">Description</label>
                        <textarea class="form-control" id="editCatDesc" name="description" rows="3"></textarea>
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
function openEditCatModal(cat) {
    document.getElementById('editCatId').value = cat.id;
    document.getElementById('editCatName').value = cat.name;
    document.getElementById('editCatDesc').value = cat.description || '';
    new bootstrap.Modal(document.getElementById('editCatModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
