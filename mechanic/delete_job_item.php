<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    // 1. Get item details and the user's role for this specific job
    $stmt = $pdo->prepare("
        SELECT ji.added_by_id, ji.job_id, ja.is_lead 
        FROM job_items ji
        JOIN job_assignments ja ON ji.job_id = ja.job_id
        WHERE ji.id = ? AND ja.mechanic_id = ?
    ");
    $stmt->execute([$item_id, $user_id]);
    $perms = $stmt->fetch();

    if (!$perms) {
        echo json_encode(['success' => false, 'message' => 'Permission denied or item not found']);
        exit;
    }

    // 2. Logic Check: Allow if Lead OR if they are the owner
    if ($perms['is_lead'] == 1 || $perms['added_by_id'] == $user_id) {
        
        // If it's a part, we should ideally return it to inventory
        $item_stmt = $pdo->prepare("SELECT type, quantity, description FROM job_items WHERE id = ?");
        $item_stmt->execute([$item_id]);
        $item = $item_stmt->fetch();

        $pdo->beginTransaction();

        // If part, find it in inventory by name and add back the quantity
        if ($item['type'] === 'part') {
            $update_inv = $pdo->prepare("UPDATE parts_inventory SET quantity = quantity + ? WHERE part_name = ?");
            $update_inv->execute([$item['quantity'], $item['description']]);
        }

        // Delete the item
        $delete = $pdo->prepare("DELETE FROM job_items WHERE id = ?");
        $delete->execute([$item_id]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Only the Lead or the person who logged this can delete it.']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
