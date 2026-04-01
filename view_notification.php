<?php
session_start();
require_once 'config/db.php'; // Ensure this matches your file name

$notif_id = $_GET['id'] ?? null;
$user_id  = $_SESSION['user_id'] ?? null;

if (!$notif_id || !$user_id) {
    header("Location: index.php"); // Or your home page
    exit;
}

try {
    // 1. Fetch link and verify user owns this notification
    $stmt = $pdo->prepare("SELECT link FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([(int)$notif_id, (int)$user_id]);
    $notif = $stmt->fetch();

    if ($notif) {
        // 2. Mark as read
        $update = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $update->execute([(int)$notif_id]);

        // 3. Redirect to destination
        $url = !empty($notif['link']) ? $notif['link'] : 'dashboard.php';
        header("Location: " . $url);
        exit;
    }
} catch (PDOException $e) {
    // Silence error and redirect home
}

header("Location: index.php");
exit;
