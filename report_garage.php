<?php
session_start();
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Please log in first.']);
  exit;
}

$user_id = $_SESSION['user_id'];
$garage_id = $_POST['garage_id'];
$reason = trim($_POST['reason']);

$stmt = $pdo->prepare("INSERT INTO garage_reports (user_id, garage_id, reason) VALUES (?, ?, ?)");
$stmt->execute([$user_id, $garage_id, $reason]);

echo json_encode(['success' => true, 'message' => 'Report submitted successfully.']);