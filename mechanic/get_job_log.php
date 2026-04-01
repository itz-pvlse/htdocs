<?php
session_start();
require_once '../config/db.php';

$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($job_id > 0) {
    // We join with the users table to get the 'name' of the person who added the item
    $stmt = $pdo->prepare("
        SELECT 
            ji.id, 
            ji.description, 
            ji.quantity, 
            ji.unit_price, 
            ji.type, 
            ji.added_by_id,
            u.name as added_by_name 
        FROM job_items ji
        LEFT JOIN users u ON ji.added_by_id = u.id
        WHERE ji.job_id = ? 
        ORDER BY ji.created_at DESC
    ");
    $stmt->execute([$job_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($items);
} else {
    echo json_encode([]);
}
