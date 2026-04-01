<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($job_id > 0) {
    // Fetch all mechanics assigned to this job from the bridge table
    $stmt = $pdo->prepare("
        SELECT u.name, u.avatar, ja.is_lead 
        FROM job_assignments ja 
        JOIN users u ON ja.mechanic_id = u.id 
        WHERE ja.job_id = ?
        ORDER BY ja.is_lead DESC, u.name ASC
    ");
    $stmt->execute([$job_id]);
    $team = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($team);
} else {
    echo json_encode([]);
}
