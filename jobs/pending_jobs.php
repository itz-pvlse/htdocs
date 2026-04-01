<?php
session_start();
require_once '../config/db.php';

$garage_id = $_SESSION['garage_id'];

// Fetch jobs with pending status
$stmt = $pdo->prepare("SELECT j.id AS job_id, sr.user_id, sr.vehicle_id, sr.service_details
                       FROM jobs j
                       JOIN service_requests sr ON sr.id = j.request_id
                       WHERE j.garage_id = ? AND j.job_status = 'pending'");
$stmt->execute([$garage_id]);
$pendingJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display table
foreach ($pendingJobs as $job) {
    echo "Job ID: {$job['job_id']} | Vehicle ID: {$job['vehicle_id']} | Service: {$job['service_details']}<br>";
}