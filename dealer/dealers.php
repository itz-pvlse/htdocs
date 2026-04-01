<?php
// File: dealers.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';
include '../includes/header.php';

$search = trim($_GET['search'] ?? '');
// PRIORITIZATION: 1. Verified, 2. Highest Rating, 3. Newest
$sql = "SELECT * FROM dealers 
        WHERE name LIKE ? 
        ORDER BY verified DESC, rating DESC, user_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$search%"]);
$dealers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$locStmt = $pdo->query("SELECT DISTINCT location FROM dealers WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
$locations = $locStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
:root {
    /* Blue Feel Palette */
    --bg-silk: #f0f4f8; 
    --surface: #ffffff; 
    --text-deep: #0f172a; 
    --text-soft: #64748b; 
    --accent-blue: #2563eb; 
    --accent-dark: #1e3a8a; 
    --accent-glow: rgba(37, 99, 235, 0.1);
    
    --radius-lg: 35px;
    --radius-md: 18px;
    --radius-sm: 12px; /* Slightly punchier for mobile cards */
    --shadow-soft: 0 20px 50px rgba(15, 23, 42, 0.05);
}

body { 
    background-color: var(--bg-silk); 
    font-family: 'Inter', sans-serif; 
    color: var(--text-deep);
    margin: 0;
}

.main-container { max-width: 1400px; margin: 0 auto; padding: 60px 20px; }

/* Rounded Header */
.section-intro { margin-bottom: 60px; text-align: center; }
.section-intro h1 { font-size: 3.5rem; font-weight: 800; letter-spacing: -2px; margin-bottom: 10px; }
.section-intro h1 span { color: var(--accent-blue); }
.section-intro p { color: var(--text-soft); font-size: 0.9rem; letter-spacing: 3px; text-transform: uppercase; }

/* Filter Sidebar */
.filter-sidebar {
    background: var(--surface);
    border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
    padding: 60px 40px;
    position: fixed;
    top: 0; left: 0;
    width: 380px;
    height: 100vh;
    z-index: 1000;
    box-shadow: 20px 0 100px rgba(30, 58, 138, 0.1);
    transform: translateX(-100%);
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.filter-sidebar.active { transform: translateX(0); }

/* Inputs */
.search-input { 
    width: 100%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); 
    padding: 18px 25px; font-size: 0.9rem; outline: none; margin-top: 8px; color: var(--text-deep);
}
.search-input:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 4px var(--accent-glow); }

/* Dealer List & Desktop Cards */
.dealers-list { display: grid; grid-template-columns: 1fr; gap: 30px; }

.showroom-entry { 
    display: flex; 
    background: var(--surface); 
    border: 1px solid rgba(37, 99, 235, 0.05);
    border-radius: var(--radius-lg); 
    overflow: hidden; 
    height: 350px; 
    transition: all 0.5s ease;
    box-shadow: var(--shadow-soft);
}

.showroom-entry:hover { 
    transform: scale(1.005);
    box-shadow: 0 40px 80px rgba(30, 58, 138, 0.12);
    border-color: var(--accent-blue);
}

.entry-visual { flex: 1.3; overflow: hidden; border-radius: var(--radius-lg); margin: 15px; position: relative; }
.entry-visual img { width: 100%; height: 100%; object-fit: cover; border-radius: calc(var(--radius-lg) - 10px); }

.entry-details { flex: 1; padding: 40px 50px 40px 20px; display:flex; flex-direction: column; justify-content: center; }
.entry-title { font-size: 2rem; font-weight: 800; letter-spacing: -0.5px; margin: 5px 0; color: var(--text-deep); }
.entry-address { font-size: 0.9rem; color: var(--text-soft); margin-bottom: 20px; }

/* Status Pills */
.status-pill { display: inline-block; font-size: 0.65rem; font-weight: 800; letter-spacing: 1px; padding: 6px 16px; border-radius: 50px; }
.status-pill.verified { background: #00ff88; color: #0f172a; box-shadow: 0 0 15px rgba(0, 255, 136, 0.3); }
.status-pill.not-verified { background: #f1f5f9; color: var(--text-soft); }

/* Specs */
.specs-row { display: flex; gap: 40px; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px; }
.spec-item b { font-size: 1.3rem; color: var(--accent-dark); }
.spec-item span { font-size: 0.7rem; color: var(--text-soft); text-transform: uppercase; display: block; }

/* Buttons */
.action-area { display: flex; gap: 12px; margin-top: 35px; }
.btn-enter { 
    flex: 2; background: var(--accent-dark); color: #fff; text-align: center; padding: 18px; 
    text-decoration: none; font-size: 0.8rem; font-weight: 700; border-radius: 50px; 
}
.btn-secondary { 
    flex: 1; border: 2px solid #cbd5e1; color: #475569; text-align: center; padding: 18px; 
    text-decoration: none; font-size: 0.8rem; font-weight: 700; border-radius: 50px; 
}

.mobile-filter-btn {
    position: fixed; bottom: 40px; left: 50%; transform: translateX(-50%);
    background: var(--accent-dark); color: #fff; padding: 20px 40px; border-radius: 100px;
    font-size: 0.8rem; font-weight: 700; z-index: 900; box-shadow: 0 20px 40px rgba(30, 58, 138, 0.3);
}

/* --- MOBILE OPTIMIZED (MINIMIZE SCROLL + NO SLICING) --- */
@media (max-width: 768px) {
    .main-container { padding: 20px 15px; }
    .section-intro { margin-bottom: 30px; }
    .section-intro h1 { font-size: 2rem; }

    .dealers-list { gap: 15px; }

    .showroom-entry { 
        flex-direction: row; 
        height: auto; /* Fixed: Prevents button slicing */
        min-height: 180px; 
        border-radius: var(--radius-md);
        align-items: stretch;
    }

    .entry-visual { 
        flex: 0 0 120px; /* Slimmer image to save width */
        margin: 10px;
        height: auto;
        border-radius: var(--radius-sm);
    }
    .entry-visual img { border-radius: var(--radius-sm); }

    .entry-details { 
        padding: 15px 15px 15px 5px; 
        justify-content: space-between; /* Pushes specs and buttons apart */
    }

    .entry-title { font-size: 1.1rem; margin: 0; line-height: 1.2; }
    .entry-address { font-size: 0.75rem; margin-bottom: 5px; }

    .specs-row { 
        gap: 15px; margin-top: 8px; padding-top: 0; border: none; 
    }
    .spec-item b { font-size: 0.95rem; }
    .spec-item span { font-size: 0.6rem; }

    .action-area { 
        margin-top: 15px; /* Ensures buttons never touch the specs */
        gap: 8px; 
    }
    .btn-enter, .btn-secondary { padding: 12px 8px; font-size: 0.7rem; border-radius: 12px; }
    
    .spec-item.distance-display { display: none; } /* Extra space saving */
}
</style>


<div class="main-container">
    <header class="section-intro">
        <h1>Partner <span>Showrooms</span></h1>
        <p>Premium Partner Network</p>
    </header>

    <div class="mobile-filter-btn" onclick="toggleSidebar()">Refine Search</div>

    <div class="main-wrapper">
        <aside class="filter-sidebar fixed inset-y-0 left-0 w-80 bg-white border-r border-slate-100 z-50 transform -translate-x-full transition-transform duration-500 ease-in-out shadow-2xl overflow-y-auto">
    
    <div class="p-8 flex justify-between items-center bg-gradient-to-r from-blue-50/50 to-white border-b border-slate-100">
        <div class="flex flex-col">
            <span class="text-[8px] font-black text-blue-600 uppercase tracking-[0.4em] mb-1">Showroom</span>
            <h2 class="text-xl font-black text-slate-900 uppercase tracking-tighter">Search <span class="text-blue-600">Filters</span></h2>
        </div>
        <button onclick="toggleSidebar()" class="group w-10 h-10 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:border-blue-500 hover:text-blue-600 transition-all shadow-sm">
    <svg xmlns="http://www.w3.org/2000/svg" 
         viewBox="0 0 24 24" 
         fill="none" 
         stroke="currentColor" 
         stroke-width="2.5" 
         stroke-linecap="round" 
         stroke-linejoin="round" 
         class="w-5 h-5 transition-transform duration-300 group-hover:rotate-90">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>
</button>

    </div>

    <div class="p-8 space-y-8">
        <div class="space-y-3">
            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.3em]">Dealer Name</label>
            <div class="relative group">
                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 w-4 h-4 group-focus-within:text-blue-500 transition-colors"></i>
                <input type="text" id="dealerSearchInput" 
                       class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-900 placeholder-slate-300 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-500/5 outline-none transition-all shadow-inner" 
                       placeholder="e.g. Prestige Motors">
            </div>
        </div>

        <div class="space-y-3">
            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.3em]">Status</label>
            <div class="relative group">
                <i data-lucide="shield-check" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 w-4 h-4 group-focus-within:text-blue-500 transition-colors"></i>
                <select id="verifiedFilter" 
                        class="w-full pl-12 pr-10 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-900 appearance-none focus:bg-white focus:border-blue-500 outline-none cursor-pointer transition-all">
                    <option value="">All Tiers</option>
                    <option value="1">Verified Partners</option>
                    <option value="0">Standard Dealers</option>
                </select>
                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 text-blue-600 w-4 h-4 pointer-events-none"></i>
            </div>
        </div>

        <div class="space-y-3">
            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-[0.3em]">Region</label>
            <div class="relative group">
                <i data-lucide="map-pin" class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 w-4 h-4 group-focus-within:text-blue-500 transition-colors"></i>
                <select id="locationFilter" 
                        class="w-full pl-12 pr-10 py-4 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-900 appearance-none focus:bg-white focus:border-blue-500 outline-none cursor-pointer transition-all">
                    <option value="">Global Coverage</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                    <?php endforeach; ?>
                </select>
                <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 text-blue-600 w-4 h-4 pointer-events-none"></i>
            </div>
        </div>

        <div class="pt-4">
            <button onclick="resetDealerFilters()" 
                    class="w-full py-4 bg-white text-blue-600 text-[10px] font-black uppercase tracking-[0.3em] rounded-2xl border border-blue-100 hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95">
                Clear Parameters
            </button>
        </div>
    </div>
</aside>


        <div class="sidebar-overlay" onclick="toggleSidebar()" id="overlay"></div>

        <div id="showroom-list" class="dealers-list">
    <?php foreach ($dealers as $dealer): 
        $dealerId = $dealer['user_id'] ?? 0;
        $isVerified = ((int)($dealer['verified'] ?? 0) === 1);
        
        // Count active cars for this dealer
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM dealer_listings WHERE dealer_id = ? AND status = 'active'");
        $stmtCount->execute([$dealerId]);
        $carsInStock = $stmtCount->fetchColumn();

        $logoPath = (!empty($dealer['logo']) && file_exists(__DIR__ . '/../uploads/dealers/' . basename($dealer['logo']))) 
            ? '../uploads/dealers/' . basename($dealer['logo']) 
            : '../assets/default_dealer.jpeg';
    ?>
    <div class="showroom-entry"
         style="<?= $isVerified ? 'border-color: var(--accent-blue);' : '' ?>"
         data-lat="<?= htmlspecialchars($dealer['latitude'] ?? '') ?>"
         data-lng="<?= htmlspecialchars($dealer['longitude'] ?? '') ?>"
         data-verified="<?= (int)$isVerified ?>"
         data-location="<?= strtolower(htmlspecialchars($dealer['location'] ?? '')) ?>"
         data-rating="<?= htmlspecialchars($dealer['rating'] ?? 0) ?>">
        
        <div class="entry-visual relative">
            <img src="<?= $logoPath ?>" alt="Showroom">
            <?php if($isVerified): ?>
                <div class="absolute top-4 right-4 bg-white rounded-full p-1.5 shadow-lg">
                    <i data-lucide="badge-check" class="w-5 h-5 text-blue-600 fill-blue-50"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="entry-details">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="status-pill <?= $isVerified ? 'verified' : 'not-verified' ?>">
                        <?= $isVerified ? 'VERIFIED PARTNER' : 'STANDARD' ?>
                    </span>
                    <?php if($isVerified): ?>
                        <i data-lucide="shield-check" class="w-4 h-4 text-blue-600"></i>
                    <?php endif; ?>
                </div>
                
                <h2 class="entry-title flex items-center gap-2">
                    <?= htmlspecialchars($dealer['name']) ?>
                </h2>
                <p class="entry-address flex items-center gap-1">
                    <i data-lucide="map-pin" class="w-3 h-3"></i>
                    <?= htmlspecialchars($dealer['location']) ?>
                </p>

                <div class="specs-row">
                    <div class="spec-item">
                        <b><?= $carsInStock ?></b>
                        <span>Units Available</span>
                    </div>
                    <div class="spec-item">
                        <div class="flex items-center gap-1">
                            <b><?= number_format($dealer['rating'] ?? 0, 1) ?></b>
                            <i data-lucide="star" class="w-3 h-3 fill-yellow-400 text-yellow-400"></i>
                        </div>
                        <span>User Rating</span>
                    </div>
                    <div class="spec-item distance-display">
                        <b>--</b>
                        <span>Proximity</span>
                    </div>
                </div>
            </div>

            <div class="action-area">
                <a href="dealer-profile.php?id=<?= $dealerId ?>" class="btn-enter flex items-center justify-center gap-2">
                    Enter Showroom <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
                <a href="#" class="btn-secondary showroom-tour-btn">Navigate</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

    </div>
</div>

<script>
// Logic remains identical to preserve functionality
let userPos = null;
const cards = document.querySelectorAll('.showroom-entry');

function toggleSidebar() {
    document.querySelector('.filter-sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('active');
}

function filterDealers() {
    const s = document.getElementById('dealerSearchInput').value.toLowerCase();
    const v = document.getElementById('verifiedFilter').value;
    const l = document.getElementById('locationFilter').value.toLowerCase();

    cards.forEach(card => {
        const name = card.querySelector('.entry-title').textContent.toLowerCase();
        const verified = card.dataset.verified;
        const loc = card.dataset.location;
        const show = name.includes(s) && (v === '' || verified === v) && (l === '' || loc === l);
        card.style.display = show ? 'flex' : 'none';
    });
}

document.getElementById('dealerSearchInput').addEventListener('input', filterDealers);
document.getElementById('verifiedFilter').addEventListener('change', filterDealers);
document.getElementById('locationFilter').addEventListener('change', filterDealers);

function resetDealerFilters() {
    document.getElementById('dealerSearchInput').value = '';
    document.getElementById('verifiedFilter').value = '';
    document.getElementById('locationFilter').value = '';
    filterDealers();
}

function calculateKm(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
}

if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
        userPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        cards.forEach(c => {
            const dLat = parseFloat(c.dataset.lat);
            const dLng = parseFloat(c.dataset.lng);
            if(dLat && dLng) {
                const dist = calculateKm(userPos.lat, userPos.lng, dLat, dLng);
                c.querySelector('.distance-display b').textContent = dist.toFixed(1) + ' km';
            }
        });
    });
}

document.querySelectorAll('.showroom-tour-btn').forEach(btn => {
    btn.addEventListener('click', e => {
        e.preventDefault();
        const card = btn.closest('.showroom-entry');
        const lat = card.dataset.lat;
        const lng = card.dataset.lng;
        // Directional logic fixed for browser standards
        const url = userPos ? `https://www.google.com/maps/dir/?api=1&origin=${userPos.lat},${userPos.lng}&destination=${lat},${lng}` : `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
        window.open(url, '_blank');
    });
});
</script>

<?php include '../includes/footer.php'; ?>
