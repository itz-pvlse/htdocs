<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$bookingId = $_GET['id'] ?? null;

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'No booking ID provided.']);
    exit;
}

$stmt = $pdo->prepare("SELECT status FROM appointments WHERE id = ?");
$stmt->execute([$bookingId]);
$status = $stmt->fetchColumn();

if ($status) {
    echo json_encode(['success' => true, 'status' => $status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
}