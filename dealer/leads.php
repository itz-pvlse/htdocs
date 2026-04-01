<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Update this line to include the dealer's name
$dealer_stmt = $pdo->prepare("SELECT name, latitude, longitude FROM dealers WHERE user_id = ? LIMIT 1");
$dealer_stmt->execute([$user_id]);
$dealer_profile = $dealer_stmt->fetch(PDO::FETCH_ASSOC);

// Store the name in a variable (fallback to 'AutoCore' if empty)
$dealer_name = !empty($dealer_profile['name']) ? $dealer_profile['name'] : 'AutoCore';


// Generate dynamic maps link or default to a search if coordinates are missing
$maps_link = "https://www.google.com/maps/search/?api=1&query=";
if ($dealer_profile && $dealer_profile['latitude'] && $dealer_profile['longitude']) {
    $maps_link = "https://www.google.com/maps?q={$dealer_profile['latitude']},{$dealer_profile['longitude']}";
}

// --- 2. AJAX API LOGIC ---
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $lead_id = $_POST['lead_id'];

    if ($_POST['action'] == 'update_status') {
        $status = $_POST['status'];
        $sql = "UPDATE leads SET status = ?, contacted = 1, responded_at = IF(responded_at IS NULL, NOW(), responded_at) 
                WHERE id = ? AND dealer_id = ?";
        $pdo->prepare($sql)->execute([$status, $lead_id, $user_id]);
        echo json_encode(['success' => true]);
    }

    if ($_POST['action'] == 'mark_contacted') {
        $sql = "UPDATE leads SET contacted = 1, responded_at = IF(responded_at IS NULL, NOW(), responded_at) 
                WHERE id = ? AND dealer_id = ?";
        $pdo->prepare($sql)->execute([$lead_id, $user_id]);
        echo json_encode(['success' => true]);
    }
    
    if ($_POST['action'] == 'save_notes') {
        $notes = $_POST['notes'];
        $sql = "UPDATE leads SET notes = ? WHERE id = ? AND dealer_id = ?";
        $pdo->prepare($sql)->execute([$notes, $lead_id, $user_id]);
        echo json_encode(['success' => true]);
    }
    exit;
}

// --- 3. DATA FETCHING (SMART URGENCY SORT) ---
$leads_stmt = $pdo->prepare("
    SELECT l.*, dl.make, dl.model, dl.year, dl.price, dl.main_image,
    CASE 
        WHEN l.contacted = 0 THEN 1 
        WHEN l.status = 'following_up' AND l.responded_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 2
        ELSE 3 
    END as urgency
    FROM leads l
    LEFT JOIN dealer_listings dl ON l.listing_id = dl.id
    WHERE l.dealer_id = ? AND l.status NOT IN ('closed_lost', 'junk')
    ORDER BY urgency ASC, l.created_at DESC
");
$leads_stmt->execute([$user_id]);
$leads = $leads_stmt->fetchAll(PDO::FETCH_ASSOC);

function formatInterval($timestamp) {
    if(!$timestamp) return 'just now';
    $time = time() - strtotime($timestamp);
    if ($time < 60) return 'just now';
    if ($time < 3600) return round($time/60).' mins ago';
    if ($time < 86400) return round($time/3600).' hrs ago';
    if ($time < 604800) return round($time/86400).' days ago';
    return date('d M', strtotime($timestamp));
}

function isStale($lead) {
    return ($lead['contacted'] == 1 && $lead['status'] == 'following_up' && strtotime($lead['responded_at']) < strtotime('-24 hours'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>AutoCore CRM | Mobile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        :root { --sat: env(safe-area-inset-top); --sab: env(safe-area-inset-bottom); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #ffffff; overflow: hidden; height: 100vh; }
        .app-shell { height: 100vh; display: flex; flex-direction: column; padding-top: var(--sat); }
        .scroll-container { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; padding-bottom: calc(100px + var(--sab)); }
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; height: calc(70px + var(--sab)); background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-top: 1px solid #f1f5f9; display: flex; justify-content: space-around; padding-bottom: var(--sab); z-index: 40; }
        .detail-sheet { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: white; z-index: 50; transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1); display: flex; flex-direction: column; }
        .detail-sheet.active { transform: translateY(0); }
        .btn-press:active { transform: scale(0.96); transition: 0.1s; }
        .status-badge { font-size: 8px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 99px; letter-spacing: 0.05em; }
        .status-following_up { background: #eff6ff; color: #3b82f6; }
        .status-test_drive { background: #fef3c7; color: #d97706; }
        .status-closed_won { background: #ecfdf5; color: #10b981; }
        .filter-chip { transition: all 0.2s; border: 1px solid #f1f5f9; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
        .filter-chip.active { background: #0f172a; color: white; border-color: #0f172a; }
        .chip-count { background: rgba(255,255,255,0.2); padding: 1px 6px; border-radius: 6px; font-size: 9px; }
        .filter-chip:not(.active) .chip-count { background: #f1f5f9; color: #94a3b8; }
        .lead-item.hidden { display: none; }
        .stale-card { background: #fffcf0 !important; border-color: #fef3c7 !important; }
    </style>
</head>
<body>

<div class="app-shell">
    <header class="px-6 py-4 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="dealer.php" class="w-10 h-10 bg-white border border-slate-100 rounded-2xl flex items-center justify-center text-slate-900 shadow-sm active:scale-95 transition-transform">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <div>
                <h1 class="text-2xl font-800 tracking-tighter uppercase italic text-slate-900 leading-none">Intelligence</h1>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Real-time Pipeline</p>
            </div>
        </div>
        <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-100">
            <i class="fas fa-bolt text-xs"></i>
        </div>
    </header>

    <div class="px-6 space-y-3">
        <div class="bg-slate-100 rounded-2xl flex items-center px-4 py-3">
            <i class="fas fa-search text-slate-400 mr-3 text-sm"></i>
            <input type="text" id="appSearch" onkeyup="runFilters()" placeholder="Search name or vehicle..." class="bg-transparent border-none outline-none text-sm font-semibold w-full">
        </div>
        
        <div class="flex gap-2 overflow-x-auto no-scrollbar pb-2">
            <button onclick="toggleFilter('all', this)" class="filter-chip active px-5 py-2 rounded-full text-[10px] font-bold uppercase">All <span class="chip-count" id="count-all">0</span></button>
            <button onclick="toggleFilter('unread', this)" class="filter-chip px-5 py-2 rounded-full text-[10px] font-bold uppercase">Unread <span class="chip-count" id="count-unread">0</span></button>
            <button onclick="toggleFilter('contacted', this)" class="filter-chip px-5 py-2 rounded-full text-[10px] font-bold uppercase">Contacted <span class="chip-count" id="count-contacted">0</span></button>
        </div>
    </div>

    <main class="scroll-container px-6 pt-2 space-y-4 no-scrollbar">
        <?php foreach($leads as $l): ?>
        <div class="lead-item btn-press bg-white border border-slate-100 rounded-[2rem] p-5 shadow-sm flex items-center gap-4 <?= isStale($l) ? 'stale-card' : '' ?>" 
             onclick='openLead(<?= htmlspecialchars(json_encode($l), ENT_QUOTES, "UTF-8") ?>)'
             data-search="<?= strtolower($l['name'] . ' ' . $l['make'] . ' ' . $l['model']) ?>"
             data-contacted="<?= $l['contacted'] ?>">
            
            <div class="relative">
                <img src="<?= $l['main_image'] ? '../'.$l['main_image'] : '../assets/no-car.jpg' ?>" class="w-14 h-14 rounded-2xl object-cover bg-slate-50 border border-slate-100">
                <?php if(!$l['contacted']): ?>
                    <span class="unread-dot absolute -top-1 -right-1 w-4 h-4 bg-indigo-600 border-2 border-white rounded-full"></span>
                <?php endif; ?>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex justify-between items-start">
                    <h3 class="font-bold text-slate-900 truncate"><?= htmlspecialchars($l['name']) ?></h3>
                    <?php if(isStale($l)): ?>
                        <span class="bg-amber-500 text-white text-[7px] font-black px-1.5 py-0.5 rounded-md">NEEDS ACTION</span>
                    <?php else: ?>
                        <span class="timeline-label text-[9px] font-bold text-slate-400 uppercase tracking-tighter">
                            <?= $l['contacted'] ? 'Contacted '.formatInterval($l['responded_at']) : 'Received '.formatInterval($l['created_at']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-[11px] text-indigo-600 font-800 uppercase tracking-tight"><?= $l['year'] ?> <?= $l['make'] ?> <?= $l['model'] ?></p>
                <div class="mt-2">
                    <span class="status-badge status-<?= $l['status'] ?>"><?= str_replace('_', ' ', $l['status']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </main>
</div>

<nav class="bottom-nav">
    <a href="dealer.php" class="flex flex-col items-center justify-center text-slate-300 w-full"><i class="fas fa-th-large text-xl"></i></a>
    <a href="#" class="flex flex-col items-center justify-center text-indigo-600 w-full"><i class="fas fa-comment-dots text-xl"></i></a>
    <a href="inventory.php" class="flex flex-col items-center justify-center text-slate-300 w-full"><i class="fas fa-car text-xl"></i></a>
</nav>

<div id="leadSheet" class="detail-sheet">
    <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mt-4 mb-2"></div>
    <div class="px-6 py-4 flex justify-between items-center">
        <button onclick="closeLead()" class="w-10 h-10 rounded-2xl bg-slate-50 text-slate-400"><i class="fas fa-times"></i></button>
        <div class="text-center">
            <h2 id="shName" class="text-xl font-800 tracking-tighter uppercase italic text-slate-900 leading-none"></h2>
            <p id="shEmail" class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-widest"></p>
        </div>
        <a id="shDial" href="#" onclick="markAsContactedSync()" class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center shadow-lg"><i class="fas fa-phone-alt text-xs"></i></a>
    </div>

    <div class="flex-1 overflow-y-auto p-6 space-y-6 no-scrollbar">
        <div class="bg-slate-900 rounded-[2.5rem] p-6 text-white relative overflow-hidden shadow-2xl">
            <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Target Inventory</p>
            <h3 id="shCar" class="text-lg font-800 uppercase italic"></h3>
            <p id="shPrice" class="text-xl font-black"></p>
            <button onclick="copyUnitLink()" class="mt-3 bg-white/10 text-[10px] font-bold px-4 py-2 rounded-xl backdrop-blur-sm"><i class="fas fa-copy mr-2"></i>Copy Unit Link</button>
        </div>

        <div class="bg-indigo-50 rounded-3xl p-5 border border-indigo-100">
            <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-2">Original Inquiry</p>
            <p id="shMsg" class="text-sm text-slate-700 leading-relaxed italic"></p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="relative group col-span-1">
                <button onclick="toggleQuickReplies()" class="w-full btn-press flex items-center justify-center gap-3 p-5 bg-emerald-500 text-white rounded-3xl shadow-xl shadow-emerald-100">
                    <i class="fab fa-whatsapp text-xl"></i>
                    <span class="text-[10px] font-black uppercase">WhatsApp</span>
                </button>
                <div id="quickReplies" class="hidden absolute bottom-full left-0 w-64 bg-white shadow-2xl rounded-3xl p-3 mb-2 z-50 border border-slate-100">
                    <p class="text-[9px] font-black text-slate-400 mb-2 px-2 uppercase tracking-widest">Quick Replies</p>
                    <button onclick="sendWA('Availability')" class="w-full text-left p-3 hover:bg-slate-50 rounded-2xl text-[11px] font-bold">"Is this still available?"</button>
                    <button onclick="sendWA('Pricing')" class="w-full text-left p-3 hover:bg-slate-50 rounded-2xl text-[11px] font-bold">"Send best price & finance"</button>
                    <button onclick="sendWA('Viewing')" class="w-full text-left p-3 hover:bg-slate-50 rounded-2xl text-[11px] font-bold">"Book a test drive viewing"</button>
                    <button onclick="sendWA('Location')" class="w-full text-left p-3 hover:bg-slate-50 rounded-2xl text-[11px] font-bold text-indigo-600 border-t border-slate-50 mt-1"><i class="fas fa-map-marker-alt mr-2"></i> Send Showroom Location</button>
                </div>
            </div>
            <button onclick="markAsContactedSync()" class="btn-press flex items-center justify-center gap-3 p-5 bg-indigo-50 text-indigo-600 rounded-3xl">
                <i class="fas fa-check-circle text-sm"></i>
                <span id="contactedLabel" class="text-[10px] font-black uppercase tracking-widest">Mark Seen</span>
            </button>
        </div>

        <div>
            <div class="flex justify-between items-center mb-2 px-2">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Dealer Commentary</p>
                <span id="saveLabel" class="text-[9px] font-bold text-emerald-500 opacity-0 transition-opacity">Synced</span>
            </div>
            <textarea id="shNotes" onblur="saveNotes()" class="w-full h-32 bg-slate-50 rounded-[2rem] p-6 text-sm font-semibold text-slate-700 outline-none border-2 border-transparent focus:bg-white focus:border-indigo-100 transition-all resize-none shadow-inner" placeholder="Log deal details..."></textarea>
        </div>
    </div>

    <div class="p-6 pb-12 border-t border-slate-50 bg-white">
        <p class="text-center text-[9px] font-black text-slate-300 uppercase tracking-widest mb-4">Pipeline Status</p>
        <div class="grid grid-cols-3 gap-2">
            <button onclick="updateStatus('following_up')" class="btn-press py-4 bg-blue-50 text-blue-600 rounded-2xl text-[9px] font-black uppercase">Follow Up</button>
            <button onclick="updateStatus('test_drive')" class="btn-press py-4 bg-amber-50 text-amber-600 rounded-2xl text-[9px] font-black uppercase">Test Drive</button>
            <button onclick="updateStatus('closed_won')" class="btn-press py-4 bg-indigo-600 text-white rounded-2xl text-[9px] font-black uppercase shadow-xl shadow-indigo-100">Closed Won</button>
        </div>
    </div>
</div>

<script>
    let activeLead = null;
    let activeStatusFilter = 'all';
    
    // DYNAMIC LOCATION FETCHED FROM DATABASE
    const SHOWROOM_LOCATION = "<?= $maps_link ?>";
    const DEALER_NAME = "<?= htmlspecialchars($dealer_name, ENT_QUOTES) ?>";
    
    function updateCounts() {
        const items = document.querySelectorAll('.lead-item');
        let unread = 0, contacted = 0;
        items.forEach(i => {
            if(i.getAttribute('data-contacted') === "0") unread++;
            else contacted++;
        });
        document.getElementById('count-all').innerText = items.length;
        document.getElementById('count-unread').innerText = unread;
        document.getElementById('count-contacted').innerText = contacted;
    }

    function toggleFilter(filter, btn) {
        document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        activeStatusFilter = filter;
        runFilters();
    }

    function runFilters() {
        const query = document.getElementById('appSearch').value.toLowerCase();
        const items = document.querySelectorAll('.lead-item');
        items.forEach(item => {
            const isContacted = item.getAttribute('data-contacted');
            const search = item.getAttribute('data-search');
            let matchesFilter = true;
            if(activeStatusFilter === 'unread') matchesFilter = (isContacted === '0');
            if(activeStatusFilter === 'contacted') matchesFilter = (isContacted === '1');
            const matchesSearch = search.includes(query);
            item.style.display = (matchesFilter && matchesSearch) ? 'flex' : 'none';
        });
    }

    function openLead(lead) {
        activeLead = lead;
        document.getElementById('shName').innerText = lead.name;
        document.getElementById('shEmail').innerText = lead.email;
        document.getElementById('shCar').innerText = `${lead.year} ${lead.make} ${lead.model}`;
        document.getElementById('shPrice').innerText = `KSH ${parseInt(lead.price).toLocaleString()}`;
        document.getElementById('shMsg').innerText = lead.message || "Generic inquiry.";
        document.getElementById('shNotes').value = lead.notes || "";
        document.getElementById('contactedLabel').innerText = "Mark Seen";
        document.getElementById('shDial').href = `tel:${lead.phone || lead.whatsapp}`;
        document.getElementById('leadSheet').classList.add('active');
        document.getElementById('quickReplies').classList.add('hidden');
    }

    function closeLead() { document.getElementById('leadSheet').classList.remove('active'); runFilters(); }

    function toggleQuickReplies() { document.getElementById('quickReplies').classList.toggle('hidden'); }
function sendWA(type) {
    // 1. Prioritize the whatsapp field, fallback to phone if whatsapp is empty
    const rawNumber = activeLead.whatsapp || activeLead.phone;
    
    if (!rawNumber) {
        alert("No contact number available for this lead.");
        return;
    }

    // 2. Clean the number (remove spaces, dashes, plus signs)
    const phone = rawNumber.replace(/\D/g, '');
    
    let msg = `Hi ${activeLead.name}, this is ${DEALER_NAME} regarding the ${activeLead.make} ${activeLead.model}.`;
    
    if(type === 'Availability') msg += " Yes, this unit is still available. When would you like to view it?";
    if(type === 'Pricing') msg += ` The price is KSH ${parseInt(activeLead.price).toLocaleString()}. We also have financing options available.`;
    if(type === 'Viewing') msg += " We are open for viewings today. Would you like me to send you our location via Google Maps?";
    if(type === 'Location') msg = `Hi ${activeLead.name}, here is our showroom location: ${SHOWROOM_LOCATION}`;
    
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
    markAsContactedSync();
}

    function copyUnitLink() {
        const url = window.location.origin + "/dealer/car_details.php?id=" + activeLead.listing_id;
        navigator.clipboard.writeText(url);
        alert("Vehicle Link Copied!");
    }

    async function markAsContactedSync() {
        const leadItem = document.querySelector(`.lead-item[onclick*='"id":${activeLead.id}']`);
        if (leadItem && leadItem.getAttribute('data-contacted') === "0") {
            leadItem.setAttribute('data-contacted', '1');
            const dot = leadItem.querySelector('.unread-dot'); if (dot) dot.remove();
            const timeLabel = leadItem.querySelector('.timeline-label');
            if(timeLabel) timeLabel.innerText = "Contacted just now";
            updateCounts();
        }
        const fd = new FormData();
        fd.append('action', 'mark_contacted');
        fd.append('lead_id', activeLead.id);
        await fetch('leads.php', { method: 'POST', body: fd });
        document.getElementById('contactedLabel').innerText = "MARKED SEEN";
    }

    async function updateStatus(s) {
        const fd = new FormData();
        fd.append('action', 'update_status');
        fd.append('lead_id', activeLead.id);
        fd.append('status', s);
        await fetch('leads.php', { method: 'POST', body: fd });
        location.reload();
    }

    async function saveNotes() {
        const fd = new FormData();
        fd.append('action', 'save_notes');
        fd.append('lead_id', activeLead.id);
        fd.append('notes', document.getElementById('shNotes').value);
        await fetch('leads.php', { method: 'POST', body: fd });
        const ind = document.getElementById('saveLabel');
        ind.style.opacity = '1';
        setTimeout(() => ind.style.opacity = '0', 2000);
    }

    window.onload = updateCounts;
</script>
</body>
</html>
