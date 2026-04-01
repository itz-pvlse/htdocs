<?php
require_once '../config/db.php';

if (isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];

    // 1. Get Notes
    $stmt = $pdo->prepare("SELECT mechanic_notes FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    // 2. Get Parts/Labor (Description and Quantity only)
    $stmt_items = $pdo->prepare("SELECT description, quantity as qty FROM job_items WHERE job_id = ?");
    $stmt_items->execute([$job_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'notes' => $job['mechanic_notes'] ?? 'No report filed.',
        'items' => $items
    ]);
}
?>
