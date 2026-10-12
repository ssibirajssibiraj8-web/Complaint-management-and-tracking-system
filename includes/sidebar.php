<?php
/**
 * Modern Responsive Sidebar Component
 * Campus Complaint Management and Tracking System
 */

$user = currentUser();
if (!$user) {
    return;
}

$role = $user['role'];
$activeNav = $activeNav ?? 'dashboard';
?>

<aside class="dashboard-sidebar">
    <div class="flex-grow-1">

        <?php if ($role === 'student'): ?>
            <!-- STUDENT NAVIGATION -->
            <div class="sidebar-heading">Student Portal</div>
            
            <a href="<?= url('student/dashboard.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= url('student/new-complaint.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'new-complaint') ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i>
                <span>Report Complaint</span>
            </a>

            <a href="<?= url('student/my-complaints.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'my-complaints') ? 'active' : '' ?>">
                <i class="bi bi-card-checklist"></i>
                <span>My Complaints</span>
            </a>

            <a href="<?= url('student/notifications.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'notifications') ? 'active' : '' ?>">
                <i class="bi bi-bell"></i>
                <span>Notifications</span>
            </a>

            <div class="sidebar-heading mt-4">Account</div>
            
            <a href="<?= url('student/profile.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'profile') ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>My Profile</span>
            </a>

        <?php elseif ($role === 'staff'): ?>
            <!-- STAFF NAVIGATION -->
            <div class="sidebar-heading">Technician Portal</div>

            <a href="<?= url('staff/dashboard.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Staff Overview</span>
            </a>

            <a href="<?= url('staff/complaints.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'complaints' && empty($_GET['status'])) ? 'active' : '' ?>">
                <i class="bi bi-tools"></i>
                <span>Assigned Tasks</span>
            </a>

            <a href="<?= url('staff/complaints.php?status=In+Progress') ?>" class="sidebar-nav-link <?= ($activeNav === 'complaints' && ($_GET['status'] ?? '') === 'In Progress') ? 'active' : '' ?>">
                <i class="bi bi-arrow-repeat"></i>
                <span>In Progress Work</span>
            </a>

            <a href="<?= url('staff/complaints.php?status=Resolved') ?>" class="sidebar-nav-link <?= ($activeNav === 'complaints' && ($_GET['status'] ?? '') === 'Resolved') ? 'active' : '' ?>">
                <i class="bi bi-check-circle"></i>
                <span>Resolved History</span>
            </a>

        <?php elseif ($role === 'admin'): ?>
            <!-- ADMIN NAVIGATION -->
            <div class="sidebar-heading">Administration</div>

            <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-pie-chart"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= url('admin/complaints.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'complaints') ? 'active' : '' ?>">
                <i class="bi bi-inbox"></i>
                <span>Complaints Manager</span>
            </a>

            <div class="sidebar-heading mt-4">Campus Master Data</div>

            <a href="<?= url('admin/users.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'users') ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>User Accounts</span>
            </a>

            <a href="<?= url('admin/staff.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'staff') ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i>
                <span>Staff Technicians</span>
            </a>

            <a href="<?= url('admin/departments.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'departments') ? 'active' : '' ?>">
                <i class="bi bi-buildings"></i>
                <span>Departments</span>
            </a>

            <a href="<?= url('admin/categories.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'categories') ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                <span>Categories</span>
            </a>

            <div class="sidebar-heading mt-4">Quality & Analytics</div>

            <a href="<?= url('admin/feedback.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'feedback') ? 'active' : '' ?>">
                <i class="bi bi-star"></i>
                <span>Student Feedback</span>
            </a>

            <a href="<?= url('admin/reports.php') ?>" class="sidebar-nav-link <?= ($activeNav === 'reports') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>Reports & Analytics</span>
            </a>

        <?php endif; ?>

    </div>

    <!-- Sidebar Bottom Card -->
    <div class="pt-3 border-top mt-3">
        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
                <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.8rem;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <div class="fw-bold small text-truncate" style="max-width: 120px;"><?= e($user['name']) ?></div>
                    <div class="text-muted" style="font-size: 0.7rem; text-transform: capitalize;"><?= e($user['role']) ?></div>
                </div>
            </div>
            <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-outline-danger p-1 rounded-2" title="Sign Out">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>
