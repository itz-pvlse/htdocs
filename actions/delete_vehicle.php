<?php
// actions/delete_vehicle.php
session_start();
require_once '../config/db.php';

// Security check: Ensure user is logged in and ID is provided
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$vehicle_id = $_GET['id'];

// 1. Fetch the image path before deleting the record
$stmt = $pdo->prepare("SELECT image_path FROM vehicles WHERE id = ? AND user_id = ?");
$stmt->execute([$vehicle_id, $user_id]);
$car = $stmt->fetch();

if ($car) {
    // 2. Delete the physical image file from the server
    if (!empty($car['image_path'])) {
        $file_to_delete = "../" . $car['image_path'];
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
    }

    // 3. Delete the database record
    // Note: If your DB has "ON DELETE CASCADE" on service_requests.vehicle_id, 
    // all logs will be wiped automatically.
    $delete = $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND user_id = ?");
    $delete->execute([$vehicle_id, $user_id]);

    header("Location: ../dashboard.php?msg=deleted");
} else {
    // Unauthorized or vehicle doesn't exist
    header("Location: ../dashboard.php?msg=error");
}
exit;