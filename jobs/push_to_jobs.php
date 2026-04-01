<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../auth/auth_check.php';
require_once '../config/db.php';

// Enable exceptions for PDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$garage_id = $_SESSION['user_id'] ?? 0;
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

if (!$request_id) {
    echo "error:invalid_request";
    exit();
}

// Ensure user is garage
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    echo "error:unauthorized";
    exit();
}

try {
    
    // Check if job already exists
// Check if job already exists
$stmt = $pdo->prepare("SELECT id FROM jobs WHERE request_id = ? AND garage_id = ?");
$stmt->execute([$request_id, $garage_id]);
if ($stmt->fetch()) {
    echo "error:already_listed";
    exit();
}

    // Insert job
    $stmt = $pdo->prepare("
    INSERT INTO jobs (request_id, garage_id, job_status, created_at)
    VALUES (?, ?, 'pending', NOW())
");
$stmt->execute([$request_id, $garage_id]);
echo "success";

    if ($success) {
        echo "success";
    } else {
        echo "error:insert_failed";
    }

} catch (PDOException $e) {
    echo "error:db_exception " . $e->getMessage();
}