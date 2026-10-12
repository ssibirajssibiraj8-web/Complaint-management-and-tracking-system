<?php
/**
 * Core Helper Functions
 * Campus Complaint Management and Tracking System
 */

/**
 * Escape HTML output to prevent XSS attacks
 */
function e($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Set a flash notification for the next page load
 */
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flashes'][] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash notifications
 */
function getFlashes(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

/**
 * Render flash alert banners
 */
function renderFlashMessages(): void {
    $flashes = getFlashes();
    if (empty($flashes)) {
        return;
    }
    
    echo '<div class="flash-messages-container mb-4">';
    foreach ($flashes as $f) {
        $type = in_array($f['type'], ['success', 'danger', 'warning', 'info']) ? $f['type'] : 'info';
        $icon = match($type) {
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            default => 'bi-info-circle-fill'
        };
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert">';
        echo '<i class="bi ' . $icon . ' me-2 fs-5 flex-shrink-0"></i>';
        echo '<div class="flex-grow-1">' . e($f['message']) . '</div>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Returns formatted HTML badge for complaint status
 */
function getStatusBadge(string $status): string {
    return match ($status) {
        'Submitted' => '<span class="badge rounded-pill px-3 py-2 bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-clock-history me-1"></i>Submitted</span>',
        'Under Review' => '<span class="badge rounded-pill px-3 py-2 bg-info-subtle text-info-emphasis border border-info-subtle"><i class="bi bi-search me-1"></i>Under Review</span>',
        'Approved' => '<span class="badge rounded-pill px-3 py-2 bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-check2-circle me-1"></i>Approved</span>',
        'Assigned' => '<span class="badge rounded-pill px-3 py-2 bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-person-gear me-1"></i>Assigned</span>',
        'In Progress' => '<span class="badge rounded-pill px-3 py-2 bg-warning text-dark shadow-xs"><i class="bi bi-tools me-1"></i>In Progress</span>',
        'Resolved' => '<span class="badge rounded-pill px-3 py-2 bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Resolved</span>',
        'Rejected' => '<span class="badge rounded-pill px-3 py-2 bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Rejected</span>',
        'Closed' => '<span class="badge rounded-pill px-3 py-2 bg-dark-subtle text-dark border border-dark-subtle"><i class="bi bi-lock-fill me-1"></i>Closed</span>',
        default => '<span class="badge rounded-pill px-3 py-2 bg-light text-muted border">' . e($status) . '</span>',
    };
}

/**
 * Returns formatted HTML badge for priority level
 */
function getPriorityBadge(string $priority): string {
    return match ($priority) {
        'Low' => '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5"><i class="bi bi-arrow-down-circle me-1"></i>Low</span>',
        'Medium' => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1.5"><i class="bi bi-dash-circle me-1"></i>Medium</span>',
        'High' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5"><i class="bi bi-exclamation-octagon-fill me-1"></i>High</span>',
        default => '<span class="badge bg-secondary px-2.5 py-1.5">' . e($priority) . '</span>',
    };
}

/**
 * Generate a unique sequential complaint code (e.g. CMP-2026-00001)
 */
function generateComplaintCode(PDO $pdo): string {
    $year = date('Y');
    $prefix = "CMP-{$year}-";
    
    $stmt = $pdo->prepare("SELECT complaint_code FROM complaints WHERE complaint_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastCode = $stmt->fetchColumn();
    
    if ($lastCode) {
        $lastNumber = (int) substr($lastCode, strlen($prefix));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return sprintf("%s%05d", $prefix, $newNumber);
}

/**
 * Dispatch an in-app notification to a user
 */
function createNotification(PDO $pdo, int $userId, string $title, string $message, ?string $link = null): bool {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        return $stmt->execute([$userId, $title, $message, $link]);
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve total unread notifications count for current user
 */
function getUnreadNotificationsCount(PDO $pdo, int $userId): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Retrieve recent notifications for current user
 */
function getUserNotifications(PDO $pdo, int $userId, int $limit = 5): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Log a status progression event to the timeline
 */
function logComplaintUpdate(PDO $pdo, int $complaintId, ?int $userId, ?string $oldStatus, string $newStatus, ?string $comment = null): bool {
    try {
        $stmt = $pdo->prepare("INSERT INTO complaint_updates (complaint_id, user_id, old_status, new_status, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$complaintId, $userId, $oldStatus, $newStatus, $comment]);
    } catch (PDOException $e) {
        error_log("Failed to log complaint update: " . $e->getMessage());
        return false;
    }
}

/**
 * Format a database timestamp to human-friendly display
 */
function formatDateTime(?string $dateStr, string $format = 'd M Y, h:i A'): string {
    if (!$dateStr) {
        return '—';
    }
    $timestamp = strtotime($dateStr);
    return $timestamp ? date($format, $timestamp) : $dateStr;
}

/**
 * Format timestamp into relative time ("X hours ago", "Yesterday")
 */
function timeAgo(?string $dateStr): string {
    if (!$dateStr) return '—';
    $time = strtotime($dateStr);
    if (!$time) return $dateStr;
    
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('d M Y', $time);
}

/**
 * Securely handle image uploads for complaints or resolutions
 * @throws Exception If upload violates security or size constraints
 */
function handleImageUpload(array $file, string $targetSubdir = 'uploads/complaints/'): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error code: ' . $file['error']);
    }
    
    // Max file size: 5 Megabytes
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('Image size exceeds 5MB limit. Please upload a smaller image.');
    }
    
    // Validate file extension
    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExtensions, true)) {
        throw new Exception('Invalid file extension. Please upload a JPG, JPEG, PNG, or WEBP image.');
    }
    
    // Validate MIME type
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $tmpPath = $file['tmp_name'];
    
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($tmpPath);
    } else {
        $imgInfo = getimagesize($tmpPath);
        $mime = $imgInfo['mime'] ?? '';
    }
    
    if (!in_array($mime, $allowedMimes, true)) {
        throw new Exception('Invalid image file format detected. Please upload a valid image file.');
    }
    
    // Generate safe random unique filename
    $safeName = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    
    // Target storage path
    $baseTargetDir = dirname(__DIR__) . '/' . trim($targetSubdir, '/') . '/';
    if (!is_dir($baseTargetDir)) {
        mkdir($baseTargetDir, 0777, true);
    }
    
    $destination = $baseTargetDir . $safeName;
    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new Exception('Failed to save uploaded image. Please check directory permissions.');
    }
    
    return $safeName;
}
