<?php
session_start();
require_once '../config/db.php';

$mechanic_id = $_SESSION['user_id'];
$job_id = $_POST['job_id'];

// Only assigned mechanic can complete
$stmt = $pdo->prepare("UPDATE jobs 
                       SET job_status = 'completed', completed_at = NOW() 
                       WHERE id = ? AND mechanic_id = ? AND job_status = 'in_progress'");
$stmt->execute([$job_id, $mechanic_id]);

echo $stmt->rowCount() > 0 ? "Job completed!" : "Unable to complete job.";