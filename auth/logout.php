<?php
session_start();
require_once '../config/db.php'; 

// 1. Check if user is actually logged in before doing anything
if (isset($_SESSION['user_id']) && !empty(session_id())) {
    try {
        // ✅ UPDATE database BEFORE destroying session
        // This clears the session link but keeps the device token for auto-login later
        $stmt = $pdo->prepare("UPDATE user_sessions SET session_id = NULL WHERE session_id = ?");
        $stmt->execute([session_id()]);
    } catch (PDOException $e) {
        // Log error instead of crashing with 500
        error_log("Logout DB Error: " . $e->getMessage());
    }
}

// 2. Clear and Destroy the session safely
$_SESSION = array(); // Wipe all data
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 3. Determine where to go (Default to index)
$target = '../index.php';
if (isset($_GET['from']) && !empty($_GET['from'])) {
    // Sanitize the 'from' target to prevent open redirect vulnerabilities
    $target = htmlspecialchars($_GET['from']);
}

header("Location: " . $target);
exit();
