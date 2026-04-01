<?php
include '../includes/header.php';
require_once '../config/db.php';

/* =========================
   SMART FETCH: Featured First + Dealer Round-Robin
   (Keeps all original columns + adds featured logic)
========================= */
$stmt = $pdo->prepare("
    WITH RankedListings AS (
        SELECT   
            dl.id, dl.title, dl.make, dl.model, dl.year, dl.price, dl.mileage, 
            dl.transmission, dl.fuel_type, dl.engine_cc, dl.color, dl.main_image,
            dl.is_featured, -- Required for spotlight logic
            dl.vehicle_condition,
            dl.location AS vehicle_location,  
            d.name AS dealer_name,  
            d.location AS dealer_location,  
            d.logo AS dealer_logo,  
            d.user_id AS dealer_user_id,
            d.verified AS dealer_verified,
            -- SMART: Prevent one dealer from flooding the top
            ROW_NUMBER() OVER (
                PARTITION BY dl.dealer_id 
                ORDER BY dl.is_featured DESC, dl.created_at DESC
            ) as dealer_rank
        FROM dealer_listings dl  
        INNER JOIN dealers d ON dl.dealer_id = d.user_id  
        WHERE dl.status = 'active'
    )
    SELECT * FROM RankedListings
    ORDER BY 
        is_featured DESC,     -- 1. Featured units absolute top
        dealer_verified DESC, -- 2. Verified partners priority
        dealer_rank ASC,      -- 3. THE SHUFFLE: Dealer A's #1, then Dealer B's #1...
        id DESC               -- 4. Tie breaker
");
$stmt->execute();
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalListings = count($listings);

/* =========================
   FETCH DEALERS FOR FILTER
========================= */
$dealerStmt = $pdo->query("
    SELECT DISTINCT d.user_id, d.name
    FROM dealers d
    INNER JOIN dealer_listings dl 
        ON dl.dealer_id = d.user_id
    WHERE dl.status = 'active'
    ORDER BY d.name
");
$dealers = $dealerStmt->fetchAll(PDO::FETCH_ASSOC);

/* Helper Slug */
function slugify($text) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
}

/* Prepare filters */
$years = array_unique(array_map(fn($c) => $c['year'], $listings));
sort($years);

$makes = array_unique(array_map(fn($c) => $c['make'], $listings));
sort($makes);

$prices = array_map(fn($c) => $c['price'], $listings);
$minPrice = count($prices) > 0 ? floor(min($prices)) : 0;
$maxPrice = count($prices) > 0 ? ceil(max($prices)) : 10000000;

$transmissions = array_unique(array_map(fn($c) => $c['transmission'], $listings));
$fuels = array_unique(array_map(fn($c) => $c['fuel_type'], $listings));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Auto Exchange | Premium Inventory</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0f172a;
            --accent: #2563eb;
            --accent-soft: #dbeafe;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text-main: #1e293b;
            --text-sub: #64748b;
            --border: #e2e8f0;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 16px;
        }

        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text-main);
            line-height: 1.5;
            overflow-x: hidden; /* Prevents right-side overflow */
        }

        .main-wrapper {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            max-width: 1440px;
            margin: auto;
            padding: 20px;
        }

        /* --- SIDEBAR STYLE --- */
        .filter-sidebar {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
            overflow-y: auto;
            box-shadow: var(--shadow);
            z-index: 1001;
            transition: transform 0.3s ease;
        }

        .filter-group { margin-bottom: 20px; }
        .filter-title {
            display: block;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--text-sub);
            margin-bottom: 8px;
        }

        .search-input {
            width: 100%;
            padding: 10px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.85rem;
            box-sizing: border-box;
            font-family: inherit;
        }

        /* --- PRICE SLIDER --- */
        .price-slider-container { position: relative; height: 30px; display: flex; align-items: center; }
        .price-slider { position: absolute; width: 100%; pointer-events: none; -webkit-appearance: none; background: none; }
        .price-slider::-webkit-slider-thumb { pointer-events: auto; width: 16px; height: 16px; border-radius: 50%; background: var(--accent); border: 2px solid white; cursor: pointer; -webkit-appearance: none; }

        /* --- LISTING GRID (COMPACT) --- */
        #listingGrid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-width: 0; /* Critical fix for right-side overflow */
        }

        .listing-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            display: flex;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
            height: 140px; /* Slimmer height for less exhaustion */
        }

        .listing-card:hover {
            border-color: var(--accent);
            transform: translateX(4px);
            box-shadow: var(--shadow);
        }

        .listing-img-wrapper {
            width: 160px;
            min-width: 160px;
            height: 100%;
            position: relative;
            background: #eee;
        }

        .listing-img { width: 100%; height: 100%; object-fit: cover; }

        .featured-badge {
            position: absolute;
            top: 8px; left: 8px;
            background: var(--accent);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.6rem;
            font-weight: 800;
            display: flex; align-items: center; gap: 3px;
        }

        .listing-content {
            padding: 12px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0; /* Fixes title overflow */
        }

        .dealer-info { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
        .dealer-logo { width: 18px; height: 18px; border-radius: 4px; object-fit: cover; }
        .dealer-name { font-size: 0.7rem; font-weight: 700; color: var(--text-sub); display: flex; align-items: center; gap: 4px; }

        .listing-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .listing-meta-grid { display: flex; gap: 8px; margin-top: 6px; }
        .meta-item {
            font-size: 0.7rem;
            color: var(--text-sub);
            background: var(--bg);
            padding: 2px 8px;
            border-radius: 6px;
            font-weight: 600;
            display: flex; align-items: center; gap: 4px;
        }

        .listing-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
        }

        .listing-price { font-size: 1.15rem; font-weight: 800; color: var(--accent); }
        .location-tag { font-size: 0.65rem; color: var(--text-sub); display: flex; align-items: center; gap: 2px; }

        /* --- MOBILE UI --- */
        .mobile-filter-btn {
            display: none;
            position: fixed;
            bottom: 20px; right: 20px;
            background: var(--primary);
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            z-index: 1002;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            font-weight: 700;
            border: none;
            cursor: pointer;
            align-items: center; gap: 8px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
        }

        @media (max-width: 992px) {
            .main-wrapper { grid-template-columns: 1fr; padding: 10px; }
            .filter-sidebar {
                position: fixed;
                left: -320px; top: 0; bottom: 0;
                width: 300px; height: 100vh;
                border-radius: 0;
                padding-top: 60px;
            }
            .filter-sidebar.active { transform: translateX(320px); }
            .mobile-filter-btn { display: flex; }
            .sidebar-overlay.active { display: block; }
            .listing-img-wrapper { width: 120px; min-width: 120px; }
            .listing-title { font-size: 0.9rem; }
        }

        @media (min-width: 1200px) {
            #listingGrid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
            }
            .listing-card { flex-direction: column; height: auto; }
            .listing-img-wrapper { width: 100%; height: 200px; }
            .listing-content { height: 160px; }
            .listing-title { white-space: normal; }
        }
    </style>
</head>
<body>

<button class="mobile-filter-btn" onclick="toggleSidebar()">
    <span class="material-icons">filter_list</span> Filters
</button>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="main-wrapper">
    <aside class="filter-sidebar">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 0 20px;">
            <h2 style="font-size: 1.1rem; margin: 0; font-weight: 800; text-transform: uppercase; letter-spacing: -0.5px;">Filters</h2>
            <button onclick="toggleSidebar()" style="border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-weight: bold;">×</button>
        </div>
        
        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Search Keywords</label>
            <input type="text" id="searchInput" class="search-input" placeholder="e.g. Toyota Prado...">
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Vehicle Condition</label>
            <select id="conditionFilter" class="search-input">
                <option value="">All Conditions</option>
                <option value="New">Brand New</option>
                <option value="Foreign Used">Foreign Used</option>
                <option value="Local Used">Locally Used</option>
            </select>
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Make</label>
            <select id="makeFilter" class="search-input">
                <option value="">All Makes</option>
                <?php foreach($makes as $make): ?>
                    <option value="<?= strtolower($make) ?>"><?= $make ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Body Type</label>
            <select id="bodyFilter" class="search-input">
                <option value="">All Body Types</option>
                <option value="suv">SUV</option>
                <option value="sedan">Sedan</option>
                <option value="hatchback">Hatchback</option>
                <option value="pickup">Pickup</option>
                <option value="van">Van / Minivan</option>
            </select>
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Year Range</label>
            <div style="display:flex; gap:8px;">
                <select id="yearMin" class="search-input">
                    <option value="">Min Year</option>
                    <?php for($y=2026; $y>=2010; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <select id="yearMax" class="search-input">
                    <option value="">Max Year</option>
                    <?php for($y=2026; $y>=2010; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Engine (CC)</label>
            <select id="ccFilter" class="search-input">
                <option value="">Any Capacity</option>
                <option value="0-1000">Below 1000cc</option>
                <option value="1001-1500">1000cc - 1500cc</option>
                <option value="1501-2000">1500cc - 2000cc</option>
                <option value="2001-3000">2000cc - 3000cc</option>
                <option value="3001-9999">Over 3000cc</option>
            </select>
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Transmission</label>
            <select id="transFilter" class="search-input">
                <option value="">All Transmissions</option>
                <option value="automatic">Automatic</option>
                <option value="manual">Manual</option>
            </select>
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Fuel Type</label>
            <select id="fuelFilter" class="search-input">
                <option value="">All Fuel Types</option>
                <option value="petrol">Petrol</option>
                <option value="diesel">Diesel</option>
                <option value="hybrid">Hybrid</option>
                <option value="electric">Electric</option>
            </select>
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Dealer Status</label>
            <select id="dealerFilter" class="search-input">
                <option value="">All Dealers</option>
                <option value="verified">Verified Only</option>
                <option value="not_verified">Standard Dealers</option>
            </select>
        </div>

        <div class="filter-group" style="padding: 0 20px;">
            <label class="filter-title">Price Range (Ksh)</label>
            <div style="display:flex; gap:8px; margin-bottom:12px;">
                <input type="number" id="priceMinInput" class="search-input" value="<?= $minPrice ?>">
                <input type="number" id="priceMaxInput" class="search-input" value="<?= $maxPrice ?>">
            </div>
            <div class="price-slider-container">
                <input type="range" id="priceSliderMin" class="price-slider" min="<?= $minPrice ?>" max="<?= $maxPrice ?>" value="<?= $minPrice ?>">
                <input type="range" id="priceSliderMax" class="price-slider" min="<?= $minPrice ?>" max="<?= $maxPrice ?>" value="<?= $maxPrice ?>">
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:10px; font-size:0.7rem; color:var(--text-sub); font-weight:700;">
                <span id="priceMin">Ksh <?= number_format($minPrice) ?></span>
                <span id="priceMax">Ksh <?= number_format($maxPrice) ?></span>
            </div>
        </div>

        <div style="padding: 20px;">
            <button onclick="resetFilters()" class="btn-reset" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:10px; background:white; font-weight:700; cursor:pointer; hover:bg-gray-50;">
                Reset All
            </button>
        </div>
    </aside>

    <main style="min-width: 0;">
        <div class="listing-header" style="display:flex; justify-content:space-between; margin-bottom:15px; align-items:center; background:#fff; padding:12px 20px; border-radius:12px; border:1px solid var(--border);">
            <div style="font-weight:800; font-size:0.9rem;">
                <span class="material-icons" style="vertical-align:middle; font-size:18px; color:var(--accent); margin-right:8px;">directions_car</span>
                <span id="resultCount">Showing <?= $totalListings ?> Vehicles</span>
            </div>
            <div style="font-size:0.7rem; font-weight:700; color:var(--text-sub); text-transform:uppercase;">Sort: Best Match</div>
        </div>

        <div id="listingGrid">
            <?php foreach ($listings as $car): 
                $isFeatured = (isset($car['is_featured']) && $car['is_featured'] == 1);
            ?>
            <a href="car_details.php?id=<?= $car['id'] ?>" 
               class="listing-card <?= $isFeatured ? 'is-featured' : '' ?>"
               data-dealer-verified="<?= $car['dealer_verified'] ? '1' : '0' ?>"
               data-price="<?= $car['price'] ?>"
               data-make="<?= strtolower($car['make']) ?>"
               data-year="<?= $car['year'] ?>"
               data-trans="<?= strtolower($car['transmission']) ?>"
               data-fuel="<?= strtolower($car['fuel_type']) ?>"
               data-condition="<?= $car['vehicle_condition'] ?>"
               data-body="<?= strtolower($car['body_type']) ?>"
               data-cc="<?= (int)$car['engine_cc'] ?>">

                <div class="listing-img-wrapper">
                    <?php if($isFeatured): ?>
                        <div class="featured-badge">
                            <span class="material-icons" style="font-size: 10px;">bolt</span> SPOTLIGHT
                        </div>
                    <?php endif; ?>
                    <img src="/<?= htmlspecialchars($car['main_image']) ?>" class="listing-img" onerror="this.src='/assets/img/car-placeholder.jpg'">
                </div>

                <div class="listing-content">
                    <div>
                        <div class="dealer-info">
                            <img src="/<?= $car['dealer_logo'] ?: 'assets/img/dealer-placeholder.png' ?>" class="dealer-logo">
                            <span class="dealer-name">
                                <?= htmlspecialchars($car['dealer_name']) ?>
                                <?php if ($car['dealer_verified']): ?>
                                    <span class="material-icons" style="font-size: 12px; color: #10b981;">verified</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <h3 class="listing-title"><?= htmlspecialchars($car['title']) ?></h3>

                        <div class="listing-meta-grid">
                            <div class="meta-item"><?= (int)$car['year'] ?></div>
                            <div class="meta-item"><?= htmlspecialchars($car['transmission']) ?></div>
                            <div class="meta-item">
                                <?php 
                                    if($car['engine_cc'] > 0) {
                                        echo ($car['engine_cc'] >= 1000) ? number_format($car['engine_cc']/1000, 1) . 'L' : $car['engine_cc'] . 'cc';
                                    } else {
                                        echo htmlspecialchars($car['fuel_type']);
                                    }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="listing-footer">
                        <div class="listing-price">Ksh <?= number_format($car['price'], 0) ?></div>
                        <div class="location-tag">
                            <span class="material-icons" style="font-size:12px;">location_on</span>
                            <?= htmlspecialchars($car['dealer_location']) ?>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div id="noResultsMsg" style="display:none; text-align:center; padding:60px; background:white; border-radius:16px;">
            <span class="material-icons" style="font-size:48px; color:var(--text-sub);">search_off</span>
            <p style="font-weight:700; margin-top:10px;">No matches found</p>
        </div>
    </main>
</div>


  <?php include '../includes/footer.php'; ?>
  
<script>
    function toggleSidebar() {
        document.querySelector('.filter-sidebar').classList.toggle('active');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
    }

    // Elements
    const searchInput = document.getElementById('searchInput');
    const conditionFilter = document.getElementById('conditionFilter');
    const makeFilter = document.getElementById('makeFilter');
    const bodyFilter = document.getElementById('bodyFilter');
    const yearMin = document.getElementById('yearMin');
    const yearMax = document.getElementById('yearMax');
    const ccFilter = document.getElementById('ccFilter');
    const transFilter = document.getElementById('transFilter');
    const fuelFilter = document.getElementById('fuelFilter');
    const dealerFilter = document.getElementById('dealerFilter');
    
    const priceSliderMin = document.getElementById('priceSliderMin');
    const priceSliderMax = document.getElementById('priceSliderMax');
    const priceMinInput = document.getElementById('priceMinInput');
    const priceMaxInput = document.getElementById('priceMaxInput');
    
    const cards = document.querySelectorAll('.listing-card');

    function applyFilters() {
        const term = searchInput.value.toLowerCase();
        const condition = conditionFilter.value;
        const make = makeFilter.value;
        const body = bodyFilter.value;
        const yMin = parseInt(yearMin.value) || 0;
        const yMax = parseInt(yearMax.value) || 9999;
        const ccRange = ccFilter.value;
        const trans = transFilter.value;
        const fuel = fuelFilter.value;
        const dealer = dealerFilter.value;
        
        const minPrice = parseFloat(priceMinInput.value) || 0;
        const maxPrice = parseFloat(priceMaxInput.value) || 999999999;

        let count = 0;
        cards.forEach(card => {
            const d = card.dataset;
            const title = card.querySelector('.listing-title').innerText.toLowerCase();
            
            // Basic Checks
            const matchSearch = title.includes(term);
            const matchCond = !condition || condition === d.condition;
            const matchMake = !make || make === d.make;
            const matchBody = !body || body === d.body;
            const matchYear = parseInt(d.year) >= yMin && parseInt(d.year) <= yMax;
            const matchTrans = !trans || trans === d.trans;
            const matchFuel = !fuel || fuel === d.fuel;
            const matchDealer = !dealer || dealer === (d.dealerVerified === '1' ? 'verified' : 'not_verified');
            const matchPrice = parseFloat(d.price) >= minPrice && parseFloat(d.price) <= maxPrice;

            // CC Range logic
            let matchCC = true;
            if (ccRange) {
                const [cMin, cMax] = ccRange.split('-').map(Number);
                const cardCC = parseInt(d.cc);
                matchCC = cardCC >= cMin && cardCC <= cMax;
            }

            if(matchSearch && matchCond && matchMake && matchBody && matchYear && matchTrans && matchFuel && matchDealer && matchPrice && matchCC) {
                card.style.display = 'flex';
                count++;
            } else {
                card.style.display = 'none';
            }
        });

        document.getElementById('resultCount').innerText = `Showing ${count} Vehicles`;
        document.getElementById('noResultsMsg').style.display = count === 0 ? 'block' : 'none';
    }

    function syncPrice(source) {
        if(source === 'slider') {
            priceMinInput.value = priceSliderMin.value;
            priceMaxInput.value = priceSliderMax.value;
        } else {
            priceSliderMin.value = priceMinInput.value;
            priceSliderMax.value = priceMaxInput.value;
        }
        document.getElementById('priceMin').innerText = "Ksh " + parseInt(priceMinInput.value).toLocaleString();
        document.getElementById('priceMax').innerText = "Ksh " + parseInt(priceMaxInput.value).toLocaleString();
        applyFilters();
    }

    // Attach Live Listeners
    [searchInput, conditionFilter, makeFilter, bodyFilter, yearMin, yearMax, ccFilter, transFilter, fuelFilter, dealerFilter].forEach(el => {
        el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', applyFilters);
    });
    
    priceSliderMin.addEventListener('input', () => syncPrice('slider'));
    priceSliderMax.addEventListener('input', () => syncPrice('slider'));
    priceMinInput.addEventListener('input', () => syncPrice('input'));
    priceMaxInput.addEventListener('input', () => syncPrice('input'));

    function resetFilters() {
        searchInput.value = '';
        conditionFilter.value = '';
        makeFilter.value = '';
        bodyFilter.value = '';
        yearMin.value = '';
        yearMax.value = '';
        ccFilter.value = '';
        transFilter.value = '';
        fuelFilter.value = '';
        dealerFilter.value = '';
        priceMinInput.value = <?= $minPrice ?>;
        priceMaxInput.value = <?= $maxPrice ?>;
        syncPrice('input');
        applyFilters();
    }
</script>
  
 

</body>
</html>