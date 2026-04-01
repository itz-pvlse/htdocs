

<?php
/**
 * GarageOS - Master Terminal Dashboard (dealer/dealer.php)
 * Logic: Synchronized with Trust Center, Inventory, and Multi-Tech Job Floor.
 */

// 1. SYSTEM SETTINGS
ini_set('session.gc_probability', 0); 
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED); 
ini_set('display_errors', 0); 

// 2. SESSION & DB
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config/db.php';

// 3. AUTHENTICATION
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: login.php");
    exit();
}

$session_user_id = $_SESSION['user_id'];
$stmt_link = $pdo->prepare("SELECT garage_id FROM users WHERE id = ?");
$stmt_link->execute([$session_user_id]);
$garage_id = $stmt_link->fetchColumn() ?: $session_user_id; // Fallback to user_id if link is missing


// 4. DATA TELEMETRY (REAL-TIME AGGREGATION)
try {
    // Fetch Base Profile & Trust Status
    $stmt = $pdo->prepare("SELECT name, logo, is_verified, is_pending, latitude, longitude FROM garages WHERE id = ?");
    $stmt->execute([$garage_id]);
    $garage = $stmt->fetch(PDO::FETCH_ASSOC);

    // Bookings Count
    $pending_count = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE garage_id = ? AND status = 'pending'");
    $pending_count->execute([$garage_id]);
    $pending_count = $pending_count->fetchColumn() ?: 0;

    // Inventory Deficits
    $low_stock = $pdo->prepare("SELECT COUNT(*) FROM parts_inventory WHERE garage_id = ? AND quantity <= reorder_level");
    $low_stock->execute([$garage_id]);
    $low_stock = $low_stock->fetchColumn() ?: 0;

    // Crew Stats
    $total_mechanics = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'mechanic' AND garage_id = ?");
    $total_mechanics->execute([$garage_id]);
    $total_mechanics = $total_mechanics->fetchColumn() ?: 0;

    // Fetch real crew data from the users table
$crew_stmt = $pdo->prepare("
    SELECT name, avatar 
    FROM users 
    WHERE garage_id = ? AND role = 'mechanic' 
    LIMIT 5
");
$crew_stmt->execute([$garage_id]);
$crew_members = $crew_stmt->fetchAll(PDO::FETCH_ASSOC);
$total_crew = count($crew_members);


// --- 1. REACH & LOYALTY STATS ---
$stmtTotalUsers = $pdo->prepare("SELECT COUNT(*) FROM user_garages WHERE garage_id = ?");
$stmtTotalUsers->execute([$garage_id]);
$total_reach = $stmtTotalUsers->fetchColumn() ?: 0;

$stmtTotalPref = $pdo->prepare("SELECT COUNT(*) FROM user_garages WHERE garage_id = ? AND is_preferred = 1");
$stmtTotalPref->execute([$garage_id]);
$total_loyal = $stmtTotalPref->fetchColumn() ?: 0;

// Calculate "Reputation" based on conversion %
$reputation = ($total_reach > 0) ? round(($total_loyal / $total_reach) * 100) : 0;


    // Utilization Logic (Busy Mechanics)
    $busy_mechanics = $pdo->prepare("
        SELECT COUNT(DISTINCT ja.mechanic_id) 
        FROM job_assignments ja
        JOIN jobs j ON ja.job_id = j.id
        JOIN users u ON ja.mechanic_id = u.id
        WHERE u.garage_id = ? AND j.status = 'ongoing'
    ");
    $busy_mechanics->execute([$garage_id]);
    $busy_mechanics = $busy_mechanics->fetchColumn() ?: 0;

    // Floor Telemetry (Live Jobs)
    $stmt = $pdo->prepare("
        SELECT 
            j.id, v.plate_no, v.make, v.model, sr.requested_service, j.created_at,
            (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') 
             FROM job_assignments ja 
             JOIN users u ON ja.mechanic_id = u.id 
             WHERE ja.job_id = j.id) as techs
        FROM jobs j
        JOIN service_requests sr ON j.request_id = sr.id
        JOIN vehicles v ON sr.vehicle_id = v.id
        WHERE sr.garage_id = ? AND j.status = 'ongoing'
        ORDER BY j.created_at DESC LIMIT 5
    ");
    $stmt->execute([$garage_id]);
    $live_jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Error handling silent or logged
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>GarageOS Terminal | <?= htmlspecialchars($garage['name'] ?? 'Dashboard') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&family=JetBrains+Mono&display=swap');
        :root { --primary: #4F46E5; --accent: #0F172A; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: radial-gradient(circle at top right, #F8FAFC, #EFF6FF); color: #0F172A; overflow-x: hidden; margin: 0; }
        
        #sidebar { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); transform: translateX(-100%); background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
        #sidebar.open { transform: translateX(0); }
        #main-content { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); width: 100%; }

        @media (min-width: 1024px) { .sidebar-open #main-content { margin-left: 18rem; width: calc(100% - 18rem); } }

        .sidebar-link { transition: all 0.3s ease; border-radius: 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1rem; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        .sidebar-link:hover { background: rgba(15, 23, 42, 0.05); color: #0f172a; }
        .sidebar-link.active { background: #0f172a; color: white; box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.3); }
        
        .bento-card { background: white; border: 1px solid rgba(226, 232, 240, 0.8); border-radius: 2.5rem; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .bento-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.05); }

        .action-pill { transition: all 0.2s ease; border: 1px solid #E2E8F0; }
        .action-pill:hover { border-color: var(--primary); background: var(--primary); color: white; transform: scale(1.05); }

        #overlay { transition: opacity 0.3s ease; pointer-events: none; opacity: 0; }
        #overlay.active { pointer-events: auto; opacity: 1; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        
        .status-pulse { width: 8px; height: 8px; border-radius: 50%; background: #10B981; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); animation: pulse-green 2s infinite; }
        @keyframes pulse-green { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
    </style>
</head>
<body class="min-h-screen">

    <button onclick="toggleNav()" class="fixed top-6 left-6 z-[100] w-14 h-14 bg-white border border-slate-200 rounded-2xl shadow-2xl flex items-center justify-center text-slate-900 hover:bg-slate-900 hover:text-white transition-all active:scale-90">
        <i class="fa-solid fa-bars-staggered text-xl" id="btn-icon"></i>
    </button>

    <div id="overlay" onclick="toggleNav()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-md z-[40]"></div>

    <aside id="sidebar" class="w-72 border-r border-slate-200/50 flex flex-col p-6 fixed h-full z-[50] bg-white">
        <div class="flex items-center gap-3 mb-10 px-2 mt-24 lg:mt-4">
            <div class="w-12 h-12 bg-slate-900 rounded-2xl flex items-center justify-center overflow-hidden shadow-lg shadow-slate-200">
                <img src="uploads/logo/<?= htmlspecialchars($garage['logo'] ?? '') ?>" class="w-full h-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($garage['name'] ?? 'G') ?>&background=0F172A&color=fff'">
            </div>
            <div>
                <h1 class="font-black italic tracking-tighter text-sm uppercase leading-none"><?= htmlspecialchars($garage['name'] ?? 'Terminal') ?></h1>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest">Master Terminal</span>
                    <?php if(($garage['is_verified'] ?? 0) == 1): ?>
                        <i class="fa-solid fa-circle-check text-[10px] text-emerald-500"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto">
    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-4 px-2">Operations</p>
    
    <a href="index.php" class="sidebar-link active">
        <i class="fa-solid fa-house-chimney-window w-5 text-center"></i>EXIT DASHBOARD
    </a>
    
    <a href="../garage/garage_requests.php" class="sidebar-link justify-between">
        <div class="flex items-center gap-3"><i class="fa-solid fa-bell-concierge w-5 text-center"></i> Bookings</div>
        <?php if($pending_count > 0): ?>
            <span class="bg-indigo-500 text-white text-[10px] px-2 py-0.5 rounded-lg font-black"><?= $pending_count ?></span>
        <?php endif; ?>
    </a>

    <a href="../garage/jobs_mgmt.php" class="sidebar-link">
        <i class="fa-solid fa-microchip w-5 text-center"></i> Active Floor
    </a>

 <a href="feeds/index.php?id=<?= $garage_id ?>" class="sidebar-link group">
    <i class="fa-solid fa-rss w-5 text-center group-hover:text-orange-600 transition-colors"></i> 
    Community Feed
</a>

    <a href="../mechanic/mechanics_section.php" class="sidebar-link">
        <i class="fa-solid fa-user-gear w-5 text-center"></i> Crew
    </a>

    <a href="../garage/admin_parts.php" class="sidebar-link justify-between">
        <div class="flex items-center gap-3"><i class="fa-solid fa-layer-group w-5 text-center"></i> Inventory</div>
        <?php if($low_stock > 0): ?>
            <span class="bg-rose-500 text-white text-[10px] px-2 py-0.5 rounded-lg font-black italic">LOW</span>
        <?php endif; ?>
    </a>

    <div class="my-8 border-t border-slate-100"></div>
    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-4 px-2">System</p>
    
    <a href="../garage/settings.php" class="sidebar-link">
        <i class="fa-solid fa-gears w-5 text-center"></i> Settings
    </a>

    <a href="../garage/verification.php" class="sidebar-link justify-between">
        <div class="flex items-center gap-3"><i class="fa-solid fa-shield-halved w-5 text-center"></i> Trust Center</div>
        <?php if(($garage['is_verified'] ?? 0) == 0): ?>
            <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse shadow-[0_0_10px_rgba(251,191,36,0.5)]"></span>
        <?php endif; ?>
    </a>

    <a href="../garage/profile_mgmt.php" class="sidebar-link">
        <i class="fa-solid fa-sliders w-5 text-center"></i> Profile
    </a>

    <a href="../auth/logout.php" class="sidebar-link text-rose-500 hover:bg-rose-50 mt-10">
        <i class="fa-solid fa-power-off w-5 text-center"></i> Kill Session
    </a>
</nav>

    </aside>

    <main id="main-content" class="p-4 md:p-10 min-h-screen pt-24">
        
        <header class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6 mb-12">
            <div>
                <h2 class="text-3xl md:text-5xl font-black italic tracking-tighter uppercase leading-none mb-2">Command Center</h2>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 bg-slate-900 text-white text-[10px] font-black rounded-lg">LIVE FEED</span>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Station: <?= htmlspecialchars($garage_id) ?>-HQ</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 bg-white/50 backdrop-blur-sm p-2 rounded-3xl border border-slate-200">
                <div class="flex items-center gap-4">
    <div class="hidden md:flex flex-col items-end">
        <p id="liveClock" class="text-[10px] font-black text-slate-900 tabular-nums">00:00:00</p>
        <p class="text-[7px] font-bold text-emerald-500 uppercase tracking-widest flex items-center gap-1">
            <span class="relative flex h-1.5 w-1.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
            </span>
            Terminal Live
        </p>
    </div>
    <button onclick="window.location.reload()" class="w-12 h-12 bg-white border border-slate-200 rounded-2xl flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:border-indigo-100 transition-all shadow-sm group">
        <i class="fa-solid fa-rotate animate-hover group-hover:rotate-180 transition-transform duration-500"></i>
    </button>
</div>

<script>
    // Keep the clock ticking
    setInterval(() => {
        const now = new Date();
        document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-GB');
    }, 1000);
</script>
                <a href="../garage/garage_requests.php" class="w-12 h-12 rounded-2xl flex items-center justify-center text-slate-400 hover:bg-slate-900 hover:text-white transition-all"><i class="fa-solid fa-plus"></i></a>
                <div class="h-8 w-[1px] bg-slate-200 mx-2"></div>
                <div class="pr-4 pl-2">
                    <p class="text-[9px] font-black uppercase text-slate-400 leading-none mb-1">Status</p>
                    <div class="flex items-center gap-2">
                        <div class="status-pulse"></div>
                        <p class="text-xs font-black uppercase italic">Operational</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto">
            
           <div class="flex justify-center items-center bg-white border border-slate-100 rounded-[2.5rem] p-6 mb-10 shadow-sm max-w-2xl mx-auto">
    
    <button onclick="openEngagementModal('saved')" class="flex-1 flex flex-col items-center group transition-all">
        <p class="text-2xl font-black text-slate-900 tracking-tighter italic group-hover:scale-110 transition-transform"><?= number_format($total_reach) ?></p>
        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 group-hover:text-indigo-500 transition-colors">Saved</p>
    </button>
    
    <div class="h-8 w-[1px] bg-slate-100"></div>
    
    <button onclick="openEngagementModal('preferred')" class="flex-1 flex flex-col items-center group transition-all">
        <p class="text-2xl font-black text-slate-900 tracking-tighter italic group-hover:scale-110 transition-transform"><?= number_format($total_loyal) ?></p>
        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 group-hover:text-amber-500 transition-colors">Preferred</p>
    </button>
    
    <div class="h-8 w-[1px] bg-slate-100"></div>
    
    <div class="flex-1 flex flex-col items-center">
        <p class="text-2xl font-black text-emerald-500 tracking-tighter italic"><?= $reputation ?>%</p>
        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">Reputation</p>
    </div>
</div>

<div id="engagementModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-[3rem] overflow-hidden shadow-2xl">
        <div class="p-8 border-b border-slate-50 flex justify-between items-center">
            <h3 id="modalTitle" class="text-xs font-black uppercase tracking-widest text-slate-400 italic">User Engagement</h3>
            <button onclick="closeEngagementModal()" class="text-slate-300 hover:text-rose-500 transition-colors"><i class="fa-solid fa-circle-xmark text-xl"></i></button>
        </div>
        <div id="modalContent" class="max-h-[400px] overflow-y-auto p-6 space-y-4">
            <div class="flex items-center justify-center py-10">
                <i class="fa-solid fa-spinner fa-spin text-slate-200 text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    <?php 
    $actions = [
        ['icon' => 'fa-car-side', 'label' => 'New Repair', 'link' => '../garage/garage_requests.php', 'color' => 'text-indigo-500'],
        ['icon' => 'fa-file-invoice-dollar', 'label' => 'Finances', 'link' => 'garage/finances.php', 'color' => 'text-emerald-500'],
        ['icon' => 'fa-boxes-stacked', 'label' => 'Inventory', 'link' => '../garage/admin_parts.php', 'color' => 'text-orange-500'],
        ['icon' => 'fa-chart-line', 'label' => 'Analytics', 'link' => '#', 'color' => 'text-rose-500']
    ];
    foreach($actions as $act): ?>
    <a href="<?= $act['link'] ?>" class="action-pill bg-white p-4 rounded-3xl flex flex-col items-center justify-center gap-3 group border border-slate-50 hover:bg-slate-900 transition-all shadow-sm hover:shadow-xl">
        <div class="w-10 h-10 rounded-2xl bg-slate-50 flex items-center justify-center <?= $act['color'] ?> group-hover:bg-white/10 group-hover:text-white transition-all">
            <i class="fa-solid <?= $act['icon'] ?> text-lg"></i>
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-slate-700 group-hover:text-white transition-colors"><?= $act['label'] ?></span>
    </a>
    <?php endforeach; ?>
</div>


            <div class="grid grid-cols-12 gap-6 lg:gap-10">
                
                <div class="col-span-12 lg:col-span-8 space-y-8">
                    <div class="bento-card p-8">
                        <div class="flex justify-between items-center mb-10">
                            <div>
                                <h3 class="text-xs font-black uppercase tracking-[0.3em] italic text-slate-400 mb-1">Floor Telemetry</h3>
                                <p class="text-xl font-black italic uppercase">Ongoing Repairs</p>
                            </div>
                            <a href="../garage/jobs_mgmt.php" class="text-[10px] font-black uppercase tracking-widest text-indigo-600 border-b-2 border-indigo-100 hover:border-indigo-600 transition-all">View All Floor</a>
                        </div>

                        <div class="space-y-4">
                            <?php if(empty($live_jobs)): ?>
                                <div class="py-20 text-center text-slate-300 font-bold text-[10px] uppercase border-4 border-dotted border-slate-50 rounded-[3rem]">
                                    System Idle: No Active Repairs
                                </div>
                            <?php else: foreach($live_jobs as $job): 
                                $start = new DateTime($job['created_at']);
                                $diff = $start->diff(new DateTime());
                                $hours = $diff->h + ($diff->days * 24);
                            ?>
                                <div class="group flex flex-col sm:flex-row sm:items-center justify-between p-6 bg-slate-50/50 rounded-[2rem] gap-4 border border-transparent hover:border-slate-200 hover:bg-white transition-all">
                                    <div class="flex items-center gap-5">
                                        <div class="w-14 h-14 bg-white rounded-2xl flex flex-col items-center justify-center shadow-sm border border-slate-100 group-hover:bg-slate-900 group-hover:text-white transition-all">
                                            <span class="text-[8px] font-black text-slate-400 group-hover:text-slate-500 text-center">PLATE</span>
                                            <span class="font-mono text-xs font-black uppercase"><?= htmlspecialchars($job['plate_no']) ?></span>
                                        </div>
                                        <div>
                                            <p class="font-black text-base uppercase italic leading-tight mb-1"><?= htmlspecialchars($job['make']) ?> <?= htmlspecialchars($job['model']) ?></p>
                                            <div class="flex items-center gap-3">
                                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">
                                                    <i class="fa-solid fa-user-ninja mr-1 text-indigo-500"></i> 
                                                    <?= $job['techs'] ? htmlspecialchars($job['techs']) : 'Pending Assign' ?>
                                                </span>
                                                <span class="w-1 h-1 bg-slate-300 rounded-full"></span>
                                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">
                                                    <i class="fa-solid fa-wrench mr-1"></i> <?= htmlspecialchars($job['requested_service']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between sm:justify-end gap-6">
                                        <div class="text-right">
                                            <p class="text-[9px] font-black uppercase text-slate-400 mb-1">Time in Bay</p>
                                            <p class="text-xs font-black italic <?= $hours > 4 ? 'text-rose-500' : 'text-emerald-500' ?>"><?= $hours ?>h <?= $diff->i ?>m</p>
                                        </div>
                                        <a href="../garage/jobs_mgmt.php" class="h-12 w-12 bg-white border border-slate-200 rounded-2xl flex items-center justify-center text-slate-900 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                            <i class="fa-solid fa-chevron-right text-xs"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <div class="bento-card p-8 bg-slate-900 text-white border-none shadow-2xl overflow-hidden relative">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-[80px] -mr-32 -mt-32"></div>
                        <div class="relative z-10">
                            <div class="flex justify-between items-center mb-10">
                                <div>
                                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Net Performance</p>
                                    <h3 class="text-2xl font-black italic uppercase">Revenue Matrix</h3>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-black italic text-emerald-400">+22.4%</p>
                                    <p class="text-[9px] font-bold text-slate-500 uppercase">vs Last Week</p>
                                </div>
                            </div>
                            <div class="flex items-end justify-between gap-2 h-32 overflow-x-auto no-scrollbar">
                                <?php for($i=0; $i<30; $i++): $h = rand(15, 100); ?>
                                    <div class="min-w-[10px] flex-1 bg-slate-800 rounded-full hover:bg-indigo-400 transition-all cursor-help relative group" style="height: <?= $h ?>%">
                                        <div class="absolute -top-10 left-1/2 -translate-x-1/2 bg-white text-slate-900 text-[9px] font-black px-3 py-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-all shadow-2xl whitespace-nowrap z-50">
                                            KES <?= rand(10,50) ?>k
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <div class="flex justify-between mt-6 text-[8px] font-black text-slate-600 uppercase tracking-widest">
                                <span>Cycle Start</span>
                                <span>Peak Phase</span>
                                <span>Current HUD</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 lg:col-span-4 space-y-8">
                    <div class="bento-card p-8 bg-indigo-600 text-white border-none shadow-xl shadow-indigo-100 relative overflow-hidden">
                         <i class="fa-solid fa-triangle-exclamation absolute -bottom-4 -right-4 text-white/10 text-9xl rotate-12"></i>
                         <div class="relative z-10">
                            <p class="text-[10px] font-black text-indigo-200 uppercase tracking-widest mb-8 italic">Supply Chain Alert</p>
                            <div class="flex items-end gap-4 mb-8">
                                <h4 class="text-7xl font-black italic leading-none"><?= $low_stock ?></h4>
                                <p class="text-xs font-bold uppercase text-indigo-100 mb-2">Stock<br>Deficits</p>
                            </div>
                            <a href="../garage/admin_parts.php" class="block w-full text-center bg-white text-indigo-600 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-50 transition-all">Inventory Audit</a>
                         </div>
                    </div>

                    <div class="bento-card p-6 flex items-center justify-between border-l-8 border-orange-500">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Queue Depth</p>
                            <p class="text-4xl font-black italic"><?= $pending_count ?></p>
                        </div>
                        <div class="w-14 h-14 rounded-full bg-orange-50 flex items-center justify-center text-orange-500">
                            <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                        </div>
                    </div>

                    <div class="bento-card p-8">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-8 italic">Utilization Engine</h3>
                        <div class="space-y-8">
                            <?php 
                            $staff_sat = ($total_mechanics > 0 ? round(($busy_mechanics / $total_mechanics) * 100) : 0);
                            $metrics = [
                                ['label' => 'Staff Saturation', 'val' => $staff_sat, 'color' => 'bg-emerald-500', 'icon' => 'fa-user-group'],
                                ['label' => 'Bay Occupancy', 'val' => (count($live_jobs) > 0 ? 75 : 0), 'color' => 'bg-indigo-500', 'icon' => 'fa-warehouse'],
                                ['label' => 'Parts Velocity', 'val' => 62, 'color' => 'bg-rose-500', 'icon' => 'fa-bolt']
                            ];
                            foreach($metrics as $m): ?>
                                <div>
                                    <div class="flex justify-between text-[10px] font-black uppercase mb-3">
                                        <span class="flex items-center gap-2"><i class="fa-solid <?= $m['icon'] ?> text-slate-300"></i> <?= $m['label'] ?></span>
                                        <span class="text-slate-900"><?= $m['val'] ?>%</span>
                                    </div>
                                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full <?= $m['color'] ?> transition-all duration-1000" style="width: <?= $m['val'] ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
<div class="mt-8 pt-6 border-t border-slate-800 flex items-center gap-3">
    <div class="flex -space-x-3">
        <?php foreach($crew_members as $m): 
            // 1. Clean the path. 
            // If database has "uploads/avatar.png", this becomes "../uploads/avatar.png"
            $avatar_url = "" . ltrim($m['avatar'], '/'); 
        ?>
            <div class="w-8 h-8 rounded-full border-2 border-slate-900 bg-slate-700 overflow-hidden ring-1 ring-slate-700">
                <?php if(!empty($m['avatar'])): ?>
                    <img src="<?= $avatar_url ?>" 
                         class="w-full h-full object-cover" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="hidden w-full h-full items-center justify-center text-[8px] font-black bg-slate-700 text-slate-400">
                        <?= strtoupper(substr($m['name'], 0, 1)) ?>
                    </div>
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-[8px] font-black uppercase bg-slate-700 text-slate-400">
                        <?= strtoupper(substr($m['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest">Active Crew</p>
</div>

                    </div>
                </div>
            </div>
        </div>
    </main>

<script>
    // --- SIDEBAR LOGIC ---
    function toggleNav() {
        const sidebar = document.getElementById('sidebar');
        const body = document.body;
        const overlay = document.getElementById('overlay');
        const icon = document.getElementById('btn-icon');

        sidebar.classList.toggle('open');
        body.classList.toggle('sidebar-open');
        overlay.classList.toggle('active');

        if (sidebar.classList.contains('open')) {
            icon.classList.replace('fa-bars-staggered', 'fa-xmark');
        } else {
            icon.classList.replace('fa-xmark', 'fa-bars-staggered');
        }
    }

    // --- SHORTCUTS ---
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            const modal = document.getElementById('engagementModal');
            if (sidebar && sidebar.classList.contains('open')) toggleNav();
            if (modal && !modal.classList.contains('hidden')) closeEngagementModal();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            console.log('Search activated');
        }
    });

    // --- MODAL LOGIC ---
    function openEngagementModal(type) {
        const modal = document.getElementById('engagementModal');
        const title = document.getElementById('modalTitle');
        const content = document.getElementById('modalContent');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        title.innerText = type === 'preferred' ? 'Preferred by Customers' : 'Saved by Customers';
        
        // Show loading spinner while fetching
        content.innerHTML = `
            <div class="flex items-center justify-center py-10">
                <i class="fa-solid fa-spinner fa-spin text-slate-200 text-2xl"></i>
            </div>`;
        
        // Fetch data from backend
        fetch(`garage/get_engagement.php?type=${type}`)
            .then(response => response.text())
            .then(data => {
                content.innerHTML = data;
            })
            .catch(err => {
                content.innerHTML = `<p class="text-center text-[10px] font-black uppercase text-rose-500 py-10">Error loading users</p>`;
            });
    }

    function closeEngagementModal() {
        const modal = document.getElementById('engagementModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Handle clicks inside the modal content
    document.addEventListener('DOMContentLoaded', () => {
        const modalContent = document.getElementById('modalContent');
        if (modalContent) {
            modalContent.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (link) {
                    // Briefly hide modal so it doesn't flicker when navigating
                    closeEngagementModal();
                }
            });
        }
        
        // Close modal when clicking on the overlay backdrop
        const modalOverlay = document.getElementById('engagementModal');
        if (modalOverlay) {
            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) closeEngagementModal();
            });
        }
    });
</script>

</body>
</html>
