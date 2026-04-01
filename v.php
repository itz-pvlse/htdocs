<?php
// v.php - The PUBLIC Shareable View
require_once 'config/db.php';

$vehicle_id = $_GET['id'] ?? null;

if (!$vehicle_id) {
    die("Vehicle not found.");
}

// 1. Fetch vehicle and owner name
$stmt = $pdo->prepare("
    SELECT v.*, u.name as owner_name, u.profile_photo as owner_avatar
    FROM vehicles v 
    JOIN users u ON v.user_id = u.id 
    WHERE v.id = ?
");
$stmt->execute([$vehicle_id]);
$veh = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$veh) { die("Vehicle not found."); }

// 2. Fetch public service history (Only Completed)
$stmtLogs = $pdo->prepare("
    SELECT sr.*, g.name as garage_name, g.logo as garage_logo 
    FROM service_requests sr 
    JOIN garages g ON sr.garage_id = g.id 
    WHERE sr.vehicle_id = ? AND sr.status = 'completed'
    ORDER BY sr.created_at DESC
");
$stmtLogs->execute([$vehicle_id]);
$history = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

// 3. Logic: Calculate Health (Example: If last service was < 6 months ago)
$last_service_date = !empty($history) ? strtotime($history[0]['created_at']) : 0;
$is_healthy = (time() - $last_service_date) < (180 * 24 * 60 * 60);

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($veh['make']) ?> <?= e($veh['model']) ?> | AutoLog Passport</title>
    <meta property="og:title" content="<?= e($veh['make']) ?> Digital Passport">
    <meta property="og:description" content="Verified Maintenance Record for Plate: <?= e($veh['plate_no']) ?>">
    <meta property="og:image" content="<?= e($veh['image_path']) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: white; }
        .glass { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .hero-grad { background: linear-gradient(to bottom, transparent 0%, #020617 100%); }
        .timeline-line { background: linear-gradient(to bottom, #6366f1 0%, rgba(99, 102, 241, 0) 100%); }
    </style>
</head>
<body class="antialiased">

    <div class="max-w-md mx-auto min-h-screen bg-slate-950 shadow-2xl relative overflow-hidden">
        
        <div class="fixed top-0 left-0 right-0 max-w-md mx-auto z-[100] p-6 flex justify-between items-center pointer-events-none">
            <div class="glass px-3 py-1.5 rounded-full flex items-center gap-2 pointer-events-auto">
                <div class="w-2 h-2 rounded-full <?= $is_healthy ? 'bg-emerald-500' : 'bg-amber-500' ?> animate-pulse"></div>
                <span class="text-[9px] font-black uppercase tracking-widest text-white"><?= $is_healthy ? 'Active & Healthy' : 'Service Due' ?></span>
            </div>
            <button class="w-10 h-10 glass rounded-full flex items-center justify-center text-white pointer-events-auto">
                <i class="fa-solid fa-share-nodes text-xs"></i>
            </button>
        </div>

        <div class="relative h-[520px]">
            <img src="/<?= e($veh['image_path']) ?>" class="w-full h-full object-cover" onerror="this.src='/assets/default_car.png'">
            <div class="absolute inset-0 hero-grad"></div>
            
            <div class="absolute bottom-12 left-0 right-0 px-8">
<div class="flex items-center gap-3 mb-4">
    <div class="w-8 h-8 rounded-full border border-white/20 overflow-hidden flex items-center justify-center bg-slate-800 shadow-lg relative">
        
        <?php if (!empty($veh['owner_avatar'])): ?>
            <img src="uploads/profiles/<?= e($veh['owner_avatar']) ?>" 
                 class="w-full h-full object-cover block"
                 id="owner-avatar"
                 onerror="this.style.display='none'; document.getElementById('initials-fallback').style.display='flex';">
            
            <span id="initials-fallback" style="display: none;" class="text-[10px] font-black text-slate-400 italic uppercase">
                <?= strtoupper(substr(e($veh['owner_name']), 0, 1)) ?>
            </span>

        <?php else: ?>
            <span class="text-[10px] font-black text-slate-400 italic uppercase">
                <?= strtoupper(substr(e($veh['owner_name']), 0, 1)) ?>
            </span>
        <?php endif; ?>
        
    </div>

    <span class="text-[10px] font-bold text-slate-300 uppercase tracking-widest italic">
        Owned by <?= e($veh['owner_name']) ?>
    </span>
</div>

                <h1 class="text-5xl font-black uppercase italic tracking-tighter leading-none mb-2">
                    <?= e($veh['make']) ?> <br><span class="text-indigo-500"><?= e($veh['model']) ?></span>
                </h1>
                
                <div class="flex items-center gap-4 mt-6">
                    <div class="bg-white text-slate-900 px-5 py-2 rounded-xl shadow-xl">
                        <p class="text-[7px] font-black uppercase tracking-tighter leading-none mb-1 opacity-50">Plate Number</p>
                        <p class="text-xl font-black tracking-tighter leading-none uppercase"><?= e($veh['plate_no']) ?></p>
                    </div>
                    <div class="glass px-5 py-2 rounded-xl">
                        <p class="text-[7px] font-black uppercase tracking-tighter leading-none mb-1 text-slate-400">Manufacture</p>
                        <p class="text-xl font-black tracking-tighter leading-none uppercase italic"><?= e($veh['year']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 -mt-4 relative z-30">
            <div class="glass rounded-[2.5rem] p-6 flex items-center justify-between shadow-2xl">
                <div class="flex flex-col items-center flex-1 border-r border-white/5">
                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Authenticity</span>
                    <div class="flex items-center gap-1.5">
                        <span class="text-lg font-black text-emerald-400 italic">SECURE</span>
                        <i class="fa-solid fa-circle-check text-emerald-500 text-[10px]"></i>
                    </div>
                </div>
                <div class="flex flex-col items-center flex-1 border-r border-white/5 px-2 text-center">
                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Rating CC</span>
                    <span class="text-lg font-black text-white italic"><?= e($veh['rating_cc']) ?></span>
                </div>
                <div class="flex flex-col items-center flex-1">
                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Verified Logs</span>
                    <span class="text-lg font-black text-indigo-400 italic"><?= count($history) ?></span>
                </div>
            </div>
        </div>

        <div class="px-8 mt-12 pb-32">
            <div class="flex items-end justify-between mb-8 px-2">
                <div>
                    <h3 class="text-xl font-black uppercase italic tracking-tighter text-white">Maintenance</h3>
                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.2em]">Verified History Timeline</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-black text-emerald-500 italic uppercase leading-none"><?= $is_healthy ? 'Up to Date' : 'Checkup Req.' ?></p>
                </div>
            </div>

            <div class="relative space-y-10">
                <div class="absolute left-[19px] top-4 bottom-4 w-[2px] timeline-line"></div>

                <?php if(empty($history)): ?>
                    <div class="text-center py-16 glass rounded-[3rem] border-dashed border-white/10">
                        <i class="fa-solid fa-cloud-upload text-slate-800 text-4xl mb-3"></i>
                        <p class="text-[9px] font-black text-slate-500 uppercase italic">Awaiting first verified log</p>
                    </div>
                <?php else: ?>
                    <?php foreach($history as $index => $log): ?>
                        <div class="relative flex gap-6 group">
                            <div class="w-10 h-10 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center relative z-10 <?= $index === 0 ? 'ring-4 ring-indigo-500/20 border-indigo-500' : '' ?>">
                                <i class="fa-solid <?= $index === 0 ? 'fa-star text-indigo-400' : 'fa-wrench text-slate-500' ?> text-xs"></i>
                            </div>
                            
                            <div class="flex-1">
                                <div class="flex justify-between items-center mb-1">
                                    <h4 class="text-sm font-black uppercase italic tracking-tight text-white"><?= e($log['requested_service']) ?></h4>
                                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter italic"><?= date('d M Y', strtotime($log['created_at'])) ?></span>
                                </div>
                                <p class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-4 flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-[8px]"></i> <?= e($log['garage_name']) ?>
                                </p>
                                
                                <div class="glass rounded-2xl p-4 flex justify-between items-center border-l-2 border-emerald-500">
                                    <div>
                                        <p class="text-[7px] font-black text-slate-500 uppercase mb-1">Logged Odometer</p>
                                        <p class="text-sm font-black text-white italic tracking-tighter"><?= number_format($log['mileage'] ?? 0) ?> <span class="text-[10px] text-slate-400 not-italic">KM</span></p>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <p class="text-[7px] font-black text-slate-500 uppercase mb-1">Auth Code</p>
                                        <p class="text-[10px] font-mono text-emerald-400">#<?= substr(md5($log['id']), 0, 6) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="fixed bottom-0 left-0 right-0 p-6 z-50 max-w-md mx-auto">
            <div class="glass rounded-[2rem] p-3 flex items-center justify-between shadow-2xl border-white/10 ring-1 ring-white/5">
                <div class="flex items-center gap-3 pl-2">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-600/30">
                        <i class="fa-solid fa-fingerprint text-lg"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-white uppercase tracking-widest">Digital Passport</p>
                        <p class="text-[7px] font-bold text-slate-500 uppercase italic leading-none">Powered by AutoLog Fleet</p>
                    </div>
                </div>
                <a href="index.php" class="bg-white text-slate-950 h-11 px-6 rounded-xl text-[10px] font-black uppercase tracking-widest flex items-center justify-center hover:bg-indigo-50 transition-colors">
                    Join Fleet
                </a>
            </div>
        </div>

        <div class="absolute top-0 right-0 w-80 h-80 bg-indigo-600/20 rounded-full blur-[120px] -mr-40 -mt-40 pointer-events-none"></div>
        <div class="absolute bottom-40 left-0 w-64 h-64 bg-emerald-600/10 rounded-full blur-[100px] -ml-32 pointer-events-none"></div>
    </div>

</body>
</html>
