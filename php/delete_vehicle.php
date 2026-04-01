<?php
require_once '../auth/auth_check.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

$userId = $_SESSION['user_id'];
$user_id= $_SESSION['user_id'];


if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $vehicleId = $_GET['id'];
    $userId = $_SESSION['user_id']; // Secure: prevent users from deleting others' cars

    // Use prepared statement to avoid SQL injection
    $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND user_id = ?");
    $stmt->execute([$vehicleId, $userId]);

    // Redirect back with anchor if needed
    header("Location: ../dashboard.php#my-cars");
    exit();
} else {
    // Fail-safe
    header("Location: ../dashboard.php?error=invalid_request");
    exit();
}
