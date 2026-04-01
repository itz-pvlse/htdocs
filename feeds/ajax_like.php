<?php
// feeds/ajax_like.php
session_start();
require_once '../config/db.php'; 
require_once 'db_functions.php'; 

header('Content-Type: application/json');

// 1. Security Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

// 2. Data Retrieval
$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$contentId = $data['content_id'] ?? null;
$contentType = $data['type'] ?? 'post'; // Default to 'post' but supports 'comment'

// 3. Validation and Execution
if ($contentId && in_array($contentType, ['post', 'comment'])) {
    try {
        // Use the reusable toggle function from db_functions.php
        $result = toggleLike($pdo, $userId, $contentId, $contentType);
        
        // Fetch fresh count to ensure UI is perfectly synced
        $newCount = getLikeCount($pdo, $contentId, $contentType);
        
        echo json_encode([
            'success' => true, 
            'action' => $result['action'], 
            'new_count' => $newCount
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Server error while processing like.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid content ID or type.']);
}
