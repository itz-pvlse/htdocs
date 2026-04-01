<?php
// 1. SYSTEM SETTINGS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. SESSION & DB
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config/db.php';

// 3. AUTHENTICATION (MUST BE BEFORE ANY HTML OUTPUT)
// We check auth_check.php first, but if you have a custom redirect here, it must be at the top.
if (!isset($_SESSION['user_id'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $current_page = $_SERVER['REQUEST_URI']; 
    $full_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $current_page;
    header("Location: login.php?redirect=" . urlencode($full_url));
    exit;
}

$user_id = $_SESSION['user_id'];

// 4. FETCH DATA
$garage_id = $_GET['garage_id'] ?? null; 
$query = "
    SELECT 
        sr.*, 
        g.name as garage_name, g.location as garage_loc, g.logo as garage_logo,
        v.plate_no, v.make as v_make, v.model as v_model, v.year as v_year
    FROM service_requests sr 
    JOIN garages g ON sr.garage_id = g.id 
    LEFT JOIN vehicles v ON sr.vehicle_id = v.id 
    WHERE sr.user_id = ?
";

if ($garage_id) {
    $query .= " AND sr.garage_id = ? ORDER BY sr.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $garage_id]);
} else {
    $query .= " ORDER BY sr.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
}
$bookings = $stmt->fetchAll();

// 5. NAVIGATION LOGIC
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (empty($referer) || strpos($referer, 'bookings.php') !== false) {
    $back_url = 'index.php';
    $back_label = 'Back to Explore';
} else {
    $back_url = $referer;
    $back_label = (strpos($referer, 'garage_profile') !== false) ? 'Back to Garage' : 'Back to Explore';
}

include 'includes/header.php'; 
include 'includes/modals.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Service Log | AutoLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; padding-bottom: 120px; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-900 antialiased">

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8 sm:mb-12 gap-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <div class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse shadow-[0_0_8px_rgba(129,140,248,0.8)]"></div>
                <p class="text-[10px] font-black text-indigo-300 uppercase tracking-[0.4em]">User Dashboard</p>
            </div>
            <h1 class="text-3xl sm:text-4xl font-black italic uppercase tracking-tighter text-white leading-none">
                Service <span class="text-indigo-500">Log</span>
            </h1>
        </div>
        
        <button onclick="smartBack('<?= htmlspecialchars($back_url) ?>')" 
                class="inline-flex items-center gap-3 px-6 py-3 rounded-full bg-white/5 border border-white/10 text-[10px] font-black uppercase tracking-widest text-white hover:bg-white/10 transition-all backdrop-blur-md shadow-2xl active:scale-95 w-fit cursor-pointer">
            <i class="fas fa-chevron-left text-[8px] text-indigo-400"></i> <?= $back_label ?>
        </button>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-10">
        <div class="bg-white border-[3px] border-indigo-500/10 p-5 rounded-[2rem] shadow-[0_20px_40px_rgba(0,0,0,0.3)] relative overflow-hidden flex flex-col justify-end transition-all">
            <i class="fa-solid fa-clipboard-list absolute -top-1 -right-1 text-5xl text-slate-900 opacity-[0.06] -rotate-12"></i>
            
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1 relative z-10">Total Requests</p>
            <p class="text-2xl font-black italic text-slate-900 relative z-10"><?= count($bookings) ?></p>
        </div>

        <div class="bg-indigo-600 p-5 rounded-[2rem] shadow-[0_20px_40px_rgba(79,70,229,0.2)] relative overflow-hidden flex flex-col justify-end">
            <i class="fa-solid fa-clock absolute -top-1 -right-1 text-5xl text-white opacity-10 -rotate-12"></i>
            
            <p class="text-[9px] font-black text-indigo-100 uppercase tracking-widest mb-1 relative z-10">Active Now</p>
            <p class="text-2xl font-black italic text-white relative z-10">
                <?= count(array_filter($bookings, fn($b) => strtolower($b['status']) == 'pending')) ?>
            </p>
        </div>
    </div>

    

<script>
// Implementing the Smart Back function
function smartBack(fallbackUrl) {
    if (document.referrer && document.referrer.includes(window.location.hostname)) {
        window.history.back();
    } else {
        window.location.href = fallbackUrl;
    }
}
</script>


    <div id="service-cards-container" class="space-y-4 sm:space-y-6">
        <?php if (empty($bookings)): ?>
            <div class="bg-white rounded-[3rem] p-16 text-center border-2 border-dashed border-slate-200">
                <p class="text-slate-400 font-bold uppercase text-xs tracking-widest">No history found</p>
            </div>
        <?php else: ?>
            <?php foreach ($bookings as $b): 
                $status_cfg = [
                    'approved' => ['color' => 'bg-emerald-500', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'icon' => 'fa-check-circle'],
                    'pending'  => ['color' => 'bg-amber-500', 'bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'icon' => 'fa-clock'],
                    'rejected' => ['color' => 'bg-rose-500', 'bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'icon' => 'fa-times-circle'],
                    'completed' => ['color' => 'bg-indigo-500', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'icon' => 'fa-flag-checkered']
                ];
                $cur = $status_cfg[strtolower($b['status'])] ?? ['color' => 'bg-slate-400', 'bg' => 'bg-slate-50', 'text' => 'text-slate-500', 'icon' => 'fa-info-circle'];
            ?>
                <div class="service-card bg-white rounded-[2rem] sm:rounded-[2.5rem] p-5 sm:p-8 border border-slate-200/60 shadow-sm hover:shadow-2xl hover:shadow-indigo-500/10 hover:border-indigo-500/30 transition-all duration-500 group relative overflow-hidden" 
                     data-status="<?= $b['status'] ?>" 
                     data-garage="<?= htmlspecialchars($b['garage_name']) ?>">
                    
                    <div class="absolute top-0 left-0 w-1 h-full <?= $cur['color'] ?>"></div>
                    
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
                        <div class="flex items-center gap-4 sm:gap-6">
                            <div class="relative">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl sm:rounded-3xl bg-slate-900 overflow-hidden flex items-center justify-center shadow-lg group-hover:rotate-6 transition-transform">
                                    <?php 
                                        $logoFile = !empty($b['garage_logo']) ? 'uploads/logo/' . $b['garage_logo'] : '';
                                        if (!empty($logoFile) && file_exists(__DIR__ . '/' . $logoFile)): 
                                    ?>
                                        <img src="<?= htmlspecialchars($logoFile) ?>" alt="Logo" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <span class="text-xl font-black text-white italic"><?= strtoupper(substr($b['garage_name'], 0, 1)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="absolute -top-2 -right-2 w-6 h-6 rounded-full <?= $cur['bg'] ?> <?= $cur['text'] ?> flex items-center justify-center text-[10px] border-2 border-white shadow-sm z-10">
                                    <i class="fas <?= $cur['icon'] ?>"></i>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-[8px] font-black uppercase tracking-[0.2em] text-slate-400">Ref: #SR-<?= $b['id'] ?></span>
                                    <span class="w-1 h-1 rounded-full bg-slate-200"></span>
                                    <span class="text-[9px] font-black uppercase tracking-widest <?= $cur['text'] ?>"><?= $b['status'] ?></span>
                                </div>
                                <h3 class="text-lg sm:text-xl font-black italic uppercase text-slate-900 tracking-tight group-hover:text-indigo-600 transition-colors">
                                    <?= htmlspecialchars($b['requested_service']) ?>
                                </h3>
                                
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-slate-400">
                                    <?php if($b['plate_no']): ?>
                                    <span class="text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5 text-slate-600">
                                        <i class="fas fa-car text-[8px] text-indigo-500"></i> 
                                        <?= htmlspecialchars($b['v_make'] . ' ' . $b['v_model']) ?>
                                        <span class="bg-slate-900 text-white px-1.5 py-0.5 rounded-[4px] text-[8px] font-black tracking-tight leading-none border border-slate-700">
                                            <?= htmlspecialchars($b['plate_no']) ?>
                                        </span>
                                    </span>
                                    <?php endif; ?>

                                    <span class="text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-warehouse text-[8px] text-indigo-500"></i> <?= htmlspecialchars($b['garage_name']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="w-full sm:w-auto pt-4 sm:pt-0 border-t sm:border-t-0 border-slate-50 flex items-center justify-between sm:justify-center gap-4">
                            <div class="sm:hidden flex flex-col">
                                <span class="text-[8px] font-black text-slate-300 uppercase">Date</span>
                                <span class="text-[10px] font-bold text-slate-900 uppercase italic tracking-tighter"><?= date('M d, Y', strtotime($b['request_date'])) ?></span>
                            </div>
                            <button onclick='openDetails(<?= json_encode([
                                "id" => $b["id"],
                                "service" => $b["requested_service"],
                                "garage" => $b["garage_name"],
                                "location" => $b["garage_loc"],
                                "status" => $b["status"],
                                "date" => date("D, M d, Y", strtotime($b["request_date"])),
                                "logo" => $b["garage_logo"],
                                "v_info" => ($b["v_make"] ?? "N/A") . " " . ($b["v_model"] ?? ""),
                                "v_plate" => $b["plate_no"] ?? "---",
                                "v_year" => $b["v_year"] ?? ""
                            ]) ?>)'
                            class="flex-1 sm:flex-none px-6 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] hover:bg-indigo-600 active:scale-95 transition-all shadow-xl">
                                Details
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[92%] max-w-lg bg-slate-900/95 backdrop-blur-2xl border border-white/10 p-2 rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.3)] flex justify-between items-center z-50">
    
    <a href="garages.php" class="flex-1 flex flex-col items-center gap-1 text-slate-500 hover:text-white transition-colors">
        <i class="fas fa-compass text-lg"></i>
        <span class="text-[8px] font-black uppercase tracking-widest">Explore</span>
    </a>

  <?php
// At the top of your page, determine if a garage_id exists in the current session or URL
$current_garage = $_GET['garage_id'] ?? $_SESSION['last_viewed_garage'] ?? null;

// Build the link
$logLink = "vehicle_logbook.php";
if ($current_garage) {
    $logLink .= "?garage_id=" . $current_garage;
}
?>

<a href="<?= $logLink ?>" class="flex-1 flex flex-col items-center gap-1 text-white">
    <div class="relative">
        <i class="fas fa-layer-group text-lg"></i>
        <span class="absolute -top-1 -right-1 w-2 h-2 bg-indigo-500 rounded-full border-2 border-slate-900"></span>
    </div>
    <span class="text-[8px] font-black uppercase tracking-widest">Log</span>
</a>
    <div class="flex-1 flex justify-center -translate-y-6">
        <button onclick="toggleSearch()" class="w-16 h-16 bg-indigo-600 text-white rounded-full shadow-xl shadow-indigo-500/40 border-4 border-slate-900 flex items-center justify-center active:scale-90 hover:scale-105 transition-all group">
            <i class="fas fa-search text-xl group-hover:scale-110 transition-transform"></i>
        </button>
    </div>

   <button onclick="openNotificationModal()" class="flex-1 flex flex-col items-center gap-1 text-slate-500 hover:text-white transition-colors">
    <div class="relative">
        <i class="fas fa-bell text-lg"></i>
        <span class="absolute -top-1 -right-1 w-2 h-2 bg-rose-500 rounded-full border-2 border-slate-900"></span>
    </div>
    <span class="text-[8px] font-black uppercase tracking-widest">Alerts</span>
</button>

    <a href="dashboard.php" class="flex-1 flex flex-col items-center gap-1 text-slate-500 hover:text-white transition-colors">
        <i class="fas fa-user-circle text-lg"></i>
        <span class="text-[8px] font-black uppercase tracking-widest">Profile</span>
    </a>
</div>

<div id="mobile-search-overlay" class="fixed inset-0 z-[70] hidden items-start justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/90 backdrop-blur-lg" onclick="toggleSearch()"></div>
    <div class="relative w-full max-w-lg mt-20 animate-in slide-in-from-top duration-300">
        <div class="bg-white rounded-[2rem] p-2 shadow-2xl flex items-center gap-3 border border-white/20">
            <div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400">
                <i class="fas fa-search"></i>
            </div>
            <input type="text" id="main-search-input" placeholder="Search service or plate..." class="flex-1 bg-transparent border-none outline-none text-sm font-bold text-slate-900" onkeyup="filterServiceLog(this.value)">
            <button onclick="toggleSearch()" class="w-12 h-12 rounded-2xl text-slate-300 hover:text-rose-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>

<div id="modal-details" class="fixed inset-0 z-[80] hidden items-end justify-center">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeDetails()"></div>
    <div class="relative w-full max-w-2xl bg-white rounded-t-[3rem] p-8 shadow-2xl transition-transform duration-500 translate-y-full" id="details-sheet">
        <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-8"></div>
        <div id="details-content"></div>
        <div class="mt-8 grid grid-cols-2 gap-4">
            <button onclick="closeDetails()" class="py-4 rounded-2xl bg-slate-100 text-slate-500 text-[10px] font-black uppercase tracking-widest">Close</button>
            <a id="directions-link" href="#" target="_blank" class="py-4 rounded-2xl bg-indigo-600 text-white text-[10px] font-black uppercase tracking-widest text-center">Get Directions</a>
        </div>
    </div>
</div>

<script>
function toggleSearch() {
    const overlay = document.getElementById('mobile-search-overlay');
    if (overlay.classList.contains('hidden')) {
        overlay.classList.remove('hidden'); overlay.classList.add('flex');
        document.body.style.overflow = 'hidden';
    } else {
        overlay.classList.add('hidden'); overlay.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

function filterServiceLog(query) {
    const cards = document.querySelectorAll('.service-card');
    cards.forEach(card => {
        const text = card.innerText.toLowerCase();
        card.style.display = text.includes(query.toLowerCase()) ? 'block' : 'none';
    });
}

function openDetails(data) {
    const modal = document.getElementById('modal-details');
    const sheet = document.getElementById('details-sheet');
    const content = document.getElementById('details-content');
    const directions = document.getElementById('directions-link');

    const status = data.status.toLowerCase();
    const isApproved = (status === 'approved' || status === 'completed');
    const isReady    = (status === 'completed');
    const isCancelled = (status === 'cancelled' || status === 'rejected');

    const logoSrc = data.logo ? `uploads/logo/${data.logo}` : null;
    const garageInitial = data.garage.charAt(0).toUpperCase();

    content.innerHTML = `
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 rounded-2xl bg-slate-900 flex items-center justify-center overflow-hidden shadow-lg">
                ${logoSrc ? `<img src="${logoSrc}" class="w-full h-full object-cover">` : `<span class="text-white font-black text-xl italic">${garageInitial}</span>`}
            </div>
            <div>
                <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Reference #SR-${data.id}</span>
                <h2 class="text-2xl font-black italic text-slate-900 uppercase tracking-tighter leading-tight">${data.service}</h2>
            </div>
        </div>
        
        <div class="space-y-4">
            <div class="flex items-center justify-between p-4 rounded-2xl bg-indigo-50/50 border border-indigo-100/50">
                <div>
                    <p class="text-[8px] font-black text-indigo-400 uppercase tracking-[0.2em]">Registered Vehicle</p>
                    <p class="text-xs font-black italic text-slate-900 uppercase">${data.v_info} ${data.v_year}</p>
                </div>
                <div class="px-3 py-1 bg-white border-2 border-slate-900 rounded-lg shadow-sm">
                    <span class="text-[10px] font-black tracking-tighter">${data.v_plate}</span>
                </div>
            </div>

            <div class="p-6 rounded-[2rem] bg-slate-50 border border-slate-100">
                <div class="flex justify-between items-center px-2">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-3 h-3 rounded-full ${isCancelled ? 'bg-rose-500' : 'bg-indigo-600'} ring-4 ${isCancelled ? 'ring-rose-50' : 'ring-indigo-50'}"></div>
                        <span class="text-[7px] font-black uppercase ${isCancelled ? 'text-rose-500' : 'text-indigo-600'}">Sent</span>
                    </div>
                    <div class="h-[2px] flex-1 mx-2 ${isApproved ? 'bg-indigo-600' : 'bg-slate-200'}"></div>
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-3 h-3 rounded-full ${isApproved ? 'bg-indigo-600' : 'bg-slate-200'}"></div>
                        <span class="text-[7px] font-black uppercase">Approved</span>
                    </div>
                    <div class="h-[2px] flex-1 mx-2 ${isReady ? 'bg-emerald-500' : 'bg-slate-200'}"></div>
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-3 h-3 rounded-full ${isReady ? 'bg-emerald-500' : 'bg-slate-200'}"></div>
                        <span class="text-[7px] font-black uppercase">Ready</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Garage</span>
                    <p class="text-xs font-bold text-slate-900 truncate mt-1">${data.garage}</p>
                </div>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Date</span>
                    <p class="text-xs font-bold text-slate-900 mt-1">${data.date}</p>
                </div>
            </div>
            ${status === 'pending' ? `<button onclick="confirmCancel(${data.id})" class="w-full mt-4 py-4 rounded-2xl border-2 border-rose-50 text-rose-500 text-[10px] font-black uppercase tracking-widest hover:bg-rose-50 transition-colors">Cancel Request</button>` : ''}
        </div>
    `;

    directions.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(data.location)}`;
    modal.classList.remove('hidden'); modal.classList.add('flex');
    setTimeout(() => sheet.classList.remove('translate-y-full'), 10);
    document.body.style.overflow = 'hidden';
}

function closeDetails() {
    const modal = document.getElementById('modal-details');
    const sheet = document.getElementById('details-sheet');
    sheet.classList.add('translate-y-full');
    setTimeout(() => { modal.classList.add('hidden'); document.body.style.overflow = ''; }, 400);
}

async function confirmCancel(id) {
    if (!confirm("Cancel this request?")) return;
    try {
        const response = await fetch('cancel_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_id=${id}`
        });
        const result = await response.json();
        if (result.status === 'success') location.reload();
        else alert(result.message);
    } catch (e) { alert("Error connecting to server"); }
}

function smartBack(fallbackUrl) {
    if (window.history.length > 1 && document.referrer !== "") window.history.back();
    else window.location.href = fallbackUrl;
}
</script>
</body>
</html>