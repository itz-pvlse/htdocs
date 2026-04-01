<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

// 1. Is the user even logged in?
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Is this specific device still allowed?
// This checks if another device "revoked" this session.
$stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE session_id = ? AND user_id = ?");
$stmt->execute([session_id(), $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    // Kicked out!
    session_unset();
    session_destroy();
    header("Location: login.php?error=terminated");
    exit;
}

// 3. Update "Last Seen" so the user knows when they last used this device
$update = $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?");
$update->execute([session_id()]);