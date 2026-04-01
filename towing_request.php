<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Please log in to request towing.');window.location='login.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $garage_id = $_POST['garage_id'] ?? null;
    $vehicle_id = $_POST['vehicle_id'] ?? null;
    $pickup = trim($_POST['pickup_location'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? ''); // New field
    $notes = trim($_POST['notes'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    // Validate required fields
    if (empty($vehicle_id) || empty($pickup) || empty($garage_id) || empty($contact_phone)) {
        echo "<script>alert('Please fill in all required fields.');window.history.back();</script>";
        exit;
    }

    // Optional: validate latitude and longitude are numeric
    if ($latitude !== null && !is_numeric($latitude)) $latitude = null;
    if ($longitude !== null && !is_numeric($longitude)) $longitude = null;

    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO towing_requests 
        (user_id, garage_id, vehicle_id, pickup_location, destination, contact_phone, notes, latitude, longitude, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $garage_id, $vehicle_id, $pickup, $destination, $contact_phone, $notes, $latitude, $longitude]);

    echo "<script>alert('Your towing request has been sent successfully!');window.location='garageprofile.php?id=$garage_id';</script>";
}
?>