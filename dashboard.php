<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'includes/auth_check.php';
 include 'includes/header.php';

require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Car Owner';

$userStmt = $pdo->prepare("SELECT security_alert, profile_photo, name FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC);
/** 1. FETCH STATS **/
$stmtVeh = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE user_id = ?");
$stmtVeh->execute([$user_id]);
$vehicle_count = $stmtVeh->fetchColumn();

$stmtBook = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE user_id = ? AND status IN ('pending', 'approved')");
$stmtBook->execute([$user_id]);
$active_bookings = $stmtBook->fetchColumn();

/** 2. FETCH PREFERRED (STARRED) GARAGES **/
$stmtPref = $pdo->prepare("
    SELECT g.id, g.name, g.location, g.logo 
    FROM garages g
    JOIN user_garages ug ON g.id = ug.garage_id
    WHERE ug.user_id = ? AND ug.is_preferred = 1
    ORDER BY ug.created_at DESC LIMIT 5
");
$stmtPref->execute([$user_id]);
$preferred_garages = $stmtPref->fetchAll(PDO::FETCH_ASSOC);

/** 3. FETCH ALL SAVED CENTERS **/
$stmtSaved = $pdo->prepare("
    SELECT g.id, g.name, g.location, g.logo, g.phone
    FROM garages g
    JOIN user_garages ug ON g.id = ug.garage_id
    WHERE ug.user_id = ?
    ORDER BY g.name ASC LIMIT 6
");
$stmtSaved->execute([$user_id]);
$all_saved = $stmtSaved->fetchAll(PDO::FETCH_ASSOC);

/** 4. FETCH FLEET **/
$stmtVehList = $pdo->prepare("
    SELECT id, make, model, plate_no, image_path, is_system_verified, verification_status 
    FROM vehicles WHERE user_id = ? ORDER BY created_at DESC LIMIT 4
");
$stmtVehList->execute([$user_id]);
$my_vehicles = $stmtVehList->fetchAll(PDO::FETCH_ASSOC);

/** 5. FETCH RECENT ACTIVITY **/
$stmtActivity = $pdo->prepare("
    SELECT sr.requested_service, sr.status, sr.created_at, g.name as garage_name 
    FROM service_requests sr JOIN garages g ON sr.garage_id = g.id 
    WHERE sr.user_id = ? ORDER BY sr.created_at DESC LIMIT 3
");
$stmtActivity->execute([$user_id]);
$recent_activity = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getStatusClass($status) {
    return match(strtolower($status)) {
        'pending'   => 'bg-amber-100 text-amber-600',
        'approved'  => 'bg-emerald-100 text-emerald-600',
        'completed' => 'bg-indigo-100 text-indigo-600',
        'rejected'  => 'bg-rose-100 text-rose-600',
        default      => 'bg-slate-100 text-slate-500',
    };
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | AutoLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .card-grad { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); }
    </style>
</head>
<body class="pb-32 lg:pb-10">

    <div class="max-w-6xl mx-auto">
        
        

        
        <div class="px-6 pt-10 pb-6 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div onclick="toggleSidebar()" class="w-14 h-14 rounded-[1.5rem] bg-indigo-600 overflow-hidden flex items-center justify-center shadow-[0_0_20px_rgba(79,70,229,0.3)] border-2 border-white/20 cursor-pointer active:scale-90 transition-all hover:border-indigo-400">
            <?php if(!empty($userData['profile_photo'])): ?>
                <img src="uploads/profiles/<?= e($userData['profile_photo']) ?>" class="w-full h-full object-cover" id="preview-img">
            <?php else: ?>
                <span id="initials" class="text-white font-black text-xl italic uppercase">
                    <?= strtoupper(substr(e($user_name), 0, 1)) ?>
                </span>
                <img src="" class="hidden w-full h-full object-cover" id="preview-img">
            <?php endif; ?>
        </div>

        <div>
            <p class="text-[10px] font-black text-indigo-400 uppercase tracking-[0.3em] mb-0.5 opacity-90">Live Dashboard</p>
            <h1 class="text-3xl lg:text-4xl font-black italic uppercase tracking-tighter text-white leading-none">
                <?= explode(' ', e($user_name))[0] ?>'s <span class="text-indigo-500">Hub</span>
            </h1>
        </div>
    </div>

    <button onclick="toggleSidebar()" class="relative w-12 h-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center shadow-2xl backdrop-blur-md hover:bg-white/10 active:scale-95 transition-all group">
        <i class="fa-solid fa-sliders text-slate-300 group-hover:text-white transition-colors"></i>
        
        <?php if ($userData['security_alert'] == 1): ?>
            <span class="absolute -top-1 -right-1 w-4 h-4 bg-rose-600 border-2 border-[#001F3F] rounded-full animate-pulse shadow-[0_0_10px_rgba(225,29,72,0.5)]"></span>
        <?php endif; ?>
    </button>


       
           
  <div id="settingsSidebar" class="fixed inset-0 z-[150] invisible transition-all duration-300">
    <div onclick="toggleSidebar()" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    
    <div id="sidebarContent" class="absolute right-0 top-0 h-full w-[85%] max-w-sm bg-white shadow-2xl translate-x-full transition-transform duration-300 flex flex-col">
        
        <div class="p-8 pb-4 flex justify-between items-center">
            <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">System Settings</h2>
            <button onclick="toggleSidebar()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-8 pb-24 no-scrollbar">
            <br>
            <div class="bg-indigo-600 rounded-[2.5rem] p-6 mb-8 text-white relative overflow-hidden shadow-xl shadow-indigo-100">
                <i class="fa-solid fa-circle-user absolute -right-4 -bottom-4 text-8xl opacity-10"></i>
                
                <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center text-xl font-black mb-4 overflow-hidden border border-white/30">
                    <?php if(!empty($userData['profile_photo'])): ?>
                        <img src="uploads/profiles/<?= e($userData['profile_photo']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="text-white">
                            <?= strtoupper(substr(e($user_name), 0, 1)) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h3 class="font-black text-lg leading-tight"><?= e($user_name) ?></h3>
                <p class="text-[9px] font-bold uppercase tracking-widest opacity-70">
                    Fleet Manager ID: #<?= str_pad($user_id, 5, '0', STR_PAD_LEFT) ?>
                </p>
            </div>

            <div class="space-y-6">
                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-2">Personal Fleet</p>
                    <nav class="space-y-1">
                        <a href="profile_settings.php" class="flex items-center gap-4 p-4 rounded-3xl hover:bg-slate-50 transition-colors group">
                            <div class="w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 transition-all">
                                <i class="fa-solid fa-user-pen text-xs"></i>
                            </div>
                            <span class="text-[11px] font-black uppercase text-slate-600">Update Profile</span>
                        </a>
                        <a href="my_vehicles.php" class="flex items-center gap-4 p-4 rounded-3xl hover:bg-slate-50 transition-colors group">
                            <div class="w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 transition-all">
                                <i class="fa-solid fa-car-side text-xs"></i>
                            </div>
                            <span class="text-[11px] font-black uppercase text-slate-600">Manage Fleet</span>
                        </a>
                    </nav>
                </div>

                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-2">System Preferences</p>
                    <nav class="space-y-1">
                        <a href="security.php" class="flex items-center gap-4 p-4 rounded-3xl transition-colors group <?= ($userData['security_alert'] == 1) ? 'bg-rose-50 ring-1 ring-rose-100' : 'hover:bg-slate-50' ?>">
                            <div class="w-10 h-10 rounded-2xl <?= ($userData['security_alert'] == 1) ? 'bg-rose-600 text-white shadow-lg shadow-rose-200' : 'bg-slate-100 text-slate-500 group-hover:bg-amber-100 group-hover:text-amber-600' ?> flex items-center justify-center transition-all">
                                <i class="fas fa-shield-alt" style="font-size: 14px;"></i>
                            </div>
                            <span class="text-[11px] font-black uppercase <?= ($userData['security_alert'] == 1) ? 'text-rose-600' : 'text-slate-600' ?>">
                                Security & Privacy
                            </span>
                        </a>
                        
                        <div class="flex items-center justify-between p-4 rounded-3xl bg-slate-50">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-2xl bg-white flex items-center justify-center text-indigo-500 shadow-sm">
                                    <i class="fa-solid fa-bell text-xs"></i>
                                </div>
                                <span class="text-[11px] font-black uppercase text-slate-600">Service Alerts</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" checked class="sr-only peer">
                                <div class="w-8 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </nav>
                </div>

                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-2">Help Desk</p>
                    <a href="contact.php" class="flex items-center gap-4 p-4 rounded-3xl border border-slate-100 hover:border-indigo-100 transition-colors">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <i class="fa-solid fa-headset text-xs"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-slate-600">Contact Support</span>
                    </a>
                </div>
            </div>

            <div class="mt-12">
                <a href="auth/logout.php" class="flex items-center justify-center gap-3 p-5 rounded-[2rem] bg-rose-50 text-rose-600 hover:bg-rose-100 transition-all group">
                    <i class="fa-solid fa-power-off text-sm group-hover:rotate-90 transition-transform"></i>
                    <span class="text-[11px] font-black uppercase tracking-widest">Terminate Session</span>
                </a>
            </div>
        </div> </div> </div>



<script>
function toggleSidebar() {
    const sidebar = document.getElementById('settingsSidebar');
    const content = document.getElementById('sidebarContent');
    
    if (sidebar.classList.contains('invisible')) {
        sidebar.classList.remove('invisible');
        setTimeout(() => content.classList.remove('translate-x-full'), 10);
    } else {
        content.classList.add('translate-x-full');
        setTimeout(() => sidebar.classList.add('invisible'), 300);
    }
}
</script>
        </div>

          <?php if ($userData && $userData['security_alert'] == 1): ?>
    <div id="security-banner" class="mx-6 mt-6 p-4 rounded-3xl bg-rose-50 border border-rose-100 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-600 text-white flex items-center justify-center">
                <i class="fas fa-shield-alt"></i>
            </div>
            <p class="text-[11px] font-bold text-slate-600">New login detected.</p>
        </div>
        
        <div class="flex gap-2">
            <a href="security.php" class="bg-white text-rose-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider border border-rose-100">Review</a>
            
            <button onclick="dismissAlert()" class="text-rose-300 hover:text-rose-600 px-2">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <script>
    function dismissAlert() {
        // Hide the UI immediately
        document.getElementById('security-banner').style.display = 'none';
        
        // Tell the server to clear the flag in the background
        fetch('actions/clear_security_alert.php');
    }
    </script>
<?php endif; ?><br>
        
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 px-6">
            
            <div class="lg:col-span-8 space-y-10">
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-5 rounded-[2.5rem] bg-indigo-600 text-white relative overflow-hidden h-32 flex flex-col justify-end shadow-[0_20px_40px_rgba(0,0,0,0.4)] border border-white/10">
    <i class="fa-solid fa-car absolute -top-2 -right-2 text-6xl text-indigo-400 opacity-30"></i>
    
    <p class="text-3xl font-black italic leading-none"><?= $vehicle_count ?></p>
    
    <p class="text-indigo-100 text-[9px] font-black uppercase tracking-[0.2em] mt-1">Vehicles</p>
</div>

                    <div class="bg-white border-[3px] border-indigo-500/20 p-5 rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.3)] relative overflow-hidden h-32 flex flex-col justify-end transition-all active:scale-95">
    
    <i class="fa-solid fa- briefcase absolute -top-1 -right-1 text-7xl text-slate-900 opacity-[0.08] -rotate-12"></i>
    
    <div class="absolute right-6 top-6">
        <div class="relative flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-600"></span>
        </div>
    </div>

    <p class="text-slate-900 text-4xl font-black italic leading-none tracking-tighter">
        <?= $active_bookings ?>
    </p>
    
    <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] mt-1">
        Active Jobs
    </p>
</div>

                    <a href="garages.php" class="hidden md:flex bg-white border border-slate-100 p-5 rounded-[2.5rem] shadow-sm flex-col items-center justify-center text-center hover:border-indigo-300 transition-colors">
                        <i class="fa-solid fa-magnifying-glass text-indigo-500 mb-2"></i>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-600">Find Garage</span>
                    </a>
                    <a href="add_vehicle.php" class="hidden md:flex bg-white border border-slate-100 p-5 rounded-[2.5rem] shadow-sm flex-col items-center justify-center text-center hover:border-indigo-300 transition-colors">
                        <i class="fa-solid fa-plus text-emerald-500 mb-2"></i>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-600">Add Car</span>
                    </a>
                </div>

                <div>
                    <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Favorites</h2>
                    <div class="flex gap-4 overflow-x-auto pb-4 no-scrollbar">
                        <?php if(empty($preferred_garages)): ?>
                            <div class="w-full py-10 bg-slate-50 rounded-[2.5rem] border-2 border-dashed border-slate-200 flex flex-col items-center">
                                <i class="fa-regular fa-heart text-slate-300 mb-2"></i>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">No favorites yet</p>
                            </div>
                        <?php endif; ?>
                        <?php foreach($preferred_garages as $g): ?>
                            <a href="garageprofile.php?id=<?= $g['id'] ?>" class="flex-shrink-0 w-44 bg-white rounded-[2.5rem] border border-slate-100 p-3 shadow-sm group hover:shadow-md active:scale-95 transition-all">
                                <div class="w-full h-24 rounded-[2rem] bg-slate-100 overflow-hidden mb-3 border border-slate-50 relative">
                                    <div class="absolute top-2 right-2 w-6 h-6 bg-white/90 backdrop-blur rounded-full flex items-center justify-center shadow-sm z-10">
                                        <i class="fa-solid fa-heart text-[10px] text-rose-500"></i>
                                    </div>
                                    <img src="<?= !empty($g['logo']) ? 'uploads/logo/'.$g['logo'] : 'assets/default_garage.jpeg' ?>" 
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                </div>
                                <div class="px-2">
                                    <h3 class="text-[10px] font-black text-slate-900 uppercase truncate"><?= e($g['name']) ?></h3>
                                    <p class="text-[8px] text-slate-400 truncate"><?= e($g['location']) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white border border-slate-100 rounded-[2.5rem] p-6 lg:p-8 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Saved Centers</h3>
                        <a href="garages.php" class="text-[9px] font-black text-indigo-600 uppercase underline underline-offset-4 hover:text-indigo-800 transition-colors">Browse All</a>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <?php foreach($all_saved as $g): ?>
                        <div class="group relative">
                            <a href="garageprofile.php?id=<?= $g['id'] ?>" class="block">
                                <img src="<?= !empty($g['logo'])?'uploads/logo/'.$g['logo']:'assets/default_garage.jpeg' ?>"
                                     class="w-full h-20 object-cover rounded-2xl mb-2 transition-transform group-hover:scale-105 border border-slate-50">
                                <div class="font-black text-slate-800 text-[10px] uppercase truncate px-1 text-center lg:text-left"><?= e($g['name']) ?></div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 space-y-10">
                
                <div>
                    <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4 px-2">My Fleet</h2>
                    <div class="space-y-3">
                        <?php foreach($my_vehicles as $veh): ?>
                        <div class="bg-white p-3 rounded-[2rem] border border-slate-100 flex items-center gap-4 shadow-sm group hover:border-indigo-200 transition-colors">
                            <div class="w-14 h-14 rounded-2xl bg-slate-100 overflow-hidden border border-slate-200 shrink-0">
                                <img src="<?= !empty($veh['image_path']) ? $veh['image_path'] : 'assets/default_car.png' ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs font-black uppercase italic text-slate-900 truncate"><?= e($veh['make']) ?> <?= e($veh['model']) ?></h3>
                                <span class="px-2 py-0.5 rounded bg-slate-900 text-[8px] font-bold text-white tracking-widest uppercase inline-block mt-1"><?= e($veh['plate_no']) ?></span>
                            </div>
                            <a href="vehicle_details.php?id=<?= $veh['id'] ?>" class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-indigo-600 group-hover:text-white transition-all shrink-0">
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                        <a href="add_vehicle.php" class="flex items-center justify-center p-4 border-2 border-dashed border-slate-200 rounded-[2rem] text-slate-400 hover:text-indigo-500 hover:border-indigo-300 transition-all text-[9px] font-black uppercase tracking-widest">
                            + Register Vehicle
                        </a>
                    </div>
                </div>

                <div>
                    <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4 px-2">Status Log</h2>
                    <div class="space-y-4">
                        <?php foreach($recent_activity as $act): ?>
                        <div class="relative pl-6 border-l-2 border-slate-200 ml-2">
                            <div class="absolute -left-[7px] top-0 w-3 h-3 rounded-full bg-white border-2 border-indigo-600"></div>
                            <div class="bg-white p-4 rounded-3xl shadow-sm border border-slate-50">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-[9px] font-black uppercase text-indigo-600 truncate"><?= e($act['garage_name']) ?></span>
                                    <span class="px-2 py-0.5 rounded text-[7px] font-black uppercase shrink-0 <?= getStatusClass($act['status']) ?>">
                                        <?= e($act['status']) ?>
                                    </span>
                                </div>
                                <p class="text-[10px] font-bold text-slate-800 uppercase tracking-tighter truncate"><?= e($act['requested_service']) ?></p>
                                <p class="text-[8px] text-slate-400 mt-1"><?= date('j M, H:i', strtotime($act['created_at'])) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[92%] max-w-md bg-slate-900/95 backdrop-blur-xl rounded-[2.5rem] p-2 flex justify-between items-center shadow-2xl border border-white/10 z-[100] md:max-w-xs md:bottom-10">
        <a href="dashboard.php" class="flex-1 flex flex-col items-center gap-1 text-white">
            <i class="fa-solid fa-house-chimney text-lg"></i>
            <span class="text-[8px] font-black uppercase tracking-widest">Home</span>
        </a>
        <a href="garages.php" class="flex-1 flex flex-col items-center gap-1 text-slate-500 hover:text-white transition-colors">
            <i class="fa-solid fa-compass text-lg"></i>
            <span class="text-[8px] font-black uppercase tracking-widest">Explore</span>
        </a>
        <div class="flex-1 flex justify-center -translate-y-6">
            <a href="add_vehicle.php" class="w-14 h-14 bg-indigo-600 text-white rounded-full shadow-xl shadow-indigo-500/40 border-4 border-slate-900 flex items-center justify-center active:scale-90 transition-all">
                <i class="fa-solid fa-plus text-xl"></i>
            </a>
        </div>
        <a href="my_bookings.php" class="flex-1 flex flex-col items-center gap-1 text-slate-500 hover:text-white transition-colors">
            <i class="fa-solid fa-layer-group text-lg"></i>
            <span class="text-[8px] font-black uppercase tracking-widest">Logs</span>
        </a>
        <a href="auth/logout.php" class="flex-1 flex flex-col items-center gap-1 text-rose-400">
            <i class="fa-solid fa-power-off text-lg"></i>
            <span class="text-[8px] font-black uppercase tracking-widest">Exit</span>
        </a>
    </div>

</body>
</html>