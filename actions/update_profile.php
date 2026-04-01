<?php
// actions/update_profile.php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $role    = $_SESSION['role'] ?? 'owner'; // Assuming role is stored in session at login
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $phone   = trim($_POST['phone']);
    
    // 1. Handle File Upload
    $photo_name = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_name = $_FILES['profile_photo']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
            $upload_path = "../uploads/profiles/" . $new_file_name;

            if (!is_dir('../uploads/profiles/')) {
                mkdir('../uploads/profiles/', 0777, true);
            }

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $photo_name = $new_file_name;
                
                // Cleanup old photo from main users table
                $stmtOld = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
                $stmtOld->execute([$user_id]);
                $old_photo = $stmtOld->fetchColumn();
                if ($old_photo && file_exists("../uploads/profiles/" . $old_photo)) {
                    unlink("../uploads/profiles/" . $old_photo);
                }
            }
        }
    }

    try {
        $pdo->beginTransaction();

        // 2. Update MAIN users table (The Feed Source)
        $userSql = "UPDATE users SET name = ?, email = ?, phone = ?";
        $userParams = [$name, $email, $phone];
        if ($photo_name) {
            $userSql .= ", profile_photo = ?";
            $userParams[] = $photo_name;
        }
        $userSql .= " WHERE id = ?";
        $userParams[] = $user_id;

        $stmtUser = $pdo->prepare($userSql);
        $stmtUser->execute($userParams);

        // 3. Update ROLE tables (Backward Compatibility for Dashboards)
        if ($role === 'garage') {
            $garageSql = "UPDATE garages SET name = ?, email = ?";
            $garageParams = [$name, $email];
            if ($photo_name) {
                $garageSql .= ", profile_pic = ?";
                $garageParams[] = $photo_name;
            }
            $garageSql .= " WHERE id = ?";
            $garageParams[] = $user_id;
            
            $stmtRole = $pdo->prepare($garageSql);
            $stmtRole->execute($garageParams);

        } elseif ($role === 'dealer') {
            $dealerSql = "UPDATE dealers SET name = ?, email = ?";
            $dealerParams = [$name, $email];
            if ($photo_name) {
                $dealerSql .= ", profile_pic = ?";
                $dealerParams[] = $photo_name;
            }
            $dealerSql .= " WHERE user_id = ?";
            $dealerParams[] = $user_id;
            
            $stmtRole = $pdo->prepare($dealerSql);
            $stmtRole->execute($dealerParams);
        }

        $pdo->commit();

        // 4. Refresh Session
        $_SESSION['name'] = $name;
        if ($photo_name) {
            $_SESSION['profile_photo'] = $photo_name;
        }

        $_SESSION['success'] = "Profile updated successfully!";

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            $_SESSION['error'] = "Email already exists.";
        } else {
            $_SESSION['error'] = "Update failed: " . $e->getMessage();
        }
    }

    header("Location: ../profile_settings.php");
    exit;
}
