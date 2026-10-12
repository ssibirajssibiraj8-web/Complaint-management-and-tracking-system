<?php
/**
 * Authentication & Authorization System
 * Campus Complaint Management and Tracking System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get current logged in user session data
 */
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user ID
 */
function currentUserId(): ?int {
    return $_SESSION['user']['id'] ?? null;
}

/**
 * Get current user role
 */
function currentUserRole(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Check if the user has a specific role
 */
function isRole(string $role): bool {
    return isLoggedIn() && currentUserRole() === $role;
}

/**
 * Enforce authentication for protected pages
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to access this page.');
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . url('login.php?redirect=' . urlencode($currentUri)));
        exit;
    }
    
    // Check if user status is active
    if (isset($_SESSION['user']['status']) && $_SESSION['user']['status'] === 'inactive') {
        logoutUser();
        setFlash('danger', 'Your account has been deactivated. Please contact campus administration.');
        header('Location: ' . url('login.php'));
        exit;
    }
}

/**
 * Enforce role-based access control (RBAC)
 * @param string|array $allowedRoles Single role or array of allowed roles
 */
function requireRole($allowedRoles): void {
    requireLogin();
    
    $roles = (array) $allowedRoles;
    $currentRole = currentUserRole();
    
    if (!in_array($currentRole, $roles, true)) {
        setFlash('danger', 'Access denied. You do not have permission to view that page.');
        
        // Redirect to user's authorized home dashboard
        if ($currentRole === 'admin') {
            header('Location: ' . url('admin/dashboard.php'));
        } elseif ($currentRole === 'staff') {
            header('Location: ' . url('staff/dashboard.php'));
        } else {
            header('Location: ' . url('student/dashboard.php'));
        }
        exit;
    }
}

/**
 * Authenticate and initialize user session
 */
function loginUser(array $user): void {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? '',
        'role' => $user['role'],
        'status' => $user['status'] ?? 'active'
    ];
}

/**
 * Log out user and destroy session
 */
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Generate or retrieve CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF input field for forms
 */
function csrfInput(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from POST request
 */
function validateCsrfToken(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
