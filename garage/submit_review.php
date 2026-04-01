<?php
session_start();
require_once '../config/db.php'; // adjust path if needed

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $garage_id = $_POST['garage_id'] ?? null;
    $user_id   = $_SESSION['user_id'] ?? null;
    $rating    = $_POST['rating'] ?? null;
    $review    = trim($_POST['review_text'] ?? '');

    if ($garage_id && $user_id && $rating && $review) {
        $stmt = $pdo->prepare("
            INSERT INTO garage_reviews (garage_id, user_id, rating, review_text, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$garage_id, $user_id, $rating, $review]);
        header("Location: ../garageprofile.php?id=$garage_id&review=success");
        exit;
    } else {
        header("Location: ../garageprofile.php?id=$garage_id&review=error");
        exit;
    }
}
?>