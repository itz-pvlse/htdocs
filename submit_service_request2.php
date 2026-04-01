<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php';
// require_once 'functions/notifications.php'; // Ensure your notification function is included

// ==========================
// Ensure user is logged in
// ==========================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in first to book an appointment.";
    header("Location: login.php");
    exit;
}

// ==========================
// Collect POST data
// ==========================
$user_id = $_SESSION['user_id'];
$garage_id = $_POST['garage_id'] ?? null;
$vehicle_id = $_POST['vehicle_id'] ?? null;
$requested_service = trim($_POST['service'] ?? '');
$request_date = $_POST['request_date'] ?? '';

// NEW: Collect mileage (Handle empty/not known as NULL)
$mileage = !empty($_POST['mileage']) ? intval($_POST['mileage']) : null;

// ==========================
// Validate required fields
// ==========================
if (empty($garage_id) || empty($vehicle_id) || empty($requested_service) || empty($request_date)) {
    $_SESSION['error'] = "Please fill in all required fields.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// ==========================
// Validate date format
// ==========================
$timestamp = strtotime($request_date);
if (!$timestamp) {
    $_SESSION['error'] = "Invalid date format.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
$formatted_date = date('Y-m-d H:i:s', $timestamp);

try {
    // 1. Check if garage exists
    $stmt = $pdo->prepare("SELECT id FROM garages WHERE id = ?");
    $stmt->execute([$garage_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Selected garage does not exist.");
    }

    // 2. Check if vehicle belongs to user
    $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? AND user_id = ?");
    $stmt->execute([$vehicle_id, $user_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Vehicle error. Please select a vehicle from your profile.");
    }

    // ==========================
    // Insert request with Mileage
    // ==========================
    $stmt = $pdo->prepare("
        INSERT INTO service_requests (
            user_id, 
            garage_id, 
            vehicle_id, 
            requested_service, 
            request_date, 
            mileage, 
            status, 
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    $executed = $stmt->execute([
        $user_id, 
        $garage_id, 
        $vehicle_id, 
        $requested_service, 
        $formatted_date, 
        $mileage // This will be passed as NULL if the user didn't know it
    ]);

    if ($executed) {
        // After successful booking insert notification:
        if (function_exists('addNotification')) {
            addNotification(
                $pdo, 
                $user_id, 
                'booking', 
                'Request Sent', 
                "Your request for $requested_service has been sent.", 
                'my_bookings.php'
            );
        }
        $_SESSION['success'] = "✅ Service request submitted successfully!";
    } else {
        throw new Exception("Database could not save the request.");
    }

} catch (Exception $e) {
    $_SESSION['error'] = "❌ " . $e->getMessage();
}

// Redirect back
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;