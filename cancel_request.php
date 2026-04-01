<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $request_id = $_POST['request_id'];
    $user_id = $_SESSION['user_id'];

    // Ensure the request belongs to the user AND is still pending
    $stmt = $pdo->prepare("UPDATE service_requests SET status = 'Cancelled' WHERE id = ? AND user_id = ? AND status = 'Pending'");
    $stmt->execute([$request_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Cannot cancel this request. It may already be approved.']);
    }
    exit;
}
