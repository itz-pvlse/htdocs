<?php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

// Ensure dealer is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo json_encode(['status'=>'error', 'message'=>'Unauthorized']);
    exit;
}

$dealerUserId = $_SESSION['user_id'];

// Get dealer.id from dealers table
$stmt = $pdo->prepare("SELECT id FROM dealers WHERE user_id = ?");
$stmt->execute([$dealerUserId]);
$dealerRow = $stmt->fetch(PDO::FETCH_ASSOC);
$dealerIdForProfile = $dealerRow['id'] ?? 0;

// === LEADS METRICS ===
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE dealer_id = ?");
$stmt->execute([$dealerUserId]);
$totalLeads = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE dealer_id = ? AND contacted = 1");
$stmt->execute([$dealerUserId]);
$contactedLeads = (int)$stmt->fetchColumn();

// === LEADS OVER LAST 7 DAYS ===
$rawLeadsOverTime = $pdo->prepare("
    SELECT DATE(created_at) AS date, COUNT(*) AS count 
    FROM leads 
    WHERE dealer_id = ? AND created_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");
$rawLeadsOverTime->execute([$dealerUserId]);

// Initialize last 7 days with 0
$leadsOverTime = ['dates'=>[], 'counts'=>[]];
$days = [];
for($i=6; $i>=0; $i--){
    $day = date('Y-m-d', strtotime("-$i day"));
    $days[$day] = 0;
}

// Fill actual counts from query
while($row = $rawLeadsOverTime->fetch(PDO::FETCH_ASSOC)){
    $days[$row['date']] = (int)$row['count'];
}

// Assign to array for front-end
foreach($days as $date=>$count){
    $leadsOverTime['dates'][] = $date;
    $leadsOverTime['counts'][] = $count;
}

// Contacted leads over last 7 days for sparkline
$rawContactedOverTime = $pdo->prepare("
    SELECT DATE(created_at) AS date, COUNT(*) AS count 
    FROM leads 
    WHERE dealer_id = ? AND contacted = 1 AND created_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");
$rawContactedOverTime->execute([$dealerUserId]);
$contactedOverTime = array_fill(0,7,0);
while($row = $rawContactedOverTime->fetch(PDO::FETCH_ASSOC)){
    $index = array_search($row['date'], array_keys($days));
    if($index !== false) $contactedOverTime[$index] = (int)$row['count'];
}

// === CARS METRICS ===
$stmt = $pdo->prepare("SELECT COUNT(*) FROM dealer_listings WHERE dealer_id = ?");
$stmt->execute([$dealerUserId]);
$carsListed = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM dealer_listings WHERE dealer_id = ? AND status = 'sold'");
$stmt->execute([$dealerUserId]);
$carsSold = (int)$stmt->fetchColumn();

// === CARS OVER LAST 6 MONTHS ===
$rawCarsOverTime = $pdo->prepare("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           COUNT(*) AS listed, 
           SUM(CASE WHEN status='sold' THEN 1 ELSE 0 END) AS sold
    FROM dealer_listings
    WHERE dealer_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY MIN(created_at)
");
$rawCarsOverTime->execute([$dealerUserId]);

$carsOverTime = ['months'=>[], 'listed'=>[], 'sold'=>[]];
while($row = $rawCarsOverTime->fetch(PDO::FETCH_ASSOC)){
    $carsOverTime['months'][] = $row['month'];
    $carsOverTime['listed'][] = (int)$row['listed']; // total cars listed that month
    $carsOverTime['sold'][] = (int)$row['sold'];
}

// === TOP 5 CAR MODELS ===
$stmt = $pdo->prepare("
    SELECT model, COUNT(*) AS unitsSold, AVG(price) AS avgPrice
    FROM dealer_listings
    WHERE dealer_id = ? AND status = 'sold'
    GROUP BY model
    ORDER BY unitsSold DESC
    LIMIT 5
");
$stmt->execute([$dealerUserId]);
$topModels = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $topModels[] = [
        'model'=>$row['model'],
        'unitsSold'=>(int)$row['unitsSold'],
        'avgPrice'=>number_format($row['avgPrice'],2)
    ];
}

// === ENGAGEMENT METRICS ===
$stmt = $pdo->prepare("SELECT COUNT(*) FROM dealer_engagement WHERE dealer_id = ? AND type='profile_view'");
$stmt->execute([$dealerIdForProfile]);
$profileViews = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM dealer_engagement e
    JOIN dealer_listings l ON e.related_id = l.id
    WHERE e.type='car_view' AND l.dealer_id = ?
");
$stmt->execute([$dealerUserId]);
$carViews = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM dealer_engagement WHERE dealer_id = ? AND type='tiktok_click'");
$stmt->execute([$dealerIdForProfile]);
$tiktokClicks = (int)$stmt->fetchColumn();

// === TRENDS ===
function getLast7Days($pdo, $dealerUserId, $table, $column='id', $condition='') {
    $query = "SELECT COUNT($column) FROM $table WHERE dealer_id=? AND created_at >= CURDATE() - INTERVAL 6 DAY";
    if($condition) $query .= " AND $condition";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$dealerUserId]);
    return (int)$stmt->fetchColumn();
}
function getPrev7Days($pdo, $dealerUserId, $table, $column='id', $condition='') {
    $query = "SELECT COUNT($column) FROM $table WHERE dealer_id=? AND created_at BETWEEN CURDATE() - INTERVAL 13 DAY AND CURDATE() - INTERVAL 7 DAY";
    if($condition) $query .= " AND $condition";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$dealerUserId]);
    return (int)$stmt->fetchColumn();
}

$trendLeadsValue = getPrev7Days($pdo,$dealerUserId,'leads') ?: 0;
$trendLeads = $trendLeadsValue == 0 ? 100 : round((($totalLeads - $trendLeadsValue)/$trendLeadsValue)*100);

$trendContactedValue = getPrev7Days($pdo,$dealerUserId,'leads','id','contacted=1') ?: 0;
$trendContacted = $trendContactedValue == 0 ? 100 : round((($contactedLeads - $trendContactedValue)/$trendContactedValue)*100);

$trendListedValue = getPrev7Days($pdo,$dealerUserId,'dealer_listings','id',"status!='sold'") ?: 0;
$trendListed = $trendListedValue == 0 ? 100 : round((($carsListed - $trendListedValue)/$trendListedValue)*100);

$trendSoldValue = getPrev7Days($pdo,$dealerUserId,'dealer_listings','id',"status='sold'") ?: 0;
$trendSold = $trendSoldValue == 0 ? 100 : round((($carsSold - $trendSoldValue)/$trendSoldValue)*100);

// === SPARKLINES DATA ===
$spark = [
    'totalLeads' => $leadsOverTime['counts'],
    'contactedLeads' => $contactedOverTime,
    'carsListed' => $carsOverTime['listed'],
    'carsSold' => $carsOverTime['sold']
];

// === OUTPUT JSON ===
echo json_encode([
    'status'=>'success',
    'totalLeads'=>$totalLeads,
    'contactedLeads'=>$contactedLeads,
    'carsListed'=>$carsListed,
    'carsSold'=>$carsSold,
    'trendLeads'=>$trendLeads,
    'trendContacted'=>$trendContacted,
    'trendListed'=>$trendListed,
    'trendSold'=>$trendSold,
    'leadsOverTime'=>$leadsOverTime,
    'carsOverTime'=>$carsOverTime,
    'topModels'=>$topModels,
    'profileViews'=>$profileViews,
    'carViews'=>$carViews,
    'tiktokClicks'=>$tiktokClicks,
    'spark'=>$spark
]);