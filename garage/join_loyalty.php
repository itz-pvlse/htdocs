<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo "⚠️ Please log in to join the loyalty program.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if already joined
$stmt = $pdo->prepare("SELECT id FROM loyalty_members WHERE user_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    echo "⭐ You’re already a loyalty member!";
    exit;
}

// Join new
$stmt = $pdo->prepare("INSERT INTO loyalty_members (user_id, points, joined_at) VALUES (?, 0, NOW())");
$stmt->execute([$user_id]);

echo "🎉 Welcome to the Loyalty Program! Start earning points now.";