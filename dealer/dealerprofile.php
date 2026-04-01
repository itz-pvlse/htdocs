<?php
session_start();
include('../config/db_connect.php');
require_once 'tracker.php'; 
// Just pass the Dealer ID (since there is no specific car)
track_engagement($dealer_id); 


$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM dealers WHERE id = ?");
$stmt->execute([$id]);
$dealer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dealer) {
    echo "Dealer not found.";
    exit;
}
?>

<h2><?= htmlspecialchars($dealer['name']) ?></h2>
<img src="uploads/logo/<?= htmlspecialchars($dealer['logo']) ?>" width="150">
<p><strong>Phone:</strong> <?= htmlspecialchars($dealer['phone']) ?></p>
<p><strong>Location:</strong> <?= htmlspecialchars($dealer['location']) ?></p>
<p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($dealer['description'])) ?></p>