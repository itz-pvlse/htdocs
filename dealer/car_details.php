<?php
// 1. Session and Timezone MUST be at the very top
session_start();
date_default_timezone_set('Africa/Nairobi'); 
require_once '../config/db.php';

/* ============================================================
   1. HARD-CODED INTELLIGENCE ENGINE (PHP-SYNCED TIME)
   ============================================================ */

// Create a single master timestamp for this page execution
$kenyaTime = date('Y-m-d H:i:s');

// A. Handle Background Button Clicks (AJAX for Calls/WhatsApp)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_log_action'])) {
    $d_id = $_POST['d_id'];
    $type = $_POST['ajax_log_action'];
    $r_id = $_POST['r_id'];
    $s_id = session_id();
    
    try {
        // We pass $kenyaTime manually to override DB NOW()
        $stmt = $pdo->prepare("INSERT INTO dealer_engagement (dealer_id, type, related_id, session_id, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$d_id, $type, $r_id, $s_id, $kenyaTime]);
    } catch (Exception $e) { }
    exit; 
}

// B. Handle Duration Heartbeat (Updates 'last_heartbeat' AND 'duration_seconds')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['heartbeat'])) {
    $r_id = (int)$_POST['r_id'];
    $s_id = session_id();
    $now = date('Y-m-d H:i:s'); // Nairobi Time
    
    try {
        // 1. Update last_heartbeat
        // 2. Calculate seconds between NOW and when the view started (created_at)
        $stmt = $pdo->prepare("
            UPDATE dealer_engagement 
            SET 
                last_heartbeat = ?, 
                duration_seconds = TIMESTAMPDIFF(SECOND, created_at, ?)
            WHERE session_id = ? AND related_id = ? AND type = 'car_view'
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$now, $now, $s_id, $r_id]);
    } catch (Exception $e) { }
    exit; 
}


// C. Handle Lead Form Submission + Engagement Tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_lead'])) {
    $dealer_id  = $_POST['dealer_id'];
    $listing_id = $_POST['listing_id'];
    $name       = strip_tags($_POST['name']);
    $email      = strip_tags($_POST['email']);
    $phone      = strip_tags($_POST['phone']);
    $message    = strip_tags($_POST['message']);
    $whatsapp   = isset($_POST['whatsapp']) ? strip_tags($_POST['whatsapp']) : null;
    $user_id    = $_SESSION['user_id'] ?? null;

    try {
        // 1. LOG ENGAGEMENT (WhatsApp Click/Inquiry)
        $logStmt = $pdo->prepare("INSERT INTO dealer_engagement (dealer_id, type, related_id, session_id, created_at) VALUES (?, 'whatsapp_click', ?, ?, ?)");
        $logStmt->execute([$dealer_id, $listing_id, session_id(), $kenyaTime]);

        // 2. SAVE THE ACTUAL LEAD
        $sql = "INSERT INTO leads (dealer_id, user_id, listing_id, name, email, phone, whatsapp, message, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$dealer_id, $user_id, $listing_id, $name, $email, $phone, $whatsapp, $message, $kenyaTime])) {
            header("Location: car_details.php?id=$listing_id&sent=1&sender=" . urlencode($name));
            exit;
        }
    } catch (Exception $e) { }
}

/* ============================================================
   2. DATA FETCHING
   ============================================================ */

$isLoggedIn = isset($_SESSION['user_id']); 
$car_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($car_id <= 0) die("Invalid car ID.");

// Fetch car details
$stmt = $pdo->prepare("SELECT * FROM dealer_listings WHERE id = ? AND status = 'active'");
$stmt->execute([$car_id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) die("Car not found or inactive.");

// D. Log Initial Page View (Auto-runs on page load)
$source = (isset($_GET['ref']) && $_GET['ref'] === 'tiktok') ? 'tiktok' : 'direct';
try {
    // Insert both created_at and last_heartbeat using PHP time so duration starts at 0
    $viewStmt = $pdo->prepare("INSERT INTO dealer_engagement 
        (dealer_id, type, related_id, session_id, source_platform, created_at, last_heartbeat) 
        VALUES (?, 'car_view', ?, ?, ?, ?, ?)");
    $viewStmt->execute([
        $car['dealer_id'], 
        $car['id'], 
        session_id(), 
        $source, 
        $kenyaTime, 
        $kenyaTime
    ]);
} catch (Exception $e) { }

/* ============================================================
   3. RECOMMENDATION ENGINE & SPECS
   ============================================================ */

// 1. Try to find Similar Inventory
$minPrice = $car['price'] * 0.8;
$maxPrice = $car['price'] * 1.2;
$stmt = $pdo->prepare("SELECT id, make, model, price, main_image, year, transmission FROM dealer_listings WHERE make=? AND price BETWEEN ? AND ? AND id!=? AND status='active' LIMIT 8");
$stmt->execute([$car['make'], $minPrice, $maxPrice, $car_id]);
$relatedCars = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sectionTitle = "Similar Inventory";
$sectionSubtitle = "Recommendation Engine";

// 2. Fallback: SAME DEALER
if (empty($relatedCars)) {
    $stmt = $pdo->prepare("SELECT id, make, model, price, main_image, year, transmission FROM dealer_listings WHERE dealer_id=? AND id!=? AND status='active' ORDER BY id DESC LIMIT 8");
    $stmt->execute([$car['dealer_id'], $car_id]);
    $relatedCars = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sectionTitle = "More From This Dealer";
    $sectionSubtitle = "Showroom Collection";
}

// Dealer info
$dealer = null;
if ($car['dealer_id']) {
    $stmt = $pdo->prepare("SELECT name, phone, email, whatsapp, verified FROM dealers WHERE user_id=?");
    $stmt->execute([$car['dealer_id']]);
    $dealer = $stmt->fetch(PDO::FETCH_ASSOC);
}


// Extra images
$stmt = $pdo->prepare("SELECT image_path FROM dealer_listing_images WHERE listing_id=?");
$stmt->execute([$car['id']]);
$extraImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$images = [];
if(!empty($car['main_image'])) $images[] = $car['main_image'];
foreach($extraImages as $img) $images[] = $img['image_path'];
if(empty($images)) $images[] = 'uploads/dealer_listings/default_car.png';

// Specs Array
$specs = [
    ['group' => 'basic', 'l' => 'Transmission', 'v' => $car['transmission'], 'svg' => '<path d="M5 12h14M12 5l7 7-7 7"/>'],
    ['group' => 'basic', 'l' => 'Fuel Type', 'v' => $car['fuel_type'], 'svg' => '<path d="M3 22L7 18M20 7L13 14"/>'],
    ['group' => 'basic', 'l' => 'Mileage', 'v' => number_format($car['mileage'] ?? 0).' km', 'svg' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
    ['group' => 'basic', 'l' => 'Color', 'v' => $car['color'], 'svg' => '<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>'],
    ['group' => 'basic', 'l' => 'Location', 'v' => $car['location'], 'svg' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>'],
    ['group' => 'engine', 'l' => 'Engine CC', 'v' => !empty($car['engine_cc']) ? number_format($car['engine_cc']).' cc' : null, 'svg' => '<path d="M2 12h20M12 2v20"/>'],
    ['group' => 'engine', 'l' => 'Drive Type', 'v' => $car['drive_type'], 'svg' => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="10"/>'],
    ['group' => 'engine', 'l' => 'Fuel Tank', 'v' => !empty($car['fuel_tank_capacity']) ? $car['fuel_tank_capacity'].' L' : null, 'svg' => '<path d="M3 14h18l-2 4H5l-2-4zM2 10h20v2H2v-2z"/>'],
    ['group' => 'interior', 'l' => 'Doors / Seats', 'v' => ($car['doors'] ?? 'N/A').' / '.($car['seats'] ?? 'N/A'), 'svg' => '<path d="M3 3h18v18H3z"/>'],
    ['group' => 'interior', 'l' => 'Airbags', 'v' => $car['airbags'], 'svg' => '<circle cx="12" cy="12" r="10"/><path d="M8 12h8"/>'],
    ['group' => 'history', 'l' => 'Service History', 'v' => $car['service_history'], 'svg' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>'],
    ['group' => 'history', 'l' => 'Accident History', 'v' => $car['accident_history'], 'svg' => '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"/>'],
    ['group' => 'history', 'l' => 'Warranty', 'v' => $car['warranty'], 'svg' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/>'],
];
?>

<script>
/**
 * Track Call/WhatsApp clicks without reloading page
 */
function logAction(type) {
    const data = new FormData();
    data.append('ajax_log_action', type);
    data.append('d_id', '<?= $car['dealer_id'] ?>');
    data.append('r_id', '<?= $car['id'] ?>');
    fetch(window.location.href, { method: 'POST', body: data });
}

/**
 * Track DURATION (Heartbeat)
 * Runs every 10 seconds to update the last_heartbeat column
 */
setInterval(function() {
    const data = new FormData();
    data.append('heartbeat', '1');
    data.append('r_id', '<?= $car['id'] ?>');
    fetch(window.location.href, { method: 'POST', body: data });
}, 10000); // 10 seconds
</script>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($car['make'].' '.$car['model']) ?> - AutoLog Elite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { alblue: '#3b82f6', bgdeep: '#0a0b0d', card: '#14161a', accent: '#22c55e' },
          fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }
        }
      }
    }
    </script>
    <style>
        body { background: radial-gradient(circle at top right, #001F3F, #000000 100%); color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-panel { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; }
        .spec-card { background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); padding: 1.25rem; border-radius: 20px; transition: all 0.3s ease; animation: fadeIn 0.4s ease forwards; }
        .spec-card:hover { background: rgba(255, 255, 255, 0.05); border-color: rgba(59, 130, 246, 0.5); transform: translateY(-2px); }
        .spec-tab { padding: 0.75rem 1.25rem; border-radius: 14px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: rgba(255,255,255,0.4); transition: all 0.3s ease; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); white-space: nowrap; }
        .spec-tab:hover { color: white; background: rgba(255,255,255,0.08); }
        .active-tab { color: white; background: #3b82f6 !important; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); border-color: #3b82f6; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="min-h-screen pb-20">

<div class="max-w-6xl mx-auto px-6 pt-10">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-10">
        <div class="space-y-2">
            <div class="flex items-center gap-3">
                <span class="bg-accent/20 text-accent text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest">Available Now</span>
                <span class="text-white/40 text-xs font-bold uppercase tracking-widest">ID: #<?= $car['id'] ?></span>
            </div>
            <h1 class="text-5xl font-black tracking-tighter italic uppercase"><?= htmlspecialchars($car['make']) ?> <span class="text-white/40 not-italic font-light"><?= htmlspecialchars($car['model']) ?></span></h1>
            <p class="text-white/60 font-medium tracking-wide"><?= $car['year'] ?> • <?= $car['location'] ?></p>
        </div>
        <div class="text-left md:text-right">
            <p class="text-[10px] font-black text-white/40 uppercase tracking-[0.3em] mb-1">Asking Price</p>
            <p class="text-4xl font-black text-alblue italic">KES <?= number_format($car['price']) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-16">
        <div class="lg:col-span-8 space-y-6">
            <div class="relative rounded-[32px] overflow-hidden group shadow-2xl border border-white/10">
                <img id="main-car-img" src="../<?= htmlspecialchars($car['main_image'] ?: 'uploads/dealer_listings/default_car.png') ?>" class="w-full aspect-video object-cover group-hover:scale-105 transition-transform duration-700" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                <button id="see-more-images" class="absolute bottom-8 left-8 bg-white/10 backdrop-blur-md border border-white/20 text-white px-6 py-3 rounded-2xl text-xs font-bold hover:bg-white/20 transition flex items-center gap-2">
                   <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                   View Gallery (<?= count($images) ?>)
                </button>
            </div>

            <div class="glass-panel p-6 flex flex-col sm:flex-row items-center justify-between gap-4 border-l-4 border-l-alblue">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-alblue/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-white font-bold tracking-tight">AI Smart Purchase Insight</h4>
                        <p class="text-white/40 text-xs">Professional market analysis for this <?= htmlspecialchars($car['model']) ?></p>
                    </div>
                </div>
                <button onclick="openAIModal()" 
                        class="w-full sm:w-auto bg-alblue hover:bg-white text-black text-[10px] font-black uppercase px-8 py-4 rounded-2xl transition-all tracking-widest active:scale-95 shadow-[0_0_20px_rgba(0,123,255,0.2)]">
                    View Analysis
                </button>
            </div>

            <div class="glass-panel p-8">
                <h3 class="text-[10px] font-black uppercase text-alblue tracking-widest mb-4">Dealer's Notes</h3>
                <div class="relative group">
                    <div class="max-h-[150px] overflow-y-auto pr-4 custom-description-scroll">
                        <p class="text-lg text-white/80 leading-relaxed font-light italic">
                            "<?= nl2br(htmlspecialchars($car['description'])) ?>"
                        </p>
                    </div>
                    <div class="absolute bottom-0 left-0 w-full h-8 bg-gradient-to-t from-[#1a1a1a]/50 to-transparent pointer-events-none opacity-50"></div>
                </div>
            </div>
        </div>
    


<div id="aiModal" class="fixed inset-0 z-[9999] hidden items-start justify-center p-4 md:p-10 bg-black/95 backdrop-blur-xl overflow-y-auto">
    <div class="max-w-xl w-full glass-panel p-6 md:p-8 border-alblue/30 relative shadow-2xl mt-4 mb-10">
        
        <div class="flex justify-between items-start mb-8 sticky top-0 bg-[#1a1a1a]/80 backdrop-blur-md z-20 pb-4 border-b border-white/5">
            <div>
                <h4 class="text-alblue text-[10px] font-black uppercase tracking-widest mb-1">Expert Market Audit</h4>
                <p class="text-white text-2xl font-black italic uppercase leading-none"><?= htmlspecialchars($car['make']) ?> <?= htmlspecialchars($car['model']) ?></p>
            </div>
            <button onclick="closeAIModal()" class="text-white/40 hover:text-white transition-colors p-2 bg-white/5 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="min-h-[300px]">
            <div id="aiLoader" class="flex flex-col items-center justify-center py-20 space-y-4">
                <div class="w-10 h-10 border-2 border-t-alblue border-white/10 rounded-full animate-spin"></div>
                <p class="text-white/40 text-[10px] font-black uppercase tracking-[0.2em]">Evaluating Value Assets...</p>
            </div>

            <div id="aiContent" class="hidden space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-white/5 p-5 rounded-2xl border border-white/10 items-center">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-2">Market Confidence</p>
                        <div id="confidenceMeter" class="flex gap-1.5">
                            </div>
                    </div>
                    <div class="sm:text-right border-t sm:border-t-0 border-white/5 pt-3 sm:pt-0 overflow-hidden">
                        <span id="confidenceLabel" class="text-lg md:text-xl font-black italic text-white uppercase tracking-tighter truncate block">Analyzing</span>
                    </div>
                </div>

                <div id="aiInsightGrid" class="grid grid-cols-1 gap-4">
                    </div>
                
                <div class="p-6 bg-alblue/10 rounded-[24px] border border-alblue/20 relative overflow-hidden">
                    <h5 class="text-alblue text-[10px] font-black uppercase tracking-widest mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-alblue animate-pulse"></span>
                        Strategic Summary
                    </h5>
                    <p id="aiVerdictText" class="text-white/90 text-sm leading-relaxed font-medium italic"></p>
                </div>

                <button onclick="closeAIModal()" class="w-full py-4 bg-alblue text-black text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl hover:bg-white transition-all active:scale-95 shadow-lg shadow-alblue/10">
                    Return to Listing
                </button>
            </div>
        </div>
    </div>
</div>


<script>
let aiCachedResponse = "";

function calculateConfidence(make, year) {
    const currentYear = new Date().getFullYear();
    const age = currentYear - parseInt(year);
    let score = 3; // Start Neutral

    // Age Logic
    if (age <= 3) score = 5;
    else if (age <= 6) score = 4;
    else if (age > 12) score = 2;

    // Local Liquidity (Resale Brand Strength)
    const liquidBrands = ['TOYOTA', 'LEXUS', 'HONDA', 'MAZDA', 'ISUZU'];
    if (liquidBrands.includes(make.toUpperCase())) score = Math.min(5, score + 1);

    return score;
}

function renderMeter(score) {
    const container = document.getElementById('confidenceMeter');
    const label = document.getElementById('confidenceLabel');
    const levels = ["Speculative", "Fair", "Stable", "Strong", "Exceptional"];
    
    // Color mapping based on score
    const colors = ["bg-red-500", "bg-orange-500", "bg-yellow-500", "bg-alblue", "bg-cyan-400"];
    const activeColor = colors[score - 1];
    
    let html = '';
    for (let i = 1; i <= 5; i++) {
        const colorClass = i <= score ? activeColor : 'bg-white/10';
        html += `<div class="flex-1 h-1.5 rounded-full ${colorClass} transition-all duration-700"></div>`;
    }
    container.innerHTML = html;
    label.innerText = levels[score - 1];
    label.className = `text-lg md:text-xl font-black italic uppercase tracking-tighter truncate block ${score >= 4 ? 'text-white' : 'text-white/70'}`;
}

function openAIModal() {
    document.getElementById('aiModal').classList.remove('hidden');
    document.getElementById('aiModal').classList.add('flex');
    if (aiCachedResponse === "") fetchAIInsight();
}

function closeAIModal() {
    document.getElementById('aiModal').classList.add('hidden');
    document.getElementById('aiModal').classList.remove('flex');
}

function fetchAIInsight() {
    const loader = document.getElementById('aiLoader');
    const content = document.getElementById('aiContent');
    const gridTarget = document.getElementById('aiInsightGrid');
    const verdictTarget = document.getElementById('aiVerdictText');

    loader.classList.remove('hidden');
    content.classList.add('hidden');

    const make = "<?= $car['make'] ?>";
    const year = "<?= $car['year'] ?>";
    
    // Animate the meter
    setTimeout(() => renderMeter(calculateConfidence(make, year)), 300);

    const prompt = `Act as a senior automotive analyst in Nairobi. Analyze this ${year} ${make} <?= $car['model'] ?> realistically. 
    Focus on local market liquidity, road suitability, and realistic maintenance (acknowledge specialist needs for luxury brands). 
    Keep it professional and supportive of the sale without being hyperbolic.
    
    Structure with these tags:
    [EDGE] Practical competitive advantage.
    [SUITABILITY] Local terrain performance.
    [MAINTENANCE] Honest ownership reality (parts/specialist availability).
    [VERDICT] 2-sentence investment summary.`;

    fetch('../includes/ai_carhelp.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            conversation: [
                { role: 'system', content: 'You are a professional Kenyan car expert.' },
                { role: 'user', content: prompt }
            ]
        })
    })
    .then(res => res.json())
    .then(data => {
        const text = data.reply;
        gridTarget.innerHTML = `
            ${createCard('Strategic Edge', extractSection(text, "[EDGE]"))}
            ${createCard('Kenyan Suitability', extractSection(text, "[SUITABILITY]"))}
            ${createCard('Ownership Reality', extractSection(text, "[MAINTENANCE]"))}
        `;
        verdictTarget.innerText = extractSection(text, "[VERDICT]");
        loader.classList.add('hidden');
        content.classList.remove('hidden');
    });
}

function createCard(title, body) {
    return `
        <div class="p-5 bg-white/5 rounded-2xl border border-white/5 hover:border-white/10 transition-all">
            <h6 class="text-alblue text-[9px] font-black uppercase tracking-[0.2em] mb-2 opacity-80">${title}</h6>
            <p class="text-white/80 text-xs leading-relaxed font-medium">${body}</p>
        </div>
    `;
}

function extractSection(text, tag) {
    const parts = text.split(tag);
    if (parts.length < 2) return "Analysis synchronized.";
    return parts[1].split('[')[0].trim();
}
</script>




        <div class="lg:col-span-4">
            <div class="glass-panel p-8 sticky top-8 border-t-4 border-t-alblue shadow-2xl">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="flex items-center gap-4 mb-8">
    <div class="w-12 h-12 rounded-xl bg-alblue flex items-center justify-center font-black text-xl italic shadow-lg shadow-alblue/20">
        <?= substr($dealer['name'], 0, 1) ?>
    </div>
    <div>
        <?php if (isset($dealer['verified']) && (int)$dealer['verified'] === 1): ?>
            <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-1.5 mb-1">
                <i class="fas fa-certificate text-[8px]"></i> Verified Dealer
            </p>
        <?php else: ?>
            <p class="text-[10px] font-black text-white/40 uppercase tracking-widest mb-1">Dealer</p>
        <?php endif; ?>
        
        <p class="font-bold text-white"><?= htmlspecialchars($dealer['name']) ?></p>
    </div>
</div>

                <div class="space-y-4">
                    <a href="tel:<?= $dealer['phone'] ?>" 
   onclick="logAction('call_click')" 
   class="flex items-center justify-center gap-3 w-full bg-white text-black py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-white/90 transition">
   Call Dealer
</a>

                   <?php if (isset($_GET['sent']) && $_GET['sent'] == '1'): ?>
    <div class="w-full bg-accent/10 border border-accent/20 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 mt-4 animate-pulse">
        <div class="w-10 h-10 bg-accent rounded-full flex items-center justify-center shadow-lg shadow-accent/20">
            <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <p class="text-[10px] font-black uppercase text-accent tracking-[0.2em]">Inquiry Sent</p>
        <p class="text-[11px] text-white/60 text-center px-4">The dealer has been notified via WhatsApp & Email.</p>
    </div>
<?php else: ?>
    <button onclick="document.getElementById('inquiryModal').classList.remove('hidden')" class="flex items-center justify-center gap-3 w-full border border-white/20 bg-white/5 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-white/10 transition mt-4 group">
        <svg class="w-4 h-4 group-hover:text-alblue transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
        </svg>
        Make an Inquiry
    </button>
<?php endif; ?>


<div id="inquiryModal" class="fixed inset-0 bg-slate-900/90 backdrop-blur-sm hidden z-[110] flex items-center justify-center p-4">
    <div class="bg-white max-w-md w-full rounded-[2rem] relative shadow-2xl overflow-hidden border border-slate-100">
        
        <div class="h-1.5 bg-alblue w-full"></div>

        <button onclick="document.getElementById('inquiryModal').classList.add('hidden')" class="absolute top-5 right-5 text-slate-300 hover:text-slate-900 transition-colors">
            <i class="fas fa-times text-lg"></i>
        </button>
        
        <form method="POST" action="" class="p-6 md:p-8">
            <input type="hidden" name="listing_id" value="<?= $car_id ?>">
            <input type="hidden" name="dealer_id" value="<?= $car['dealer_id'] ?>">
            
            <header class="mb-6">
                <h2 class="text-xl font-black text-slate-900 uppercase italic">Inquiry Form</h2>
                <p class="text-[10px] font-bold text-alblue uppercase tracking-widest"><?= htmlspecialchars($car['make'].' '.$car['model']) ?></p>
            </header>

            <div class="space-y-4">
                <div>
                    <label class="text-[9px] font-black uppercase text-slate-400 ml-1 mb-1 block">Full Name</label>
                    <input type="text" name="name" required placeholder="Enter your name" 
                        class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:border-alblue outline-none transition text-slate-900">
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-slate-400 ml-1 mb-1 block">Email Address</label>
                    <input type="email" name="email" required placeholder="email@example.com" 
                        class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:border-alblue outline-none transition text-slate-900">
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-slate-400 ml-1 mb-1 block">Phone Number</label>
                    <div class="flex gap-2">
                        <select name="country_code" class="bg-slate-100 border border-slate-100 rounded-xl px-3 py-3 text-[10px] font-black text-slate-600 outline-none">
                            <option value="254">KE (+254)</option>
                            <option value="1">US (+1)</option>
                            <option value="44">UK (+44)</option>
                        </select>
                        <input type="text" id="phone_input" name="phone" required placeholder="712345678" 
                            class="flex-1 bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:border-alblue outline-none transition text-slate-900">
                    </div>
                </div>

                <div class="bg-emerald-50 rounded-2xl p-4 border border-emerald-100">
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-[9px] font-black uppercase text-emerald-600 ml-1">WhatsApp Number</label>
                        <button type="button" onclick="syncWA()" class="text-[8px] font-black uppercase bg-white border border-emerald-200 px-2 py-1 rounded-lg text-emerald-600 shadow-sm">Sync Phone</button>
                    </div>
                    <div class="relative">
                        <i class="fab fa-whatsapp absolute left-4 top-1/2 -translate-y-1/2 text-emerald-500"></i>
                        <input type="text" id="wa_input" name="whatsapp" placeholder="Country code + Number" 
                            class="w-full bg-white border border-emerald-100 rounded-xl pl-10 pr-4 py-3 text-sm focus:border-emerald-500 outline-none transition text-slate-900 shadow-inner">
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-slate-400 ml-1 mb-1 block">Message</label>
                    <textarea name="message" rows="2" required 
                        class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm focus:border-alblue outline-none transition text-slate-900 italic">I'm interested in this unit.</textarea>
                </div>
            </div>

           <button type="submit" name="submit_lead" class="w-full mt-6 bg-slate-900 text-white py-4 rounded-xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-alblue transition-all shadow-lg shadow-slate-200">
    Send Inquiry
</button>

        </form>
    </div>
</div>

<script>
function syncWA() {
    const phone = document.getElementById('phone_input').value;
    const code = document.querySelector('select[name="country_code"]').value;
    if(phone) {
        const cleanPhone = phone.startsWith('0') ? phone.substring(1) : phone;
        document.getElementById('wa_input').value = code + cleanPhone;
    }
}

document.querySelector('form').onsubmit = function() {
    const btn = this.querySelector('button[name="submit_lead"]');
    btn.innerHTML = '<i class="fas fa-circle-notch animate-spin"></i> SENDING...';
    btn.style.opacity = '0.7';
};

</script>


                </div>
                
                
                
                <div class="mt-8 pt-8 border-t border-white/5 flex flex-col items-center">
                    <a href="dealer-profile.php?id=<?= $car['dealer_id'] ?>" class="text-[10px] font-black uppercase text-white/40 hover:text-alblue transition tracking-[0.2em]">Visit Full Showroom</a>
                </div>
            </div>
        </div>
    </div>

    <div id="specs-section" class="mb-20">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div class="flex gap-2 overflow-x-auto no-scrollbar pb-2">
                <button onclick="filterSpecs('basic', this)" class="spec-tab active-tab">Basic Info</button>
                <button onclick="filterSpecs('engine', this)" class="spec-tab">Engine & Drive</button>
                <button onclick="filterSpecs('interior', this)" class="spec-tab">Interior</button>
                <button onclick="filterSpecs('history', this)" class="spec-tab">History & Trust</button>
                <button onclick="filterSpecs('all', this)" class="spec-tab bg-white/10 text-white">View All</button>
            </div>
            <h2 class="text-[10px] font-black uppercase tracking-[0.4em] text-alblue hidden md:block text-right">Technical Data</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach($specs as $s): if(!empty($s['v'])): ?>
            <div class="spec-card group-item" data-group="<?= $s['group'] ?>" style="display: <?= ($s['group'] === 'basic') ? 'block' : 'none' ?>;">
                <div class="flex items-center gap-4">
                    <div class="text-alblue">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><?= $s['svg'] ?></svg>
                    </div>
                    <div>
                        <p class="text-[9px] font-black uppercase text-white/40 tracking-widest"><?= $s['l'] ?></p>
                        <p class="text-sm font-bold text-white"><?= htmlspecialchars($s['v']) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <?php if(!empty($relatedCars)): ?>
<div class="max-w-6xl mx-auto px-6 mb-20 mt-16">
    <div class="flex items-end justify-between mb-8">
        <div>
            <h3 class="text-[10px] font-black uppercase tracking-[0.4em] text-alblue mb-2"><?= $sectionSubtitle ?></h3>
            <h2 class="text-3xl font-black italic uppercase tracking-tighter"><?= explode(' ', $sectionTitle)[0] ?> <span class="text-white/40 not-italic"><?= substr($sectionTitle, strpos($sectionTitle, ' ')) ?></span></h2>
        </div>
        
        <div class="hidden md:flex gap-2">
            <button onclick="document.getElementById('related-scroll').scrollBy({left: -400, behavior: 'smooth'})" class="p-3 rounded-full border border-white/10 hover:bg-white/5 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button onclick="document.getElementById('related-scroll').scrollBy({left: 400, behavior: 'smooth'})" class="p-3 rounded-full border border-white/10 hover:bg-white/5 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    <div id="related-scroll" class="flex gap-6 overflow-x-auto no-scrollbar pb-10 snap-x">
        <?php foreach($relatedCars as $rCar): ?>
        <a href="car_details.php?id=<?= $rCar['id'] ?>" class="w-80 flex-shrink-0 group snap-start">
            <div class="glass-panel p-3 h-full transition-all duration-500 hover:border-alblue/40 group-hover:translate-y-[-8px]">
                <div class="aspect-[16/10] rounded-[20px] overflow-hidden mb-4 relative">
                    <img src="../<?= htmlspecialchars($rCar['main_image'] ?: 'uploads/dealer_listings/default_car.png') ?>" 
                         class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                    
                    <div class="absolute bottom-3 right-3 bg-black/60 backdrop-blur-md px-3 py-1 rounded-lg border border-white/10">
                        <p class="text-[10px] font-bold text-alblue italic">KES <?= number_format($rCar['price']) ?></p>
                    </div>
                </div>

                <div class="px-2 pb-2">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="font-black text-lg uppercase tracking-tight text-white group-hover:text-alblue transition"><?= htmlspecialchars($rCar['make']) ?></h4>
                            <p class="text-xs text-white/40 font-bold uppercase tracking-widest"><?= htmlspecialchars($rCar['model']) ?></p>
                        </div>
                        <span class="text-[10px] font-black text-white/20"><?= $rCar['year'] ?></span>
                    </div>

                    <div class="flex items-center gap-4 pt-4 border-t border-white/5">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="text-[9px] font-black text-white/60 uppercase tracking-widest"><?= $rCar['transmission'] ?></span>
                        </div>
                        <div class="ml-auto">
                             <span class="text-[8px] font-black text-alblue border border-alblue/30 px-2 py-0.5 rounded-md uppercase">View</span>
                        </div>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        
        <a href="dealer-profile.php?id=<?= $car['dealer_id'] ?>" class="w-80 flex-shrink-0 snap-start">
    <div class="glass-panel border-dashed border-white/20 h-full flex flex-col items-center justify-center p-8 text-center hover:bg-white/5 transition group">
        <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center mb-4 group-hover:bg-alblue/20 transition">
            <svg class="w-6 h-6 text-white/40 group-hover:text-alblue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
        </div>
        <h4 class="font-black uppercase tracking-widest text-xs text-white">Full Inventory</h4>
        <p class="text-[10px] text-white/40 uppercase font-bold mt-1">Explore all from <?= htmlspecialchars($dealer['name']) ?></p>
    </div>
</a>

    </div>
</div>
<?php endif; ?>


<div id="image-lightbox" class="fixed inset-0 bg-black/98 backdrop-blur-3xl hidden z-[100] flex flex-col items-center justify-center p-4">
    <button id="close-lightbox" class="absolute top-6 right-6 text-white/40 hover:text-white transition-all text-5xl z-[110]">&times;</button>
    
    <button onclick="scrollLightbox(-1)" class="absolute left-6 top-1/2 -translate-y-1/2 w-14 h-14 rounded-full bg-white/5 border border-white/10 hidden md:flex items-center justify-center text-white hover:bg-alblue transition-all z-[110]">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
    </button>
    
    <button onclick="scrollLightbox(1)" class="absolute right-6 top-1/2 -translate-y-1/2 w-14 h-14 rounded-full bg-white/5 border border-white/10 hidden md:flex items-center justify-center text-white hover:bg-alblue transition-all z-[110]">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
    </button>

    <div id="lightbox-images" class="flex gap-10 overflow-x-auto w-full max-w-6xl no-scrollbar snap-x snap-mandatory scroll-smooth">
        <?php foreach($images as $index => $img): ?>
            <div class="flex-shrink-0 w-full flex items-center justify-center snap-center" id="main-img-<?= $index ?>">
                <img src="../<?= htmlspecialchars($img) ?>" class="rounded-[32px] max-h-[70vh] max-w-full object-contain shadow-2xl border border-white/10">
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-10 flex gap-3 overflow-x-auto p-2 max-w-4xl no-scrollbar">
        <?php foreach($images as $index => $img): ?>
            <button onclick="jumpToImage(<?= $index ?>)" class="flex-shrink-0 relative group">
                <img src="../<?= htmlspecialchars($img) ?>" 
                     class="thumb-img w-20 h-14 md:w-28 md:h-20 object-cover rounded-xl border-2 border-transparent opacity-40 hover:opacity-100 transition-all duration-300"
                     data-index="<?= $index ?>">
            </button>
        <?php endforeach; ?>
    </div>
</div>



<script>
    // Tab Filtering Logic
    function filterSpecs(group, el) {
        document.querySelectorAll('.spec-tab').forEach(tab => tab.classList.remove('active-tab'));
        el.classList.add('active-tab');

        document.querySelectorAll('.group-item').forEach(item => {
            if (group === 'all') {
                item.style.display = 'block';
            } else {
                item.style.display = (item.getAttribute('data-group') === group) ? 'block' : 'none';
            }
        });
    }

    // Lightbox Logic
    const lightbox = document.getElementById('image-lightbox');
    const openBtn = document.getElementById('see-more-images');
    const closeBtn = document.getElementById('close-lightbox');

    openBtn.onclick = (e) => { e.preventDefault(); lightbox.classList.remove('hidden'); }
    closeBtn.onclick = () => lightbox.classList.add('hidden');
    lightbox.onclick = (e) => { if(e.target === lightbox) lightbox.classList.add('hidden'); }

    
    // Navigation Function
function scrollLightbox(direction) {
    const container = document.getElementById('lightbox-images');
    // Get the width of one image container
    const scrollAmount = container.clientWidth; 
    container.scrollBy({
        left: direction * scrollAmount,
        behavior: 'smooth'
    });
}

// Optional: Keyboard Navigation (Left/Right Arrows)
document.addEventListener('keydown', (e) => {
    const lightbox = document.getElementById('image-lightbox');
    if (!lightbox.classList.contains('hidden')) {
        if (e.key === "ArrowLeft") scrollLightbox(-1);
        if (e.key === "ArrowRight") scrollLightbox(1);
        if (e.key === "Escape") lightbox.classList.add('hidden');
    }
});

    
    function jumpToImage(index) {
    const container = document.getElementById('lightbox-images');
    const targetImg = document.getElementById('main-img-' + index);
    
    if (targetImg) {
        container.scrollTo({
            left: targetImg.offsetLeft,
            behavior: 'smooth'
        });
    }
    updateThumbnails(index);
}

function updateThumbnails(activeIndex) {
    document.querySelectorAll('.thumb-img').forEach((thumb, idx) => {
        if (idx == activeIndex) {
            thumb.classList.add('border-alblue', 'opacity-100', 'scale-110');
            thumb.classList.remove('opacity-40', 'border-transparent');
        } else {
            thumb.classList.remove('border-alblue', 'opacity-100', 'scale-110');
            thumb.classList.add('opacity-40', 'border-transparent');
        }
    });
}

// Sync thumbnails when scrolling manually
document.getElementById('lightbox-images').addEventListener('scroll', function() {
    const index = Math.round(this.scrollLeft / this.clientWidth);
    updateThumbnails(index);
});

// Initialize first thumbnail as active when opening
document.getElementById('see-more-images').addEventListener('click', () => {
    setTimeout(() => updateThumbnails(0), 100);
});


    
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('sent') === '1') {
        // FALLBACK LOGIC: Tries sender, then name, then 'Interested Client'
        const name = urlParams.get('sender') || urlParams.get('name') || 'Interested Client';
        
        const carMake = "<?= htmlspecialchars($car['make']) ?>";
        const carModel = "<?= htmlspecialchars($car['model']) ?>";
        const dealerWhatsApp = "<?= preg_replace('/\D/', '', $dealer['whatsapp']) ?>";
        const dealerEmail = "<?= $dealer['email'] ?>";
        
        // Construct the message
        const currentUrl = window.location.href.split('&')[0]; 
        const messageBody = `Hello, I am ${name}. I am inquiring about the ${carMake} ${carModel} on AutoLog Elite. Link: ${currentUrl}`;

        // 1. Show the Success Toast
        const toast = document.getElementById('success-toast');
        if(toast) {
            toast.classList.remove('translate-y-20', 'opacity-0');
        }

        // 2. Open WhatsApp (Small delay to ensure UI renders)
        setTimeout(() => {
            window.open(`https://wa.me/${dealerWhatsApp}?text=${encodeURIComponent(messageBody)}`, '_blank');
        }, 600);

        // 3. Open Email Client
        setTimeout(() => {
            window.location.href = `mailto:${dealerEmail}?subject=Inquiry: ${carMake} ${carModel}&body=${encodeURIComponent(messageBody)}`;
        }, 1800);

        // 4. CLEAN URL: Strip parameters so refresh doesn't trigger this again
        setTimeout(() => {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?id=<?= $car_id ?>";
            window.history.replaceState({}, '', cleanUrl);
        }, 3000);

        // 5. Hide Toast
        setTimeout(() => {
            if(toast) toast.classList.add('translate-y-20', 'opacity-0');
        }, 7000);
    }
}


    
    </script>
 <script>
function logAction(actionType) {
    const formData = new FormData();
    formData.append('ajax_log_action', actionType);
    formData.append('d_id', '<?= $car['dealer_id'] ?>');
    formData.append('r_id', '<?= $car['id'] ?>');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(() => {
        console.log('Intelligence logged: ' + actionType);
    }).catch(err => console.error('Tracking failed', err));
}

// Start the heartbeat 10 seconds after page load
setInterval(function() {
    const data = new FormData();
    data.append('heartbeat', '1');
    data.append('r_id', '<?= $car['id'] ?>');

    fetch(window.location.href, {
        method: 'POST',
        body: data
    });
}, 10000); // 10000ms = 10 seconds

</script>


<?php include '../includes/footer.php'; ?>

<div id="toast" class="fixed bottom-10 left-1/2 -translate-x-1/2 translate-y-20 opacity-0 bg-alblue text-white px-8 py-4 rounded-2xl font-bold shadow-2xl transition-all duration-500 z-[200] flex items-center gap-3">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
    Inquiry Recorded Successfully!
</div>



</body>
</html>
