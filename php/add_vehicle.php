<?php
require_once '../auth/auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $make = $_POST['make'];
    $model = $_POST['model'];
    $plate_no = strtoupper(trim($_POST['plate_no']));
    $year = $_POST['year'];
    $color = $_POST['color'];
	$insurance_expiry_date = $_POST['insurance_expiry_date'];
	$last_service_date     = $_POST['last_service_date'];
	$service_interval_km   = $_POST['service_interval_km'];

    // Check if plate already exists for this user
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE plate_no = ? AND user_id = ?");
    $checkStmt->execute([$plate_no, $user_id]);
    $exists = $checkStmt->fetchColumn();

    if ($exists) {
        header("Location: ../dashboard.php?error=duplicate_plate#my_vehicles");
        exit();
    }

    // Handle image upload
    $imagePath = null;
    if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === 0) {
        $imageName = uniqid() . "_" . basename($_FILES['vehicle_image']['name']);
        $uploadDir = '../uploads/';
        $uploadPath = $uploadDir . $imageName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $uploadPath)) {
            $imagePath = 'uploads/' . $imageName;
        } else {
            header("Location: ../dashboard.php?error=image_upload_failed#my_vehicles");
            exit();
        }
    }

    // Insert vehicle
    $stmt = $pdo->prepare("INSERT INTO vehicles (user_id, make, model, plate_no, year, color, image_path, insurance_expiry_date, last_service_date, service_interval_km) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $make, $model, $plate_no, $year, $color, $imagePath, $insurance_expiry_date, $last_service_date, $service_interval_km]);

    header("Location: ../dashboard.php#my_vehicles");
    exit();
} else {
    header("Location: ../dashboard.php?error=invalid_request");
    exit();
}
?>
