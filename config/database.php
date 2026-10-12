<?php
/**
 * Database Configuration & Connection Handler
 * Campus Complaint Management and Tracking System
 */

// Load .env file if it exists (simple key=value parser)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue; // skip comments
        if (strpos($line, '=') !== false) {
            list($key, $value) = array_map('trim', explode('=', $line, 2));
            // Remove surrounding quotes if present
            $value = trim($value, '"\'');
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// Define application configuration
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'campus_complaint_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Establish Base URL dynamically based on deployment folder inside htdocs
if (!defined('BASE_URL')) {
    if (php_sapi_name() === 'cli') {
        define('BASE_URL', '/');
    } else {
        $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        
        if (!empty($docRoot) && strpos($projectRoot, $docRoot) === 0) {
            $subPath = trim(substr($projectRoot, strlen($docRoot)), '/\\');
            $base = $subPath ? '/' . $subPath . '/' : '/';
        } else {
            // Fallback to script name detection
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            // Remove subfolders like /admin, /student, /staff, /api if present
            $base = preg_replace('#/(admin|student|staff|api|config|includes|assets)(/.*)?$#i', '', $scriptDir);
            $base = rtrim($base, '/') . '/';
        }
        define('BASE_URL', $base);
    }
}

/**
 * Helper to build absolute URLs within the application
 */
if (!function_exists('url')) {
    function url($path = '') {
        $path = ltrim($path, '/');
        return BASE_URL . $path;
    }
}

/**
 * Returns a shared PDO instance
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // If database does not exist, provide a helpful prompt to run setup.php
        $code = $e->getCode();
        $isAuthError = ($code == 1045 || strpos($e->getMessage(), 'Access denied') !== false);
        $currentScript = basename($_SERVER['PHP_SELF'] ?? '');

        if ($isDbMissing && $currentScript !== 'setup.php' && $currentScript !== 'configure.php') {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Database Setup Required - Campus Complaint System</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>
            <body class="bg-light py-5">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-md-7">
                            <div class="card shadow border-0 rounded-4 p-4 text-center">
                                <div class="mb-3">
                                    <span class="badge bg-warning text-dark p-3 rounded-circle fs-3">⚠️</span>
                                </div>
                                <h3 class="fw-bold text-dark">Database Not Found</h3>
                                <p class="text-secondary mt-2">
                                    The database <strong><?= htmlspecialchars(DB_NAME) ?></strong> has not been imported yet.
                                </p>
                                <div class="alert alert-info text-start mt-3">
                                    <h6 class="fw-bold mb-2">Quick Solution:</h6>
                                    <p class="mb-1">You can automatically initialize the database and demo records using the web installer:</p>
                                    <a href="<?= url('setup.php') ?>" class="btn btn-primary btn-sm mt-2">Run One-Click Setup (setup.php)</a>
                                </div>
                                <p class="small text-muted mb-0">Alternatively, import <code>database.sql</code> via phpMyAdmin.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        if ($isAuthError && $currentScript !== 'configure.php') {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>MySQL Password Configuration - Campus Complaint System</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>
            <body class="bg-light py-5">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-md-7">
                            <div class="card shadow border-0 rounded-4 p-4 text-center">
                                <div class="mb-3">
                                    <span class="badge bg-danger text-white p-3 rounded-circle fs-3">🔐</span>
                                </div>
                                <h3 class="fw-bold text-dark">MySQL Password Required</h3>
                                <p class="text-secondary mt-2">
                                    MySQL rejected the connection for user <strong>root</strong> because a root password is set on your MySQL server.
                                </p>
                                <div class="alert alert-warning text-start mt-3">
                                    <h6 class="fw-bold mb-2">Configure Password in 1 Click:</h6>
                                    <p class="mb-2">Enter your MySQL root password so Campus Care can connect to your database:</p>
                                    <a href="<?= url('configure.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-gear-fill me-1"></i>Open MySQL Configuration (configure.php)</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        // Generic friendly error
        error_log("Database Connection Error: " . $e->getMessage());
        die("<div style='font-family:sans-serif;padding:30px;background:#FEF2F2;color:#991B1B;border-radius:10px;margin:30px auto;max-width:600px;'>
            <h3>Database Connection Failed</h3>
            <p>Could not connect to MySQL server. Please make sure MySQL is running.</p>
            <p><a href='" . url('configure.php') . "'>Click here to configure database connection</a></p>
            <small style='color:#6B7280;'>Error code: " . htmlspecialchars($e->getMessage()) . "</small>
        </div>");
    }
}

// Global PDO reference (skip in setup/configure mode)
if (!defined('SETUP_MODE') && basename($_SERVER['PHP_SELF'] ?? '') !== 'configure.php') {
    $pdo = getDBConnection();
} else {
    $pdo = null;
}
