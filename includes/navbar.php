<?php
/**
 * Modern Responsive Top Navigation Bar
 * Campus Complaint Management and Tracking System
 */

$user = currentUser();
$unreadNotifsCount = 0;
$recentNotifs = [];

if ($user && isset($pdo)) {
    $unreadNotifsCount = getUnreadNotificationsCount($pdo, (int) $user['id']);
    $recentNotifs = getUserNotifications($pdo, (int) $user['id'], 5);
}

// Determine role-based home link
$homeUrl = url('index.php');
if ($user) {
    $homeUrl = match($user['role']) {
        'admin' => url('admin/dashboard.php'),
        'staff' => url('staff/dashboard.php'),
        default => url('student/dashboard.php'),
    };
}
?>

<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        
        <!-- Left Side: Mobile Sidebar Toggle + Brand Logo -->
        <div class="d-flex align-items-center gap-2">
            <?php if ($user): ?>
                <button class="btn btn-light d-lg-none p-2 border-0" id="sidebarToggleBtn" aria-label="Toggle navigation">
                    <i class="bi bi-list fs-4"></i>
                </button>
            <?php endif; ?>

            <a class="brand-logo" href="<?= $homeUrl ?>">
                <span class="brand-badge"><i class="bi bi-shield-check"></i></span>
                <span class="d-none d-sm-inline">Campus<span class="text-primary">Care</span></span>
            </a>
        </div>

        <!-- Center / Public Links (When not logged in) -->
        <?php if (!$user): ?>
            <div class="collapse navbar-collapse justify-content-center" id="publicNavbarNav">
                <ul class="navbar-nav gap-2">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold px-3" href="<?= url('index.php') ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold px-3" href="<?= url('index.php#how-it-works') ?>">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold px-3" href="<?= url('index.php#categories') ?>">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold px-3" href="<?= url('index.php#stats') ?>">Campus Stats</a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Right Side: Auth buttons or User Profile & Notifications -->
        <div class="d-flex align-items-center gap-3">
            <?php if ($user): ?>
                <!-- Notifications Bell Dropdown -->
                <div class="dropdown">
                    <button class="notification-bell-btn" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadNotifsCount > 0): ?>
                            <span class="notification-badge-count"><?= $unreadNotifsCount ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="dropdown-menu dropdown-menu-end notifications-dropdown-menu" aria-labelledby="notifDropdown">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bell me-1 text-primary"></i> Notifications</h6>
                            <?php if ($unreadNotifsCount > 0): ?>
                                <span class="badge bg-primary rounded-pill"><?= $unreadNotifsCount ?> new</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="list-group list-group-flush" style="max-height: 320px; overflow-y: auto;">
                            <?php if (empty($recentNotifs)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-bell-slash fs-3 d-block mb-1"></i>
                                    <small>No notifications yet</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentNotifs as $notif): ?>
                                    <div class="list-group-item list-group-item-action p-3 <?= $notif['is_read'] ? '' : 'bg-light fw-bold' ?>" onclick="markNotificationAsRead(<?= (int)$notif['id'] ?>, this)">
                                        <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                            <span class="small text-dark fw-bold"><?= e($notif['title']) ?></span>
                                            <span class="text-muted" style="font-size: 0.72rem;"><?= timeAgo($notif['created_at']) ?></span>
                                        </div>
                                        <p class="mb-1 text-secondary small fw-normal"><?= e($notif['message']) ?></p>
                                        <?php if (!empty($notif['link'])): ?>
                                            <a href="<?= url($notif['link']) ?>" class="small text-decoration-none text-primary fw-semibold">View Details &rarr;</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($user['role'] === 'student'): ?>
                            <div class="p-2 border-top text-center bg-light">
                                <a href="<?= url('student/notifications.php') ?>" class="small text-decoration-none fw-semibold text-primary">View All Notifications</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light d-flex align-items-center gap-2 py-1.5 px-2.5 rounded-3 border-0" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.85rem;">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <div class="text-start d-none d-md-block" style="line-height: 1.2;">
                            <div class="fw-bold small text-dark"><?= e($user['name']) ?></div>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 0.65rem; text-transform: uppercase;">
                                <?= e($user['role']) ?>
                            </span>
                        </div>
                        <i class="bi bi-chevron-down small text-muted ms-1"></i>
                    </button>
                    
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2" aria-labelledby="userMenuDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <span class="d-block small text-muted">Signed in as</span>
                            <strong class="d-block text-dark small text-truncate" style="max-width: 180px;"><?= e($user['email']) ?></strong>
                        </li>
                        <?php if ($user['role'] === 'student'): ?>
                            <li><a class="dropdown-item py-2" href="<?= url('student/profile.php') ?>"><i class="bi bi-person me-2 text-muted"></i> My Profile</a></li>
                            <li><a class="dropdown-item py-2" href="<?= url('student/my-complaints.php') ?>"><i class="bi bi-journal-text me-2 text-muted"></i> My Complaints</a></li>
                        <?php elseif ($user['role'] === 'staff'): ?>
                            <li><a class="dropdown-item py-2" href="<?= url('staff/dashboard.php') ?>"><i class="bi bi-speedometer2 me-2 text-muted"></i> Staff Portal</a></li>
                            <li><a class="dropdown-item py-2" href="<?= url('staff/complaints.php') ?>"><i class="bi bi-tools me-2 text-muted"></i> Assigned Tasks</a></li>
                        <?php elseif ($user['role'] === 'admin'): ?>
                            <li><a class="dropdown-item py-2" href="<?= url('admin/dashboard.php') ?>"><i class="bi bi-speedometer2 me-2 text-muted"></i> Admin Console</a></li>
                            <li><a class="dropdown-item py-2" href="<?= url('admin/reports.php') ?>"><i class="bi bi-graph-up me-2 text-muted"></i> Analytics & Reports</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger fw-semibold" href="<?= url('logout.php') ?>">
                                <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                            </a>
                        </li>
                    </ul>
                </div>

            <?php else: ?>
                <!-- Public Buttons -->
                <a href="<?= url('login.php') ?>" class="btn btn-outline-primary fw-semibold px-3 py-1.5">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Log In
                </a>
                <a href="<?= url('register.php') ?>" class="btn btn-primary fw-semibold px-3 py-1.5 shadow-sm">
                    <i class="bi bi-person-plus me-1"></i> Register
                </a>
            <?php endif; ?>
        </div>

    </div>
</nav>
