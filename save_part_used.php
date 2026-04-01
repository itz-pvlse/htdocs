<?php
require_once 'config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mechanic_id = $_SESSION['user_id'];
    $part_id = $_POST['part_id'];
    $quantity_used = $_POST['quantity_used'];
    $notes = $_POST['notes'] ?? '';

    // Check current stock
    $stockStmt = $pdo->prepare("SELECT quantity FROM parts_inventory WHERE id = ?");
    $stockStmt->execute([$part_id]);
    $part = $stockStmt->fetch();

    if ($part && $part['quantity'] >= $quantity_used) {
        // Reduce stock
        $newQty = $part['quantity'] - $quantity_used;
        $updateStmt = $pdo->prepare("UPDATE parts_inventory SET quantity = ? WHERE id = ?");
        $updateStmt->execute([$newQty, $part_id]);

        // Record usage
        $insertStmt = $pdo->prepare("INSERT INTO parts_used (mechanic_id, part_id, quantity_used, notes) VALUES (?, ?, ?, ?)");
        $insertStmt->execute([$mechanic_id, $part_id, $quantity_used, $notes]);

        header('Location: mechanic/mechanic_dashboard.php?success=part_added');
        exit;
    } else {
        header('Location: mechanic/mechanic_dashboard.php?error=insufficient_stock');
        exit;
    }
}
?>