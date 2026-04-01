<?php
require_once 'config/db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, part_name, quantity FROM parts_inventory WHERE quantity <= reorder_level AND quantity > 0 ORDER BY id DESC");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($alerts);