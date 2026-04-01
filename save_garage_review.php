<?php
session_start();
require_once 'config/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=login_required");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $dealer_id = filter_input(INPUT_POST, 'dealer_id', FILTER_SANITIZE_NUMBER_INT);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_SANITIZE_NUMBER_INT);
    $review_text = trim($_POST['comment']); // 'comment' is the name attribute in our HTML form

    // Basic Validation
    if (!$dealer_id || empty($review_text)) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "&status=missing_data");
        exit();
    }

    try {
        // Updated table name: dealer_reviews
       // Inside save_review.php
$stmt = $pdo->prepare("
    INSERT INTO garage_reviews (garage_id, user_id, rating, review_text, created_at) 
    VALUES (?, ?, ?, ?, NOW())
");
$success = $stmt->execute([$garage_id, $user_id, $rating, $review_text]);

        if ($success) {
            // Redirect back with a success flag
            header("Location: garageprofile.php?id=$dealer_id&status=review_success#reviews");
            exit();
        }
    } catch (PDOException $e) {
        // Log error and redirect with error status
        error_log("Review Error: " . $e->getMessage());
        header("Location: " . $_SERVER['HTTP_REFERER'] . "&status=db_error");
        exit();
    }
}