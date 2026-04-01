<?php
// feeds/process_comment_ajax.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// --- UNIVERSAL DATA PICKUP ---
// 1. Try to get standard POST data (from your new Modal FormData)
$postId      = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
$commentText = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';
$parentId    = (!empty($_POST['parent_id']) && $_POST['parent_id'] !== "") ? (int)$_POST['parent_id'] : null;

// 2. If POST is empty, try to get JSON data (from your older Index logic)
if (!$postId) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if ($data) {
        $postId      = $data['post_id'] ?? null;
        $commentText = trim($data['comment_text'] ?? '');
        // FIXED: Removed the extra underscore from $_data
        $parentId    = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
    }
}

$userId = $_SESSION['user_id'];

if ($postId && !empty($commentText)) {
    try {
        // Updated to include parent_id column
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, parent_id, comment_text, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$postId, $userId, $parentId, $commentText]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Log error message internally if needed, but keep response clean
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data: Content missing']);
}
