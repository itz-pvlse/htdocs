<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') exit('unauthorized');

$id = intval($_POST['id'] ?? 0);
$dealerId = $_SESSION['user_id'];

$stmt = $pdo->prepare("UPDATE dealer_listings SET status = 'sold' WHERE id = ? AND dealer_id = ?");
$stmt->execute([$id, $dealerId]);

header('Location: dealer.php'); exit;