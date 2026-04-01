<?php
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$garage_id = $data['garage_id'] ?? 0;
$action = $data['action'] ?? ''; 

if (!$garage_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Garage ID']);
    exit;
}

try {
    if ($action === 'save') {
        // Toggle Logic: If the row exists, delete it (Unsave). If not, create it.
        $stmt = $pdo->prepare("SELECT id FROM user_garages WHERE user_id = ? AND garage_id = ?");
        $stmt->execute([$user_id, $garage_id]);
        
        if ($stmt->fetch()) {
            // Unsaving removes everything (including preferred status) because the row is deleted
            $pdo->prepare("DELETE FROM user_garages WHERE user_id = ? AND garage_id = ?")->execute([$user_id, $garage_id]);
            $res = ['status' => 'success', 'message' => 'Removed from your list'];
        } else {
            // Saving creates the row with default is_preferred = 0
            $pdo->prepare("INSERT INTO user_garages (user_id, garage_id) VALUES (?, ?)")->execute([$user_id, $garage_id]);
            $res = ['status' => 'success', 'message' => 'Garage saved'];
        }
    } 

    else if ($action === 'preferred') {
        // 1. Check current state
        $stmt = $pdo->prepare("SELECT is_preferred FROM user_garages WHERE user_id = ? AND garage_id = ?");
        $stmt->execute([$user_id, $garage_id]);
        $row = $stmt->fetch();

        if ($row) {
            // CASE: Row exists. We just flip the bit (1 to 0 or 0 to 1)
            $newValue = ($row['is_preferred'] == 1) ? 0 : 1;
            
            // If setting to 1, we must ensure no other garage is preferred for this user
            if ($newValue == 1) {
                $pdo->prepare("UPDATE user_garages SET is_preferred = 0 WHERE user_id = ?")->execute([$user_id]);
            }

            $pdo->prepare("UPDATE user_garages SET is_preferred = ? WHERE user_id = ? AND garage_id = ?")
                ->execute([$newValue, $user_id, $garage_id]);
            
            $res = ['status' => 'success', 'message' => ($newValue == 1 ? "Set as Preferred" : "Preference removed")];
        } else {
            // CASE: Row doesn't exist. We must CREATE it and set as preferred
            // First, clear any other preferred flags
            $pdo->prepare("UPDATE user_garages SET is_preferred = 0 WHERE user_id = ?")->execute([$user_id]);
            
            $pdo->prepare("INSERT INTO user_garages (user_id, garage_id, is_preferred) VALUES (?, ?, 1)")
                ->execute([$user_id, $garage_id]);
            
            $res = ['status' => 'success', 'message' => 'Saved and Set as Preferred'];
        }
    } else {
        $res = ['status' => 'error', 'message' => 'Invalid action'];
    }

    echo json_encode($res);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
