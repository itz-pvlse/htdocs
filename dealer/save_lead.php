<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$dealerId = intval($_POST['dealer_id']);
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

if ($userId) {
    // Registered user — pull details from DB
    $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $name = $user['name'];
    $email = $user['email'];
    $phone = $user['phone'];
} else {
    // Guest
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
}

$message = trim($_POST['message']);

// Insert into leads table
$stmt = $pdo->prepare("
    INSERT INTO leads (dealer_id, user_id, name, email, phone, message, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->execute([$dealerId, $userId, $name, $email, $phone, $message]);

header("Location: dealer_profile.php?id=$dealerId&success=1");
exit;