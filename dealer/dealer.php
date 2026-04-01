<?php
session_start();
require_once '../config/db.php'; 

// 1. AUTH & SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. DATA AGGREGATION
$dealer = $pdo->prepare("SELECT * FROM dealers WHERE user_id = ? LIMIT 1");
$dealer->execute([$user_id]);
$d = $dealer->fetch(PDO::FETCH_ASSOC);

// Financial & Inventory Stats
$inv_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_units,
        SUM(price) as stock_value,
        SUM(price - cost_price) as potential_profit,
        AVG(DATEDIFF(NOW(), created_at)) as avg_dol
    FROM dealer_listings WHERE dealer_id = ? AND status = 'active'
");
$inv_stats->execute([$user_id]);
$stats = $inv_stats->fetch(PDO::FETCH_ASSOC);

// Lead & Conversion Stats
$lead_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_leads,
        SUM(CASE WHEN status = 'hot' THEN 1 ELSE 0 END) as s_hot,
        SUM(CASE WHEN status = 'following_up' THEN 1 ELSE 0 END) as s_fup,
        SUM(CASE WHEN status = 'test_drive' THEN 1 ELSE 0 END) as s_td,
        SUM(CASE WHEN status = 'closed_won' THEN 1 ELSE 0 END) as won,
        AVG(TIMESTAMPDIFF(MINUTE, created_at, responded_at)) as resp_time
    FROM leads WHERE dealer_id = ?
");
$lead_stats->execute([$user_id]);
$ls = $lead_stats->fetch(PDO::FETCH_ASSOC);

$conv_rate = ($ls['total_leads'] > 0) ? ($ls['won'] / $ls['total_leads']) * 100 : 0;

// 3. ENGAGEMENT ANALYTICS
$eng_stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN type = 'profile_view' THEN 1 ELSE 0 END) as p_views,
        SUM(CASE WHEN type = 'car_view' THEN 1 ELSE 0 END) as c_views,
        SUM(CASE WHEN type = 'whatsapp_click' THEN 1 ELSE 0 END) as wa_clicks,
        SUM(CASE WHEN type = 'call_click' THEN 1 ELSE 0 END) as call_clicks
    FROM dealer_engagement WHERE dealer_id = ?
");
$eng_stmt->execute([$user_id]);
$engagement = $eng_stmt->fetch(PDO::FETCH_ASSOC);

$total_social = ($engagement['wa_clicks'] ?? 0) + ($engagement['call_clicks'] ?? 0);

// Fetch Car-Specific Views
$car_views_stmt = $pdo->prepare("
    SELECT related_id, COUNT(*) as views 
    FROM dealer_engagement 
    WHERE dealer_id = ? AND type = 'car_view' 
    GROUP BY related_id
");
$car_views_stmt->execute([$user_id]);
$car_views_map = $car_views_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch Inventory List
$cars = $pdo->prepare("
    SELECT *, DATEDIFF(NOW(), created_at) as dol 
    FROM dealer_listings WHERE dealer_id = ? ORDER BY created_at DESC
");
$cars->execute([$user_id]);
$inventory = $cars->fetchAll(PDO::FETCH_ASSOC);

// 4. NAVIGATION LOGIC
$current_page = basename($_SERVER['PHP_SELF']); 
function nav_class($page, $current_page) {
    $base = "flex items-center gap-3 px-4 py-3 transition-all font-bold ";
    return ($page == $current_page) 
        ? $base . "text-indigo-600 bg-indigo-50/50 rounded-2xl" 
        : $base . "text-slate-400 hover:text-indigo-600";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoLog Terminal | <?= htmlspecialchars($d['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; color: #0F172A; }
        .glass { background: white; border: 1px solid #F1F5F9; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.04); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        #mobile-sidebar { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hidden-sidebar { transform: translateX(-100%); }
    </style>
</head>
<body class="antialiased overflow-x-hidden">

<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/40 z-40 hidden backdrop-blur-sm lg:hidden" onclick="toggleSidebar()"></div>

<div class="flex min-h-screen relative">
    
    <aside id="mobile-sidebar" class="fixed inset-y-0 left-0 w-64 bg-white border-r border-slate-100 flex flex-col p-6 z-50 lg:static lg:translate-x-0 hidden-sidebar lg:flex shadow-2xl lg:shadow-none">
        <div class="flex items-center justify-between mb-10">
    <div class="flex items-center gap-3">
        <div class="relative">
            <div class="w-10 h-10 rounded-full border-2 border-slate-900 bg-white overflow-hidden flex items-center justify-center p-0.5 shadow-lg">
                <img src="/assets/toplogo.png" alt="AutoLog" class="w-full h-full object-contain">
            </div>
            
            <?php if($d['verified'] == 1): ?>
                <div class="absolute -top-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 border-2 border-white rounded-full shadow-sm z-10"></div>
            <?php elseif($d['verified'] == 2): ?>
                <div class="absolute -top-0.5 -right-0.5 w-3.5 h-3.5 bg-amber-500 border-2 border-white rounded-full shadow-sm z-10"></div>
            <?php endif; ?>
        </div>

        <span class="font-black text-xl tracking-tighter italic uppercase italic">Auto<span class="text-indigo-600">Log</span></span>
    </div>
    
    <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 p-2 hover:text-slate-900 transition-colors">
        <i class="fas fa-times"></i>
    </button>
</div>

        
<nav class="space-y-1 flex-1">
    <a href="dealer.php" class="<?= nav_class('dealer.php', $current_page) ?>">
        <i class="fas fa-layer-group text-sm"></i> Dashboard
    </a>
    <a href="inventory.php" class="<?= nav_class('inventory.php', $current_page) ?>">
        <i class="fas fa-car text-sm"></i> Inventory
    </a>
    
    <a href="../feeds/index.php" class="<?= nav_class('index.php', $current_page) ?>">
        <i class="fas fa-users text-sm"></i> Community
    </a>

    <a href="showroom.php" class="<?= nav_class('showroom.php', $current_page) ?>">
        <i class="fas fa-store text-sm"></i> Showroom
    </a>
    <a href="engagement.php" class="<?= nav_class('engagement.php', $current_page) ?>">
        <i class="fas fa-chart-line text-sm"></i> Engagement
    </a>
    <a href="leads.php" class="<?= nav_class('leads.php', $current_page) ?>">
        <i class="fas fa-comment-dots text-sm"></i> Leads
    </a>
    <a href="settings.php" class="<?= nav_class('settings.php', $current_page) ?>">
        <i class="fas fa-cog text-sm"></i> Settings
    </a>
</nav>

        <div class="pt-6 border-t border-slate-50">
            <a href="../auth/logout.php" class="text-slate-400 font-bold text-sm flex items-center gap-3 px-4 hover:text-rose-500 transition-colors">
                <i class="fas fa-power-off text-xs"></i> Logout
            </a>
        </div>
    </aside>

    <main class="flex-1 w-full overflow-x-hidden">
        
        <header class="glass sticky top-0 z-30 px-6 py-4 flex justify-between items-center lg:bg-transparent lg:border-none lg:shadow-none">
    <div class="lg:hidden flex items-center gap-3">
        <a href="/index.php" class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-500 shadow-sm active:scale-95 transition-all">
            <i class="fas fa-arrow-left text-[10px]"></i>
        </a>

        <div class="flex items-center gap-2">
            <div class="w-9 h-9 rounded-full border-2 border-slate-900 overflow-hidden flex items-center justify-center bg-white p-0.5 shadow-sm">
                <img src="/assets/toplogo.png" alt="AutoLog Logo" class="w-full h-full object-contain rounded-full">
            </div>
            <div class="flex flex-col leading-none">
                <span class="font-black tracking-tighter uppercase text-[10px] italic">Auto<span class="text-red-600">Log</span></span>
                <span class="text-[6px] font-bold text-slate-400 uppercase tracking-widest">Network</span>
            </div>
        </div>
    </div>
    
    <div class="hidden lg:block">
        <h1 class="text-2xl font-black tracking-tight italic uppercase">Agency <span class="text-indigo-600">Hub</span></h1>
    </div>

    <div class="flex items-center gap-3">
        <a href="dealer-profile.php?id=<?= $user_id ?>" target="_blank" class="flex items-center gap-2 bg-white border border-slate-100 text-slate-900 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm hover:bg-slate-50 transition-all">
            <span class="hidden sm:inline">Public View</span>
            <span class="sm:hidden text-[9px]">View</span>
            <i class="fas fa-external-link-alt opacity-40 text-[9px]"></i>
        </a>
        
        <button onclick="toggleSidebar()" class="lg:hidden w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center text-white active:scale-95">
            <i class="fas fa-bars text-xs"></i>
        </button>
    </div>
</header>


        <div class="p-6 lg:p-10">
            
            <?php if ($d['verified'] == 0): ?>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 p-4 bg-white border-l-4 border-amber-500 rounded-2xl shadow-sm">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-shield-halved text-xs"></i>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 leading-none mb-1">Status: Unverified</p>
                <h4 class="text-xs font-bold text-slate-900 uppercase italic">Verification required to unlock premium badges</h4>
            </div>
        </div>
        <a href="verify.php" class="px-6 py-2.5 bg-slate-900 text-white rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-lg shadow-slate-200">
            Verify Now
        </a>
    </div>

<?php elseif ($d['verified'] == 2): ?>
    <div class="mb-6 flex items-center justify-between p-4 bg-slate-900 rounded-2xl border border-slate-800 shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-white/10 text-amber-400 rounded-xl flex items-center justify-center animate-pulse">
                <i class="fas fa-hourglass-start text-xs"></i>
            </div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Compliance Review in Progress <span class="text-white/20 ml-2">|</span> <span class="text-white">ETA 48H</span></p>
        </div>
    </div>
<?php endif; ?>


            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                <div>
                    <h2 class="text-3xl lg:text-5xl font-[900] tracking-tighter leading-none mb-2"><?= explode(' ', $d['name'])[0] ?> Dashboard.</h2>
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-[0.2em]">Management Terminal</p>
                </div>
                <a href="inventory.php?action=add" class="w-full md:w-auto bg-indigo-600 text-white px-8 py-5 rounded-[2rem] font-black text-[10px] uppercase tracking-widest shadow-2xl shadow-indigo-200 active:scale-95 transition-all text-center">
                    + Register New Unit
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-10">
                <div class="glass p-8 rounded-[2.5rem] relative overflow-hidden">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Portfolio Value</p>
                    <h3 class="text-3xl font-black italic tracking-tighter">Ksh <?= number_format(($stats['stock_value'] ?? 0) / 1000000, 1) ?>M</h3>
                    <div class="mt-4 flex items-center gap-2">
                        <span class="text-[10px] bg-emerald-50 text-emerald-600 px-2 py-1 rounded-lg font-black uppercase"><?= $stats['total_units'] ?? 0 ?> Units</span>
                    </div>
                </div>

                <div class="glass p-8 rounded-[2.5rem]">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Inventory Age</p>
                    <h3 class="text-3xl font-black italic tracking-tighter"><?= round($stats['avg_dol'] ?? 0) ?> <span class="text-sm font-bold uppercase opacity-40">Days</span></h3>
                    <div class="w-full bg-slate-100 h-1.5 mt-5 rounded-full">
                        <div class="bg-indigo-600 h-full rounded-full" style="width: 45%"></div>
                    </div>
                </div>

                <div class="glass p-8 rounded-[2.5rem]">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Response Efficiency</p>
                    <h3 class="text-3xl font-black italic tracking-tighter"><?= round($ls['resp_time'] ?? 0) ?><span class="text-sm font-bold uppercase opacity-40">m</span></h3>
                    <p class="text-[9px] font-black text-indigo-500 uppercase mt-4 italic">Top 5% of Network</p>
                </div>

                <div class="bg-slate-950 p-8 rounded-[2.5rem] text-white shadow-2xl shadow-slate-200">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Conversion Rate</p>
                    <h3 class="text-3xl font-black italic tracking-tighter text-indigo-400"><?= number_format($conv_rate, 1) ?>%</h3>
                    <p class="text-[10px] font-bold text-slate-400 mt-4 uppercase"><?= $ls['won'] ?? 0 ?> Handshakes Closed</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6 mb-10">
                <div class="bg-white border border-slate-100 p-6 rounded-[2rem] flex items-center justify-between">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Store Views</p>
                        <h4 class="text-2xl font-black"><?= number_format($engagement['p_views'] ?? 0) ?></h4>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center"><i class="fas fa-fingerprint"></i></div>
                </div>
                <div class="bg-white border border-slate-100 p-6 rounded-[2rem] flex items-center justify-between">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Car Interactions</p>
                        <h4 class="text-2xl font-black"><?= number_format($engagement['c_views'] ?? 0) ?></h4>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center"><i class="fas fa-eye"></i></div>
                </div>
                <div class="bg-indigo-700 p-6 rounded-[2.5rem] flex flex-col justify-between text-white shadow-xl shadow-indigo-100 relative overflow-hidden">
                    <i class="fas fa-comment-nodes absolute -right-2 -bottom-2 text-white/5 text-6xl"></i>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-[9px] font-black opacity-60 uppercase tracking-widest">Direct Conversions</p>
                            <h4 class="text-3xl font-black italic tracking-tighter"><?= number_format($total_social) ?></h4>
                        </div>
                        <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center"><i class="fas fa-bolt text-xs"></i></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <div class="bg-white/10 p-2 rounded-xl text-center">
                            <p class="text-[8px] font-black opacity-60 uppercase">WhatsApp</p>
                            <p class="text-sm font-black"><?= number_format($engagement['wa_clicks'] ?? 0) ?></p>
                        </div>
                        <div class="bg-white/10 p-2 rounded-xl text-center">
                            <p class="text-[8px] font-black opacity-60 uppercase">Calls</p>
                            <p class="text-sm font-black"><?= number_format($engagement['call_clicks'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                <div class="xl:col-span-2 glass rounded-[2.5rem] overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
                        <h2 class="font-black text-slate-800 text-[10px] uppercase tracking-[0.3em] italic">Stock Performance Ledger</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[600px]">
                            <thead class="bg-slate-50/50 text-[9px] font-black text-slate-400 uppercase tracking-[2px]">
                                <tr>
                                    <th class="px-8 py-5">Asset Detail</th>
                                    <th class="px-8 py-5">Market Pulse</th>
                                    <th class="px-8 py-5 text-right">Liquidity Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach($inventory as $car): 
                                    $vCount = $car_views_map[$car['id']] ?? 0;
                                ?>
                                <tr class="group hover:bg-slate-50/80 transition-all cursor-pointer">
                                    <td class="px-8 py-6 flex items-center gap-5">
                                        <div class="w-16 h-12 bg-slate-100 rounded-xl overflow-hidden shrink-0 border border-slate-200">
                                            <img src="../<?= $car['main_image'] ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-slate-900 leading-tight"><?= $car['make'] ?> <?= $car['model'] ?></p>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase mt-1"><?= $car['year'] ?> • <?= $car['transmission'] ?></p>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-black text-slate-800"><?= $vCount ?> Views</span>
                                                <span class="text-[9px] font-black uppercase <?= $car['dol'] > 30 ? 'text-orange-500' : 'text-emerald-500' ?>">
                                                    <?= $car['dol'] ?> Days Old
                                                </span>
                                            </div>
                                            <div class="flex-1 w-16 bg-slate-100 h-1 rounded-full overflow-hidden">
                                                <div class="bg-indigo-600 h-full transition-all" style="width: <?= min($vCount * 5, 100) ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <p class="text-sm font-black text-slate-900 tracking-tighter">Ksh <?= number_format($car['price']) ?></p>
                                        <p class="text-[9px] font-black text-emerald-500 uppercase mt-1">Est. Margin: +<?= number_format($car['price'] - ($car['cost_price'] ?? 0)) ?></p>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="glass p-8 rounded-[2.5rem]">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-8 text-center">Pipeline Integrity</h3>
                        <div class="space-y-4">
                            <?php 
                            $funnel = [
                                ['Hot Leads', $ls['s_hot'], 'bg-rose-600', '100%'],
                                ['Following Up', $ls['s_fup'], 'bg-indigo-600', '85%'],
                                ['Test Drives', $ls['s_td'], 'bg-blue-600', '70%'],
                                ['Closed Deals', $ls['won'], 'bg-emerald-500', '55%']
                            ];
                            foreach($funnel as $f): 
                            ?>
                            <div class="flex flex-col items-center">
                                <div class="<?= $f[2] ?> h-11 rounded-2xl flex items-center justify-center text-white text-[9px] font-black uppercase shadow-lg shadow-indigo-50/10 transition-all hover:scale-[1.03]" style="width: <?= $f[3] ?>">
                                    <?= $f[0] ?> (<?= $f[1] ?? 0 ?>)
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bg-slate-900 p-10 rounded-[2.5rem] text-white shadow-2xl shadow-slate-200 relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:rotate-12 transition-transform duration-700">
                             <i class="fas fa-terminal text-8xl"></i>
                        </div>
                        <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-6">Terminal Shortcuts</h3>
                        <div class="grid grid-cols-1 gap-3 relative z-10">
                            <a href="inventory.php" class="bg-white/10 hover:bg-indigo-600 p-4 rounded-2xl text-[10px] font-black uppercase transition-all flex items-center justify-center gap-3">
                                <i class="fas fa-car-side text-xs"></i> Inventory
                            </a>
                            <a href="showroom.php" class="bg-white/10 hover:bg-indigo-600 p-4 rounded-2xl text-[10px] font-black uppercase transition-all flex items-center justify-center gap-3">
                                <i class="fas fa-store text-xs"></i> Showroom
                            </a>
                            <a href="leads.php" class="bg-white/10 hover:bg-indigo-600 p-4 rounded-2xl text-[10px] font-black uppercase transition-all flex items-center justify-center gap-3">
                                <i class="fas fa-comment-dots text-xs"></i> Leads Management
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mobile-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('hidden-sidebar');
        overlay.classList.toggle('hidden');
    }
</script>

</body>
</html>
