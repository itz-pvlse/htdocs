<?php
session_start();
require_once('../config/db.php');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

// Get POST data safely
$dealer_id  = (int)($_POST['dealer_id'] ?? 0);
$listing_id = (int)($_POST['listing_id'] ?? 0);
$name       = trim($_POST['name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$message    = trim($_POST['message'] ?? '');

// Basic validation
if ($dealer_id <= 0 || empty($name) || empty($email)) {
    die("Please fill in all required fields.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email address.");
}

try {
    // Insert lead into database
    $stmt = $pdo->prepare("
        INSERT INTO leads (dealer_id, user_id, listing_id, name, email, phone, message, status, created_at)
        VALUES (:dealer_id, :user_id, :listing_id, :name, :email, :phone, :message, 'new', NOW())
    ");

    $stmt->execute([
        ':dealer_id'  => $dealer_id,
        ':user_id'    => $_SESSION['user_id'] ?? null, // if logged in
        ':listing_id' => $listing_id ?: null,
        ':name'       => $name,
        ':email'      => $email,
        ':phone'      => $phone ?: null,
        ':message'    => $message ?: null,
    ]);

    // Optional: redirect back with success message
    header("Location: dealer-profile.php?id={$dealer_id}&lead=success");
    exit;

} catch (PDOException $e) {
    // Handle errors
    die("Database error: " . $e->getMessage());
}