<?php
require_once '../config/db.php';

// Ensure the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    die('User is not logged in.');
}

$userId = $_SESSION['user_id'];

// Fetch settings for the dealer
$query = "SELECT * FROM dealer_settings WHERE user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Set defaults in case the settings are not set
$emailNotifications = isset($settings['email_notifications']) ? $settings['email_notifications'] : 1;
$smsNotifications = isset($settings['sms_notifications']) ? $settings['sms_notifications'] : 1;
$inAppNotifications = isset($settings['in_app_notifications']) ? $settings['in_app_notifications'] : 1;
$carCategories = isset($settings['car_categories']) ? $settings['car_categories'] : '';
$defaultPricing = isset($settings['default_pricing']) ? $settings['default_pricing'] : '';
$twoFactorAuth = isset($settings['two_factor_auth']) ? $settings['two_factor_auth'] : 0;
?>