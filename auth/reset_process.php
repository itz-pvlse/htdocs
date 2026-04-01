<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_password.php?token=$token");
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token=?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user || strtotime($user['token_expiry']) < time()) {
        $_SESSION['error'] = "Invalid or expired token.";
        header("Location: request_reset.php");
        exit();
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password=?, reset_token=NULL, token_expiry=NULL WHERE reset_token=?");
    $stmt->execute([$hashed, $token]);

    $_SESSION['success'] = "Password reset successfully. You can now login.";
    header("Location: ../login.php");
    exit();
} else {
    header("Location: request_reset.php");
    exit();
}
?>
