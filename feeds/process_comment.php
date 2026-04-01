<?php
// feeds/process_comment.php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $postId = $_POST['post_id'];
    $commentText = trim($_POST['comment_text']);

    if (!empty($commentText)) {
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, comment_text, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$postId, $userId, $commentText]);
    }
    
    header("Location: post_view.php?id=" . $postId);
    exit;
}
