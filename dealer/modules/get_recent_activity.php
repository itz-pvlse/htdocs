<?php
require_once '../../config/db.php';
session_start();
// Only allow dealer access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$dealerId = $_SESSION['user_id'];

// Recent listings
$stmt = $pdo->prepare("
    SELECT 'listing' AS type, CONCAT('Added new car: ', title) AS description, created_at
    FROM dealer_listings
    WHERE dealer_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$dealerId]);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent sales
$stmt = $pdo->prepare("
    SELECT 'sale' AS type, CONCAT('Sold car: ', title) AS description, created_at
    FROM dealer_listings
    WHERE dealer_id = ? AND status = 'sold'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$dealerId]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent leads (make sure your leads table exists)
$stmt = $pdo->prepare("
    SELECT 'lead' AS type, CONCAT('New lead from ', name) AS description, created_at
    FROM leads
    WHERE dealer_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$dealerId]);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge and sort all activities
$activities = array_merge($listings, $sales, $leads);
usort($activities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Limit to latest 10 entries
$activities = array_slice($activities, 0, 10);

// Output JSON
header('Content-Type: application/json');
echo json_encode($activities);