<?php
session_start();
require_once '../config/db.php';
require_once 'db_functions.php'; // Ensure toggleFollow is defined here

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$followerId = $_SESSION['user_id'];
$followingId = $data['user_id'] ?? null; // Match the JS key 'user_id'

if ($followingId && $followerId != $followingId) {
    // 1. Perform the toggle logic
    $result = toggleFollow($pdo, $followerId, $followingId);
    
    // 2. Fetch the updated follower count for the profile
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
    $stmt->execute([$followingId]);
    $newCount = $stmt->fetchColumn();

    echo json_encode([
        'success' => true, 
        'action' => $result['status'], // 'followed' or 'unfollowed'
        'new_count' => $newCount
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
