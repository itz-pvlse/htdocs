<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$dealerId = $_SESSION['user_id'];
$leadId = $_POST['lead_id'] ?? 0;

if (!$leadId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Lead ID']);
    exit;
}

// Delete lead
$stmt = $pdo->prepare("DELETE FROM leads WHERE id = ? AND dealer_id = ?");
$stmt->execute([$leadId, $dealerId]);

echo json_encode(['status' => 'success']);