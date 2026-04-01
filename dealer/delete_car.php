<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') exit('unauthorized');

$id = intval($_GET['id'] ?? 0); // use GET since the link passes ?id=
$dealerId = $_SESSION['user_id'];

// fetch listing to remove files
$stmt = $pdo->prepare("SELECT main_image FROM dealer_listings WHERE id = ? AND dealer_id = ?");
$stmt->execute([$id, $dealerId]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($listing) {
    // delete image file from disk
    $fullPath = __DIR__ . '/../../' . $listing['main_image'];
    if (!empty($listing['main_image']) && file_exists($fullPath)) {
        @unlink($fullPath);
    }

    // delete record from database
    $del = $pdo->prepare("DELETE FROM dealer_listings WHERE id = ? AND dealer_id = ?");
    $del->execute([$id, $dealerId]);
}

// redirect back to inventory
header('Location: dealer.php');
exit;