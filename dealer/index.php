<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header('Location: dealerlogin.php');
    exit;
}

$dealerId = $_SESSION['user_id'];

// Fetch dealer info
$stmt = $pdo->prepare("SELECT * FROM dealers WHERE user_id = ?");
$stmt->execute([$dealerId]);
$dealer = $stmt->fetch(PDO::FETCH_ASSOC);

// Stats
$totalListings = $pdo->prepare("SELECT COUNT(*) FROM dealer_listings WHERE dealer_id = ?");
$totalListings->execute([$dealerId]);
$totalListings = $totalListings->fetchColumn();

$totalSold = $pdo->prepare("SELECT COUNT(*) FROM dealer_listings WHERE dealer_id = ? AND status = 'sold'");
$totalSold->execute([$dealerId]);
$totalSold = $totalSold->fetchColumn();

$totalLeads = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE dealer_id = ?");
$totalLeads->execute([$dealerId]);
$totalLeads = $totalLeads->fetchColumn();

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'inventory', 'leads', 'analytics', 'profile', 'settings'];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Dealer Dashboard</title>
<link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

<!-- Hamburger Button -->
<button id="sidebarToggle">☰</button>
<div id="overlay"></div>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="page-content">
    <?php
        if ($page === 'dashboard') {
            include 'modules/dashboard.php';
        } elseif (in_array($page, $allowedPages)) {
            include "modules/$page.php";
        } else {
            echo "<p>Page not found</p>";
        }
    ?>
    </div>
</div>

<script>
// Sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.querySelector('.sidebar');
  const toggleBtn = document.getElementById('sidebarToggle');
  const overlay = document.getElementById('overlay');

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
  });

  overlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
  });
});
</script>

</body>
</html>
