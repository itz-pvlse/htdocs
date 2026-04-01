<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    exit('unauthorized');
}

$mechanic_id = $_SESSION['user_id'];
$job_id = $_POST['job_id'] ?? null;
$action = $_POST['action'] ?? '';
$comment = trim($_POST['comment'] ?? '');

if (!$job_id || !$action) {
    exit('invalid');
}

try {
    // Check job ownership
    $stmt = $pdo->prepare("SELECT mechanic_id FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job || $job['mechanic_id'] != $mechanic_id) {
        exit('unauthorized');
    }

    if ($action === 'release') {
        $update = $pdo->prepare("
            UPDATE jobs 
            SET mechanic_id = NULL, job_status = 'released' 
            WHERE id = ?
        ");
        $update->execute([$job_id]);
    } elseif ($action === 'complete') {
        $update = $pdo->prepare("
            UPDATE jobs 
            SET job_status = 'completed', completed_at = NOW(), completion_comment = ? 
            WHERE id = ?
        ");
        $update->execute([$comment, $job_id]);
    } else {
        exit('invalid_action');
    }

    echo 'success';
} catch (Exception $e) {
    echo 'error: ' . $e->getMessage();
}
?>