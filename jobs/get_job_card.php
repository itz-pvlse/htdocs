<?php
require_once '../config/db.php';
session_start();

$job_id = $_GET['job_id'] ?? 0;
$type = $_GET['type'] ?? 'available'; // available or picked

if (!$job_id) exit;

$stmt = $pdo->prepare("
  SELECT j.id AS job_id, v.model, v.plate_no, sr.requested_service, j.job_status, j.assigned_at
  FROM jobs j
  JOIN service_requests sr ON sr.id = j.request_id
  JOIN vehicles v ON sr.vehicle_id = v.id
  WHERE j.id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) exit;

// AVAILABLE JOB CARD
if ($type === 'available') {
  echo "
  <div class='col-md-4' id='jobCard{$job['job_id']}'>
    <div class='card shadow-sm border-0 rounded-3'>
      <div class='card-body'>
        <h5 class='card-title text-primary fw-bold'>Job #{$job['job_id']}</h5>
        <p><strong>Vehicle:</strong> {$job['model']}</p>
        <p><strong>Plate:</strong> {$job['plate_no']}</p>
        <p><strong>Service:</strong> {$job['requested_service']}</p>
        <span class='badge bg-secondary mb-2'>Released</span>
        <div class='d-flex justify-content-end'>
          <button class='btn btn-success btn-sm pickJobBtn' data-job='{$job['job_id']}'>
            <i class='bi bi-wrench'></i> Pick Job
          </button>
        </div>
      </div>
    </div>
  </div>";
} 
// PICKED JOB CARD
else {
  echo "
  <div class='col-md-6' id='pickedJob{$job['job_id']}'>
    <div class='card border-0 shadow-sm rounded-4 overflow-hidden position-relative'>
      <div style='height: 5px; background-color: #2563eb;'></div>
      <div class='card-body'>
        <div class='d-flex justify-content-between align-items-center mb-2'>
          <h5 class='card-title fw-bold text-dark mb-0'>#{$job['job_id']} - {$job['model']}</h5>
          <span class='badge bg-info text-dark'>In Progress</span>
        </div>
        <p><strong>Plate:</strong> {$job['plate_no']}</p>
        <p><strong>Service:</strong> {$job['requested_service']}</p>
        <p class='text-muted' style='font-size: 0.9em;'>Assigned: {$job['assigned_at']}</p>
        <div class='d-flex justify-content-end gap-2'>
          <button class='btn btn-outline-primary btn-sm completeJobBtn' data-job='{$job['job_id']}'>✅ Mark Complete</button>
          <button class='btn btn-outline-danger btn-sm releaseJobBtn' data-job='{$job['job_id']}'>🔄 Release Job</button>
        </div>
      </div>
    </div>
  </div>";
}