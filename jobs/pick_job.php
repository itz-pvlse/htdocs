<?php
session_start();
require_once '../config/db.php';

$mechanic_id = $_SESSION['user_id'];
$job_id = $_POST['job_id'];

// Pick job only if it's pending or released
$stmt = $pdo->prepare("UPDATE jobs 
                       SET mechanic_id = ?, job_status = 'in_progress', assigned_at = NOW() 
                       WHERE id = ? AND job_status IN ('pending','released')");
$stmt->execute([$mechanic_id, $job_id]);

echo $stmt->rowCount() > 0 ? "Job picked successfully!" : "Job already picked or unavailable.";