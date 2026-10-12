<?php
/**
 * MySQL Configuration Helper
 * Helps you find and set the correct MySQL password for this application
 */

$testResult = null;
$savedOk = false;
$envFile = __DIR__ . '/.env';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testPass = $_POST['db_pass'] ?? '';
    $testHost = $_POST['db_host'] ?? '127.0.0.1';
    $testPort = $_POST['db_port'] ?? '3306';
    $testUser = $_POST['db_user'] ?? 'root';
    
    // Test the connection
    try {
        $dsn = "mysql:host={$testHost};port={$testPort};charset=utf8mb4";
        $pdo = new PDO($dsn, $testUser, $testPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        $testResult = 'success';
        
        // Save to .env file
        if (isset($_POST['save']) && $_POST['save'] === '1') {
            $envContent = "# MySQL Database Configuration\n";
            $envContent .= "DB_HOST={$testHost}\n";
            $envContent .= "DB_PORT={$testPort}\n";
            $envContent .= "DB_USER={$testUser}\n";
            $envContent .= "DB_PASS={$testPass}\n";
            
            if (file_put_contents($envFile, $envContent) !== false) {
                $savedOk = true;
            }
        }
    } catch (PDOException $e) {
        $testResult = 'error';
        $errorMsg = $e->getMessage();
    }
}

// Read current .env values
$currentEnv = ['DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_USER' => 'root', 'DB_PASS' => ''];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = array_map('trim', explode('=', $line, 2));
            $currentEnv[$key] = trim($value, '"\'');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MySQL Configuration | Campus Care</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .config-card { background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .btn-test { background: linear-gradient(135deg, #667eea, #764ba2); border: none; color: white; }
        .btn-test:hover { background: linear-gradient(135deg, #5a6fd6, #6a4299); color: white; transform: translateY(-1px); }
    </style>
</head>
<body class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="text-center mb-4 text-white">
                    <h2 class="fw-bold"><i class="bi bi-database-gear me-2"></i>MySQL Configuration</h2>
                    <p class="opacity-75">Enter your MySQL credentials to connect the Campus Complaint System</p>
                </div>

                <?php if ($testResult === 'success'): ?>
                    <div class="alert alert-success shadow-sm d-flex align-items-center mb-4">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong>Connection Successful!</strong>
                            <?php if ($savedOk): ?>
                                <br>Settings saved to <code>.env</code> file.
                                <br><a href="setup.php" class="btn btn-success btn-sm mt-2"><i class="bi bi-lightning-charge-fill me-1"></i>Continue to Database Setup →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($testResult === 'error'): ?>
                    <div class="alert alert-danger shadow-sm d-flex align-items-center mb-4">
                        <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
                        <div>
                            <strong>Connection Failed!</strong><br>
                            <small><?= htmlspecialchars($errorMsg) ?></small>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="config-card p-4 p-md-5">
                    <form method="POST">
                        <input type="hidden" name="save" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">MySQL Host</label>
                            <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($currentEnv['DB_HOST']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">MySQL Port</label>
                            <input type="text" name="db_port" class="form-control" value="<?= htmlspecialchars($currentEnv['DB_PORT']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">MySQL Username</label>
                            <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($currentEnv['DB_USER']) ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold small">MySQL Password</label>
                            <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($currentEnv['DB_PASS']) ?>" placeholder="Enter your MySQL root password">
                            <div class="form-text">This is the password you set when you installed MySQL.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-test btn-lg w-100 py-2">
                            <i class="bi bi-plug-fill me-2"></i>Test Connection & Save
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="small text-muted mb-2">Don't know your MySQL password?</p>
                        <details class="text-start">
                            <summary class="small text-primary fw-bold" style="cursor:pointer">How to reset MySQL root password</summary>
                            <div class="mt-2 small text-muted">
                                <ol>
                                    <li>Open <strong>PowerShell as Administrator</strong> (right-click → Run as administrator)</li>
                                    <li>Run: <code>net stop MySQL80</code></li>
                                    <li>Run: <code>Start-Process "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqld.exe" -ArgumentList '--skip-grant-tables','--shared-memory' -NoNewWindow</code></li>
                                    <li>Wait 5 seconds, then run: <code>& "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -e "FLUSH PRIVILEGES; ALTER USER 'root'@'localhost' IDENTIFIED BY 'newpassword';"</code></li>
                                    <li>Run: <code>taskkill /f /im mysqld.exe</code></li>
                                    <li>Run: <code>net start MySQL80</code></li>
                                    <li>Come back here and enter <code>newpassword</code> as your password</li>
                                </ol>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
