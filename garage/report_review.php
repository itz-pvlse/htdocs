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
    $garage_id = $_SESSION['user_id'];

    if ($review_id) {
        // ✅ Check if already reported to prevent duplicates
        $check = $pdo->prepare("SELECT id FROM review_reports WHERE review_id = ? AND garage_id = ?");
        $check->execute([$review_id, $garage_id]);

        if ($check->rowCount() > 0) {
            $_SESSION['success'] = "You already reported this review.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO review_reports (review_id, garage_id, report_date)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$review_id, $garage_id]);
            $_SESSION['success'] = "Review reported successfully!";
        }
    } else {
        $_SESSION['success'] = "Invalid review selected.";
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>