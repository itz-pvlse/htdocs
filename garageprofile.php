<?php
// garage_profile.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'config/db.php';

// 1. INPUT VALIDATION
$garage_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// We define $user_id once globally.
$user_id = $_SESSION['user_id'] ?? null;
$isLoggedIn = !empty($user_id);

if ($garage_id <= 0) {
    die("<p>Invalid garage ID.</p>");
}

// 2. FETCH GARAGE DATA
$stmt = $pdo->prepare("SELECT * FROM garages WHERE id = ?");
$stmt->execute([$garage_id]);
$garage = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$garage) {
    die("<p>Garage not found.</p>");
}

// Fetch secondary garage details
$service_stmt = $pdo->prepare("SELECT id, service_name, base_price FROM garage_services WHERE garage_id = ?");
$service_stmt->execute([$garage_id]);
$services = $service_stmt->fetchAll(PDO::FETCH_ASSOC);

$specialties_stmt = $pdo->prepare("SELECT * FROM garage_specialties WHERE garage_id = ? ORDER BY created_at DESC");
$specialties_stmt->execute([$garage_id]);
$specialties = $specialties_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Gallery (ensure variable name is $gallery to match your Bento HTML)
$gallery_stmt = $pdo->prepare("SELECT * FROM garage_gallery WHERE garage_id = ? ORDER BY uploaded_at DESC");
$gallery_stmt->execute([$garage_id]);
$gallery = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Certifications (ensure path consistency)
$certs_stmt = $pdo->prepare("SELECT * FROM garage_certifications WHERE garage_id = ? ORDER BY uploaded_at DESC");
$certs_stmt->execute([$garage_id]);
$certifications = $certs_stmt->fetchAll(PDO::FETCH_ASSOC);

$hours_stmt = $pdo->prepare("SELECT * FROM garage_hours WHERE garage_id = ? ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
$hours_stmt->execute([$garage_id]);
$hours = $hours_stmt->fetchAll(PDO::FETCH_ASSOC);

$social_stmt = $pdo->prepare("SELECT * FROM garage_socials WHERE garage_id = ? LIMIT 1");
$social_stmt->execute([$garage_id]);
$socials = $social_stmt->fetch(PDO::FETCH_ASSOC);

// 3. REVIEWS & SENTIMENT LOGIC
$rev_stmt = $pdo->prepare("
    SELECT gr.rating, gr.review_text, gr.response, gr.response_date, gr.created_at, u.name 
    FROM garage_reviews gr 
    JOIN users u ON gr.user_id = u.id 
    WHERE gr.garage_id = ? 
    ORDER BY gr.created_at DESC
");
$rev_stmt->execute([$garage_id]);
$reviews = $rev_stmt->fetchAll();

$total_reviews = count($reviews);
$total_score = array_sum(array_column($reviews, 'rating'));
$avg_rating = ($total_reviews > 0) ? ($total_score / $total_reviews) : 0.0;

// 4. POST REVIEW HANDLING
if (isset($_POST['submit_review']) && $isLoggedIn) {
    $rating = intval($_POST['rating']);
    $review_text = trim($_POST['review_text']);

    $check = $pdo->prepare("SELECT id FROM garage_reviews WHERE user_id = ? AND garage_id = ?");
    $check->execute([$user_id, $garage_id]);

    if ($check->rowCount() == 0) {
        $insert = $pdo->prepare("INSERT INTO garage_reviews (garage_id, user_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, NOW())");
        $insert->execute([$garage_id, $user_id, $rating, $review_text]);
        echo "<script>alert('Review submitted!'); window.location.reload();</script>";
    } else {
        echo "<script>alert('You have already reviewed this garage.');</script>";
    }
}

// 5. USER SPECIFIC DATA
$is_saved = false;
$is_preferred = false;
$vehicles = [];
$unread_count = 0;
$notifications = [];
$hasActiveBooking = false;

if ($isLoggedIn) {
    // Check saved status
    $rel_stmt = $pdo->prepare("SELECT is_preferred FROM user_garages WHERE user_id = ? AND garage_id = ?");
    $rel_stmt->execute([$user_id, $garage_id]);
    $relation = $rel_stmt->fetch();
    if ($relation) {
        $is_saved = true;
        $is_preferred = (bool)$relation['is_preferred'];
    }

    // Fetch vehicles
    $veh_stmt = $pdo->prepare("SELECT id, plate_no, make, model FROM vehicles WHERE user_id = ?");
    $veh_stmt->execute([$user_id]);
    $vehicles = $veh_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Notifications
    $notif_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notif_count_stmt->execute([$user_id]);
    $unread_count = $notif_count_stmt->fetchColumn();

    $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $notif_stmt->execute([$user_id]);
    $notifications = $notif_stmt->fetchAll();

    // Check for active bookings
    $checkBooking = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE user_id = ? AND garage_id = ? AND status IN ('Pending', 'Approved')");
    $checkBooking->execute([$user_id, $garage_id]);
    $hasActiveBooking = (int)$checkBooking->fetchColumn() > 0;
}

// 6. UI THEME LOGIC
if (!$isLoggedIn) {
    $btnColor  = 'bg-slate-800 shadow-slate-900/20';
    $btnLabel  = 'Login to Book';
    $btnIcon   = 'fa-lock';
    $btnLink   = 'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    $btnAction = ""; 
} elseif ($hasActiveBooking) {
    $btnColor  = 'bg-emerald-500 shadow-emerald-500/30';
    $btnLabel  = 'View Booking';
    $btnIcon   = 'fa-clock-rotate-left';
    $btnLink   = 'my_bookings.php';
    $btnAction = "";
} else {
    $btnColor  = 'bg-indigo-600 shadow-indigo-500/30';
    $btnLabel  = 'Book Now';
    $btnIcon   = 'fa-calendar-check';
    $btnLink   = 'javascript:void(0)';
    $btnAction = "openServiceRequestModal('')";
}

// Helper for escaping
if (!function_exists('e')) {
    function e($s) { 
        return htmlspecialchars($s ?? '', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($garage['name']) ?> | GarageHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .glass-dark { background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(16px); }
        .modal-sheet { 
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
    transform: translateY(100%); 
}

.modal-sheet.active { 
    transform: translateY(0); 
}
#serviceRequestModal.flex {
    display: flex !important;
}

/* Service Card Active State refinement */
.service-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.service-check:checked + .service-card {
    border-color: #f59e0b !important; /* Amber-500 */
    background-color: rgba(254, 243, 199, 0.4) !important; /* Amber-50 */
    transform: scale(1.02);
}


    </style>
</head>
<body class="pb-24 lg:pb-0">




<nav class="bg-white/80 backdrop-blur-md sticky top-0 z-50 border-b border-slate-200 px-6 py-4">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-3">
            <button onclick="history.back()" class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-white hover:shadow-lg transition group active:scale-95">
                <i class="fas fa-arrow-left text-sm group-hover:-translate-x-1 transition-transform"></i>
            </button>

            <div class="flex items-center gap-2">
                <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                    <i class="fas fa-car-side text-xs"></i>
                </div>
                <span class="font-black text-lg tracking-tighter italic uppercase">Garage<span class="text-indigo-600">Hub</span></span>
            </div>
        </div>

        <div class="flex gap-2">
            <button onclick="openNotificationModal()" class="relative w-12 h-12 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-white hover:shadow-xl transition group">
                <i class="fas fa-bell group-hover:shake"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-5 w-5 bg-rose-500 text-[10px] font-bold text-white flex items-center justify-center">
                            <?= $unread_count ?>
                        </span>
                    </span>
                <?php endif; ?>
            </button>

            <div class="relative inline-block text-left" id="garageSettingsWrapper">
                <button onclick="toggleGarageMenu()" class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center border border-slate-200 hover:bg-slate-200 transition shadow-sm active:scale-95">
                    <i class="fas fa-ellipsis-v"></i>
                </button>

                <div id="garageDropdown" class="hidden absolute right-0 mt-3 w-64 bg-white rounded-[2rem] shadow-2xl border border-slate-100 overflow-hidden z-[700] animate-in fade-in zoom-in duration-200 origin-top-right p-2">
                    <div class="space-y-1">
                        <button onclick="handleGarageAction('save')" class="w-full flex items-center gap-3 p-3 rounded-2xl hover:bg-slate-50 transition text-left group">
                            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center group-hover:bg-rose-500 group-hover:text-white transition">
                                <i class="<?= $is_saved ? 'fas' : 'far' ?> fa-bookmark text-sm"></i>
                            </div>
                            <span class="text-[11px] font-black uppercase text-slate-700 tracking-tight"><?= $is_saved ? 'Saved to List' : 'Save Garage' ?></span>
                        </button>

                        <button onclick="handleGarageAction('preferred')" class="w-full flex items-center gap-3 p-3 rounded-2xl hover:bg-slate-50 transition text-left group">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition">
                                <i class="<?= $is_preferred ? 'fas' : 'far' ?> fa-star text-sm"></i>
                            </div>
                            <span class="text-[11px] font-black uppercase text-slate-700 tracking-tight"><?= $is_preferred ? 'Preferred Garage' : 'Set as Preferred' ?></span>
                        </button>

                        <div class="h-[1px] bg-slate-100 my-2 mx-3"></div>

                        <button onclick="shareGarage()" class="w-full flex items-center gap-3 p-3 rounded-2xl hover:bg-slate-50 transition text-left group">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                                <i class="fas fa-share-nodes text-sm"></i>
                            </div>
                            <span class="text-[11px] font-black uppercase text-slate-700 tracking-tight">Share Profile</span>
                        </button>

                        <?php 
                        $userName = $_SESSION['name'] ?? 'Guest';
                        $supportMsg = "Garage App Support: \nClient: " . $userName . "\nIssue on Garage: " . (isset($garage['name']) ? $garage['name'] : 'System') . "\n> ";
                        $whatsappNum = !empty($socials['whatsapp']) ? preg_replace('/[^0-9]/', '', $socials['whatsapp']) : '254XXXXXXXXX';
                        ?>
                        <a href="https://wa.me/<?= $whatsappNum ?>?text=<?= urlencode($supportMsg) ?>" target="_blank" class="w-full flex items-center gap-3 p-3 rounded-2xl hover:bg-emerald-50 transition text-left group">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition">
                                <i class="fas fa-headset text-sm"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[11px] font-black uppercase text-slate-700">Support Chat</span>
                                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tight">Open WhatsApp</span>
                            </div>
                        </a>

                        <a href="auth/logout.php" onclick="return confirm('Are you sure you want to log out?')" class="w-full flex items-center gap-3 p-3 rounded-2xl hover:bg-rose-50 transition text-left group">
                            <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 flex items-center justify-center group-hover:bg-rose-500 group-hover:text-white transition">
                                <i class="fas fa-sign-out-alt text-sm"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[11px] font-black uppercase text-slate-700">Log Out</span>
                                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tight">Secure Session End</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>


<script>
function toggleGarageMenu() {
    const dropdown = document.getElementById('garageDropdown');
    dropdown.classList.toggle('hidden');
    
    // Close when clicking outside
    document.addEventListener('click', function closeMenu(e) {
        if (!document.getElementById('garageSettingsWrapper').contains(e.target)) {
            dropdown.classList.add('hidden');
            document.removeEventListener('click', closeMenu);
        }
    });
}

function handleGarageAction(action) {
    console.log("Garage Action Triggered:", action);
    // Add your AJAX logic here to save/set preferred in the DB
    alert(action.charAt(0).toUpperCase() + action.slice(1) + " updated successfully!");
}


/**
 * Placeholder for notification toggle logic
 * You can connect this to an AJAX call to update your database
 */
function toggleGarageNotifications(btn, garageId) {
    // Visual toggle for demo purposes
    const icon = btn.querySelector('i');
    const isActive = btn.classList.contains('bg-amber-50');
    
    if (isActive) {
        // Switch to Off
        btn.classList.remove('bg-amber-50', 'text-amber-500', 'border-amber-100');
        btn.classList.add('bg-slate-100', 'text-slate-400');
        icon.className = 'far fa-bell';
        // Remove pulse dot if it exists
        const dot = btn.querySelector('span');
        if(dot) dot.remove();
    } else {
        // Switch to On
        btn.classList.remove('bg-slate-100', 'text-slate-400');
        btn.classList.add('bg-amber-50', 'text-amber-500', 'border-amber-100');
        icon.className = 'fas fa-bell';
        // Add pulse dot
        btn.innerHTML += `
            <span class="absolute top-2 right-2 flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
            </span>`;
    }
    
    // Suggestion: Call your PHP toggle script here via fetch()
}
</script>

        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 md:px-8 py-6">
        
       <section class="bg-white rounded-[2.5rem] p-8 md:p-12 border border-slate-100 shadow-sm mb-10 overflow-hidden relative group">
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-indigo-50/50 rounded-full blur-3xl transition-all group-hover:bg-indigo-100/40"></div>
    
    <div class="relative z-10 flex flex-col md:flex-row gap-8 lg:gap-12 items-center md:items-start">
        
        <div class="relative shrink-0">
            <div class="w-32 h-32 md:w-48 md:h-48 rounded-[2.5rem] border-4 border-white shadow-2xl overflow-hidden bg-slate-50 relative">
                <?php 
                    $logoFile = !empty($garage['logo']) ? 'uploads/logo/' . $garage['logo'] : '';
                    if (!empty($logoFile) && file_exists(__DIR__ . '/' . $logoFile)): 
                ?>
                    <img src="<?= e($logoFile) ?>" alt="<?= e($garage['name']) ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <img src="https://images.unsplash.com/photo-1597766353939-957c91370213?w=400" class="w-full h-full object-cover">
                <?php endif; ?>
            </div>
            
            <div class="absolute -bottom-2 right-4 md:-right-2 flex items-center gap-1.5 bg-white px-4 py-1.5 rounded-2xl shadow-xl border border-slate-100">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                </span>
                <span class="text-[10px] font-black text-slate-700 tracking-tighter uppercase">Available Now</span>
            </div>
        </div>

        <div class="flex-1 text-center md:text-left">
            <div class="flex flex-col gap-2 mb-6">
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-3">
                    <h1 class="text-4xl md:text-6xl font-black tracking-tight text-slate-900 italic uppercase">
                        <?= e($garage['name']) ?>
                    </h1>
                    <div class="bg-indigo-600 text-white px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-[0.2em] shadow-lg shadow-indigo-100">
                        Verified Partner
                    </div>
                </div>
                
                <p class="text-slate-400 flex items-center justify-center md:justify-start gap-2 font-bold text-sm">
                    <i class="fas fa-location-dot text-indigo-500"></i> 
                    <?= e($garage['location']) ?>
                </p>
            </div>

            <div class="flex flex-wrap justify-center md:justify-start gap-2 mb-8">
                <?php foreach($specialties as $spec): ?>
                    <div class="group/spec flex items-center gap-2 bg-slate-50 hover:bg-white hover:shadow-md hover:border-indigo-100 transition-all px-4 py-2 rounded-xl border border-slate-200">
                        <i class="fas fa-check-circle text-indigo-500 text-[10px] opacity-50 group-hover/spec:opacity-100 transition-opacity"></i>
                        <span class="text-slate-600 text-[10px] font-black uppercase tracking-widest italic">
                            <?= e($spec['specialty_name']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="relative">
                <i class="fas fa-quote-left absolute -left-6 -top-2 text-slate-100 text-4xl -z-10"></i>
                <p class="text-slate-600 text-sm leading-relaxed max-w-2xl font-medium">
                    <?= e($garage['description']) ?>
                </p>
            </div>
        </div>
    </div>
</section>


<?php if (!empty($gallery)): ?>
<style>
    #modal-lightbox { transition: opacity 0.3s ease; display: none; }
    #modal-lightbox.active { display: flex; opacity: 1; }
    .nav-btn { background: rgba(255,255,255,0.1); backdrop-filter: blur(8px); transition: all 0.3s; }
    .nav-btn:hover { background: rgba(255,255,255,0.2); transform: scale(1.1); }
</style>

<section class="mb-12">
    <div class="flex items-end justify-between mb-6 px-2">
        <div>
            <h2 class="text-xs font-black uppercase tracking-[0.3em] text-indigo-600 mb-1 italic">Visual Tour</h2>
            <h3 class="text-3xl font-black text-slate-900 tracking-tighter">Inside the Workshop</h3>
        </div>
        <button onclick="toggleModal('modal-gallery-grid')" class="hidden md:block text-sm font-bold text-slate-400 hover:text-indigo-600 transition">
            Browse all <?= count($gallery) ?> photos <i class="fas fa-arrow-right ml-1"></i>
        </button>
    </div>

    <div class="grid grid-cols-12 grid-rows-2 h-[500px] gap-3">
        <?php 
            $firstImg = 'uploads/gallery/' . $gallery[0]['image_path']; 
            $firstCap = htmlspecialchars($gallery[0]['caption'] ?? 'Workshop Area', ENT_QUOTES);
        ?>
        <div class="col-span-12 md:col-span-8 row-span-2 rounded-[2.5rem] overflow-hidden relative shadow-2xl cursor-pointer group" 
             onclick="openLightbox(0)">
            <img src="<?= $firstImg ?>" class="w-full h-full object-cover transition duration-700 group-hover:scale-105">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent"></div>
            <div class="absolute bottom-8 left-8 text-white font-bold text-xl italic"><?= $firstCap ?></div>
        </div>
        
        <?php if(isset($gallery[1])): ?>
        <div class="hidden md:block col-span-4 row-span-1 rounded-[2rem] overflow-hidden shadow-lg cursor-pointer group"
             onclick="openLightbox(1)">
            <img src="uploads/gallery/<?= $gallery[1]['image_path'] ?>" class="w-full h-full object-cover transition duration-700 group-hover:scale-105">
        </div>
        <?php endif; ?>

        <div class="hidden md:flex col-span-4 row-span-1 rounded-[2rem] bg-indigo-600 flex-col items-center justify-center text-white cursor-pointer hover:bg-indigo-700 transition" 
             onclick="toggleModal('modal-gallery-grid')">
            <span class="text-3xl font-black italic">+<?= count($gallery) ?></span>
            <span class="text-[10px] font-black uppercase tracking-widest opacity-60 mt-1">View All</span>
        </div>
    </div>
</section>

<div id="modal-gallery-grid" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/95 backdrop-blur-xl" onclick="toggleModal('modal-gallery-grid')"></div>
    <div class="relative h-full w-full max-w-5xl mx-auto flex flex-col p-6">
        <div class="flex justify-between items-center mb-8 text-white">
            <h3 class="text-3xl font-black italic uppercase">Full Gallery</h3>
            <button onclick="toggleModal('modal-gallery-grid')" class="text-4xl">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <?php foreach($gallery as $index => $img): ?>
                    <div class="aspect-square rounded-[1.5rem] overflow-hidden cursor-pointer" 
                         onclick="openLightbox(<?= $index ?>)">
                        <img src="uploads/gallery/<?= $img['image_path'] ?>" class="w-full h-full object-cover hover:opacity-80 transition">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div id="modal-lightbox" class="fixed inset-0 bg-slate-950/98 z-[600] flex-col items-center justify-center p-6" onclick="closeLightbox()">
    
    <button class="absolute top-8 right-8 text-white text-5xl z-[700]">&times;</button>

    <button onclick="changeImage(-1, event)" class="nav-btn absolute left-8 w-16 h-16 rounded-full text-white flex items-center justify-center z-[700]">
        <i class="fas fa-chevron-left text-2xl"></i>
    </button>
    <button onclick="changeImage(1, event)" class="nav-btn absolute right-8 w-16 h-16 rounded-full text-white flex items-center justify-center z-[700]">
        <i class="fas fa-chevron-right text-2xl"></i>
    </button>

    <div class="relative max-w-5xl w-full flex flex-col items-center" onclick="event.stopPropagation()">
        <img id="lightbox-img" src="" class="max-w-full max-h-[75vh] rounded-[2rem] shadow-2xl object-contain border border-white/5 transition-all duration-300">
        <div class="mt-8 text-center">
            <p id="lightbox-caption" class="text-white font-black italic text-2xl uppercase tracking-tighter"></p>
            <p id="lightbox-counter" class="text-indigo-400 text-xs font-bold mt-2 uppercase tracking-widest"></p>
        </div>
    </div>
</div>

<script>
// 1. Pass PHP gallery data to JavaScript
const galleryData = <?= json_encode($gallery) ?>;
let currentIndex = 0;

function openLightbox(index) {
    currentIndex = index;
    updateLightboxContent();
    
    const lightbox = document.getElementById('modal-lightbox');
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function updateLightboxContent() {
    const img = document.getElementById('lightbox-img');
    const cap = document.getElementById('lightbox-caption');
    const count = document.getElementById('lightbox-counter');
    
    const item = galleryData[currentIndex];
    
    // Add a small fade effect during swap
    img.style.opacity = '0';
    
    setTimeout(() => {
        img.src = 'uploads/gallery/' + item.image_path;
        cap.innerText = item.caption || 'Workshop View';
        count.innerText = `Photo ${currentIndex + 1} of ${galleryData.length}`;
        img.style.opacity = '1';
    }, 150);
}

function changeImage(step, event) {
    if (event) event.stopPropagation();
    
    currentIndex += step;
    
    // Loop back to start/end
    if (currentIndex >= galleryData.length) currentIndex = 0;
    if (currentIndex < 0) currentIndex = galleryData.length - 1;
    
    updateLightboxContent();
}

function closeLightbox() {
    const lightbox = document.getElementById('modal-lightbox');
    lightbox.classList.remove('active');
    
    if (document.getElementById('modal-gallery-grid').classList.contains('hidden')) {
        document.body.style.overflow = 'auto';
    }
}

function toggleModal(id) {
    const el = document.getElementById(id);
    el.classList.toggle('hidden');
    document.body.style.overflow = el.classList.contains('hidden') ? 'auto' : 'hidden';
}

// Arrow Key Navigation
document.addEventListener('keydown', (e) => {
    const lightbox = document.getElementById('modal-lightbox');
    if (!lightbox.classList.contains('active')) return;

    if (e.key === "ArrowRight") changeImage(1);
    if (e.key === "ArrowLeft") changeImage(-1);
    if (e.key === "Escape") closeLightbox();
});
</script>
<?php endif; ?>



                <div class="bg-white rounded-[2.5rem] border border-slate-200 p-8 shadow-sm">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h3 class="text-2xl font-black italic tracking-tighter text-slate-900 uppercase">Available Services</h3>
            <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mt-1">Standard Rates • KES Currency</p>
        </div>
        <div class="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300">
            <i class="fas fa-tools text-xl"></i>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach($services as $service): ?>
        <div class="p-6 rounded-[2rem] bg-slate-50 border border-slate-100 flex justify-between items-center group hover:border-indigo-500 hover:bg-white hover:shadow-xl hover:shadow-indigo-500/5 transition-all duration-300">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 group-hover:text-indigo-600 transition-colors">
                    <i class="fas fa-screwdriver-wrench text-sm"></i>
                </div>
                
                <div>
                    <h4 class="font-black text-slate-800 italic uppercase text-sm tracking-tight"><?= e($service['service_name']) ?></h4>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">From</span>
                        <span class="text-xs font-black text-indigo-600 tracking-tight">
                            KES <?= number_format($service['base_price'], 0) ?>
                        </span>
                    </div>
                </div>
            </div>

            <button onclick="openServiceRequestModal('<?= htmlspecialchars($service['service_name']) ?>')" 
        class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center shadow-sm group-hover:bg-indigo-600 group-hover:border-indigo-600 group-hover:text-white transition-all active:scale-90">
    <i class="fas fa-plus text-xs"></i>
</button>

        </div>
        <?php endforeach; ?>
    </div>

    
    
<div id="serviceRequestModal" class="fixed inset-0 z-[600] hidden items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm">
  <div class="bg-[#0f172a] p-8 rounded-[2.5rem] w-full max-w-[520px] shadow-2xl border border-white/10 relative">
    
    <div class="flex justify-between items-center mb-8">
      <div>
        <h3 class="text-xl font-black italic text-white uppercase tracking-tighter">Book Service</h3>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Direct Request • KES Booking</p>
      </div>
      <button onclick="closeServiceRequestModal()" class="w-10 h-10 rounded-full bg-white/5 text-white flex items-center justify-center hover:bg-white/10 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <?php if ($user_id <= 0): ?>
      <div class="text-center py-8">
        <div class="w-20 h-20 bg-indigo-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
          <i class="fas fa-user-lock text-3xl text-indigo-500"></i>
        </div>
        <h4 class="text-white font-black uppercase italic tracking-tight mb-2">Authentication Required</h4>
        <p class="text-slate-400 text-xs font-medium leading-relaxed mb-8 px-6">
          You need to be logged in to send a service request to this garage.
        </p>
        <div class="flex flex-col gap-3">
          <a href="login.php?redirect=garageprofile.php?id=<?= $garage_id ?>" 
             class="w-full bg-indigo-600 py-4 rounded-2xl text-white font-black uppercase text-[10px] tracking-widest hover:bg-indigo-700 transition">
             Log In to Continue
          </a>
          <a href="register.php" class="text-slate-500 text-[10px] font-black uppercase tracking-widest hover:text-white transition">
            Create an Account
          </a>
        </div>
      </div>

    <?php elseif (empty($vehicles)): ?>
      <div class="text-center py-8">
        <div class="w-20 h-20 bg-amber-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
          <i class="fas fa-car text-3xl text-amber-500"></i>
        </div>
        <h4 class="text-white font-black uppercase italic tracking-tight mb-2">No Vehicle Found</h4>
        <p class="text-slate-400 text-xs font-medium leading-relaxed mb-8 px-6">
          Your garage needs to know which vehicle they are servicing. Please add a vehicle to your profile first.
        </p>
        <div class="flex flex-col gap-3">
          <a href="add_vehicle.php?redirect=garageprofile.php?id=<?= $garage_id ?>" 
             class="w-full bg-amber-500 py-4 rounded-2xl text-white font-black uppercase text-[10px] tracking-widest hover:bg-amber-600 transition">
             Register a Vehicle
          </a>
        </div>
      </div>

    <?php else: ?>
      <form action="submit_service_request2.php" method="POST" class="space-y-5">
        <input type="hidden" name="garage_id" value="<?= $garage_id ?>">

        <div class="space-y-2">
          <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-2">Your Vehicle</label>
          <select name="vehicle_id" class="w-full bg-slate-800 border-2 border-slate-700 rounded-2xl py-4 px-5 text-white font-bold focus:border-indigo-500 outline-none transition appearance-none" required>
            <?php foreach ($vehicles as $v): ?>
              <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['plate_no'].' — '.$v['make']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="space-y-2">
          <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-2">Service Type</label>
          <input type="text" name="service" class="w-full bg-slate-800 border-2 border-slate-700 rounded-2xl py-4 px-5 text-white font-bold focus:border-indigo-500 outline-none transition" placeholder="e.g., Brake Check" required>
        </div>
		
          <div class="space-y-2">
  <div class="flex justify-between items-end ml-2">
    <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Current Mileage</label>
    <span class="text-[9px] text-slate-500 font-bold italic uppercase leading-none">Optional</span>
  </div>
  <div class="relative group">
    <input type="number" 
           name="mileage" 
           id="mileageInput"
           class="w-full bg-slate-800 border-2 border-slate-700 rounded-2xl py-4 px-5 text-white font-bold focus:border-indigo-500 outline-none transition placeholder:text-slate-600" 
           placeholder="e.g. 85400">
    <div class="absolute right-5 top-1/2 -translate-y-1/2 flex items-center gap-2">
      <span class="text-[10px] font-black text-slate-500">KM</span>
    </div>
  </div>
  <p class="text-[9px] text-slate-500 ml-2 italic">Leave blank if you're not sure — the mechanic will update it on arrival.</p>
</div>
          
          
          
        <div class="space-y-2">
          <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 ml-2">Preferred Date</label>
          <input type="datetime-local" name="request_date" class="w-full bg-slate-800 border-2 border-slate-700 rounded-2xl py-4 px-5 text-white font-bold focus:border-indigo-500 outline-none transition" required>
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-5 rounded-2xl font-black italic uppercase tracking-widest transition shadow-lg active:scale-95 mt-4">
          Submit Request
        </button>    
      </form>
    <?php endif; ?>

  </div>
</div>


<script>
function openServiceRequestModal(serviceName = '') {
    const modal = document.getElementById('serviceRequestModal');
    const serviceInput = document.getElementById('service');

    if (serviceName && serviceInput) {
        serviceInput.value = serviceName;
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex'); // This centers the modal
    document.body.style.overflow = 'hidden';
}

function closeServiceRequestModal() {
    const modal = document.getElementById('serviceRequestModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

// Prevent selecting past dates in the datetime-local input
const dateInput = document.querySelector('input[type="datetime-local"]');
if(dateInput) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    dateInput.min = now.toISOString().slice(0,16);
}

</script>
   
    
    
    <div class="mt-8 pt-6 border-t border-slate-50 flex items-center gap-2 text-slate-400">
        <i class="fas fa-info-circle text-xs"></i>
        <p class="text-[9px] font-bold uppercase tracking-widest">Prices may vary based on vehicle model and spare part costs.</p>
    </div>
</div>
<br>
        <div class="lg:col-span-4 space-y-6">
                <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white shadow-2xl">
                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-6 italic">Operating Hours</h4>
                    <div class="space-y-3">
                        <?php foreach($hours as $hour): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-bold text-slate-300"><?= $hour['day_of_week'] ?></span>
                            <span class="text-sm font-black italic <?= $hour['is_closed'] ? 'text-red-400' : 'text-indigo-400' ?>">
                                <?= $hour['is_closed'] ? 'Closed' : substr($hour['open_time'], 0, 5) . ' - ' . substr($hour['close_time'], 0, 5) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-[2.5rem] p-8">
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-6 italic">Direct Line</h4>
                    <a href="tel:<?= e($garage['phone']) ?>" class="flex items-center gap-4 group">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Call Now</p>
                            <p class="text-lg font-black italic"><?= e($garage['phone']) ?></p>
                        </div>
                    </a>
                </div>
        



    <section id="leave-review" class="mt-12">
        <h3 class="text-2xl font-black italic uppercase tracking-tighter mb-6">Leave a Review</h3>
        <?php if ($user_id): ?>
            <form action="garageprofile.php?id=<?= $garage_id ?>" method="POST" class="space-y-6">
                <input type="hidden" name="dealer_id" value="<?= $garage_id ?>">
                
                <div class="bg-slate-50 rounded-[2.5rem] p-8 border border-slate-100 flex flex-col items-center justify-center shadow-inner">
                    <p class="text-[10px] font-black uppercase text-slate-400 mb-4 tracking-[0.2em]">Overall Rating</p>
                    <div class="flex gap-3">
                        <input type="hidden" name="rating" id="rating-value" value="5">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <button type="button" onclick="setRating(<?= $i ?>)" class="transition-all duration-300 transform active:scale-90">
                                <i class="fas fa-star text-3xl cursor-pointer star-btn text-amber-400" data-index="<?= $i ?>"></i>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <p id="rating-label" class="text-[11px] font-black uppercase text-indigo-600 mt-4 tracking-widest">Excellent</p>
                </div>

                <div class="relative">
                    <label class="text-[10px] font-black uppercase text-slate-400 ml-6 mb-2 block tracking-widest">Your Experience</label>
                    <textarea name="review_text" required rows="5" 
                        class="w-full bg-white border-2 border-slate-200 rounded-[2.5rem] p-8 text-sm font-bold text-slate-800 placeholder:text-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-50 outline-none transition-all resize-none shadow-sm"
                        placeholder="Tell us about the quality, timing, and staff..."></textarea>
                    <i class="fas fa-quote-right absolute bottom-8 right-8 text-slate-100 text-3xl"></i>
                </div>

                <button type="submit" name="submit_review" 
                    class="w-full bg-indigo-600 text-white py-6 rounded-[2.5rem] font-black uppercase italic tracking-[0.2em] shadow-2xl shadow-indigo-200 hover:bg-indigo-700 hover:-translate-y-1 active:translate-y-0 transition-all duration-300 flex items-center justify-center gap-3">
                    <span>Post Review</span>
                    <i class="fas fa-paper-plane text-xs opacity-50"></i>
                </button>
            </form>
        <?php else: ?>
            <div class="bg-slate-100 p-8 rounded-[2.5rem] text-center">
                <p class="font-bold text-slate-500">Please <a href="login.php" class="text-indigo-600 underline">login</a> to leave a review.</p>
            </div>
        <?php endif; ?>
    </section>

   <section id="reviews-anchor" class="bg-white rounded-[2.5rem] border border-slate-200 p-8 shadow-sm">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h3 class="text-xl font-black italic uppercase tracking-tighter text-slate-900">Customer Sentiment</h3>
            <p class="text-[10px] text-slate-400 font-black uppercase tracking-widest mt-1">
                Based on <?= count($reviews) ?> Verified Reviews
            </p>
        </div>
        <div class="text-right">
            <span class="text-4xl font-black italic text-slate-900 leading-none">
                <?php 
                    // Safely handle null/missing avg_rating for PHP 8.1+
                    $display_avg = isset($avg_rating) ? (float)$avg_rating : 0.0;
                    echo number_format($display_avg, 1); 
                ?>
            </span>
            <div class="text-amber-400 text-[10px] mt-1 flex justify-end gap-0.5">
                <?php 
                $full_stars = floor($display_avg);
                for($i=1; $i<=5; $i++) {
                    echo $i <= $full_stars ? '<i class="fas fa-star"></i>' : '<i class="far fa-star text-slate-200"></i>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <?php if (empty($reviews)): ?>
            <div class="text-center py-10 bg-slate-50 rounded-[2rem] border border-dashed border-slate-200">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">No reviews for this garage yet</p>
            </div>
        <?php else: foreach($reviews as $review): ?>
            <div class="group">
                <div class="p-6 rounded-[2rem] bg-slate-50 border border-slate-100 transition-all group-hover:bg-white group-hover:shadow-xl group-hover:shadow-indigo-100/50 relative z-10">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex flex-col">
                            <span class="text-[11px] font-black uppercase tracking-widest text-slate-800">
                                <?= e($review['name']) ?>
                            </span>
                            <div class="flex text-amber-400 text-[8px] mt-1">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="<?= $i <= $review['rating'] ? 'fas' : 'far text-slate-200' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <span class="text-[9px] font-black text-slate-400 uppercase italic">
                            <?= !empty($review['created_at']) ? date('M Y', strtotime($review['created_at'])) : 'Recent' ?>
                        </span>
                    </div>
                    <p class="text-xs font-bold text-slate-600 leading-relaxed italic">
                        "<?= e($review['review_text']) ?>"
                    </p>
                </div>

                <?php if (!empty($review['response'])): ?>
                    <div class="mt-2 ml-8 p-5 bg-indigo-50 border-l-4 border-indigo-400 rounded-br-[1.5rem] rounded-bl-[1.5rem] rounded-tr-[1.5rem] relative">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fas fa-reply text-indigo-400 text-[10px] -scale-x-100"></i>
                            <span class="text-[9px] font-black uppercase tracking-widest text-indigo-600">Garage Response</span>
                        </div>
                        
                        <p class="text-[11px] font-bold text-indigo-900 leading-snug">
                            <?= e($review['response']) ?>
                        </p>

                        <?php if (!empty($review['response_date'])): ?>
                            <span class="text-[8px] font-black text-indigo-300 uppercase mt-2 block">
                                <?= date('M d, Y', strtotime($review['response_date'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>
</section>
</main>

<script>
function setRating(val) {
    document.getElementById('rating-value').value = val;
    const stars = document.querySelectorAll('.star-btn');
    const label = document.getElementById('rating-label');
    
    stars.forEach((star, index) => {
        if (index < val) {
            star.classList.remove('text-slate-200');
            star.classList.add('text-amber-400');
        } else {
            star.classList.remove('text-amber-400');
            star.classList.add('text-slate-200');
        }
    });

    const labels = { 1: 'Poor', 2: 'Fair', 3: 'Good', 4: 'Great', 5: 'Excellent' };
    label.innerText = labels[val];
}
</script>
<div class="fixed bottom-6 left-6 right-6 z-[300] lg:max-w-md lg:mx-auto">
    <div class="glass-dark rounded-[2.5rem] border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.3)] p-2 flex items-center justify-between">
        
               <a href="socials/garage_feed.php?id=<?= $garage_id ?>" class="flex-1 flex flex-col items-center gap-1.5 py-3 text-slate-400 hover:text-white transition group">
    <div class="w-11 h-11 rounded-2xl bg-white/5 flex items-center justify-center group-hover:bg-white/10 transition">
        <i class="fas fa-users text-lg"></i> </div>
    <span class="text-[9px] font-black uppercase tracking-widest">Community</span> </a>


       <a href="<?= $btnLink ?>" 
   <?php if ($btnAction): ?> onclick="<?= $btnAction ?>; return false;" <?php endif; ?>
   class="flex-[1.4] <?= $btnColor ?> rounded-[2.2rem] py-4 flex flex-col items-center gap-1 shadow-xl -translate-y-4 active:scale-95 transition-all text-center no-underline">
    
    <i class="fas <?= $btnIcon ?> text-white text-xl"></i>
    
    <span class="text-[10px] font-black uppercase tracking-widest text-white">
        <?= $btnLabel ?>
    </span>
</a>

        <button onclick="toggleModal('modal-history')" class="flex-1 flex flex-col items-center gap-1.5 py-3 text-slate-400 hover:text-white transition group">
            <div class="w-11 h-11 rounded-2xl bg-white/5 flex items-center justify-center group-hover:bg-white/10 transition">
                <i class="fas fa-layer-group text-lg"></i> 
            </div>
            <span class="text-[9px] font-black uppercase tracking-widest">My Hub</span>
        </button>

    </div>
</div>

<div id="modal-estimate" class="fixed inset-0 z-[60] hidden items-end justify-center">
    <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-md" onclick="toggleModal('modal-estimate')"></div>
    
    <div class="modal-sheet w-full max-w-2xl bg-white rounded-t-[3rem] p-8 pb-12 shadow-2xl relative flex flex-col">
        <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-10"></div>
        
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-amber-500 flex items-center justify-center text-white text-2xl shadow-lg shadow-amber-200/50">
                    <i class="fas fa-calculator"></i>
                </div>
                <div>
                    <h3 class="text-3xl font-black italic tracking-tighter text-slate-900 uppercase">Cost <span class="text-amber-500">Calculator</span></h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Tap services to calculate total</p>
                </div>
            </div>
            <button onclick="resetCalculator()" class="text-[10px] font-black uppercase tracking-widest text-rose-500 bg-rose-50 px-4 py-2 rounded-xl active:scale-95 transition-all">Reset</button>
        </div>

        <div class="space-y-3 max-h-[40vh] overflow-y-auto pr-2 custom-scrollbar">
            <?php if (!empty($services)): ?>
                <?php foreach($services as $service): ?>
                    <div class="service-card group p-5 rounded-3xl bg-slate-50 border border-slate-100 transition-all duration-300 flex items-center justify-between cursor-pointer active:scale-95" 
                         data-selected="false"
                         data-price="<?= (float)$service['base_price'] ?>"
                         onclick="selectService(this)">
                        
                        <div class="flex items-center gap-4">
                            <div class="check-box w-6 h-6 rounded-lg border-2 border-slate-200 flex items-center justify-center transition-all duration-300 pointer-events-none">
                                <i class="fas fa-check text-[10px] text-white opacity-0 transition-opacity"></i>
                            </div>
                            <div class="pointer-events-none">
                                <span class="block text-sm font-black uppercase tracking-tight text-slate-900"><?= e($service['service_name']) ?></span>
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest italic">Professional Service</span>
                            </div>
                        </div>
                        <div class="text-right pointer-events-none">
                            <span class="block text-lg font-black italic text-slate-900">KES <?= number_format($service['base_price']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-100">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Estimated Total</span>
                    <p class="text-[9px] font-bold text-slate-900 uppercase mt-1"><span id="items-count">0</span> Services Selected</p>
                </div>
                <div class="text-right">
                    <span id="estimate-total-display" class="text-3xl font-black italic text-slate-900 tracking-tighter">KES 0</span>
                </div>
            </div>

            <button onclick="toggleModal('modal-estimate'); openServiceRequestModal();" class="w-full py-5 bg-slate-900 text-white rounded-[2rem] font-black uppercase tracking-[0.2em] shadow-xl hover:bg-indigo-600 transition-colors">
                Book with this Estimate
            </button>
        </div>
    </div>
</div>


    
<div id="modal-history" class="fixed inset-0 z-[60] hidden items-end justify-center">
    <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-md" onclick="toggleModal('modal-history')"></div>
    
    <div class="modal-sheet w-full max-w-2xl bg-white rounded-t-[3.5rem] p-8 pb-12 shadow-2xl relative">
        <div class="w-12 h-1.5 bg-slate-100 rounded-full mx-auto mb-10"></div>
        
        <div class="flex items-center gap-4 mb-10">
            <div class="w-16 h-16 rounded-2xl bg-indigo-600 flex items-center justify-center text-white text-2xl shadow-lg">
                <i class="fas fa-user-gear"></i>
            </div>
            <div>
                <h3 class="text-3xl font-black italic tracking-tighter text-slate-900 uppercase">Manage <span class="text-indigo-600">Account</span></h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Activity at <?= e($garage['name']) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
           <button onclick="window.location.href='my_bookings.php?garage_id=<?= $garage_id ?>'" class="group p-6 rounded-[2.5rem] bg-slate-50 border border-slate-100 hover:bg-white hover:border-indigo-100 hover:shadow-xl transition-all text-left">
    <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-indigo-600 shadow-sm mb-4 group-hover:scale-110 transition">
        <i class="fas fa-calendar-check text-xl"></i>
    </div>
    <span class="block text-sm font-black uppercase tracking-tight text-slate-900">My Bookings</span>
    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Active & Pending</span>
</button>

           <button onclick="window.location.href='invoices.php?garage_id=<?= (int)$garage_id ?>'" 
        class="group p-6 rounded-[2.5rem] bg-slate-50 border border-slate-100 hover:bg-white hover:border-indigo-100 hover:shadow-xl transition-all text-left w-full">
    <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-emerald-500 shadow-sm mb-4 group-hover:scale-110 transition">
        <i class="fas fa-file-invoice-dollar text-xl"></i>
    </div>
    <span class="block text-sm font-black uppercase tracking-tight text-slate-900">Invoices</span>
    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Payments & Receipts</span>
</button>
<button 
    onclick="window.location.href='vehicle_logbook.php?garage_id=<?= (int)$garage_id ?>'" 
    class="group p-6 rounded-[2.5rem] bg-slate-50 border border-slate-100 hover:bg-white hover:border-indigo-100 hover:shadow-xl transition-all text-left w-full">
    <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-amber-500 shadow-sm mb-4 group-hover:scale-110 transition">
        <i class="fas fa-book text-xl"></i>
    </div>
    <span class="block text-sm font-black uppercase tracking-tight text-slate-900">Logbook</span>
    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Repair Timeline</span>
</button>
            <button onclick="window.location.href='rewards.php'" class="group p-6 rounded-[2.5rem] bg-slate-50 border border-slate-100 hover:bg-white hover:border-indigo-100 hover:shadow-xl transition-all text-left">
    <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-rose-500 shadow-sm mb-4 group-hover:scale-110 transition">
        <i class="fas fa-star text-xl"></i>
    </div>
    <span class="block text-sm font-black uppercase tracking-tight text-slate-900">Rewards</span>
    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">0 Points Earned</span>
</button>
        </div>
<br>
        <div class="mt-8 pt-8 border-t border-slate-100">
    <div class="flex gap-3">
       
    </div>
</div>

<script>
function shareGarage() {
    if (navigator.share) {
        navigator.share({
            title: '<?= htmlspecialchars($garage['name']) ?>',
            text: 'Check out <?= htmlspecialchars($garage['name']) ?> for quality vehicle service!',
            url: window.location.href
        }).then(() => {
            console.log('Thanks for sharing!');
        }).catch((err) => {
            console.log('Share failed:', err.message);
        });
    } else {
        // Fallback: Copy to clipboard if Web Share API is not supported
        const el = document.createElement('textarea');
        el.value = window.location.href;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        alert('Link copied to clipboard!');
    }
}
</script>
    </div>
</div>
    
    
    
    <div id="modal-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[400] hidden opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0" onclick="closeAllModals()"></div>
        
        <div id="modal-book" class="modal-sheet absolute bottom-0 left-0 right-0 bg-white rounded-t-[3rem] p-8 z-[410] max-h-[85vh] overflow-y-auto">
            <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-8"></div>
            <h3 class="text-2xl font-black mb-6 italic italic">Reserve Appointment</h3>
            <form action="booking_process.php" method="POST" class="space-y-4">
                <input type="hidden" name="garage_id" value="<?= $garage_id ?>">
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 ml-2">Select Vehicle</label>
                    <select name="vehicle_id" class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-100 font-bold focus:outline-indigo-500">
                        <?php if (empty($vehicles)): ?>
                            <option disabled>No vehicles found. Add one in profile.</option>
                        <?php else: ?>
                            <?php foreach($vehicles as $veh): ?>
                                <option value="<?= $veh['id'] ?>"><?= e($veh['plate_no']) ?> - <?= e($veh['make']) ?> <?= e($veh['model']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase text-slate-400 ml-2">Preferred Service</label>
                    <select name="service_id" class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-100 font-bold">
                        <?php foreach($services as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['service_name']) ?> ($<?= e($s['base_price']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-5 rounded-2xl font-black uppercase tracking-widest shadow-xl shadow-indigo-100">Confirm Booking</button>
            </form>
        </div>

        <div id="modal-estimate" class="modal-sheet absolute bottom-0 left-0 right-0 bg-white rounded-t-[3rem] p-8 z-[410]">
            <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-8"></div>
            <h3 class="text-2xl font-black mb-6 italic">Quick Cost Check</h3>
            <div class="space-y-4">
                <?php foreach($services as $s): ?>
                    <div class="flex justify-between items-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="font-bold text-slate-700"><?= e($s['service_name']) ?></span>
                        <span class="font-black text-indigo-600">$<?= number_format($s['base_price'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    
   <div id="notificationModal" class="fixed inset-0 z-[600] hidden items-center justify-center p-4 bg-slate-900/80 backdrop-blur-sm">
  <div class="bg-[#0f172a] p-0 rounded-[2.5rem] w-full max-w-[520px] shadow-2xl border border-white/10 relative overflow-hidden">
    
    <div class="p-8 pb-4 flex justify-between items-center border-b border-white/5">
      <div>
        <h3 class="text-xl font-black italic text-white uppercase tracking-tighter">Activity Hub</h3>
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Updates & Alerts</p>
      </div>
      <button onclick="closeNotificationModal()" class="text-white/50 hover:text-white transition text-3xl">&times;</button>
    </div>

    <div class="max-h-[60vh] overflow-y-auto p-4 space-y-3">
      <?php if (empty($notifications)): ?>
        <div class="py-20 text-center">
          <i class="fas fa-bell-slash text-slate-700 text-4xl mb-4"></i>
          <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">No recent notifications</p>
        </div>
      <?php else: ?>
        <?php foreach ($notifications as $n): ?>
          <div class="p-5 rounded-3xl bg-white/5 border border-white/5 hover:border-indigo-500/50 transition cursor-pointer group">
            <div class="flex gap-4">
              <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center shrink-0">
                 <i class="fas <?= $n['type'] == 'booking' ? 'fa-calendar-check' : 'fa-info-circle' ?> text-xs"></i>
              </div>
              
              <div class="flex-1">
                <div class="flex justify-between items-start">
                  <h4 class="text-white font-black italic uppercase text-sm tracking-tight"><?= htmlspecialchars($n['title']) ?></h4>
                  <span class="text-[8px] font-bold text-slate-500 uppercase"><?= date('M d', strtotime($n['created_at'])) ?></span>
                </div>
                <p class="text-slate-400 text-[11px] mt-1 leading-relaxed">
                  <?= htmlspecialchars($n['message']) ?>
                </p>
                <?php if (!empty($n['link'])): ?>
    <a href="view_notification.php?id=<?= $n['id'] ?>" 
       class="inline-block mt-3 text-[9px] font-black text-indigo-400 uppercase tracking-widest hover:text-white transition-all border-b border-transparent hover:border-indigo-400 pb-0.5">
       View Details &rarr;
    </a>
<?php endif; ?>

              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="p-6 bg-white/5 text-center">
      <button onclick="markAllRead()" class="text-[10px] font-black text-indigo-400 uppercase tracking-widest hover:text-white transition">
        Mark all as read
      </button>
    </div>
  </div>
</div>




<script>
/**
 * 1. MODAL TOGGLE SYSTEM
 */
function toggleModal(id) {
    const wrapper = document.getElementById(id);
    if (!wrapper) return;
    const sheet = wrapper.querySelector('.modal-sheet');

    if (wrapper.classList.contains('hidden')) {
        wrapper.classList.remove('hidden');
        wrapper.classList.add('flex');
        setTimeout(() => { if(sheet) sheet.classList.add('active'); }, 10);
        document.body.style.overflow = 'hidden';
    } else {
        if(sheet) sheet.classList.remove('active');
        setTimeout(() => {
            wrapper.classList.add('hidden');
            wrapper.classList.remove('flex');
            document.body.style.overflow = '';
        }, 400); 
    }
}

// Global Backdrop Click to Close
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('fixed') && event.target.id.startsWith('modal')) {
        toggleModal(event.target.id);
    }
    if (event.target.id === 'notificationModal') {
        closeNotificationModal();
    }
});

/**
 * 2. CALCULATOR LOGIC (Data-Attribute Technique)
 */
function selectService(card) {
    const isSelected = card.getAttribute('data-selected') === 'true';
    card.setAttribute('data-selected', !isSelected);

    const checkBox = card.querySelector('.check-box');
    const checkIcon = card.querySelector('.fa-check');

    if (card.getAttribute('data-selected') === 'true') {
        card.classList.add('border-amber-500', 'bg-amber-50', 'ring-1', 'ring-amber-500/20');
        checkBox.classList.add('bg-amber-500', 'border-amber-500');
        checkIcon.classList.replace('opacity-0', 'opacity-100');
    } else {
        card.classList.remove('border-amber-500', 'bg-amber-50', 'ring-1', 'ring-amber-500/20');
        checkBox.classList.remove('bg-amber-500', 'border-amber-500');
        checkIcon.classList.replace('opacity-100', 'opacity-0');
    }
    calculateTotal();
}

function calculateTotal() {
    let total = 0;
    let count = 0;
    const allCards = document.querySelectorAll('.service-card');

    allCards.forEach(card => {
        if (card.getAttribute('data-selected') === 'true') {
            total += parseFloat(card.getAttribute('data-price') || 0);
            count++;
        }
    });

    document.getElementById('estimate-total-display').innerText = 'KES ' + total.toLocaleString();
    document.getElementById('items-count').innerText = count;
}

function resetCalculator() {
    const allCards = document.querySelectorAll('.service-card');
    allCards.forEach(card => {
        card.setAttribute('data-selected', 'false');
        card.classList.remove('border-amber-500', 'bg-amber-50', 'ring-1', 'ring-amber-500/20');
        card.querySelector('.check-box').classList.remove('bg-amber-500', 'border-amber-500');
        card.querySelector('.fa-check').classList.replace('opacity-100', 'opacity-0');
    });
    calculateTotal();
}

/**
 * 3. NOTIFICATIONS LOGIC
 */
function openNotificationModal() {
    const modal = document.getElementById('notificationModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeNotificationModal() {
    const modal = document.getElementById('notificationModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }
}

async function markAllRead(event) {
    // 1. Properly capture the button
    const markBtn = event ? event.currentTarget : document.querySelector('[onclick*="markAllRead"]');
    if (!markBtn) return;

    const originalText = markBtn.innerText;
    markBtn.innerText = "Processing...";
    markBtn.disabled = true;

    try {
        // 2. Use POST and check path
        const response = await fetch('mark_notifications_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });
        
        const data = await response.json();

        if (data.status === 'success') {
            // 3. Remove badges (pings and red dots)
            document.querySelectorAll('.animate-ping, .bg-rose-500, .notification-badge').forEach(b => b.remove());
            
            // 4. Fade out the unread background styling
            // It's better to target a specific wrapper class like '.notification-item'
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('bg-white/5'); // Remove the "unread" highlight
                item.style.opacity = '0.6';
            });

            markBtn.innerText = "All Read";
        } else {
            throw new Error(data.message || 'Failed to update');
        }
    } catch (error) {
        console.error("Fetch Error:", error);
        markBtn.innerText = "Retry";
        markBtn.disabled = false;
    }
}


function toggleGarageMenu() {
    const menu = document.getElementById('garageDropdown');
    menu.classList.toggle('hidden');
    menu.classList.toggle('flex');
    menu.classList.toggle('flex-col');
}

// Close menu when clicking outside
window.addEventListener('click', function(e) {
    const wrapper = document.getElementById('garageSettingsWrapper');
    const menu = document.getElementById('garageDropdown');
    if (!wrapper.contains(e.target)) {
        menu.classList.add('hidden');
    }
});

async function handleGarageAction(actionType) {
    // 1. Close menu
    const menu = document.getElementById('garageDropdown');
    menu.classList.add('hidden');

    try {
        const response = await fetch('set_preferred_garage.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                garage_id: <?= $garage_id ?>, 
                action: actionType 
            })
        });
        
        const res = await response.json();
        if (res.status === 'success') {
            // We reload to ensure the 'is_preferred' logic (unsetting others) is reflected
            location.reload(); 
        }
    } catch (err) {
        alert("Check your connection");
    }
}



</script>





</body>
</html>
