<?php
session_start();
// Adjust this path to match your actual database connection file
require_once '../config/db.php'; 

header('Content-Type: application/json');

// 1. Security Check: Only logged-in users (dealers) can share
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please login.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
    $caption = isset($_POST['caption']) ? trim($_POST['caption']) : '';

    // 2. Validation
    if ($listing_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Listing ID.']);
        exit;
    }

    if (empty($caption)) {
        echo json_encode(['status' => 'error', 'message' => 'A caption is required to share to the feed.']);
        exit;
    }

    try {
        /**
         * 3. Database Insert
         * We are inserting into your 'posts' table.
         * post_type 'listing_share' tells your feed to render this as a car card.
         */
        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, content, post_type, listing_id, created_at) 
            VALUES (:user_id, :content, 'listing_share', :listing_id, NOW())
        ");

        $params = [
            ':user_id'    => $user_id,
            ':content'    => $caption,
            ':listing_id' => $listing_id
        ];

        if ($stmt->execute($params)) {
            echo json_encode(['status' => 'success', 'message' => 'Listing shared to community feed!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save the post.']);
        }

    } catch (PDOException $e) {
        // Log the error for debugging
        error_log("Share Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
