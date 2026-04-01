<?php
require 'config/db.php'; // your PDO connection

if (isset($_POST['id'], $_POST['status'])) {
    $id = (int)$_POST['id'];
    $status = $_POST['status'];

    // Validate status
    if (!in_array($status, ['Accepted', 'Rejected'])) {
        echo 'error';
        exit;
    }

    $stmt = $pdo->prepare("UPDATE towing_requests SET status=? WHERE id=?");
    if ($stmt->execute([$status, $id])) {
        echo 'success';
    } else {
        echo 'error';
    }
} else {
    echo 'error';
}