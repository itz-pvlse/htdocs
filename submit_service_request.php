<?php
require_once 'auth/auth_check.php';




if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php';
include 'includes/header.php';

$userId = $_SESSION['user_id'];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $garage_id = $_POST['garage_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $requested_service = $_POST['requested_service'];
    $request_date = $_POST['request_date'];

$stmt = $pdo->prepare("INSERT INTO service_requests (garage_id, vehicle_id, requested_service, request_date, owner_id) 
                       VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$garage_id, $vehicle_id, $requested_service, $request_date, $userId]);
    echo " <script>
        alert('Service request submitted successfully!');
        window.location.href = 'dashboard.php#requestservice';
    </script>
    ";
    exit();
    // Optionally redirect: header("Location: my_requests.php");
}
?>