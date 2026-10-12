<?php
/**
 * Auto Setup Runner
 */
define('SETUP_MODE', true);
require_once __DIR__ . '/config/database.php';

echo "Connecting to MySQL server at " . DB_HOST . ":" . DB_PORT . "...\n";

try {
    $pdoServer = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $sqlFile = __DIR__ . '/database.sql';
    if (!file_exists($sqlFile)) {
        die("ERROR: database.sql not found\n");
    }

    $sqlContent = file_get_contents($sqlFile);
    echo "Importing database.sql schema and seed records...\n";
    $pdoServer->exec($sqlContent);

    echo "Updating password hashes with PHP native bcrypt...\n";
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

    $uploadDir = __DIR__ . '/uploads/complaints/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    echo "SUCCESS: Database initialized, seeded, and all demo accounts ready!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
