<?php
require_once '../auth/auth_check.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';
include '../includes/header.php';

// Only garages can access this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: logingarage.php");
    exit();
}

$garage_id = $_SESSION['user_id'];

// Validate required inputs
if (isset($_POST['vehicle_id'], $_POST['service_type'], $_POST['service_date'], $_POST['cost'])) {
    
    $vehicle_id = $_POST['vehicle_id'];
    $service_type = $_POST['service_type'];
    $service_description = $_POST['service_description'] ?? '';
    $service_date = $_POST['service_date'];
    $next_service_date = $_POST['next_service_date'] ?? null;
    $cost = $_POST['cost'];
    $payment_status = $_POST['payment_status'] ?? 'pending';

    // Optional: handle multiple images
    $service_images = null;
    if (!empty($_FILES['service_images']['name'][0])) {
        $uploaded = [];
        $upload_dir = '../uploads/service_images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        foreach ($_FILES['service_images']['tmp_name'] as $i => $tmp_name) {
            $filename = uniqid() . '_' . basename($_FILES['service_images']['name'][$i]);
            $target = $upload_dir . $filename;
            if (move_uploaded_file($tmp_name, $target)) {
                $uploaded[] = 'uploads/service_images/' . $filename;
            }
        }
        if ($uploaded) $service_images = json_encode($uploaded);
    }

    // Fetch user_id of vehicle owner
    $user_stmt = $pdo->prepare("SELECT user_id FROM vehicles WHERE id = ?");
    $user_stmt->execute([$vehicle_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Vehicle not found.");
    }

    $user_id = $user['user_id'];

    // Insert into service_logs
    $stmt = $pdo->prepare("
        INSERT INTO service_logs 
        (user_id, vehicle_id, garage_id, service_type, service_description, service_date, next_service_date, cost, payment_status, service_images, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $user_id,
        $vehicle_id,
        $garage_id,
        $service_type,
        $service_description,
        $service_date,
        $next_service_date,
        $cost,
        $payment_status,
        $service_images
    ]);

    echo "<script>alert('Service logged successfully!'); window.location.href='../garage.php?status=success';</script>";
    exit();

} else {
    echo "<script>alert('All required fields must be filled!'); window.history.back();</script>";
    exit();
}