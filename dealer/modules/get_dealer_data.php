<?php
require_once '../../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dealerId = (int)$_SESSION['user_id'];

// Generate last 12 months without mutating the same DateTime
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $date = new DateTime();
    $date->modify("-$i months");
    $months[] = $date->format('Y-m');
}

function fillMissingMonths($data, $months) {
    $map = [];
    foreach ($data as $row) {
        $map[$row['month']] = (int)$row['total'];
    }
    $filled = [];
    foreach ($months as $m) {
        $filled[] = $map[$m] ?? 0;
    }
    return $filled;
}

// Totals
$stmt = $pdo->prepare("SELECT COUNT(*) FROM dealer_listings WHERE dealer_id = ?");
$stmt->execute([$dealerId]);
$totalListings = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM dealer_listings WHERE dealer_id = ? AND status = 'sold'");
$stmt->execute([$dealerId]);
$totalSold = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE dealer_id = ?");
$stmt->execute([$dealerId]);
$totalLeads = (int)$stmt->fetchColumn();

// Monthly Listings
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
    FROM dealer_listings
    WHERE dealer_id = ?
    GROUP BY month
");
$stmt->execute([$dealerId]);
$listingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly Leads
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
    FROM leads
    WHERE dealer_id = ?
    GROUP BY month
");
$stmt->execute([$dealerId]);
$leadsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly Sales
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
    FROM dealer_listings
    WHERE dealer_id = ? AND status = 'sold'
    GROUP BY month
");
$stmt->execute([$dealerId]);
$salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill missing months
$listingsFilled = fillMissingMonths($listingsData, $months);
$leadsFilled = fillMissingMonths($leadsData, $months);
$salesFilled = fillMissingMonths($salesData, $months);

// Output JSON
echo json_encode([
    'totalListings' => $totalListings,
    'totalSold' => $totalSold,
    'totalLeads' => $totalLeads,
    'months' => $months,
    'listingsFilled' => $listingsFilled,
    'leadsFilled' => $leadsFilled,
    'salesFilled' => $salesFilled
]);