<?php
session_start();
require_once '../config/db.php';

// Fetch all released jobs
$stmt = $pdo->prepare("SELECT j.id AS job_id, sr.user_id, sr.vehicle_id, sr.service_details
                       FROM jobs j
                       JOIN service_requests sr ON sr.id = j.request_id
                       WHERE j.job_status = 'released'");
$stmt->execute();
$releasedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display with pick button
foreach ($releasedJobs as $job) {
    echo "Job ID: {$job['job_id']} | Vehicle ID: {$job['vehicle_id']} | Service: {$job['service_details']} <form method='POST' action='pick_job.php'>
            <input type='hidden' name='job_id' value='{$job['job_id']}'>
            <button type='submit'>Pick Job</button>
          </form><br>";
}