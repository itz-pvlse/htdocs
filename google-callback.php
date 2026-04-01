<?php
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

    
require_once 'config/db.php';
session_start();

// Include Google Identity Services
require_once 'vendor/autoload.php'; // Google Client via Composer

$client = new Google_Client(['client_id' => '1058075626453-s6dg0ppd1gcan4nift4966euke45p56g.apps.googleusercontent.com']); 

$id_token = $_POST['credential'] ?? '';

if (!$id_token) {
    die('No token provided.');
}

try {
    $payload = $client->verifyIdToken($id_token);
    if ($payload) {
        $google_id = $payload['sub'];
        $name = $payload['name'] ?? '';
        $email = $payload['email'] ?? '';
        $profile_photo = $payload['picture'] ?? null;

        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR google_id = ?");
        $stmt->execute([$email, $google_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // User exists → log them in
            $_SESSION['user_id'] = $user['idPrimary'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit;
        } else {
            // New user → store Google info in session
            $_SESSION['google_temp'] = [
                'google_id' => $google_id,
                'name' => $name,
                'email' => $email,
                'profile_photo' => $profile_photo
            ];

            // Redirect to role selection page
            header("Location: select-role.php");
            exit;
        }
    } else {
        die('Invalid ID token.');
    }
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
