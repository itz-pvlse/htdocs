<?php
// 1. SESSION & DB LOGIC
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config/db.php';
// require_once 'includes/auth_check.php'; // Ensure this doesn't echo anything before the header

// 2. INCLUDE HEADER (Provides <html>, <head>, and opening <body>)
include 'includes/header.php'; 

$user_id = $_SESSION['user_id'];

// Fetch all vehicles for this user
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}
?>

<style>
    /* Force the deep gradient background */
    body {
        background: linear-gradient(180deg, #001F3F 0%, #000000 100%) !important;
        background-attachment: fixed !important;
        min-height: 100vh;
    }

    .white-card-fleet {
        background: #ffffff;
        border: 3px solid rgba(79, 70, 229, 0.1);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .white-card-fleet:hover {
        transform: translateY(-3px);
        border-color: rgba(79, 70, 229, 0.4);
    }
</style>

<div class="max-w-md mx-auto pb-24">
    
    <div class="px-6 pt-10 pb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
           <a href="javascript:history.back()" class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-white backdrop-blur-md active:scale-95 transition-all">
    <i class="fa-solid fa-chevron-left text-xs"></i>
</a>

            <div>
                <p class="text-[9px] font-black text-indigo-400 uppercase tracking-[0.3em] mb-0.5">Asset Management</p>
                <h1 class="text-2xl font-black uppercase italic tracking-tighter text-white">My <span class="text-indigo-500">Fleet</span></h1>
            </div>
        </div>
        <a href="add_vehicle.php" class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-900/40 active:scale-95 transition-all">
            <i class="fa-solid fa-plus text-xs"></i>
        </a>
    </div>

    <div class="px-6 mb-8">
        <div class="bg-indigo-600 rounded-[2.5rem] p-7 text-white relative overflow-hidden shadow-2xl">
            <i class="fa-solid fa-car-side absolute -right-6 -bottom-6 text-8xl opacity-10"></i>
            <div class="relative z-10">
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-indigo-100 mb-1">Active Portfolio</p>
                <h3 class="text-3xl font-black italic uppercase tracking-tighter">
                    <?= count($vehicles) ?> <span class="text-indigo-200">Vehicles</span>
                </h3>
            </div>
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 blur-3xl rounded-full -mr-16 -mt-16"></div>
        </div>
    </div>

    <div class="px-6 space-y-5">
        <?php if (empty($vehicles)): ?>
            <div class="py-20 text-center border-2 border-dashed border-white/10 rounded-[3rem] bg-white/5">
                <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4 border border-white/10 text-slate-500">
                    <i class="fa-solid fa-car-rear text-xl"></i>
                </div>
                <p class="text-[11px] font-black uppercase text-slate-400 tracking-widest">No assets registered</p>
                <a href="add_vehicle.php" class="mt-4 inline-block text-[10px] font-black uppercase text-indigo-400 border-b-2 border-indigo-400 pb-1">Initialize Fleet</a>
            </div>
        <?php else: ?>
            <?php foreach ($vehicles as $veh): ?>
                <div class="white-card-fleet rounded-[2.5rem] p-5 group relative overflow-hidden">
                    
                    <i class="fa-solid fa-car absolute -right-4 -bottom-4 text-8xl text-slate-900 opacity-[0.05] -rotate-12 transition-transform group-hover:scale-110"></i>

                    <div class="flex items-center gap-5 relative z-10">
                        <div class="w-20 h-20 rounded-[1.8rem] bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center border-2 border-slate-50 shadow-inner">
                            <?php if (!empty($veh['image_path'])): ?>
                               <img src="<?= e($veh['image_path']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa-solid fa-car text-slate-300 text-2xl"></i>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="text-base font-black uppercase text-slate-900 tracking-tight italic truncate">
                                    <?= e($veh['make']) ?> <?= e($veh['model']) ?>
                                </h4>
                            </div>
                            
                            <div class="flex flex-col gap-1">
                                <span class="text-[10px] font-black text-white bg-slate-900 px-2 py-0.5 rounded-md tracking-[0.2em] uppercase w-max mb-2">
                                    <?= e($veh['plate_no']) ?>
                                </span>
                                
                                <div class="flex items-center gap-1.5">
                                    <?php if ($veh['verification_status'] === 'pending'): ?>
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span class="text-[8px] font-black uppercase tracking-widest text-amber-600">Pending</span>
                                    <?php else: ?>
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span class="text-[8px] font-black uppercase tracking-widest text-emerald-600">Verified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0">
                            <a href="vehicle_details.php?id=<?= $veh['id'] ?>" class="w-11 h-11 rounded-2xl bg-slate-900 flex items-center justify-center text-white hover:bg-indigo-600 transition-all shadow-lg">
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    // Initialize Lucide icons if any are used in header/footer
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

<?php include 'includes/footer.php'; ?>
