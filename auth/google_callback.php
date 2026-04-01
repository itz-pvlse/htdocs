<?php
session_start();
require_once '../google-api/vendor/autoload.php';

// Create Google Client
$client = new Google_Client();
$client->setClientId("YOUR_CLIENT_ID");        
$client->setClientSecret("YOUR_CLIENT_SECRET"); 
$client->setRedirectUri("https://YOURDOMAIN.infinityfreeapp.com/google_callback.php"); 

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);

        $service = new Google_Service_Oauth2($client);
        $user = $service->userinfo->get();

        // Store Google user info in session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['email']   = $user->email;
        $_SESSION['name']    = $user->name;
        $_SESSION['picture'] = $user->picture;

        // OPTIONAL: Save user to database if not exists
        /*
        require_once 'config/db.php';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$user->email]);
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id) VALUES (?, ?, ?)");
            $stmt->execute([$user->name, $user->email, $user->id]);
        }
        */

        // Redirect to dashboard
        header("Location: dashboard.php");
        exit;
    }
}

echo "Google login failed!";
