<?php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['google_temp'])) {
    header("Location: register.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'owner';
    $temp = $_SESSION['google_temp'];

    $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, role, profile_photo) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$temp['name'], $temp['email'], $temp['google_id'], $role, $temp['profile_photo']]);
    $newUserId = $pdo->lastInsertId();

    // Log in user
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['role'] = $role;

    unset($_SESSION['google_temp']);
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Select Role - AUTOLOG</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f5f7; display:flex; justify-content:center; align-items:center; min-height:100vh; }
.box { background:#fff; padding:30px; border-radius:12px; box-shadow:0 5px 20px rgba(0,0,0,0.1); max-width:400px; width:100%; text-align:center; }
button { padding:12px 20px; margin-top:15px; border:none; border-radius:8px; background:#007bff; color:#fff; cursor:pointer; font-size:1rem; }
</style>
</head>
<body>
<div class="box">
    <h2>Choose Your Role</h2>
    <form method="POST">
        <select name="role" required>
            <option value="">Select Role</option>
            <option value="owner">Car Owner</option>
            <option value="garage">Garage/Mechanic</option>
            <option value="dealer">Car Dealership</option>
        </select>
        <br>
        <button type="submit">Continue</button>
    </form>
</div>
</body>
</html>
