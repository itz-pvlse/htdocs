<?php
session_start();
require_once '../config/db.php';

$mechanic_id = $_SESSION['user_id'];
$job_id = $_POST['job_id'];

// Only assigned mechanic can release
$stmt = $pdo->prepare("UPDATE jobs 
                       SET job_status = 'released', mechanic_id = NULL 
                       WHERE id = ? AND mechanic_id = ? AND job_status = 'in_progress'");
$stmt->execute([$job_id, $mechanic_id]);

echo $stmt->rowCount() > 0 ? "Job released!" : "Unable to release job.";