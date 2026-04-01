<?php
// 1. Error Reporting & Session
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// 2. Force Nairobi Time Synchronization
date_default_timezone_set('Africa/Nairobi'); 
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header("Location: ../login.php"); exit;
}

$user_id = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

// 3. GET DEALER ID
$dealer_info = $pdo->prepare("SELECT id FROM dealers WHERE user_id = ?");
$dealer_info->execute([$user_id]);
$dealer = $dealer_info->fetch();
$real_dealer_id = $dealer['id'] ?? 0;

// 4. FETCH TOP ENGAGED CARS
$stmt = $pdo->prepare("
    SELECT 
        dl.id, dl.make, dl.model, dl.year, dl.price, dl.main_image,
        COUNT(CASE WHEN de.type = 'car_view' THEN 1 END) as total_views,
        COUNT(CASE WHEN de.type = 'tiktok_click' THEN 1 END) as tiktok_clicks,
        COUNT(CASE WHEN de.type IN ('whatsapp_click', 'call_click', 'lead_submission') THEN 1 END) as contact_clicks,
        ROUND(AVG(NULLIF(de.duration_seconds, 0))) as avg_duration
    FROM dealer_listings dl
    LEFT JOIN dealer_engagement de ON dl.id = de.related_id
    WHERE dl.dealer_id = ?
    GROUP BY dl.id
    HAVING (COUNT(CASE WHEN de.type = 'car_view' THEN 1 END) + 
            COUNT(CASE WHEN de.type = 'tiktok_click' THEN 1 END) + 
            COUNT(CASE WHEN de.type IN ('whatsapp_click', 'call_click', 'lead_submission') THEN 1 END)) > 0
    ORDER BY (COUNT(CASE WHEN de.type = 'car_view' THEN 1 END) + 
             (COUNT(CASE WHEN de.type IN ('whatsapp_click', 'call_click', 'lead_submission') THEN 1 END) * 12) + 
             (COUNT(CASE WHEN de.type = 'tiktok_click' THEN 1 END) * 3)) DESC
    LIMIT 15
");
$stmt->execute([$user_id]);
$top_listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. FETCH GLOBAL STATS (Including Profile Views)
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN type = 'car_view' THEN 1 END) as total_car_views,
        COUNT(CASE WHEN source_platform = 'tiktok' OR type = 'tiktok_click' THEN 1 END) as total_tiktok,
        COUNT(CASE WHEN type = 'profile_view' THEN 1 END) as total_profile,
        COUNT(CASE WHEN type IN ('whatsapp_click', 'call_click', 'lead_submission') THEN 1 END) as total_leads
    FROM dealer_engagement 
    WHERE dealer_id = ?
");
$stats_stmt->execute([$user_id]);
$overall_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$total_all = array_sum($overall_stats) ?: 0;
$safe_total = $total_all ?: 1; 

$tiktok_perc = round(($overall_stats['total_tiktok'] / $safe_total) * 100);
$direct_perc = round(($overall_stats['total_car_views'] / $safe_total) * 100);
$profile_perc = round(($overall_stats['total_profile'] / $safe_total) * 100);
$profile_views = $overall_stats['total_profile'] ?? 0;

// 6. FETCH TREND DATA (Fixed 7-Day Window for Sunday Rise)
$trend_stmt = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM dealer_engagement 
    WHERE dealer_id = ? AND created_at >= DATE_SUB(?, INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");
$trend_stmt->execute([$user_id, $now]);
$db_trends = $trend_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$trend_labels = [];
$trend_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = ($i === 0) ? 'TODAY' : date('D', strtotime($date));
    $trend_labels[] = $day_name;
    $trend_data[] = $db_trends[$date] ?? 0;
}

// 7. LIVE VISITORS
$live_stmt = $pdo->prepare("SELECT COUNT(DISTINCT session_id) FROM dealer_engagement WHERE dealer_id = ? AND last_heartbeat >= DATE_SUB(?, INTERVAL 60 SECOND)");
$live_stmt->execute([$user_id, $now]);
$live_now = $live_stmt->fetchColumn();

// 8. DURATION FORMATTER
function formatDuration($seconds) {
    if (!$seconds || $seconds < 1) return '---';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    if ($hours > 0) return "{$hours}h {$minutes}m";
    if ($minutes > 0) return "{$minutes}m {$secs}s";
    return "{$secs}s";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Intelligence | AutoLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; letter-spacing: -0.01em; }
        .pulse-red { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    </style>
</head>
<body class="pb-28">

    <div class="p-6">
        <header class="flex justify-between items-start mb-8">
            <div class="flex items-start gap-4">
                <a href="dealer.php" class="w-10 h-10 bg-white border border-slate-100 rounded-2xl flex items-center justify-center text-slate-900 shadow-sm active:scale-95 transition-transform">
                    <i class="fas fa-chevron-left text-xs"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-800 tracking-tighter uppercase italic text-slate-900 leading-none">Intelligence</h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Live Engagement Hub</p>
                </div>
            </div>
            
            <?php if($live_now > 0): ?>
            <div class="flex items-center gap-2 bg-rose-50 px-3 py-1.5 rounded-full border border-rose-100">
                <div class="w-1.5 h-1.5 bg-rose-500 rounded-full pulse-red"></div>
                <span class="text-[10px] font-black text-rose-600 uppercase italic"><?= $live_now ?> Active</span>
            </div>
            <?php else: ?>
            <div class="w-10 h-10 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                <i class="fas fa-chart-line text-sm"></i>
            </div>
            <?php endif; ?>
        </header>

        <div class="bg-slate-900 p-6 rounded-[2.5rem] text-white shadow-xl mb-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-8 opacity-10"><i class="fas fa-bolt text-6xl"></i></div>
            <p class="text-[9px] font-bold uppercase opacity-60 mb-1 tracking-widest">Total Digital Interactions</p>
            <div class="flex justify-between items-end relative z-10">
                <h3 class="text-4xl font-black italic"><?= number_format($total_all) ?></h3>
                <div class="bg-emerald-500/20 text-emerald-400 text-[9px] font-black px-3 py-1.5 rounded-xl border border-emerald-500/30 uppercase">
                    <?= $overall_stats['total_leads'] ?> Leads Generated
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 mb-8">
            <div class="bg-white border border-slate-100 p-6 rounded-[2rem] shadow-sm">
                <h3 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-4">Traffic Pulse (7D)</h3>
                <canvas id="pulseChart" style="max-height: 120px;"></canvas>
            </div>

            <div class="bg-white border border-slate-100 p-6 rounded-[2rem] shadow-sm">
                <h3 class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-4">Channel Distribution</h3>
                <div class="flex items-center">
                    <div class="w-1/2"><canvas id="intentChart" style="max-height: 110px;"></canvas></div>
                    <div class="w-1/2 pl-6 space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full bg-indigo-500"></div>
                            <span class="text-[9px] font-extrabold text-slate-500 uppercase">Direct (<?= $direct_perc ?>%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full bg-black"></div>
                            <span class="text-[9px] font-extrabold text-slate-500 uppercase">TikTok (<?= $tiktok_perc ?>%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-2.5 h-2.5 rounded-full bg-slate-300"></div>
                            <span class="text-[9px] font-extrabold text-slate-500 uppercase">Profile (<?= $profile_perc ?>%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="bg-white border border-slate-100 p-5 rounded-[2rem] shadow-sm">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600">
                        <i class="fas fa-user-circle text-xs"></i>
                    </div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Profile Views</span>
                </div>
                <h4 class="text-xl font-800 text-slate-900"><?= number_format($profile_views) ?></h4>
                <p class="text-[8px] font-bold text-emerald-500 uppercase mt-1">Showroom Visits</p>
            </div>

            <div class="bg-white border border-slate-100 p-5 rounded-[2rem] shadow-sm">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                        <i class="fas fa-mouse-pointer text-xs"></i>
                    </div>
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest">CTR Rate</span>
                </div>
                <?php $ctr = ($total_all > 0) ? round(($overall_stats['total_leads'] / $total_all) * 100, 1) : 0; ?>
                <h4 class="text-xl font-800 text-slate-900"><?= $ctr ?>%</h4>
                <p class="text-[8px] font-bold text-slate-400 uppercase mt-1">Lead Conversion</p>
            </div>
        </div>

        <h2 class="text-xs font-black uppercase text-slate-900 mb-4 px-2 tracking-widest italic">Unit Performance</h2>
        <div class="space-y-3">
            <?php foreach($top_listings as $unit): ?>
            <div class="flex items-center gap-4 bg-white border border-slate-100 p-4 rounded-[2rem] shadow-sm">
                <div class="relative">
                    <img src="../<?= $unit['main_image'] ?>" class="w-16 h-16 rounded-2xl object-cover border border-slate-100">
                    <?php if($unit['contact_clicks'] > 0): ?>
                        <div class="absolute -top-1 -right-1 bg-emerald-500 w-5 h-5 rounded-full border-2 border-white flex items-center justify-center">
                            <i class="fas fa-bolt text-[8px] text-white"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-slate-900 truncate text-sm"><?= $unit['year'] ?> <?= $unit['make'] ?> <?= $unit['model'] ?></h4>
                    <div class="flex items-center gap-3 mt-1.5">
                        <div class="flex items-center gap-1"><i class="fas fa-eye text-[9px] text-slate-400"></i><span class="text-[10px] font-extrabold text-slate-600"><?= $unit['total_views'] ?></span></div>
                        <div class="flex items-center gap-1"><i class="fas fa-clock text-[9px] text-indigo-400"></i><span class="text-[10px] font-extrabold text-indigo-600"><?= formatDuration($unit['avg_duration']) ?></span></div>
                        <div class="flex items-center gap-1"><i class="fas fa-comment-alt text-[9px] text-emerald-400"></i><span class="text-[10px] font-extrabold text-emerald-600"><?= $unit['contact_clicks'] ?></span></div>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-black text-slate-900 mb-1">KSH <?= number_format($unit['price'] / 1000) ?>K</p>
                    <?php 
                        $score = ($unit['total_views'] * 1) + ($unit['tiktok_clicks'] * 3) + ($unit['contact_clicks'] * 15);
                        $label = $score > 50 ? 'Hot Deal' : ($score > 15 ? 'Rising' : 'Cold');
                        $color = $score > 50 ? 'bg-orange-500 text-white' : ($score > 15 ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500');
                    ?>
                    <span class="<?= $color ?> text-[7px] font-black px-2 py-0.5 rounded-md uppercase"><?= $label ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <nav class="fixed bottom-0 left-0 right-0 h-20 bg-white/90 backdrop-blur-xl border-t border-slate-100 flex justify-around items-center px-6 pb-2 z-50">
        <a href="dealer.php" class="text-slate-300 flex flex-col items-center gap-1"><i class="fas fa-layer-group text-xl"></i><span class="text-[8px] font-bold uppercase tracking-widest">Leads</span></a>
        <a href="engagement.php" class="text-indigo-600 flex flex-col items-center gap-1"><i class="fas fa-chart-pie text-xl"></i><span class="text-[8px] font-bold uppercase tracking-widest">Insights</span></a>
        <a href="inventory.php" class="text-slate-300 flex flex-col items-center gap-1"><i class="fas fa-car text-xl"></i><span class="text-[8px] font-bold uppercase tracking-widest">Stock</span></a>
    </nav>

    <script>
        const pulseCtx = document.getElementById('pulseChart').getContext('2d');
        new Chart(pulseCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trend_labels) ?>,
                datasets: [{
                    data: <?= json_encode($trend_data) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.05)',
                    borderWidth: 4,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 9, weight: 'bold' }, color: '#94a3b8' } },
                    y: { beginAtZero: true, display: false }
                }
            }
        });

        const intentCtx = document.getElementById('intentChart').getContext('2d');
        new Chart(intentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Direct', 'TikTok', 'Profile'],
                datasets: [{
                    data: [<?= $overall_stats['total_car_views'] ?>, <?= $overall_stats['total_tiktok'] ?>, <?= $overall_stats['total_profile'] ?>],
                    backgroundColor: ['#6366f1', '#000000', '#e2e8f0'],
                    borderWidth: 0,
                    cutout: '80%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>

</html>
