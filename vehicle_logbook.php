<?php
// 1. SYSTEM SETTINGS & AUTHENTICATION
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config/db.php';

// PREVENT "HEADERS ALREADY SENT" ERROR: Auth check must happen before any HTML
if (!isset($_SESSION['user_id'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $current_page = $_SERVER['REQUEST_URI']; 
    $full_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $current_page;
    header("Location: login.php?redirect=" . urlencode($full_url));
    exit;
}

$user_id = $_SESSION['user_id'];
$garage_id = $_GET['garage_id'] ?? null; 

// 2. FETCH GARAGE INFO
$g_stmt = $pdo->prepare("SELECT name, location, phone FROM garages WHERE id = ?");
$g_stmt->execute([$garage_id]);
$garage = $g_stmt->fetch();

if (!$garage) {
    die("Invalid Garage ID. Please return to the dashboard.");
}

// 3. FETCH SERVICE HISTORY
$stmt = $pdo->prepare("
    SELECT 
        sr.id as request_id,
        sr.requested_service,
        sr.request_date,
        sr.status as request_status,
        sr.mileage,
        v.make, v.model, v.plate_no,
        u.name as mechanic_name,
        j.mechanic_notes,
        (SELECT SUM(subtotal) FROM job_items WHERE job_id = j.id) as total_spent,
        (SELECT GROUP_CONCAT(
            CONCAT(
                CAST(ji.description AS CHAR CHARACTER SET utf8mb4), 
                ' (KES ', 
                FORMAT(ji.subtotal, 0), 
                ')'
            ) 
            SEPARATOR '||'
         ) 
         FROM job_items ji 
         WHERE ji.job_id = j.id) as service_items
    FROM service_requests sr
    JOIN vehicles v ON sr.vehicle_id = v.id
    LEFT JOIN jobs j ON sr.id = j.request_id
    LEFT JOIN users u ON j.mechanic_id = u.id
    WHERE sr.garage_id = ? AND sr.user_id = ?
    ORDER BY sr.request_date DESC
");

$stmt->execute([$garage_id, $user_id]);
$history = $stmt->fetchAll();

$grand_total = 0;
foreach($history as $row) { $grand_total += ($row['total_spent'] ?? 0); }

// Now safe to include header/modals
include 'includes/header.php'; 
include 'includes/modals.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Logbook | <?= htmlspecialchars($garage['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(180deg, #001F3F 0%, #000000 100%) !important;
            background-attachment: fixed !important;
            min-height: 100vh;
            margin: 0; /* Ensures no default displacement */
        }
        .details-wrapper {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .log-card.open .details-wrapper { grid-template-rows: 1fr; }
        .details-content { overflow: hidden; background: #f8fafc; border-bottom-left-radius: 2.5rem; border-bottom-right-radius: 2.5rem; }
        .chevron { transition: transform 0.4s ease; }
        .log-card.open .chevron { transform: rotate(180deg); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="text-slate-900 antialiased">
    <div class="flex flex-col items-center w-full px-4">
        
        <div class="w-full max-w-xl pb-20">
            
            <div class="mb-8 pt-8 flex flex-col gap-4">
                <a href="javascript:history.back()" class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-white backdrop-blur-md active:scale-95 transition-all">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-black tracking-tight italic uppercase leading-none text-white">Service <span class="text-indigo-500">Logs</span></h1>
                    <p class="text-indigo-300 text-[10px] font-black uppercase tracking-widest mt-2 leading-none">
                        Certified by <span class="text-white"><?= htmlspecialchars($garage['name']) ?></span>
                    </p>
                </div>
            </div>

            <div class="bg-indigo-600 rounded-[2.5rem] p-8 mb-8 text-white shadow-2xl relative overflow-hidden">
                <i class="fas fa-shield-check absolute -right-4 -bottom-4 text-white/10 text-9xl"></i>
                <div class="relative z-10">
                    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-indigo-100 mb-1">Lifetime Maintenance</p>
                    <h2 class="text-4xl font-black italic tracking-tighter">KES <?= number_format($grand_total, 0) ?></h2>
                </div>
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 blur-3xl rounded-full -mr-16 -mt-16"></div>
            </div>

            <div class="sticky top-4 z-50 space-y-4 mb-8">
                <div class="relative group">
                    <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="logSearch" onkeyup="applyFilters()" 
                           placeholder="Search plate or service..." 
                           class="w-full bg-white border-2 border-indigo-500/10 rounded-[2rem] py-4 pl-12 pr-6 text-sm font-bold shadow-2xl outline-none focus:border-indigo-500 transition-all">
                </div>

                <div class="flex gap-2 overflow-x-auto scrollbar-hide pb-2">
                    <button onclick="setStatusFilter('all', this)" class="filter-tab active shrink-0 bg-slate-900 text-white text-[10px] font-black px-6 py-3.5 rounded-2xl uppercase shadow-xl transition-all">All</button>
                    <button onclick="setStatusFilter('completed', this)" class="filter-tab shrink-0 bg-white text-slate-500 text-[10px] font-black px-6 py-3.5 rounded-2xl border border-white/10 uppercase shadow-md transition-all">Completed</button>
                    <button onclick="setStatusFilter('active', this)" class="filter-tab shrink-0 bg-white text-slate-500 text-[10px] font-black px-6 py-3.5 rounded-2xl border border-white/10 uppercase shadow-md transition-all">Active</button>
                    <button onclick="setStatusFilter('cancelled', this)" class="filter-tab shrink-0 bg-white text-slate-500 text-[10px] font-black px-6 py-3.5 rounded-2xl border border-white/10 uppercase shadow-md transition-all">Cancelled</button>
                </div>
            </div>

            <div class="space-y-6" id="logContainer">
                <?php foreach ($history as $log): 
                    $status = strtolower($log['request_status'] ?? '');
                    $filter_cat = ($status === 'completed') ? 'completed' : (($status === 'cancelled' || $status === 'rejected') ? 'cancelled' : 'active');
                    $is_interactable = ($status == 'completed');
                    $badge_class = ($status == 'completed') ? 'bg-emerald-50 text-emerald-600' : ($status == 'cancelled' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600');
                ?>
                <div class="log-card bg-white rounded-[2.5rem] border-l-[6px] border-indigo-500 shadow-xl overflow-hidden" 
                     data-status="<?= $filter_cat ?>" 
                     data-search="<?= strtolower($log['requested_service'] . ' ' . $log['plate_no'] . ' ' . $log['make']) ?>">
                    
                    <div <?= $is_interactable ? 'onclick="toggleAccordion(this.parentElement)"' : '' ?> 
                         class="p-7 <?= $is_interactable ? 'cursor-pointer' : '' ?> transition-colors relative">
                        
                        <i class="fa-solid fa-file-contract absolute -right-4 -bottom-4 text-7xl text-slate-900 opacity-[0.03] -rotate-12"></i>

                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex-1 pr-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400"><?= date('d M Y', strtotime($log['request_date'])) ?></span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[8px] font-black uppercase <?= $badge_class ?>">
                                         <?= $status ?>
                                    </span>
                                </div>
                                <h2 class="text-xl font-black uppercase italic text-slate-900 leading-tight mb-2"><?= htmlspecialchars($log['requested_service']) ?></h2>
                                <div class="flex items-center gap-2">
                                    <span class="bg-slate-900 text-white text-[8px] font-black px-2 py-1 rounded uppercase tracking-widest"><?= htmlspecialchars($log['plate_no']) ?></span>
                                    <span class="text-slate-400 text-[9px] font-bold uppercase italic"><?= htmlspecialchars($log['make']) ?> <?= htmlspecialchars($log['model']) ?></span>
                                </div>
                            </div>
                            <?php if ($is_interactable): ?>
                            <div class="w-10 h-10 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400 chevron shrink-0">
                                <i class="fas fa-chevron-down text-[10px]"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($is_interactable): ?>
                    <div class="details-wrapper">
                        <div class="details-content px-6 pb-8 space-y-4">
                            <div class="h-1"></div>
                            <?php if (!empty($log['mechanic_notes'])): ?>
                            <div class="bg-amber-50 p-4 rounded-2xl border-l-4 border-amber-400">
                                <p class="text-[8px] font-black text-amber-600 uppercase mb-1">Technician Notes</p>
                                <p class="text-[11px] font-bold text-slate-700 italic">"<?= htmlspecialchars($log['mechanic_notes']) ?>"</p>
                            </div>
                            <?php endif; ?>

                            <div class="bg-white p-5 rounded-3xl border border-slate-100">
                                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-3">Service Items</p>
                                <div class="space-y-2">
                                    <?php if($log['service_items']): foreach (explode('||', $log['service_items']) as $item): ?>
                                    <div class="flex items-start text-[10px] font-bold text-slate-600 gap-2">
                                        <i class="fas fa-check-circle text-emerald-500 mt-0.5"></i>
                                        <span><?= htmlspecialchars($item) ?></span>
                                    </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>

                            <div class="bg-slate-900 p-5 rounded-[2rem] flex items-center justify-between">
                                <div><p class="text-[8px] font-bold text-white/50 uppercase mb-1">Total Spent</p><p class="text-lg font-black text-white italic">KES <?= number_format($log['total_spent'] ?? 0, 0) ?></p></div>
                                <div class="text-right"><p class="text-[8px] font-bold text-white/50 uppercase mb-1">Mechanic</p><p class="text-[10px] font-black text-indigo-400 uppercase"><?= htmlspecialchars($log['mechanic_name'] ?? 'N/A') ?></p></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    let currentFilter = 'all';
    function toggleAccordion(card) { card.classList.toggle('open'); }
    function setStatusFilter(filter, btn) {
        currentFilter = filter;
        document.querySelectorAll('.filter-tab').forEach(b => {
            b.classList.remove('bg-slate-900', 'text-white', 'shadow-xl', 'active');
            b.classList.add('bg-white', 'text-slate-500');
        });
        btn.classList.add('bg-slate-900', 'text-white', 'shadow-xl', 'active');
        btn.classList.remove('bg-white', 'text-slate-500');
        applyFilters();
    }
    function applyFilters() {
        const term = document.getElementById('logSearch').value.toLowerCase();
        document.querySelectorAll('.log-card').forEach(card => {
            const status = card.dataset.status;
            const search = card.dataset.search;
            const matchesSearch = search.includes(term);
            const matchesFilter = (currentFilter === 'all' || status === currentFilter);
            card.style.display = (matchesSearch && matchesFilter) ? 'block' : 'none';
        });
    }
    </script>
</body>
</html>
