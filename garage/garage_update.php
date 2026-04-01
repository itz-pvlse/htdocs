<?php
session_start();
include('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $garage_id = $_POST['garage_id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? $_POST['latitude'] : '0.00';
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? $_POST['longitude'] : '0.00';

    // Handle logo upload if provided
    $logo = null;
    if (!empty($_FILES['logo']['name'])) {
        $targetDir = "../uploads/logo/";

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true); // Create directory if it doesn't exist
        }

        $logo = time() . '_' . basename($_FILES['logo']['name']);
        $targetFilePath = $targetDir . $logo;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFilePath)) {
            // File uploaded successfully
        } else {
            echo "Logo upload failed!";
            exit();
        }
    }

    // Build the SQL query
    if ($logo) {
        $sql = "UPDATE garages SET name = ?, phone = ?, location = ?, description = ?, latitude = ?, longitude = ?, logo = ? WHERE id = ?";
        $params = [$name, $phone, $location, $description, $latitude, $longitude, $logo, $garage_id];
    } else {
        $sql = "UPDATE garages SET name = ?, phone = ?, location = ?, description = ?, latitude = ?, longitude = ? WHERE id = ?";
        $params = [$name, $phone, $location, $description, $latitude, $longitude, $garage_id];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: ../garage.php"); // back to profile
    exit();
}
?>