<?php
session_start();
require_once '../config/db.php';

// Ensure mechanic is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    header("Location: mechanic_dashboard.php?error=Unauthorized+access!");
    exit;
}

$mechanic_id = $_SESSION['user_id'];
$job_id = $_POST['job_id'] ?? 0;

if (!$job_id) {
    header("Location: mechanic_dashboard.php?error=Invalid+job+ID!");
    exit;
}

// Check if job exists and is pickable
$stmt = $pdo->prepare("SELECT job_status FROM jobs WHERE id = ? AND job_status IN ('pending','released')");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header("Location: mechanic_dashboard.php?error=Job+cannot+be+picked+or+does+not+exist!");
    exit;
}

// Assign job to mechanic and update status
$stmt = $pdo->prepare("UPDATE jobs SET mechanic_id = ?, job_status = 'in_progress', assigned_at = NOW() WHERE id = ?");
$success = $stmt->execute([$mechanic_id, $job_id]);

if ($success) {
    header("Location: mechanic_dashboard.php?success=Job+picked+successfully!");
} else {
    header("Location: mechanic_dashboard.php?error=Failed+to+pick+job!");
}
exit;