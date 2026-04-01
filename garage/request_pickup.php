<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo "⚠️ Please log in to request pickup.";
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    INSERT INTO pickup_requests (user_id, requested_at, status)
    VALUES (?, NOW(), 'pending')
");
$stmt->execute([$user_id]);

echo "✅ Pickup request sent successfully!";