<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sid'])) {
    $sid_to_kill = $_POST['sid'];
    $user_id = $_SESSION['user_id'];

    // 1. Delete the specific session from the DB. 
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$sid_to_kill, $user_id]);

    // 2. Check if any other "foreign" IPs still exist.
    // If only the current IP remains, we can safely turn off the alert.
    $current_ip = $_SERVER['REMOTE_ADDR'];
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND ip_address != ?");
    $checkStmt->execute([$user_id, $current_ip]);
    $other_ips_count = $checkStmt->fetchColumn();

    if ($other_ips_count == 0) {
        $updateAlert = $pdo->prepare("UPDATE users SET security_alert = 0 WHERE id = ?");
        $updateAlert->execute([$user_id]);
    }

    $_SESSION['success'] = "Device access revoked successfully.";
}

header("Location: ../security.php");
exit;