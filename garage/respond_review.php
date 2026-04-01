<?php
session_start();
require_once '../config/db.php';

// Ensure user is a garage
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = $_POST['review_id'] ?? null;
    $response_text = trim($_POST['response_text'] ?? '');
    $garage_id = $_SESSION['user_id'];

    if ($review_id && $response_text) {
        $stmt = $pdo->prepare("
            UPDATE garage_reviews
            SET response = ?, response_date = NOW()
            WHERE id = ? AND garage_id = ?
        ");
        $stmt->execute([$response_text, $review_id, $garage_id]);

        $_SESSION['success'] = "Response added successfully!";
    } else {
        $_SESSION['success'] = "Failed to respond — please try again.";
    }

    header("Location: ../garage.php");
    exit;
}
?>