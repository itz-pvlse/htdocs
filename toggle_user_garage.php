<?php
session_start();
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$garage_id = $_POST['garage_id'] ?? null;

if (!$garage_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid garage ID.']);
    exit;
}

// Check if user already saved this garage
$stmt = $pdo->prepare("SELECT id FROM user_garages WHERE user_id = ? AND garage_id = ?");
$stmt->execute([$user_id, $garage_id]);
$exists = $stmt->fetch();

if ($exists) {
    // Remove from favorites
    $delete = $pdo->prepare("DELETE FROM user_garages WHERE user_id = ? AND garage_id = ?");
    $delete->execute([$user_id, $garage_id]);
    echo json_encode(['success' => true, 'is_saved' => false]);
} else {
    // Save new garage
    $insert = $pdo->prepare("INSERT INTO user_garages (user_id, garage_id) VALUES (?, ?)");
    $insert->execute([$user_id, $garage_id]);
    echo json_encode(['success' => true, 'is_saved' => true]);
}