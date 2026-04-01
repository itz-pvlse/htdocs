<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $current_sid = session_id();

    // 1. Delete all sessions for this user UNLESS it is the current one
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
    $stmt->execute([$user_id, $current_sid]);

    // 2. NEW: Reset the security alert flag in the users table
    // This turns off the yellow warning box on the security page
    $stmtAlert = $pdo->prepare("UPDATE users SET security_alert = 0 WHERE id = ?");
    $stmtAlert->execute([$user_id]);

    $_SESSION['success'] = "All other devices have been signed out and account is secured.";
}

header("Location: ../security.php");
exit;