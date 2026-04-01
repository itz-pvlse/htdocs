<?php
require_once '../config/db.php';

// Ensure the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    die('User is not logged in.');
}

$userId = $_SESSION['user_id'];

// Get the form data from the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which action was requested
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // Initialize settings from POST request
    $emailNotifications = isset($_POST['emailNotifications']) ? $_POST['emailNotifications'] : 0;
    $smsNotifications = isset($_POST['smsNotifications']) ? $_POST['smsNotifications'] : 0;
    $inAppNotifications = isset($_POST['inAppNotifications']) ? $_POST['inAppNotifications'] : 0;
    $carCategories = isset($_POST['carCategories']) ? $_POST['carCategories'] : '';
    $defaultPricing = isset($_POST['defaultPricing']) ? $_POST['defaultPricing'] : '';
    $twoFactorAuth = isset($_POST['twoFactorAuth']) ? $_POST['twoFactorAuth'] : 0;
    $currentPassword = isset($_POST['currentPassword']) ? $_POST['currentPassword'] : '';
    $newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';
    $confirmNewPassword = isset($_POST['confirmNewPassword']) ? $_POST['confirmNewPassword'] : '';

    // Ensure all necessary data is set before proceeding
    if ($action === '') {
        echo json_encode(['status' => 'error', 'message' => 'Action not specified!']);
        exit;
    }

    try {
        // Update the settings based on the action
        if ($action === 'notificationsettings') {
            $query = "UPDATE dealer_settings SET 
                      email_notifications = ?, sms_notifications = ?, in_app_notifications = ?
                      WHERE user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$emailNotifications, $smsNotifications, $inAppNotifications, $userId]);

            echo json_encode(['status' => 'success', 'message' => 'Notification settings updated successfully!']);
            exit;

        } elseif ($action === 'carlistingpreferences') {
            $query = "UPDATE dealer_settings SET 
                      car_categories = ?, default_pricing = ?
                      WHERE user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$carCategories, $defaultPricing, $userId]);

            echo json_encode(['status' => 'success', 'message' => 'Car preferences updated successfully!']);
            exit;

        } elseif ($action === 'securitysettings') {
            $query = "UPDATE dealer_settings SET 
                      two_factor_auth = ?
                      WHERE user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$twoFactorAuth, $userId]);

            echo json_encode(['status' => 'success', 'message' => 'Security settings updated successfully!']);
            exit;

        } elseif ($action === 'passwordmanagement') {
            // Check if new password and confirm password match
            if ($newPassword !== $confirmNewPassword) {
                echo json_encode(['status' => 'error', 'message' => 'Passwords do not match!']);
                exit;
            }

            // Hash the new password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            // Update the password
            $query = "UPDATE dealer_settings SET password = ? WHERE user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$hashedPassword, $userId]);

            echo json_encode(['status' => 'success', 'message' => 'Password updated successfully!']);
            exit;

        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid action!']);
            exit;
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}
?>