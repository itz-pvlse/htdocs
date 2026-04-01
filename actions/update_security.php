<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // 1. Validate Password Match
    if ($new_pass !== $confirm_pass) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: ../security.php");
        exit;
    }

    // 2. Minimum Length Check
    if (strlen($new_pass) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters.";
        header("Location: ../security.php");
        exit;
    }

    try {
        // 3. Fetch current hashed password from DB
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        // 4. Verify Current Password
        if ($user && password_verify($current_pass, $user['password'])) {
            
            // 5. Hash and Update
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hashed_pass, $user_id]);

            $_SESSION['success'] = "Security updated. Use new credentials next time.";
        } else {
            $_SESSION['error'] = "The current password you entered is incorrect.";
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "System error. Please try again later.";
    }

    header("Location: ../security.php");
    exit;
} else {
    header("Location: ../dashboard.php");
    exit;
}