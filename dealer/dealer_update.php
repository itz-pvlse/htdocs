<?php
session_start();
include('config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dealer_id   = $_POST['dealer_id'];
    $name        = $_POST['name'];
    $phone       = $_POST['phone'];
    $location    = $_POST['location'];
    $description = $_POST['description'];
    $latitude    = $_POST['latitude'] ?? null;
    $longitude   = $_POST['longitude'] ?? null;

    $logo = null;
    if (!empty($_FILES['logo']['name'])) {
        $targetDir = "uploads/logo/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        $logo = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['logo']['name']));
        $targetFilePath = $targetDir . $logo;
        move_uploaded_file($_FILES['logo']['tmp_name'], $targetFilePath);
    }

    if ($logo) {
        $sql = "UPDATE dealers SET name=?, phone=?, location=?, latitude=?, longitude=?, description=?, logo=? WHERE user_id=?";
        $params = [$name, $phone, $location, $latitude, $longitude, $description, $logo, $dealer_id];
    } else {
        $sql = "UPDATE dealers SET name=?, phone=?, location=?, latitude=?, longitude=?, description=? WHERE user_id=?";
        $params = [$name, $phone, $location, $latitude, $longitude, $description, $dealer_id];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: dealerprofile.php?id=" . $dealer_id);
    exit();
}
?>