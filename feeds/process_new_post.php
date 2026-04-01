<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $content = trim($_POST['content']);
    
    // 1. Initial Insert of the Post to get the Post ID
    // We insert with null media first, then update the primary media later
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, media_path, media_type, created_at) VALUES (?, ?, NULL, 'none', NOW())");
    $stmt->execute([$userId, $content]);
    $postId = $pdo->lastInsertId();

    $primaryMediaPath = null;
    $primaryMediaType = 'none';

    if (isset($_FILES['post_media']) && !empty($_FILES['post_media']['name'][0])) {
        $uploadDir = '../uploads/media/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $videoExts = ['mp4', 'webm', 'mov'];

        foreach ($_FILES['post_media']['name'] as $key => $name) {
            if ($_FILES['post_media']['error'][$key] === 0) {
                
                $fileTmp = $_FILES['post_media']['tmp_name'][$key];
                $fileExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $newFileName = time() . '_' . uniqid() . '.' . $fileExt;
                $targetFile = $uploadDir . $newFileName;

                $currentType = 'none';
                if (in_array($fileExt, $imageExts)) {
                    $currentType = 'image';
                } elseif (in_array($fileExt, $videoExts)) {
                    $currentType = 'video';
                }

                if ($currentType !== 'none' && move_uploaded_file($fileTmp, $targetFile)) {
                    // Store ALL images/videos in the gallery table
                    $stmtMedia = $pdo->prepare("INSERT INTO post_media (post_id, media_path, media_type) VALUES (?, ?, ?)");
                    $stmtMedia->execute([$postId, $newFileName, $currentType]);

                    // Set the FIRST file as the primary media for the 'posts' table (for backward compatibility)
                    if ($primaryMediaPath === null) {
                        $primaryMediaPath = $newFileName;
                        $primaryMediaType = $currentType;
                    }
                }
            }
        }

        // 2. Update the main 'posts' table with the primary media info
        if ($primaryMediaPath) {
            $updateStmt = $pdo->prepare("UPDATE posts SET media_path = ?, media_type = ? WHERE id = ?");
            $updateStmt->execute([$primaryMediaPath, $primaryMediaType, $postId]);
        }
    }

    header("Location: index.php");
    exit;
}
