<?php
// actions/edit_vehicle.php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $id = $_POST['id'];
    
    // 1. Ownership & Existing Data Check
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $old_data = $stmt->fetch();

    if (!$old_data) {
        die("Unauthorized access.");
    }

    $make = $_POST['make'];
    $model = $_POST['model'];
    $year = $_POST['year'];
    $rating_cc = $_POST['rating_cc'];
    $plate_no = strtoupper(trim($_POST['plate_no']));
    
    // 2. Verification Reset Logic
    $v_status = $old_data['verification_status'];
    if ($plate_no !== $old_data['plate_no']) {
        $v_status = 'Unverified'; 
    }

    // 3. Image Upload Handling (Syncing to uploads/ folder)
    $image_path = $old_data['image_path']; 
    
    if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === 0) {
        // Physical folder on the server (one level up from /actions)
        $target_dir = "../uploads/";
        
        // Ensure directory exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['vehicle_image']['name'], PATHINFO_EXTENSION);
        $new_filename = "v_" . time() . "_" . uniqid() . "." . $ext;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $target_file)) {
            // Delete old physical image file
            // Note: If old_data['image_path'] is 'uploads/file.jpg', we add '../' to find it from this folder
            if (!empty($old_data['image_path'])) {
                $old_file_physical = "../" . $old_data['image_path'];
                if (file_exists($old_file_physical)) {
                    unlink($old_file_physical);
                }
            }
            
            // This is the path the database will store
            // It matches your request: uploads/filename.jpg
            $image_path = "uploads/" . $new_filename;
        }
    }

    // 4. Update Database
    $update = $pdo->prepare("
        UPDATE vehicles 
        SET make = ?, model = ?, year = ?, rating_cc = ?, plate_no = ?, image_path = ?, verification_status = ?
        WHERE id = ? AND user_id = ?
    ");
    
    if ($update->execute([$make, $model, $year, $rating_cc, $plate_no, $image_path, $v_status, $id, $user_id])) {
        header("Location: ../vehicle_details.php?id=" . $id . "&msg=success");
    } else {
        header("Location: ../vehicle_details.php?id=" . $id . "&msg=error");
    }
    exit;
}