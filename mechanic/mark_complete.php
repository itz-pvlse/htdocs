<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    header("Location: ../login.php");
    exit;
}

$mechanic_id = $_SESSION['user_id'];
$job_id = $_GET['job_id'] ?? null;
$source = $_GET['source'] ?? 'jobs'; // 'jobs' or 'released'

if (!$job_id || !is_numeric($job_id)) {
    $_SESSION['error'] = "Invalid job ID.";
    header("Location: mechanic_dashboard.php");
    exit;
}

try {
    // Fetch job based on source
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND mechanic_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$job_id, $mechanic_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        $_SESSION['error'] = "Job not found or already completed.";
        header("Location: mechanic_dashboard.php");
        exit;
    }

    // Mark job as completed
    $pdo->prepare("UPDATE jobs SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$job_id]);

    // Log completion in job history
    $pdo->prepare("INSERT INTO job_history (job_id, mechanic_id, action, timestamp) VALUES (?, ?, 'completed', NOW())")
        ->execute([$job_id, $mechanic_id]);

    // ============================
    // AWARD LOYALTY POINTS
    // ============================
    $user_id = $job['user_id'];
    $points_awarded = 10; // Default points per completed job

    // Check if user is already a loyalty member
    $stmtCheck = $pdo->prepare("SELECT id, points FROM loyalty_members WHERE user_id = ?");
    $stmtCheck->execute([$user_id]);
    $member = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        // Update existing points
        $pdo->prepare("UPDATE loyalty_members SET points = points + ?, joined_at = NOW() WHERE user_id = ?")
            ->execute([$points_awarded, $user_id]);
    } else {
        // Insert new loyalty member
        $pdo->prepare("INSERT INTO loyalty_members (user_id, points, joined_at) VALUES (?, ?, NOW())")
            ->execute([$user_id, $points_awarded]);
    }

    // Optional: log points transaction
    $pdo->prepare("INSERT INTO loyalty_points_log (user_id, job_id, points_awarded, created_at) VALUES (?, ?, ?, NOW())")
        ->execute([$user_id, $job_id, $points_awarded]);

    $_SESSION['success'] = "Job marked as completed! 🎉 Loyalty points awarded to the customer.";
    header("Location: mechanic_dashboard.php");
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    header("Location: mechanic_dashboard.php");
    exit;
}