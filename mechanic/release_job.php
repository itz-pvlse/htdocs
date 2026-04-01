<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    header("Location: ../login.php");
    exit;
}

$mechanic_id = $_SESSION['user_id'];
$job_id = $_GET['job_id'] ?? null;

if (!$job_id || !is_numeric($job_id)) {
    $_SESSION['error'] = "Invalid job ID.";
    header("Location: mechanic_dashboard.php");
    exit;
}

try {
    // Get job details
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND mechanic_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$job_id, $mechanic_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $_SESSION['error'] = "Job not found or cannot be released.";
        header("Location: mechanic_dashboard.php");
        exit;
    }

    // Move to released_jobs
    $insert = $pdo->prepare("
        INSERT INTO released_jobs (vehicle_id, service_type, status, service_request_id, previous_mechanic, created_at, updated_at)
        VALUES (?, ?, 'pending', ?, ?, NOW(), NOW())
    ");
    $insert->execute([
        $job['vehicle_id'],
        $job['service_type'],
        $job['service_request_id'],
        $mechanic_id
    ]);

    // Log release
    $released_id = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO job_history (job_id, mechanic_id, action, timestamp) VALUES (?, ?, 'released', NOW())")
        ->execute([$released_id, $mechanic_id]);

    // Remove from jobs table
    $pdo->prepare("DELETE FROM jobs WHERE id = ?")->execute([$job_id]);

    $_SESSION['success'] = "Job released and now available for others.";
    header("Location: mechanic_dashboard.php");
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    header("Location: mechanic_dashboard.php");
    exit;
}