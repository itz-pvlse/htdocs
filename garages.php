<?php
require_once 'config/db.php';
include 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =======================
   FETCH LOCATIONS FOR FILTER
======================= */
$stmt = $pdo->query("SELECT DISTINCT location FROM garages WHERE location IS NOT NULL AND location != ''");
$allLocations = $stmt->fetchAll(PDO::FETCH_COLUMN);

$search = trim($_GET['search'] ?? '');
$selectedLocation = trim($_GET['location'] ?? '');
$latitude = $_GET['latitude'] ?? null;
$longitude = $_GET['longitude'] ?? null;

/* =======================
   BUILD FILTERS
======================= */
$filters = [];
$params = [];

// 1. Keyword Search (Search Name, Location, and Specialties)
if (!empty($search)) {
    $filters[] = "(g.name LIKE ? OR g.location LIKE ? OR s.specialties LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// 2. Specific Region Filter
if (!empty($selectedLocation)) {
    $filters[] = "g.location = ?";
    $params[] = $selectedLocation;
}

$whereClause = !empty($filters) ? " WHERE " . implode(" AND ", $filters) : "";


$sql = "
    SELECT g.*,
           s.specialties,
           IFNULL(AVG(r.rating), 0) AS avg_rating,
           COUNT(r.id) AS reviews_count
    FROM garages g
    LEFT JOIN (
        SELECT garage_id, GROUP_CONCAT(specialty_name SEPARATOR ',') AS specialties
        FROM garage_specialties
        GROUP BY garage_id
    ) s ON g.id = s.garage_id
    LEFT JOIN garage_reviews r ON g.id = r.garage_id
    $whereClause
    GROUP BY g.id
";


/* =======================
   FETCH GARAGES WITH SPECIALTIES & REVIEWS
======================= */
$sql = "
    SELECT g.*,
           s.specialties,
           IFNULL(AVG(r.rating), 0) AS avg_rating,
           COUNT(r.id) AS reviews_count
    FROM garages g
    LEFT JOIN (
        SELECT garage_id, GROUP_CONCAT(specialty_name SEPARATOR ',') AS specialties
        FROM garage_specialties
        GROUP BY garage_id
    ) s ON g.id = s.garage_id
    LEFT JOIN garage_reviews r ON g.id = r.garage_id
";

if (!empty($filters)) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}

$sql .= " GROUP BY g.id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$garages = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =======================
   USER SAVED GARAGES
======================= */
$user_id = $_SESSION['user_id'] ?? null;
$savedGarages = [];
$preferredGarage = null;

if ($user_id) {
    $prefStmt = $pdo->prepare("SELECT g.* FROM user_garages ug JOIN garages g ON ug.garage_id = g.id WHERE ug.user_id = ? AND ug.is_preferred = 1 LIMIT 1");
    $prefStmt->execute([$user_id]);
    $preferredGarage = $prefStmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT g.* FROM user_garages ug JOIN garages g ON ug.garage_id = g.id WHERE ug.user_id = ? AND (ug.is_preferred = 0 OR ug.is_preferred IS NULL)");
    $stmt->execute([$user_id]);
    $savedGarages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<style>
body { background-color: #fcfcfd; color: #1e293b; }
.modern-shadow { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02); }

/* Premium Card Base */
.garage-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #ffffff;
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
}

@media (min-width: 768px) {
    .garage-card:hover {
        transform: translateY(-8px) scale(1.01);
        box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.12);
        border-color: #4f46e5;
    }
}

/* Utilities */
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;  
    overflow: hidden;
}

.glass-badge {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Specialty Pills */
.spec-pill {
    background: #f1f5f9;
    color: #475569;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 4px 10px;
    border-radius: 8px;
    white-space: nowrap;
}

.garage-card:hover .spec-pill {
    background: #eef2ff;
    color: #4f46e5;
}

/* MOBILE FIX: Horizontal Layout & Dynamic Height */
@media (max-width: 640px) {
    .garage-card {
        flex-direction: row !important;
        /* Use min-height instead of fixed height to prevent button cropping */
        min-height: 180px; 
        height: auto !important;
        border-radius: 24px !important;
    }

    .garage-card .image-container {
        width: 135px !important;
        /* Ensures image stretches to match content height */
        height: auto !important; 
        align-self: stretch;
        flex-shrink: 0;
    }

    .garage-card .content-container {
        padding: 14px 16px !important;
        display: flex;
        flex-direction: column;
        /* Pushes top content and buttons apart */
        justify-content: space-between; 
        flex: 1;
        width: 100%;
        overflow: hidden;
    }

    /* Keep specialties to strictly one line on mobile */
    .specialties-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        max-height: 26px; 
        overflow: hidden;
        margin-bottom: 8px;
    }

    /* Hide text on small buttons to save space */
    .garage-card .btn-text { display: none; }
    
    /* Only show first 2 pills on mobile to keep it clean */
    .garage-card .spec-pill:nth-child(n+3) { display: none; }
}
</style>

    </head>
<body class="font-sans antialiased bg-[#fcfcfd] text-[#1e293b]">

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

  <div class="flex flex-col lg:flex-row lg:items-end justify-between mb-12 gap-8">
    <div class="max-w-xl">
      <h1 class="text-5xl font-extrabold tracking-tight text-blue-400 mb-3">Find a Garage</h1>
      <p class="text-slate-500 text-lg leading-relaxed">Discover top-rated automotive experts and trusted service centers across Kenya.</p>
    </div>
  </div>

  <?php if($user_id): ?>
  <section class="mb-16">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-slate-500 flex items-center gap-3">
            <span class="w-1.5 h-8 bg-accent rounded-full"></span> My Network
        </h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <?php if($preferredGarage): ?>
      <div class="lg:col-span-5 bg-gradient-to-br from-slate-900 to-slate-800 rounded-[2rem] p-8 text-white relative overflow-hidden shadow-2xl">
        <div class="relative z-10">
          <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black bg-amber-400 text-slate-900 mb-4 uppercase tracking-[0.2em]">
            Preferred Garage
          </span>
          <div class="flex items-center gap-6 mb-8">
            <img src="<?= htmlspecialchars(!empty($preferredGarage['logo']) ? 'uploads/logo/'.$preferredGarage['logo'] : 'assets/default_garage.jpeg') ?>" 
                 class="w-24 h-24 object-cover rounded-2xl ring-4 ring-white/10 shadow-lg">
            <div>
              <h3 class="text-2xl font-extrabold mb-1"><?= htmlspecialchars($preferredGarage['name']) ?></h3>
              <p class="text-slate-400 flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <?= htmlspecialchars($preferredGarage['location']) ?>
              </p>
            </div>
          </div>
          <a href="garageprofile.php?id=<?= $preferredGarage['id'] ?>" 
             class="inline-block w-full py-4 bg-white text-slate-900 text-center font-bold rounded-2xl hover:bg-slate-100 transition shadow-xl no-underline">
             View Account 
          </a>
        </div>
        <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-brand/20 rounded-full blur-3xl"></div>
      </div>
      <?php endif; ?>

      <div class="<?= $preferredGarage ? 'lg:col-span-7' : 'lg:col-span-12' ?> bg-white border border-slate-100 rounded-[2rem] p-8 modern-shadow">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-6">Saved Centers</h3>
        <div id="savedGaragesGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
          <?php foreach($savedGarages as $i=>$g): ?>
            <div class="group relative <?= $i>=4 ? 'hidden extra-garage' : '' ?>">
              <a href="garageprofile.php?id=<?= $g['id'] ?>" class="block no-underline">
                <img src="<?= !empty($g['logo']) ? 'uploads/logo/'.$g['logo'] : 'assets/default_garage.jpeg' ?>"
                     class="w-full h-24 object-cover rounded-xl mb-2 transition-transform group-hover:scale-105">
                <div class="font-bold text-slate-800 text-xs truncate"><?= htmlspecialchars($g['name']) ?></div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if(count($savedGarages) > 4): ?>
          <button id="toggleSavedGarages" class="mt-6 text-brand font-bold text-sm flex items-center gap-2 hover:gap-3 transition-all">
            View All Saved <span>→</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

 <div id="garageContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
    <?php if (empty($garages)): ?>
        <div class="col-span-full py-32 text-center bg-white rounded-[3rem] border border-dashed border-slate-200">
            <div class="text-7xl mb-6">🔎</div>
            <h3 class="text-2xl font-bold text-slate-900">No results found</h3>
            <p class="text-slate-500 mt-2">Try adjusting your filters or search keywords.</p>
        </div>
    <?php else: ?>
        <?php foreach ($garages as $garage):
            $logo = !empty($garage['logo']) ? "uploads/logo/".$garage['logo'] : "assets/default_garage.jpeg";
        ?>
            <div class="garage-card group flex flex-col rounded-[2.5rem] overflow-hidden modern-shadow"
                 data-lat="<?= htmlspecialchars($garage['latitude']) ?>"
                 data-lng="<?= htmlspecialchars($garage['longitude']) ?>"
                 data-distance="">

                <div class="image-container relative h-52 sm:h-64 overflow-hidden">
                    <a href="garageprofile.php?id=<?= $garage['id'] ?>" class="block h-full w-full">
                        <img src="<?= htmlspecialchars($logo) ?>" 
                             class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110">
                    </a>
                    
                    <div class="absolute top-4 left-4">
                        <div class="glass-badge backdrop-blur-md bg-white/80 border border-white/20 px-3 py-1.5 rounded-2xl flex items-center gap-2 shadow-lg">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-accent"></span>
                            </span>
                            <span class="distance-value text-[10px] font-black text-slate-900 tracking-tighter uppercase">
                                Locating...
                            </span>
                        </div>
                    </div>

                    <?php if($garage['avg_rating'] >= 4.5): ?>
                    <div class="absolute top-4 right-4">
                        <div class="bg-slate-900/90 text-white p-2 rounded-xl shadow-lg">
                            <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.184c.969 0 1.371 1.24.588 1.81l-3.39 2.462a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.39-2.462a1 1 0 00-1.175 0l-3.39 2.462c-.784.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.364-1.118L2.043 9.384c-.783-.57-.38-1.81.588-1.81h4.183a1 1 0 00.95-.69l1.285-3.957z"/></svg>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="content-container p-6 sm:p-8 flex flex-col flex-1 bg-white">
                    <div class="flex-1">
                        <div class="flex items-start justify-between mb-3">
                            <div class="max-w-[70%]">
                                <h3 class="text-xl font-extrabold text-slate-900 leading-tight tracking-tight group-hover:text-brand transition-colors line-clamp-1">
                                    <?= htmlspecialchars($garage['name']) ?>
                                </h3>
                                <div class="flex items-center gap-1.5 mt-1 text-slate-400">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <span class="text-xs font-bold tracking-wide uppercase line-clamp-1"><?= htmlspecialchars($garage['location']) ?></span>
                                </div>
                            </div>
                            
                            <div class="text-right">
                                <div class="flex items-center gap-1 bg-amber-50 px-2 py-1 rounded-lg border border-amber-100">
                                    <span class="text-amber-600 font-black text-sm"><?= number_format($garage['avg_rating'], 1) ?></span>
                                    <svg class="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.184c.969 0 1.371 1.24.588 1.81l-3.39 2.462a1 1 0 00-.364 1.118l1.287 3.957c.3.921-.755 1.688-1.54 1.118l-3.39-2.462a1 1 0 00-1.175 0l-3.39 2.462c-.784.57-1.838-.197-1.539-1.118l1.286-3.957a1 1 0 00-.364-1.118L2.043 9.384c-.783-.57-.38-1.81.588-1.81h4.183a1 1 0 00.95-.69l1.285-3.957z"/></svg>
                                </div>
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter mt-1 block"><?= $garage['reviews_count'] ?> reviews</span>
                            </div>
                        </div>

                        <?php if (!empty($garage['specialties'])): ?>
                            <div class="flex flex-wrap gap-2 mb-6">
                                <?php 
                                $specs = explode(',', $garage['specialties']);
                                foreach (array_slice($specs, 0, 3) as $spec): ?>
                                    <span class="spec-pill"><?= htmlspecialchars(trim($spec)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-3 mt-auto">
                        <a href="garageprofile.php?id=<?= $garage['id'] ?>"
                           class="flex-[3] py-4 bg-slate-900 text-white rounded-2xl flex items-center justify-center gap-2 hover:bg-brand transition-all shadow-lg active:scale-95 no-underline">
                            <span class="text-[11px] font-black uppercase tracking-[0.15em] btn-text">View Profile</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>

                        <a target="_blank" href="#"
                           class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl flex items-center justify-center hover:bg-accent hover:text-white transition-all active:scale-95 get-direction">
                           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button id="fabFilter" class="fixed bottom-8 right-8 z-[100] bg-slate-900 text-white w-16 h-16 md:w-20 md:h-20 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] flex flex-col items-center justify-center border border-white/10 hover:bg-blue-600 transition-all duration-300 group ring-8 ring-slate-900/5">
    <svg class="w-6 h-6 md:w-7 md:h-7 text-white mb-1 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
    </svg>
    <span class="text-[9px] font-black uppercase tracking-[0.2em] opacity-70 group-hover:opacity-100 transition-opacity">Search</span>
    <?php if(!empty($search) || !empty($selectedLocation)): ?>
        <span class="absolute -top-2 -right-2 flex h-4 w-4">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-4 w-4 bg-blue-600 border-2 border-slate-900"></span>
        </span>
    <?php endif; ?>
</button>

<div id="sideFilterBackdrop" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[110] hidden opacity-0 transition-opacity duration-500"></div>

<div id="sideFilterBackdrop" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[998] hidden opacity-0 transition-opacity duration-500"></div>

<div id="sideFilterDrawer" class="fixed top-0 right-0 h-full w-full max-w-[380px] bg-white z-[999] translate-x-full transition-transform duration-500 ease-in-out flex flex-col shadow-2xl">
    
    <div class="p-8 pt-20 pb-4 flex items-center justify-between border-b border-slate-50">
        <div>
            <h2 class="text-2xl font-black italic uppercase text-slate-900 tracking-tighter">Refine</h2>
            <p class="text-[9px] font-bold text-blue-600 uppercase tracking-widest">Inventory Filters</p>
        </div>
        <button id="closeSideFilter" type="button" class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-900 hover:bg-slate-200 transition-all font-bold text-2xl">×</button>
    </div>

    <div class="flex-grow overflow-y-auto p-8 no-scrollbar">
        <form id="filterForm" action="" method="GET" class="space-y-6">
            <input type="hidden" name="u_lat" id="u_lat" value="<?= htmlspecialchars($_GET['u_lat'] ?? '') ?>">
            <input type="hidden" name="u_lng" id="u_lng" value="<?= htmlspecialchars($_GET['u_lng'] ?? '') ?>">

            <div>
                <label class="text-[9px] font-black uppercase text-slate-400 mb-2 block tracking-widest">Keywords</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search..." class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/5 outline-none transition-all text-slate-900">
            </div>

            <div>
                <label class="text-[9px] font-black uppercase text-slate-400 mb-2 block tracking-widest">Region</label>
                <select name="location" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm outline-none cursor-pointer hover:border-slate-300 transition-colors">
                    <option value="">All Regions</option>
                    <?php if(isset($allLocations)): foreach ($allLocations as $loc): ?>
                      <option value="<?= htmlspecialchars($loc) ?>" <?= (isset($selectedLocation) && $selectedLocation === $loc) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc) ?>
                      </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div>
                <label class="text-[9px] font-black uppercase text-slate-400 mb-2 block tracking-widest">Radius Range</label>
                <select id="radiusFilter" name="radius" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-5 py-3.5 text-sm outline-none cursor-pointer hover:border-slate-300 transition-colors">
                    <?php $radVal = $_GET['radius'] ?? 'all'; ?>
                    <option value="all" <?= $radVal == 'all' ? 'selected' : '' ?>>Any distance</option>
                    <option value="5" <?= $radVal == '5' ? 'selected' : '' ?>>Within 5 km</option>
                    <option value="10" <?= $radVal == '10' ? 'selected' : '' ?>>Within 10 km</option>
                    <option value="50" <?= $radVal == '50' ? 'selected' : '' ?>>Within 50 km</option>
                </select>
            </div>
        </form>
    </div>

    <div class="p-8 border-t border-slate-50 bg-white">
        <button type="submit" form="filterForm" class="w-full bg-slate-900 text-white py-4 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] shadow-xl hover:bg-blue-600 transition-all active:scale-[0.98]">Update Results</button>
        <a href="garages.php" class="block text-center mt-4 text-[9px] font-black uppercase text-slate-300 hover:text-red-500 transition-colors tracking-widest no-underline">Reset Filters</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function haversine(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

const radiusSelect = document.getElementById('radiusFilter');
const garageContainer = document.getElementById('garageContainer');
const uLatInput = document.getElementById('u_lat');
const uLngInput = document.getElementById('u_lng');

/**
 * CORE LOGIC: Apply filtering and sorting
 */
function applyGeoLogic(userLat, userLng) {
    const cards = [...document.querySelectorAll('.garage-card')];
    const maxDistance = radiusSelect.value === 'all' ? Infinity : parseFloat(radiusSelect.value);

    cards.forEach(card => {
        const lat = parseFloat(card.dataset.lat);
        const lng = parseFloat(card.dataset.lng);
        
        if (!isNaN(lat) && !isNaN(lng)) {
            const dist = haversine(userLat, userLng, lat, lng);
            card.dataset.distance = dist;
            card.querySelector('.distance-value').textContent = dist.toFixed(1) + " KM AWAY";

            // Toggle visibility based on Radius Range
            card.style.display = dist <= maxDistance ? "flex" : "none";

            // Update directions
            const dirBtn = card.querySelector('.get-direction');
            if (dirBtn) dirBtn.href = `https://www.google.com/maps/dir/?api=1&origin=${userLat},${userLng}&destination=${lat},${lng}&travelmode=driving`;
        }
    });

    cards.sort((a, b) => (parseFloat(a.dataset.distance) || 9999) - (parseFloat(b.dataset.distance) || 9999))
         .forEach(card => garageContainer.appendChild(card));
}

function processPosition(pos) {
    const userLat = pos.coords.latitude;
    const userLng = pos.coords.longitude;
    
    // Store in hidden fields so they persist on refresh
    uLatInput.value = userLat;
    uLngInput.value = userLng;
    
    applyGeoLogic(userLat, userLng);
}

function initGeo() {
    // If we have saved coordinates from a previous load, use them immediately
    if (uLatInput.value && uLngInput.value) {
        applyGeoLogic(parseFloat(uLatInput.value), parseFloat(uLngInput.value));
    }

    // Always try to get fresh location to stay accurate
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(processPosition, (err) => {
            console.warn("Location error:", err.message);
        }, { enableHighAccuracy: true });
    }
}

// Drawer Controls
const fabBtn = document.getElementById('fabFilter');
const drawer = document.getElementById('sideFilterDrawer');
const backdrop = document.getElementById('sideFilterBackdrop');
const closeBtn = document.getElementById('closeSideFilter');

if (fabBtn) {
    fabBtn.onclick = () => {
        backdrop.classList.remove('hidden');
        setTimeout(() => backdrop.classList.add('opacity-100'), 10);
        drawer.classList.remove('translate-x-full');
    };
}

const closeDrawer = () => {
    backdrop.classList.remove('opacity-100');
    drawer.classList.add('translate-x-full');
    setTimeout(() => backdrop.classList.add('hidden'), 500);
};

if (closeBtn) closeBtn.onclick = closeDrawer;
if (backdrop) backdrop.onclick = closeDrawer;

initGeo();
</script>

</body>
</html>
