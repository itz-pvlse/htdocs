<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    exit('unauthorized');
}

$mechanic_id = $_SESSION['user_id'];
$job_id = $_POST['job_id'] ?? null;
$comment = trim($_POST['comment'] ?? '');

if (!$job_id) {
    exit('missing_job_id');
}

try {
    // Verify mechanic owns the job
    $stmt = $pdo->prepare("SELECT mechanic_id FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job || $job['mechanic_id'] != $mechanic_id) {
        exit('unauthorized');
    }

    // Update the comment
    $update = $pdo->prepare("UPDATE jobs SET completion_comment = ? WHERE id = ?");
    $update->execute([$comment, $job_id]);

    echo 'success';
} catch (Exception $e) {
    echo 'error: ' . $e->getMessage();
}
?>