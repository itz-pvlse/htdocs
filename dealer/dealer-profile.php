<?php
session_start();
require_once('../config/db.php');

/* ============================================================
   1. INTELLIGENCE TRACKING (  VIEW)
   ============================================================ */
$dealer_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($dealer_user_id <= 0) die("Invalid dealer");

// A. Handle Background Button Clicks (AJAX for Profile Clicks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_log_action'])) {
    $d_id = $_POST['d_id'];
    $type = $_POST['ajax_log_action'];
    $s_id = session_id();
    
    try {
        // Log Profile-specific clicks (like 'call_click' from profile)
        $stmt = $pdo->prepare("INSERT INTO dealer_engagement (dealer_id, type, session_id) VALUES (?, ?, ?)");
        $stmt->execute([$d_id, $type, $s_id]);
    } catch (Exception $e) { }
    exit; 
}

// B. Log Initial Profile View (Shows up as 'total_profile' in your hub)
try {
    $viewStmt = $pdo->prepare("INSERT INTO dealer_engagement (dealer_id, type, session_id) VALUES (?, 'profile_view', ?)");
    $viewStmt->execute([$dealer_user_id, session_id()]);
} catch (Exception $e) { }


/* =========================
   GET DEALER DATA (WITH HANDLE)
========================= */
// We join the users table to get the 'handle' for sharing purposes
$stmt = $pdo->prepare("
    SELECT d.*, u.handle, u.name as user_name 
    FROM dealers d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.user_id = ?
");
$stmt->execute([$dealer_user_id]);
$dealer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dealer) die("Dealer not found");

// Define these clearly for your share button in fetch_profile_posts.php
$dealer_handle = $dealer['handle']; 
$dealer_name = $dealer['name']; // The business name from dealers table

/* =========================
   FOLLOW SYSTEM DATA
========================= */
$current_user_id = $_SESSION['user_id'] ?? 0;

// Fetch Follower Counts & Status
$statsStmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM follows WHERE following_id = ?) as followers_count,
        (SELECT COUNT(*) FROM follows WHERE follower_id = ?) as following_count,
        (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?) as is_following
");
$statsStmt->execute([$dealer_user_id, $dealer_user_id, $current_user_id, $dealer_user_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$followers = $stats['followers_count'] ?? 0;
$following = $stats['following_count'] ?? 0;
$isFollowing = ($stats['is_following'] > 0);


/* =========================
   LOGO
========================= */
$logoPath = (!empty($dealer['logo']) && file_exists('../' . $dealer['logo']))
    ? '../' . $dealer['logo']
    : '../uploads/dealers/default_logo.png';

/* =========================
   FILTER & SORT LISTINGS
========================= */
$where = "dealer_id = ? AND status = 'active'";

$params = [$dealer_user_id];

if (!empty($_GET['search'])) {
    $where .= " AND (model LIKE ? OR make LIKE ? OR description LIKE ?)";
    $searchVal = "%" . $_GET['search'] . "%";
    array_push($params, $searchVal, $searchVal, $searchVal);
}

if (!empty($_GET['make'])) {
    $where .= " AND make = ?";
    $params[] = $_GET['make'];
}

if (!empty($_GET['year'])) {
    $where .= " AND year = ?";
    $params[] = $_GET['year'];
}

if (!empty($_GET['transmission'])) {
    $where .= " AND transmission = ?";
    $params[] = $_GET['transmission'];
}

if (!empty($_GET['fuel_type'])) {
    $where .= " AND fuel_type = ?";
    $params[] = $_GET['fuel_type'];
}

$order = "created_at DESC";
if (!empty($_GET['sort'])) {
    switch($_GET['sort']) {
        case 'price_asc':  $order = "price ASC"; break;
        case 'price_desc': $order = "price DESC"; break;
        case 'year_asc':   $order = "year ASC"; break;
        case 'year_desc':  $order = "year DESC"; break;
    }
}

$stmt = $pdo->prepare("SELECT * FROM dealer_listings WHERE $where ORDER BY $order");
$stmt->execute($params);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   REVIEWS
========================= */
$stmt = $pdo->prepare("
    SELECT r.rating, r.review, r.created_at, u.name
    FROM dealer_reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.dealer_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$dealer_user_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   MAP + META
========================= */
$lat = $dealer['latitude'] ?? -1.286389;
$lng = $dealer['longitude'] ?? 36.817223;
$locationText = $dealer['location'] ?? 'Kenya';
$rating = $dealer['rating'] ? number_format($dealer['rating'], 1) : null;

/* =========================
   FETCH FEATURED CARS
========================= */
$feat_stmt = $pdo->prepare("SELECT * FROM dealer_listings WHERE dealer_id = ? AND is_featured = 1 AND status = 'active' ORDER BY created_at DESC");
$feat_stmt->execute([$dealer_user_id]);
$featured_listings = $feat_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($dealer['name']) ?> | AutoLog Dealer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Outfit', 'sans-serif'],
                },
                colors: {
                    primary: '#2563eb',
                    secondary: '#0f172a',
                }
            }
        }
    }
</script>

<style>
    :root {
        --brand-blue: #2563eb;
    }

    .hero-container {
        position: relative;
        min-height: 600px;
        height: 75vh;
        display: flex;
        align-items: flex-end;
        overflow: hidden;
        background-color: #0f172a;
    }

    #map {
        position: absolute;
        inset: 0;
        z-index: 0;
        filter: grayscale(0.4) contrast(1.1) brightness(0.7);
    }

    .map-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, 
            rgba(15, 23, 42, 0.1) 0%, 
            rgba(15, 23, 42, 0.4) 50%, 
            rgba(15, 23, 42, 0.9) 100%);
        z-index: 2;
        pointer-events: none;
    }

    .text-shadow-premium {
        text-shadow: 0 4px 15px rgba(0, 0, 0, 0.6);
    }

    /* Force horizontal scroll for socials if they overflow on tiny screens */
    .social-scroll {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .social-scroll::-webkit-scrollbar { display: none; }

    @media (max-width: 640px) {
        .hero-container { min-height: 550px; height: auto; padding-top: 80px; }
        .button-text { display: none; } /* Show only icons on very small mobile for compactness */
    }
    
     .icon-action-btn {
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-center;
        border-radius: 1.25rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(8px);
    }

    .icon-action-btn:hover {
        transform: translateY(-4px);
        filter: brightness(1.1);
    }

    @media (min-width: 768px) {
        .icon-action-btn { width: 64px; height: 64px; }
    }
    
        .bg-whatsapp { background-color: #25D366; }
    .bg-whatsapp:hover { background-color: #128C7E; }
    
    


/* Disable the hold-menu on the modal image and comments */
.comment-item, 
.comment-item img,
.modal-listing-image { /* Add this class to your main image in the modal */
    -webkit-touch-callout: none !important;
    -webkit-user-select: none !important;
    user-select: none !important;
    -webkit-tap-highlight-color: transparent;
}

/* Ensure the progress bar doesn't flicker */
.hold-progress-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: #ef4444;
    width: 0%;
    opacity: 0;
    transition: width 0.6s linear;
    pointer-events: none;
    z-index: 50;
}

.is-holding .hold-progress-bar {
    opacity: 1;
    width: 100%;
}
</style>



<?php include '../includes/header.php'; ?>


<body class="bg-slate-50 text-slate-800 antialiased selection:bg-blue-100 selection:text-blue-900">



<section class="relative min-h-[85vh] flex items-end overflow-hidden bg-[#0a0a0b] -mt-20 pt-20">
    
    <div id="map" class="absolute inset-0 z-0 grayscale contrast-[1.1] brightness-[0.35] scale-105"></div>
    
    <div class="absolute inset-x-0 top-0 h-1/2 z-10 bg-gradient-to-b from-[#0a0a0b] via-[#0a0a0b]/60 to-transparent"></div>
    
    <div class="absolute inset-x-0 bottom-0 z-10 h-1/2 bg-gradient-to-t from-blue-950 via-blue-950/40 to-transparent"></div>

    <div class="absolute inset-y-0 left-0 z-10 w-full md:w-1/2 bg-gradient-to-r from-[#0a0a0b]/90 to-transparent"></div>

    <div class="relative z-20 w-full max-w-7xl mx-auto px-6 pb-12 md:pb-20">
        <div class="max-w-4xl">
            
            <div class="flex items-center gap-5 mb-8">
                <div class="relative flex-shrink-0">
    <div class="absolute inset-0 bg-blue-600 rounded-full blur-3xl opacity-20"></div>
    
    <div class="relative w-24 h-24 md:w-32 md:h-32 rounded-full p-1.5 bg-white/10 border border-white/20 backdrop-blur-md shadow-2xl">
        <img src="<?= htmlspecialchars($logoPath) ?>" 
             class="w-full h-full object-cover rounded-full bg-white shadow-inner"
             alt="Dealer Logo">
        
        <div class="absolute inset-0 rounded-full bg-gradient-to-tr from-white/10 to-transparent pointer-events-none"></div>
    </div>
</div>


                <div class="flex flex-col gap-1.5 text-left">
                 <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
    <div class="flex items-center gap-3">
        <?php if (isset($dealer['verified']) && $dealer['verified'] == 1): ?>
            <div class="relative group flex items-center shimmer-wrapper">
                <div class="absolute inset-0 bg-blue-600/30 blur-md rounded-lg"></div>
                
                <div class="relative flex items-center gap-2.5 bg-gradient-to-r from-blue-700 to-blue-600 text-white px-4 py-1.5 rounded-lg border border-blue-400/30 shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-center w-5 h-5 bg-white rounded-full shadow-inner shadow-blue-900/20">
                        <i data-lucide="check" class="w-3 h-3 text-blue-700 stroke-[4px]"></i>
                    </div>
                    
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] drop-shadow-md">
                        Verified <span class="text-blue-200">Dealer</span>
                    </span>
                    
                    <div class="shimmer-sweep"></div>
                    
                    <div class="absolute inset-0 bg-gradient-to-tr from-white/20 via-transparent to-transparent opacity-50 rounded-lg"></div>
                </div>
            </div>
        <?php else: ?>
            <div class="flex items-center gap-2 bg-white/5 border border-white/10 backdrop-blur-md px-3 py-1.5 rounded-lg opacity-60">
                <div class="w-1.5 h-1.5 bg-slate-500 rounded-full"></div>
                <span class="text-white/70 text-[9px] font-black uppercase tracking-[0.3em]">Registered</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($rating): ?>
        <div class="hidden sm:block h-4 w-px bg-white/10 mx-1"></div> 
        <span class="text-white/40 text-[9px] font-black uppercase tracking-[0.3em] ml-0 sm:ml-0">
            Est. Quality
        </span>
    <?php endif; ?>
</div>

<style>
/* SHIMMER EFFECT KEYFRAMES */
@keyframes shimmer {
    0% { transform: translateX(-150%) skewX(-20deg); }
    100% { transform: translateX(150%) skewX(-20deg); }
}

.shimmer-sweep {
    position: absolute;
    top: 0;
    left: 0;
    width: 50%;
    height: 100%;
    background: linear-gradient(
        to right,
        transparent 0%,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.4) 50%,
        transparent 100%
    );
    animation: shimmer 3s infinite;
    pointer-events: none;
}

/* Ensure parent is the container for the sweep */
.shimmer-wrapper {
    isolation: isolate;
}
</style>


                    <div class="flex items-center gap-2 text-blue-400">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                        <span class="text-[9px] font-black uppercase tracking-[0.5em] text-white/70 italic"><?= htmlspecialchars($locationText) ?></span>
                    </div>
                </div>
            </div>

            <h1 class="text-6xl md:text-[110px] font-black text-white uppercase leading-[0.85] tracking-tighter drop-shadow-2xl mb-8">
                <?= htmlspecialchars($dealer['name']) ?><span class="text-blue-600">.</span>
            </h1>

            
<div class="flex flex-wrap items-center gap-4 md:gap-8 mb-8">
    <div class="flex gap-5 md:gap-6">
        <div class="flex flex-col">
            <span id="follower-count" class="text-white text-lg md:text-xl font-black leading-none"><?= number_format($followers) ?></span>
            <span class="text-[#818384] text-[8px] uppercase font-bold tracking-[0.2em] mt-1">Followers</span>
        </div>
        <div class="flex flex-col">
            <span class="text-white text-lg md:text-xl font-black leading-none"><?= number_format($following) ?></span>
            <span class="text-[#818384] text-[8px] uppercase font-bold tracking-[0.2em] mt-1">Following</span>
        </div>
    </div>

    <div class="hidden sm:block h-8 w-px bg-white/10"></div>

    <?php if ($current_user_id != $dealer_user_id): ?>
        <button onclick="handleFollow(<?= $dealer_user_id ?>)" 
                id="follow-btn"
                class="min-w-[120px] px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 transform active:scale-95 <?= $isFollowing ? 'bg-white/5 text-white border border-white/10' : 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' ?>">
            <?= $isFollowing ? 'Following' : 'Follow' ?>
        </button>
    <?php endif; ?>
</div>

            
            
            
            <div class="flex flex-wrap items-stretch gap-3 md:gap-4 mt-10">
    <?php if (!empty($dealer['whatsapp'])): ?>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$dealer['whatsapp']) ?>" 
       onclick="logProfileAction('whatsapp_click')"
       target="_blank"
       class="group flex items-center gap-4 bg-[#25D366] hover:bg-emerald-500 text-white pl-2 pr-6 py-2 rounded-2xl transition-all hover:-translate-y-1">
        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
            <i data-lucide="message-circle" class="w-5 h-5"></i>
        </div>
        <span class="font-black text-[10px] uppercase tracking-widest">WhatsApp</span>
    </a>
    <?php endif; ?>

    <?php if (!empty($dealer['phone'])): ?>
    <a href="tel:<?= htmlspecialchars($dealer['phone']) ?>" 
       onclick="logProfileAction('call_click')"
       class="group flex items-center gap-4 bg-white hover:bg-slate-100 text-slate-900 pl-2 pr-6 py-2 rounded-2xl transition-all hover:-translate-y-1 shadow-xl">
        <div class="w-10 h-10 bg-slate-900 rounded-xl flex items-center justify-center text-white">
            <i data-lucide="phone" class="w-5 h-5"></i>
        </div>
        <span class="font-black text-[10px] uppercase tracking-widest">Call Showroom</span>
    </a>
    <?php endif; ?>


<script>
function logProfileAction(actionType) {
    const formData = new FormData();
    formData.append('ajax_log_action', actionType);
    formData.append('d_id', <?= $dealer_user_id ?>);

    // Send the click data to the current page (handled by your PHP block 1-A)
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).catch(err => console.error('Tracking failed:', err));
}
</script>


               <div class="flex items-center gap-6 px-8 bg-white/5 border border-white/10 backdrop-blur-md rounded-2xl">
    <?php 
    $socials = [
        ['link' => $dealer['facebook'], 'icon' => 'facebook'],
        ['link' => $dealer['instagram'], 'icon' => 'instagram'],
        ['link' => $dealer['tiktok'], 'icon' => 'music-2'], // Lucide often uses 'music-2' or 'tiktok' depending on version
        ['link' => $dealer['website'], 'icon' => 'globe']
    ];
    
    foreach($socials as $s): if(!empty($s['link'])): ?>
        <a href="<?= htmlspecialchars($s['link']) ?>" 
           target="_blank" 
           class="text-white/30 hover:text-white transition-all scale-100 hover:scale-125 py-3">
            <i data-lucide="<?= $s['icon'] ?>" class="w-4 h-4"></i>
        </a>
    <?php endif; endforeach; ?>
</div>

            </div>

            <?php if (!empty($dealer['description'])): ?>
<div class="mt-8 space-y-2">
    <div class="flex items-center gap-2 opacity-50">
        <i data-lucide="align-left" class="w-3 h-3 text-white"></i>
        <span class="text-[9px] font-black uppercase tracking-widest text-white">Dealer Bio</span>
    </div>

    <div class="max-w-xl h-24 overflow-y-auto pr-4 no-scrollbar custom-scroll">
        <p class="text-slate-300 text-xs md:text-sm leading-relaxed font-medium opacity-80">
            <?= nl2br(htmlspecialchars($dealer['description'])) ?>
        </p>
    </div>
</div>

<style>
/* This makes the scrollbar subtle and thin */
.custom-scroll::-webkit-scrollbar {
    width: 3px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: rgba(99, 102, 241, 0.5); /* Indigo color */
    border-radius: 10px;
}
</style>
<?php endif; ?>

        </div>
    </div>
</section>

<style>
/* This class should be applied to your modal background/container when open */
.modal-open-fix {
    touch-action: none; /* Disables all browser gestures (zoom, scroll, menus) */
    -webkit-touch-callout: none !important;
    -webkit-user-select: none !important;
}

/* Apply this specifically to the listing image inside the modal */
.modal-listing-image {
    pointer-events: none !important; /* The image becomes "invisible" to touches */
    -webkit-user-drag: none;
}
</style>




<main class="max-w-7xl mx-auto px-6 py-12">

    <div class="flex items-center gap-8 mb-8 border-b border-slate-200">
        <button onclick="showTab('inventory')" id="tab-inv" class="pb-4 text-[11px] font-black uppercase tracking-widest border-b-2 border-blue-600 text-blue-600 transition-all">
            Showroom (<?= count($listings) ?>)
        </button>
        <button onclick="showTab('feed')" id="tab-feed" class="pb-4 text-[11px] font-black uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition-all">
            Community Feed
        </button>
    </div>

    <div id="section-inventory">
        
        <?php 
        $inventory_count = count($listings);
        if (!empty($featured_listings)): 
        ?>
        <section class="mb-12">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-1 h-6 bg-indigo-600 rounded-full"></div>
                <h2 class="text-sm font-black text-white uppercase italic tracking-[0.2em] drop-shadow-sm">Spotlight</h2>
            </div>
            
            <div class="flex gap-4 overflow-x-auto no-scrollbar pb-4 px-1">
                <?php foreach ($featured_listings as $feat): 
                    $feat_img = (!empty($feat['main_image']) && file_exists('../'.$feat['main_image'])) 
                        ? '../'.$feat['main_image'] 
                        : '../uploads/dealer_listings/default_car.png';
                ?>
                <div class="min-w-[260px] md:min-w-[300px]">
                    <a href="car_details.php?id=<?= $feat['id'] ?>" class="group block">
                        <div class="relative aspect-[16/10] rounded-2xl overflow-hidden mb-3 shadow-sm group-hover:shadow-indigo-100 group-hover:shadow-lg transition-all">
                            <img src="<?= htmlspecialchars($feat_img) ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                            <div class="absolute top-2 left-2">
                                <span class="bg-indigo-600/90 backdrop-blur text-white text-[7px] font-black uppercase tracking-widest px-2 py-1 rounded-lg">Featured</span>
                            </div>
                        </div>
                        <h3 class="text-[11px] uppercase tracking-wider truncate mb-1">
                            <span class="font-bold text-slate-800"><?= htmlspecialchars($feat['make']) ?></span>
                            <span class="font-medium text-slate-400"><?= htmlspecialchars($feat['model']) ?></span>
                        </h3>
                        <p class="text-indigo-600 font-black text-[11px] tracking-tight">KES <?= number_format($feat['price']) ?></p>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-1 h-6 bg-blue-600 rounded-full"></div>
                <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">
                    Inventory (<?= $inventory_count ?>)
                </h2>
            </div>
            <button onclick="toggleFilterDrawer()" class="group flex items-center gap-2 text-slate-500 hover:text-blue-600 transition-all">
                <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 group-hover:rotate-180 transition-transform"></i>
                <span class="text-[9px] font-black uppercase tracking-widest">Filter Units</span>
            </button>
        </div>

        <?php if(!empty($_GET['make']) || !empty($_GET['year']) || !empty($_GET['search'])): ?>
        <div class="flex flex-wrap gap-2 mb-6">
            <?php foreach(['search', 'make', 'year'] as $key): if(!empty($_GET[$key])): ?>
                <span class="bg-white border border-slate-100 text-slate-600 px-3 py-1.5 rounded-full text-[8px] font-bold uppercase tracking-wider flex items-center gap-2 shadow-sm">
                    <?= htmlspecialchars($_GET[$key]) ?> 
                    <a href="?id=<?=$dealer_user_id?>" class="text-slate-300 hover:text-red-500"><i data-lucide="x" class="w-2.5 h-2.5"></i></a>
                </span>
            <?php endif; endforeach; ?>
            <a href="?id=<?= $dealer_user_id ?>" class="text-[8px] font-bold uppercase tracking-widest text-blue-500 self-center ml-2">Reset</a>
        </div>
        <?php endif; ?>

        <?php if ($inventory_count > 0): ?>
        <div id="listingGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            <?php foreach ($listings as $car):
                $img = (!empty($car['main_image']) && file_exists('../'.$car['main_image'])) 
                    ? '../'.$car['main_image'] 
                    : '../uploads/dealer_listings/default_car.png';
            ?>
            <a href="car_details.php?id=<?= $car['id'] ?>" class="group block">
                <article class="bg-white rounded-xl overflow-hidden border border-slate-100 shadow-sm hover:shadow-md hover:border-blue-100 transition-all flex flex-col h-full">
                    <div class="relative aspect-[16/10] overflow-hidden bg-slate-50">
                        <img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-cover transition duration-500 group-hover:scale-110">
                        <div class="absolute top-1.5 left-1.5 flex flex-col gap-1 items-start">
                            <span class="bg-black/70 backdrop-blur-[2px] px-1.5 py-0.5 rounded text-[7px] font-bold text-white uppercase italic">
                                <?= (int)$car['year'] ?>
                            </span>
                            <?php if (strtotime($car['created_at']) > strtotime('-2 days')): ?>
                                <span class="bg-emerald-500 shadow-lg shadow-emerald-500/20 px-1.5 py-0.5 rounded text-[7px] font-black text-white uppercase tracking-widest animate-pulse">New</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-2.5 flex flex-col flex-1">
                        <div class="mb-2">
                            <p class="text-[8px] font-bold text-blue-500 uppercase tracking-tighter mb-0.5"><?= htmlspecialchars($car['make']) ?></p>
                            <h3 class="font-bold text-[11px] text-slate-800 uppercase truncate leading-tight"><?= htmlspecialchars($car['model']) ?></h3>
                        </div>

                        <div class="flex items-center gap-2 mb-3 text-[8px] font-medium text-slate-400 border-t border-slate-50 pt-2">
                            <span class="flex items-center gap-0.5" title="<?= htmlspecialchars($car['transmission']) ?>"><i data-lucide="settings-2" class="w-2.5 h-2.5"></i> <?= !empty($car['transmission']) ? strtoupper(substr($car['transmission'], 0, 1)) : '—' ?></span>
                            <span class="flex items-center gap-0.5" title="<?= htmlspecialchars($car['fuel_type']) ?>"><i data-lucide="fuel" class="w-2.5 h-2.5"></i> <?= !empty($car['fuel_type']) ? strtoupper(substr($car['fuel_type'], 0, 1)) : '—' ?></span>
                            <span class="flex items-center gap-0.5"><i data-lucide="zap" class="w-2.5 h-2.5"></i> 
                                <?= (strpos(strtolower($car['fuel_type']), 'elect') !== false) ? 'EV' : ($car['engine_cc'] > 0 ? number_format($car['engine_cc']) : '—') ?>
                            </span>
                        </div>

                        <div class="mt-auto pt-2 border-t border-slate-50 flex items-center justify-between">
                            <p class="text-[12px] font-black text-slate-900 tracking-tighter"><span class="text-[8px] font-normal text-slate-400 mr-0.5">KES</span><?= number_format($car['price']) ?></p>
                            <div class="text-blue-500 transition-transform group-hover:translate-x-0.5"><i data-lucide="chevron-right" class="w-4 h-4"></i></div>
                        </div>
                    </div>
                </article>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-24 bg-white rounded-3xl border border-dashed border-slate-200 mt-6">
            <div class="bg-slate-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"><i data-lucide="search-x" class="w-6 h-6 text-slate-300"></i></div>
            <h3 class="text-lg font-black text-slate-400 uppercase tracking-tighter">No Units Found</h3>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1 mb-6">Try adjusting your preferences</p>
            <button onclick="toggleFilterDrawer()" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-blue-600 transition-all">Modify Filter</button>
        </div>
        <?php endif; ?>
    </div>

<div id="section-feed" class="hidden">
    <div class="w-full max-w-7xl mx-auto py-6">
        
        <div class="flex items-center justify-between mb-8 px-5 sm:px-6">
            <div class="flex flex-col">
                <div class="flex items-center gap-3">
                    <h3 class="text-blue-500 font-black text-[11px] uppercase tracking-[0.4em] drop-shadow-[0_0_8px_rgba(59,130,246,0.5)]">
                        Dealer Feed
                    </h3>
                    <span class="flex h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                </div>
                <div class="h-[2px] w-8 bg-blue-600 mt-2 shadow-[0_0_10px_rgba(37,99,235,0.8)]"></div>
            </div>

            <a href="../feeds/index.php" 
               class="relative group flex items-center gap-4 bg-[#0a0a0b] pl-5 pr-4 py-2.5 rounded-2xl border border-white/10 transition-all active:scale-95 shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
                <div class="absolute right-0 top-1/4 bottom-1/4 w-0.5 bg-blue-600"></div>
                <div class="flex flex-col items-end">
                    <span class="text-[7px] font-black text-blue-400 uppercase tracking-[0.2em] leading-none mb-1">Explore Full</span>
                    <span class="text-[10px] font-black text-white uppercase tracking-widest leading-none">Community</span>
                </div>
                <div class="relative w-9 h-9 rounded-xl bg-white/5 flex items-center justify-center group-hover:bg-blue-600/20 transition-all border border-white/5">
                    <i data-lucide="globe" class="relative w-4 h-4 text-white group-hover:text-blue-400 transition-all"></i>
                </div>
            </a>
        </div>

        <div id="profile-posts-container" 
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-0 sm:gap-6 w-full">
            
            <div class="col-span-full flex flex-col items-center justify-center py-24">
                <div class="relative w-10 h-10 mb-6">
                    <div class="absolute inset-0 border-t-2 border-blue-600 rounded-full animate-spin"></div>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.4em]">Syncing Feed</p>
            </div>
        </div>
    </div>
</div>


</main>

<script>
/* ============================================================
   DEALER DASHBOARD GLOBAL SCRIPT - FULL VERSION
   ============================================================ */

let feedLoaded = false;

/**
 * TAB NAVIGATION
 */
function showTab(tab) {
    const invSection = document.getElementById('section-inventory');
    const feedSection = document.getElementById('section-feed');
    const invBtn = document.getElementById('tab-inv');
    const feedBtn = document.getElementById('tab-feed');
    const filterMobileTrigger = document.getElementById('filter-mobile-trigger');

    if(tab === 'feed') {
        if (filterMobileTrigger) {
            filterMobileTrigger.classList.add('opacity-0', 'translate-y-20', 'pointer-events-none');
        }
        invSection.classList.add('hidden');
        feedSection.classList.remove('hidden');
        feedBtn.classList.add('border-blue-600', 'text-blue-600');
        feedBtn.classList.remove('border-transparent', 'text-slate-400');
        invBtn.classList.remove('border-blue-600', 'text-blue-600');
        invBtn.classList.add('border-transparent', 'text-slate-400');
        
        if(!feedLoaded) {
            loadProfilePosts(<?= $dealer_user_id ?>);
        }
    } else {
        if (filterMobileTrigger) {
            filterMobileTrigger.classList.remove('opacity-0', 'translate-y-20', 'pointer-events-none');
        }
        feedSection.classList.add('hidden');
        invSection.classList.remove('hidden');
        invBtn.classList.add('border-blue-600', 'text-blue-600');
        invBtn.classList.remove('border-transparent', 'text-slate-400');
        feedBtn.classList.remove('border-blue-600', 'text-blue-600');
        feedBtn.classList.add('border-transparent', 'text-slate-400');
    }
}

/**
 * FEED LOADING & MEDIA INITIALIZATION
 */
function loadProfilePosts(userId) {
    const container = document.getElementById('profile-posts-container');
    fetch(`../feeds/fetch_profile_posts.php?user_id=${userId}`)
        .then(res => res.text())
        .then(html => {
            container.innerHTML = html;
            feedLoaded = true;
            if(window.lucide) lucide.createIcons();

            // CRITICAL: Initialize observers AFTER the HTML exists in the DOM
            initMediaObservers();
        })
        .catch(() => {
            container.innerHTML = '<p class="text-center text-xs text-red-500 font-bold uppercase tracking-widest py-10">Failed to load posts.</p>';
        });
}

/**
 * SLIDER SYNCING (Numbers and Dots)
 * Specifically for the 4:5 Instagram-style layout
 */
function handleSliderScroll(el, postId) {
    const scrollLeft = el.scrollLeft;
    const width = el.clientWidth;
    const index = Math.round(scrollLeft / width);
    const total = el.children.length;

    // Update Numerical Counter (e.g., 1 / 4)
    const counter = document.getElementById(`counter-${postId}`);
    if (counter) counter.innerText = `${index + 1} / ${total}`;

    // Update Pagination Dots
    const dotsContainer = document.getElementById(`dots-${postId}`);
    if (dotsContainer) {
        const dots = dotsContainer.querySelectorAll('div');
        dots.forEach((dot, i) => {
            if (i === index) {
                dot.className = 'w-3 h-1.5 rounded-full transition-all duration-300 bg-blue-600';
            } else {
                dot.className = 'w-1.5 h-1.5 rounded-full transition-all duration-300 bg-slate-200';
            }
        });
    }
}

function initMediaObservers() {
    const feedVideos = document.querySelectorAll('.feed-video-player');
    
    // 1. Setup Intersection Observer
    const videoObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const video = entry.target;
            if (entry.isIntersecting) {
                video.muted = true; 
                const playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.catch(() => { /* Log blocked autoplay if needed */ });
                }
            } else {
                video.pause();
            }
        });
    }, { threshold: 0.4 });

    feedVideos.forEach(vid => videoObserver.observe(vid));

    // 2. Global Unlocker
    const unlockAutoplay = () => {
        feedVideos.forEach(video => {
            if (video.paused) {
                video.play().catch(() => {});
            }
        });
        document.removeEventListener('touchstart', unlockAutoplay);
        document.removeEventListener('click', unlockAutoplay);
    };
    document.addEventListener('touchstart', unlockAutoplay, { passive: true });
    document.addEventListener('click', unlockAutoplay, { passive: true });
}

/**
 * SOCIAL & INTERACTION LOGIC
 */
function sharePost(postId, title, handle, userId) {
    const shareUrl = `${window.location.origin}/feeds/index.php?post_id=${postId}`;
    
    if (navigator.share) {
        navigator.share({
            title: `Check out @${handle}'s post on CarSoko`,
            url: shareUrl
        }).catch(err => console.log('Error sharing', err));
    } else {
        // Fallback to Clipboard
        navigator.clipboard.writeText(shareUrl).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}

function handleUpvote(postId, btn) {
    const span = btn.querySelector('span');
    fetch('../feeds/ajax_like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content_id: postId, type: 'post' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('svg, i');
            if (data.action === 'liked') {
                icon.classList.add('fill-orange-500', 'text-orange-500');
                icon.classList.remove('text-slate-300');
            } else {
                icon.classList.remove('fill-orange-500', 'text-orange-500');
                icon.classList.add('text-slate-300');
            }
            span.innerText = data.new_count;
        }
    })
    .catch(err => console.error("Upvote error:", err));
}

function handleFollow(userId) {
    const btn = document.getElementById('follow-btn');
    const countSpan = document.getElementById('follower-count');
    
    fetch('../feeds/ajax_follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            countSpan.innerText = data.new_count;
            if (data.action === 'followed') {
                btn.innerText = 'Following';
                btn.className = "w-full py-3 rounded-2xl text-[11px] font-black uppercase tracking-widest transition-all duration-300 bg-[#2D2D2E] text-white border border-white/10";
            } else {
                btn.innerText = 'Follow';
                btn.className = "w-full py-3 rounded-2xl text-[11px] font-black uppercase tracking-widest transition-all duration-300 bg-white text-black shadow-lg";
            }
            if (navigator.vibrate) navigator.vibrate(30);
        }
    })
    .catch(err => console.error("Follow error:", err));
}

/**
 * COMMENTING SYSTEM
 */
function openComments(postId, authorName) {
    const modal = document.getElementById('commentModal');
    const panel = document.getElementById('modalPanel');
    document.getElementById('modalPostId').value = postId;
    document.getElementById('modalPostAuthor').innerText = `Discussion with ${authorName}`;
    cancelReply();
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        panel.classList.remove('translate-y-full');
        panel.classList.add('translate-y-0');
    }, 10);
    fetchComments(postId);
}

function fetchComments(postId) {
    const container = document.getElementById('modalCommentsContainer');
    container.innerHTML = '<div class="flex justify-center py-10 opacity-20"><div class="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full"></div></div>';
    fetch(`../feeds/get_comments.php?post_id=${postId}`)
        .then(res => res.text())
        .then(html => {
            container.innerHTML = html;
            if(window.lucide) lucide.createIcons();
        });
}

function closeComments() {
    const modal = document.getElementById('commentModal');
    const panel = document.getElementById('modalPanel');
    panel.classList.remove('translate-y-0');
    panel.classList.add('translate-y-full');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 500);
}

function submitComment(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const postId = formData.get('post_id');

    fetch('../feeds/process_comment_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            form.reset();
            cancelReply();
            fetchComments(postId);
            if(typeof loadProfilePosts === "function") loadProfilePosts(<?= $dealer_user_id ?>);
        }
    });
}

function prepareReply(handle, commentId) {
    const indicator = document.getElementById('replyIndicator');
    const input = document.getElementById('modalParentId');
    const text = document.getElementById('commentText');
    const handleSpan = indicator.querySelector('span');
    input.value = commentId;
    handleSpan.innerText = `Replying to @${handle}`;
    indicator.classList.remove('hidden');
    text.placeholder = `Reply to @${handle}...`;
    text.focus();
}

function cancelReply() {
    const indicator = document.getElementById('replyIndicator');
    if(indicator) {
        document.getElementById('modalParentId').value = "";
        indicator.classList.add('hidden');
        document.getElementById('commentText').placeholder = "What are your thoughts?";
    }
}

/**
 * MENU & OVERLAY LOGIC
 */
function toggleCommentMenu(e, id) {
    e.stopPropagation();
    document.querySelectorAll('[id^="dropdown-"]').forEach(menu => {
        if (menu.id !== `dropdown-${id}`) menu.classList.add('hidden');
    });
    const menu = document.getElementById(`dropdown-${id}`);
    if (menu) menu.classList.toggle('hidden');
}

function processCommentDelete(commentId) {
    const formData = new FormData();
    formData.append('comment_id', commentId);
    fetch('../feeds/comment_delete.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const el = document.getElementById(`comment-wrapper-${commentId}`);
            if (el) {
                el.style.opacity = '0';
                el.style.transform = 'scale(0.95)';
                setTimeout(() => el.remove(), 300);
            }
        }
    });
}

/**
 * MEDIA HANDLING (PREVIEW & VOLUME)
 */
function toggleFeedVolume(btn, e) {
    e.stopPropagation();
    const video = btn.parentElement.querySelector('video');
    const icon = btn.querySelector('.volume-icon');
    if (video.muted) {
        video.muted = false;
        icon.setAttribute('data-lucide', 'volume-2');
    } else {
        video.muted = true;
        icon.setAttribute('data-lucide', 'volume-x');
    }
    lucide.createIcons();
}

function openMediaPreview(src, type) {
    const modal = document.createElement('div');
    modal.id = 'media-preview-modal';
    // Using flex and centering to ensure the media stays centered during the drag
    modal.className = 'fixed inset-0 z-[10000] bg-black flex flex-col items-center justify-center p-0 md:p-10 select-none touch-none animate-in fade-in duration-200';
    
    const mediaContent = type === 'image' 
        ? `<img src="${src}" id="preview-media" class="max-w-full max-h-full object-contain shadow-2xl transition-transform duration-200 ease-out">`
        : `<video src="${src}" id="preview-media" class="max-w-full max-h-full" controls autoplay playsinline></video>`;

    modal.innerHTML = `
        <button class="absolute top-6 right-6 z-[10001] bg-white/10 hover:bg-white/20 p-2 rounded-full text-white backdrop-blur-md transition-all" onclick="closeMediaPreview()">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
        <div class="w-full h-full flex items-center justify-center overflow-hidden" id="preview-drag-area">
            ${mediaContent}
        </div>
        <div class="absolute bottom-6 text-white/30 text-[9px] font-black uppercase tracking-[0.4em] pointer-events-none">Swipe any direction to exit</div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden'; 
    if(window.lucide) lucide.createIcons();

    const mediaObj = document.getElementById('preview-media');
    let startX = 0, startY = 0;
    let currentX = 0, currentY = 0;

    modal.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        mediaObj.style.transition = 'none'; // Disable transition while dragging for 1:1 feel
    }, {passive: true});

    modal.addEventListener('touchmove', (e) => {
        currentX = e.touches[0].clientX - startX;
        currentY = e.touches[0].clientY - startY;

        // Calculate distance from origin to fade background
        const distance = Math.sqrt(currentX * currentX + currentY * currentY);
        const opacity = Math.max(0, 1 - (distance / 400));
        
        modal.style.backgroundColor = `rgba(0,0,0, ${opacity})`;
        // Move the image with the finger
        mediaObj.style.transform = `translate(${currentX}px, ${currentY}px) scale(${1 - (distance / 2000)})`;
    }, {passive: true});

    modal.addEventListener('touchend', () => {
        const distance = Math.sqrt(currentX * currentX + currentY * currentY);
        
        // If swiped more than 100px in any direction, close it
        if (distance > 100) {
            closeMediaPreview();
        } else {
            // Snap back to center
            mediaObj.style.transition = 'transform 0.3s cubic-bezier(0.2, 0, 0.2, 1)';
            modal.style.backgroundColor = 'black';
            mediaObj.style.transform = 'translate(0, 0) scale(1)';
        }
        // Reset tracking
        currentX = 0;
        currentY = 0;
    });

    modal.onclick = (e) => { 
        if(e.target === modal || e.target.id === 'preview-drag-area') closeMediaPreview(); 
    };
}

function closeMediaPreview() {
    const modal = document.getElementById('media-preview-modal');
    if (modal) {
        modal.classList.replace('fade-in', 'fade-out');
        setTimeout(() => { modal.remove(); document.body.style.overflow = ''; }, 200);
    }
}

/**
 * GLOBAL LISTENERS
 */
document.addEventListener('click', () => {
    document.querySelectorAll('[id^="dropdown-"]').forEach(menu => menu.classList.add('hidden'));
});
document.addEventListener('keydown', (e) => { if (e.key === "Escape") closeMediaPreview(); });

</script>




<div id="filterBackdrop" onclick="toggleFilterDrawer()" class="fixed inset-0 bg-[#0a0a0b]/60 backdrop-blur-sm z-40 hidden transition-opacity opacity-0"></div>

<div id="filterDrawer" 
     class="fixed top-6 bottom-6 right-6 w-[85%] max-w-[300px] bg-[#0a0a0b]/95 border border-white/20 backdrop-blur-2xl shadow-[0_0_40px_rgba(0,0,0,0.7)] z-50 transform translate-x-[120%] transition-transform duration-500 ease-out flex flex-col rounded-[2rem] overflow-hidden">
    
    <div class="p-5 border-b border-white/10 flex justify-between items-center bg-gradient-to-b from-white/5 to-transparent">
        <div>
            <h3 class="text-xl font-black text-white uppercase tracking-tighter leading-none">Filter<span class="text-blue-500">.</span></h3>
            <p class="text-[7px] font-black text-white/30 uppercase tracking-[0.3em] mt-1">Refine Stock</p>
        </div>
        <button onclick="toggleFilterDrawer()" class="p-2 bg-white text-black rounded-lg transition-all hover:scale-110 active:scale-90 shadow-lg shadow-white/10">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

    <div class="flex-1 overflow-y-auto p-5 custom-scrollbar">
    <form method="GET" id="drawerFilterForm" class="space-y-6">
        <input type="hidden" name="id" value="<?= $dealer_user_id ?>">
        
        <div class="space-y-2">
            <label class="block text-[8px] font-black text-white/30 uppercase tracking-[0.4em]">Search Engine</label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-white/40 w-3.5 h-3.5"></i>
                <input type="text" name="search" 
                       placeholder="Model, keyword..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                       class="w-full pl-10 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-white focus:bg-white/10 outline-none font-bold text-xs text-white transition-all placeholder-white/20">
            </div>
        </div>

        <?php 
        // 2. YOUR ORIGINAL FETCH LOGIC
        $makes = $pdo->query("SELECT DISTINCT make FROM dealer_listings WHERE dealer_id = $dealer_user_id AND make IS NOT NULL ORDER BY make ASC")->fetchAll(PDO::FETCH_COLUMN);

        // 3. DROPDOWN FIELDS
        $fields = [
            ['name' => 'make', 'label' => 'Manufacturer', 'icon' => 'car', 'options' => $makes],
            ['name' => 'transmission', 'label' => 'Drive System', 'icon' => 'settings-2', 'options' => ['Automatic', 'Manual']],
            ['name' => 'fuel_type', 'label' => 'Energy Source', 'icon' => 'zap', 'options' => ['Petrol', 'Diesel', 'Hybrid', 'Electric']],
            ['name' => 'sort', 'label' => 'Sort By', 'icon' => 'layers', 'options' => ['price_asc' => 'Price: Low-High', 'price_desc' => 'Price: High-Low', 'year_desc' => 'Newest First']]
        ];

        foreach($fields as $field): ?>
        <div class="space-y-2">
            <label class="block text-[8px] font-black text-white/30 uppercase tracking-[0.4em]"><?= $field['label'] ?></label>
            <div class="relative group">
                <select name="<?= $field['name'] ?>" 
                        class="w-full pl-4 pr-10 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-white focus:bg-white/10 outline-none appearance-none font-bold text-xs text-white cursor-pointer transition-all">
                    
                    <option value="" class="bg-[#0a0a0b]">Any <?= $field['label'] ?></option>
                    
                    <?php foreach($field['options'] as $key => $val): 
                        $optionVal = is_numeric($key) ? $val : $key;
                        $isSelected = (isset($_GET[$field['name']]) && $_GET[$field['name']] == $optionVal) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($optionVal) ?>" <?= $isSelected ?> class="bg-[#0a0a0b]">
                            <?= htmlspecialchars($val) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 text-white/20 w-3 h-3 pointer-events-none group-focus-within:text-white transition-colors"></i>
            </div>
        </div>
        <?php endforeach; ?>
    </form>
</div>



    <div class="p-5 border-t border-white/10 bg-black/40 flex flex-col gap-2">
        <button type="submit" form="drawerFilterForm" class="w-full py-4 bg-blue-600 text-white rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-blue-500 transition-all shadow-lg shadow-blue-600/20">
            Show Results
        </button>
        <a href="?id=<?= $dealer_user_id ?>" class="w-full py-3 text-center bg-white text-black rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-slate-200 transition-all">
            Reset All
        </a>
    </div>
</div>

<script>
// Update your toggle function to use 120% for hidden and 0 for visible
function toggleFilterDrawer() {
    const drawer = document.getElementById('filterDrawer');
    const backdrop = document.getElementById('filterBackdrop');
    const isHidden = drawer.classList.contains('translate-x-[120%]');

    if (isHidden) {
        backdrop.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            drawer.classList.remove('translate-x-[120%]');
            drawer.classList.add('translate-x-0');
        }, 10);
        document.body.style.overflow = 'hidden';
    } else {
        drawer.classList.remove('translate-x-0');
        drawer.classList.add('translate-x-[120%]');
        backdrop.classList.add('opacity-0');
        setTimeout(() => backdrop.classList.add('hidden'), 500);
        document.body.style.overflow = '';
    }
}
</script>



<section class="max-w-7xl mx-auto mt-24 mb-20 px-6">
    <div class="flex items-center gap-5 mb-12">
        <div class="w-1 h-14 bg-slate-200 rounded-full"></div>
        <div class="flex flex-col">
            <h3 class="text-4xl md:text-5xl font-black text-slate-300 tracking-tighter uppercase leading-none">
                Client Voices
            </h3>
            <p class="text-[10px] md:text-xs font-black text-slate-400 uppercase tracking-[0.3em] mt-2">
                Authentic Dealer Feedback
            </p>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-8">
        <?php foreach ($reviews as $rev): ?>
            <div class="relative bg-slate-50/50 border border-slate-100 p-10 rounded-[2.5rem] transition-all duration-500 hover:bg-white hover:shadow-2xl hover:shadow-slate-200/50 group">
                
                <div class="absolute top-8 right-10 text-slate-200 opacity-50 group-hover:text-blue-500 transition-colors">
                    <i data-lucide="quote" class="w-8 h-8 fill-current"></i>
                </div>

                <div class="flex flex-col h-full">
                    <div class="flex gap-1 mb-6">
                        <?php for($i=0; $i<5; $i++): ?>
                            <i data-lucide="star" class="w-4 h-4 <?= $i < $rev['rating'] ? 'text-yellow-400 fill-current' : 'text-slate-200' ?>"></i>
                        <?php endfor; ?>
                    </div>

                    <?php if (!empty($rev['review'])): ?>
                        <blockquote class="flex-1">
                            <p class="text-xl md:text-2xl font-black text-slate-900 leading-tight tracking-tight mb-8 line-clamp-4">
                                "<?= nl2br(htmlspecialchars($rev['review'])) ?>"
                            </p>
                        </blockquote>
                    <?php endif; ?>

                    <div class="flex items-center gap-4 pt-6 border-t border-slate-200/60">
                        <div class="w-12 h-12 rounded-full bg-white border-2 border-slate-100 flex items-center justify-center shadow-sm">
                            <span class="text-lg font-black text-slate-400 uppercase"><?= substr(htmlspecialchars($rev['name']), 0, 1) ?></span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-black text-slate-900 uppercase tracking-tighter leading-none">
                                <?= htmlspecialchars($rev['name']) ?>
                            </span>
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">
                                <?= date('F Y', strtotime($rev['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>



<?php include '../includes/footer.php'; ?>

<script>
    // Initialize All Icons at once
    lucide.createIcons();



    // 3. Map Initialization (Only one declaration!)
    // Make sure $lat and $lng are provided by your PHP
    var dealerMap = L.map('map', {
        zoomControl: false,
        scrollWheelZoom: false,
        dragging: true 
    }).setView([<?= $lat ?>, <?= $lng ?>], 15);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CARTO'
    }).addTo(dealerMap);

    var customIcon = L.divIcon({
        className: 'custom-div-icon',
        html: "<div style='background-color:#2563eb; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(37,99,235,0.5);'></div>",
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });

    L.marker([<?= $lat ?>, <?= $lng ?>], { icon: customIcon }).addTo(dealerMap);

    
</script>

<div id="filter-mobile-trigger" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 lg:hidden transition-all duration-300 ease-in-out">
    <button onclick="toggleFilterDrawer()" 
            class="relative group flex items-center gap-3 bg-[#0a0a0b]/90 backdrop-blur-xl pl-4 pr-5 py-3 rounded-xl border border-white/10 transition-all active:scale-90 shadow-2xl">
                  <div class="absolute left-0 top-1/4 bottom-1/4 w-0.5 bg-blue-600"></div>

        <div class="relative flex items-center justify-center">
            <div class="absolute inset-0 bg-blue-500 blur-md opacity-20"></div>
            <i data-lucide="list-filter" class="relative w-4 h-4 text-blue-400"></i>
        </div>

        <div class="flex flex-col items-start">
            <span class="text-[7px] font-black text-blue-500 uppercase tracking-[0.3em] leading-none mb-0.5">Search &</span>
            <span class="text-[10px] font-black text-white uppercase tracking-widest leading-none">Filter</span>
        </div>

        <div class="absolute inset-0 overflow-hidden rounded-xl pointer-events-none">
            <div class="w-full h-[1px] bg-white/5 absolute top-0 animate-[scan_3s_linear_infinite]"></div>
        </div>
        </button>
</div>



<style>
@keyframes scan {
    0% { top: 0%; opacity: 0; }
    50% { opacity: 1; }
    100% { top: 100%; opacity: 0; }
}
</style>

<div id="commentModal" class="fixed inset-0 top-[80px] z-[100] hidden flex justify-center items-end p-2 md:p-4 overflow-hidden">
    <div class="absolute inset-0 bg-black/90 backdrop-blur-md" onclick="closeComments()"></div>
    
    <div id="modalPanel" class="relative w-full max-w-2xl bg-[#0B1416] h-full shadow-[0_-10px_40px_rgba(0,0,0,0.5)] transform translate-y-full transition-transform duration-500 ease-[cubic-bezier(0.33,1,0.68,1)] flex flex-col border-t border-x border-white/10 rounded-t-[40px] overflow-hidden">
        
        <div class="absolute top-3 left-1/2 -translate-x-1/2 w-10 h-1 bg-white/10 rounded-full z-20"></div>

        <div class="pt-10 pb-4 px-6 border-b border-white/5 relative bg-[#0B1416]/80 backdrop-blur-xl">
            <div class="text-center">
                <h3 class="text-[12px] font-black uppercase tracking-[0.2em] text-white">Comments</h3>
                <p id="modalPostAuthor" class="text-[9px] text-blue-500/80 font-bold mt-1 tracking-widest uppercase">Thread View</p>
            </div>
            
            <button onclick="closeComments()" class="absolute right-4 top-1/2 -translate-y-1/2 p-2 hover:bg-white/5 rounded-full transition-all group">
                <i data-lucide="x" class="w-5 h-5 text-white/40 group-hover:text-white"></i>
            </button>
        </div>

        <div id="modalCommentsContainer" class="flex-1 overflow-y-auto p-6 space-y-6 bg-transparent custom-scrollbar">
             </div>

        <div class="flex-shrink-0 p-6 border-t border-white/5 bg-[#0B1416] pb-10 md:pb-6">
            <form id="commentForm" onsubmit="submitComment(event)" class="flex flex-col gap-4">
                <input type="hidden" id="modalPostId" name="post_id">
                <input type="hidden" id="modalParentId" name="parent_id" value="">
                
                <textarea id="commentText" name="comment_text" 
                    class="w-full text-sm p-4 bg-white/5 text-[#D7DADC] rounded-2xl border border-white/10 focus:border-blue-500/50 focus:ring-4 focus:ring-blue-500/10 resize-none transition-all placeholder-white/20" 
                    placeholder="Add a comment..." rows="2" required></textarea>
                
                <div class="flex flex-col items-center gap-4">
                    <div id="replyIndicator" class="hidden flex items-center gap-2 px-3 py-1.5 bg-blue-500/10 rounded-lg text-[10px] font-bold text-blue-400 uppercase tracking-tight border border-blue-500/20">
                        <i data-lucide="corner-down-right" class="w-3 h-3"></i>
                        <span>Replying to User</span>
                        <button type="button" onclick="cancelReply()" class="ml-2 text-white/40 hover:text-white">
                            <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>

                    <button type="submit" class="w-full max-w-[240px] flex items-center justify-center gap-2 bg-white text-[#0B1416] text-[11px] font-black uppercase tracking-[0.2em] py-4 rounded-full hover:scale-[1.02] active:scale-95 transition-all shadow-xl shadow-white/5">
                        Post Comment
                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Smooth slide-up timing */
#modalPanel {
    transition-timing-function: cubic-bezier(0.33, 1, 0.68, 1);
}

/* Hide horizontal scroll for mobile safety */
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

/* Focus glow for the textarea */
#commentText:focus {
    background-color: rgba(255, 255, 255, 0.07);
}
</style>

<div id="shareToast" class="fixed top-24 left-1/2 -translate-x-1/2 z-[200] transform -translate-y-20 opacity-0 transition-all duration-500 pointer-events-none">
    <div class="bg-white text-[#0B1416] px-6 py-3 rounded-full shadow-2xl flex items-center gap-3 border border-white/20">
        <i data-lucide="check-circle" class="w-4 h-4 text-green-600"></i>
        <span class="text-[11px] font-black uppercase tracking-widest">Link Copied to Clipboard</span>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const sharedPostId = params.get('post_id');
    
    if (sharedPostId) {
        // 1. Target your specific tab button ID to reveal the feed
        const feedTab = document.getElementById('tab-feed');
        
        if (feedTab) {
            // Trigger your existing showTab('feed') function
            feedTab.click(); 
        }

        // 2. Wait for the feed content to be active in the DOM
        setTimeout(() => {
            const targetPost = document.querySelector(`[data-post-id="${sharedPostId}"]`);
            
            if (targetPost) {
                // 3. Scroll to the post so it's perfectly centered on screen
                targetPost.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // 4. Add a temporary highlight effect so the user knows this is the shared post
                targetPost.classList.add('ring-2', 'ring-blue-500', 'shadow-lg');
                
                // Remove the highlight after 3 seconds for a clean look
                setTimeout(() => {
                    targetPost.classList.remove('ring-2', 'ring-blue-500', 'shadow-lg');
                }, 3000);
            }
        }, 600); 

        // 5. Clean URL to prevent re-scrolling on manual refresh
        const cleanUrl = window.location.origin + window.location.pathname + '?u=' + params.get('u');
        window.history.replaceState({}, '', cleanUrl);
    }
});

</script>
</body>
</html>
