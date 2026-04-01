<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['post_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = (int)$_POST['post_id'];

try {
    // 1. Verify ownership before deleting
    $stmt = $pdo->prepare("SELECT media_path FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $post = $stmt->fetch();

    if ($post) {
        // 2. Delete physical file if it exists
        if (!empty($post['media_path'])) {
            $filePath = "../uploads/media/" . $post['media_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // 3. Delete from DB (Foreign keys should handle likes/comments if ON DELETE CASCADE is set)
        $del = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $del->execute([$post_id, $user_id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Post not found or unauthorized']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
