<?php
session_start();
require_once('../config/db.php');

/* =========================
   CHECK IF USER IS LOGGED IN
========================= */
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to submit a review.");
}

/* =========================
   VALIDATE POST DATA
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

$dealer_id = isset($_POST['dealer_id']) ? (int)$_POST['dealer_id'] : 0;
$user_id   = $_SESSION['user_id'];
$rating    = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review    = isset($_POST['review']) ? trim($_POST['review']) : '';

if ($dealer_id <= 0 || $rating < 1 || $rating > 5) {
    die("Invalid dealer or rating.");
}

/* =========================
   CHECK IF USER ALREADY REVIEWED
========================= */
$stmt = $pdo->prepare("SELECT id FROM dealer_reviews WHERE dealer_id = ? AND user_id = ?");
$stmt->execute([$dealer_id, $user_id]);
if ($stmt->rowCount() > 0) {
    die("You have already submitted a review for this dealer.");
}

/* =========================
   INSERT REVIEW
========================= */
$stmt = $pdo->prepare("INSERT INTO dealer_reviews (dealer_id, user_id, rating, review, created_at) VALUES (?, ?, ?, ?, NOW())");
$inserted = $stmt->execute([$dealer_id, $user_id, $rating, $review]);

if ($inserted) {
    /* =========================
       UPDATE DEALER AVERAGE RATING
    ========================= */
    $stmt = $pdo->prepare("
        UPDATE dealers d
        JOIN (
            SELECT dealer_id, ROUND(AVG(rating), 1) AS avg_rating
            FROM dealer_reviews
            WHERE dealer_id = ?
            GROUP BY dealer_id
        ) r ON d.user_id = r.dealer_id
        SET d.rating = r.avg_rating
    ");
    $stmt->execute([$dealer_id]);

    /* =========================
       REDIRECT BACK WITH SUCCESS
    ========================= */
    header("Location: dealer-profile.php?id=".$dealer_id."&review=success");
    exit;
} else {
    die("Failed to submit review. Please try again.");
}
?>