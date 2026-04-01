<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_name = $_POST['part_name'];
    $part_number = $_POST['part_number'];
    $category = $_POST['category'];
    $quantity = $_POST['quantity'];
    $unit_price = $_POST['unit_price'];
    $supplier = $_POST['supplier'];
    $reorder_level = $_POST['reorder_level'];

    // Determine stock status
    if ($quantity == 0) {
        $status = 'Out of Stock';
    } elseif ($quantity <= $reorder_level) {
        $status = 'Low Stock';
    } else {
        $status = 'In Stock';
    }

    $stmt = $pdo->prepare("
        INSERT INTO parts_inventory (part_name, part_number, category, quantity, unit_price, supplier, reorder_level, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$part_name, $part_number, $category, $quantity, $unit_price, $supplier, $reorder_level, $status]);

    header('Location: admin_parts.php');
    exit;
}
?>