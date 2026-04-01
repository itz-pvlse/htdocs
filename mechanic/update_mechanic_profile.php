<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_POST['user_id'];
$name = $_POST['name'];
$phone = $_POST['phone'];

// Handle avatar upload
$avatarPath = null;
if (!empty($_FILES['avatar']['name'])) {
    $targetDir = "../uploads/";
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
    $fileName = time() . "_" . basename($_FILES["avatar"]["name"]);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $targetFile)) {
        $avatarPath = "uploads/" . $fileName;
    }
}

// ✅ Update user info
$query = "UPDATE users SET name = ?, phone = ?" . ($avatarPath ? ", avatar = ?" : "") . " WHERE id = ?";
$stmt = $pdo->prepare($query);
$params = $avatarPath ? [$name, $phone, $avatarPath, $user_id] : [$name, $phone, $user_id];
$stmt->execute($params);

$_SESSION['success'] = "Profile updated successfully!";
header("Location: mechanic_dashboard.php");
exit;
?>