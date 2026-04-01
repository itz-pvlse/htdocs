<?php
// api_part.php - PERFORMANCE OPTIMIZED
require_once 'config/db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) exit(json_encode(['success'=>false, 'error'=>'Auth Failed']));

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save': // Handles both Add and Update (Upsert)
            $id = $_POST['id'] ?? null;
            $data = [
                $_POST['part_name'], $_POST['part_number'], $_POST['category'],
                $_POST['quantity'], $_POST['reorder_level'], $_POST['unit_price'],
                $_POST['selling_price'], $_POST['supplier_id'], $_POST['location']
            ];
            
            if ($id) {
                $sql = "UPDATE parts_inventory SET part_name=?, part_number=?, category=?, quantity=?, 
                        reorder_level=?, unit_price=?, selling_price=?, supplier_id=?, location=? WHERE id=?";
                $data[] = $id;
            } else {
                $sql = "INSERT INTO parts_inventory (part_name, part_number, category, quantity, 
                        reorder_level, unit_price, selling_price, supplier_id, location) VALUES (?,?,?,?,?,?,?,?,?)";
            }
            $pdo->prepare($sql)->execute($data);
            echo json_encode(['success' => true]);
            break;

        case 'bulk_receive':
            $pdo->beginTransaction();
            $items = $_POST['batch']; // Expects array of {id: X, qty: Y}
            $stmt = $pdo->prepare("UPDATE parts_inventory SET quantity = quantity + ?, last_restocked_at = NOW() WHERE id = ?");
            foreach($items as $item) {
                $stmt->execute([$item['qty'], $item['id']]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'get':
            $stmt = $pdo->prepare("SELECT * FROM parts_inventory WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['success' => true, 'part' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;
    }
} catch (Exception $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
