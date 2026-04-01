<?php
// 1. Error Reporting & Sessions
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mechanic') {
    header("Location: ../login.php");
    exit();
}

$mechanic_id = $_SESSION['user_id'];

// --- FETCH MECHANIC PROFILE ---
$stmt_me = $pdo->prepare("SELECT name, email, avatar FROM users WHERE id = ?");
$stmt_me->execute([$mechanic_id]);
$me = $stmt_me->fetch();

// --- FETCH STATS (Multi-Tech structure) ---
$stmt_stats = $pdo->prepare("
    SELECT COUNT(DISTINCT j.id) as total 
    FROM jobs j
    JOIN job_assignments ja ON j.id = ja.job_id
    WHERE ja.mechanic_id = ? 
    AND j.status IN ('completed', 'pending_release')
");
$stmt_stats->execute([$mechanic_id]);
$completed_count = $stmt_stats->fetch()['total'];

// --- ACTION: SUBMIT FOR RELEASE ---
if (isset($_POST['complete_job'])) {
    $job_id = $_POST['job_id'];
    $notes = $_POST['mechanic_notes'];
    $mileage = $_POST['mileage'] ?? 0;

    try {
        $pdo->beginTransaction();

        $stmt_mil = $pdo->prepare("
            UPDATE service_requests sr
            JOIN jobs j ON j.request_id = sr.id
            SET sr.mileage = ?
            WHERE j.id = ?
        ");
        $stmt_mil->execute([$mileage, $job_id]);

        $stmt_job = $pdo->prepare("
            UPDATE jobs 
            SET status = 'pending_release', 
                mechanic_notes = ?, 
                completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt_job->execute([$notes, $job_id]);

        $pdo->commit();
        // Fixed: Added last_job parameter
        header("Location: mechanic_dashboard.php?success=submitted&last_job=" . $job_id); 
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: mechanic_dashboard.php?error=system_error");
        exit();
    }
}

// --- ACTION: ADD PART (Multi-User Tagged) ---
if (isset($_POST['add_inventory_part'])) {
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    $part_id = isset($_POST['part_id']) ? (int)$_POST['part_id'] : 0;
    $qty_requested = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $mechanic_id = $_SESSION['user_id']; 

    $pst = $pdo->prepare("SELECT part_name, selling_price, quantity FROM parts_inventory WHERE id = ?");
    $pst->execute([$part_id]);
    $part = $pst->fetch();
    
    if ($part && $part['quantity'] >= $qty_requested) {
        $pdo->beginTransaction();
        try {
            $stmt1 = $pdo->prepare("INSERT INTO job_items (job_id, type, description, quantity, unit_price, added_by_id) VALUES (?, 'part', ?, ?, ?, ?)");
            $stmt1->execute([$job_id, $part['part_name'], $qty_requested, $part['selling_price'], $mechanic_id]);
            
            $stmt2 = $pdo->prepare("INSERT INTO parts_used (part_id, job_id, quantity_used) VALUES (?, ?, ?)");
            $stmt2->execute([$part_id, $job_id, $qty_requested]);
            
            $stmt3 = $pdo->prepare("UPDATE parts_inventory SET quantity = quantity - ? WHERE id = ?");
            $stmt3->execute([$qty_requested, $part_id]);
            
            $pdo->commit();
            header("Location: mechanic_dashboard.php?success=part_added&last_job=" . $job_id);
            exit();
        } catch (Exception $e) { 
            $pdo->rollBack();
            header("Location: mechanic_dashboard.php?error=db_error");
            exit();
        }
    } else {
        header("Location: mechanic_dashboard.php?error=insufficient_stock");
        exit();
    }
}

// --- ACTION: ADD LABOR (Multi-User Tagged) ---
if (isset($_POST['add_labor'])) {
    $job_id = $_POST['job_id']; // Define the variable from POST
    $description = $_POST['description'];
    $quantity = $_POST['quantity'];
    $unit_price = $_POST['unit_price'];

    $stmt = $pdo->prepare("INSERT INTO job_items (job_id, type, description, quantity, unit_price, added_by_id) VALUES (?, 'labor', ?, ?, ?, ?)");
    $stmt->execute([
        $job_id, 
        $description, 
        $quantity, 
        $unit_price,
        $mechanic_id
    ]);
    // Fixed: Redirect now has both variables needed for auto-reopen
    header("Location: mechanic_dashboard.php?success=labor_logged&last_job=" . $job_id);    
    exit();
}

// --- FETCH ACTIVE JOBS ---
$jobs_query = $pdo->prepare("
    SELECT j.*, v.plate_no, v.make, v.model, sr.requested_service, sr.mileage as current_mileage,
    (SELECT SUM(quantity * unit_price) FROM job_items WHERE job_id = j.id) as total_billable,
    ja.is_lead
    FROM jobs j
    JOIN job_assignments ja ON j.id = ja.job_id
    JOIN service_requests sr ON j.request_id = sr.id
    JOIN vehicles v ON sr.vehicle_id = v.id
    WHERE ja.mechanic_id = ? AND j.status IN ('ongoing', 'pending_release')
    ORDER BY FIELD(j.status, 'ongoing', 'pending_release'), j.created_at DESC
");
$jobs_query->execute([$mechanic_id]);
$my_jobs = $jobs_query->fetchAll();

$inventory = $pdo->query("SELECT id, part_name, quantity, selling_price FROM parts_inventory WHERE quantity > 0")->fetchAll();

// --- FETCH COMPLETED HISTORY ---
$history_query = $pdo->prepare("
    SELECT DISTINCT j.*, v.plate_no, v.make, v.model, j.completed_at,
    (SELECT SUM(quantity * unit_price) FROM job_items WHERE job_id = j.id) as total_billable
    FROM jobs j
    JOIN job_assignments ja ON j.id = ja.job_id
    JOIN service_requests sr ON j.request_id = sr.id
    JOIN vehicles v ON sr.vehicle_id = v.id
    WHERE ja.mechanic_id = ? AND j.status = 'completed'
    ORDER BY j.completed_at DESC
    LIMIT 15
");
$history_query->execute([$mechanic_id]);
$history_jobs = $history_query->fetchAll();

include '../includes/header.php'; 
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tech Dashboard | <?= htmlspecialchars($me['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; color: #1E293B; }
        .job-card { transition: all 0.2s ease; border: 1px solid #E2E8F0; }
        .drawer { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(100%); }
        .drawer.open { transform: translateY(0); }
        .profile-gradient { background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); }
        #jobDrawer {
    z-index: 9999 !important;
}

#jobDrawer > div {
    z-index: 10000 !important;
}

    </style>
</head>
<body class="pb-24">

    <header class="bg-white border-b border-slate-200 pt-8 pb-6 px-6 sticky top-0 z-40">
        <div class="max-w-2xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <?php 
                            $db_path = $me['avatar']; 
                            $full_path = "../" . $db_path;
                        ?>
                        
                        <?php if (!empty($db_path) && file_exists($full_path)): ?>
                            <img src="<?= $full_path ?>" 
                                 alt="Profile" 
                                 class="w-14 h-14 rounded-2xl shadow-lg border-2 border-white object-cover">
                        <?php else: ?>
                            <div class="w-14 h-14 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg font-black text-xl border-2 border-white">
                                <?= strtoupper(substr($me['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full"></span>
                    </div>

                    <div>
                        <h1 class="text-xl font-black tracking-tight"><?= htmlspecialchars($me['name']) ?></h1>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Technician ID: #<?= $mechanic_id ?></p>
                    </div>
                </div>
                <a href="../auth/logout.php" class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 border border-slate-100">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-slate-900 p-4 rounded-3xl text-white shadow-xl">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Finished</p>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl font-black"><?= $completed_count ?></span>
                        <i class="fa-solid fa-circle-check text-emerald-400 text-xs"></i>
                    </div>
                </div>
                <div class="bg-indigo-50 p-4 rounded-3xl border border-indigo-100">
                    <p class="text-[8px] font-black text-indigo-400 uppercase tracking-widest mb-1">Active Queue</p>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl font-black text-indigo-600"><?= count($my_jobs) ?></span>
                        <span class="text-[10px] font-bold text-indigo-400 uppercase italic">In Bay</span>
                    </div>
                </div>
            </div>
        </div>
    </header>


  <main class="p-6 max-w-2xl mx-auto space-y-6">
    <div class="flex p-1 bg-slate-200/50 rounded-2xl mb-2">
        <button onclick="switchMainTab('active')" id="btnActive" class="flex-1 py-3 text-[10px] font-black uppercase rounded-xl bg-white text-slate-900 shadow-sm transition-all">
            Active Bay (<?= count($my_jobs) ?>)
        </button>
        <button onclick="switchMainTab('history')" id="btnHistory" class="flex-1 py-3 text-[10px] font-black uppercase rounded-xl text-slate-400 transition-all">
            Work History (<?= count($history_jobs) ?>)
        </button>
    </div>

    <div id="activeSection" class="space-y-4">
        <div class="flex justify-between items-center px-2">
            <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Ongoing Tasks</h2>
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                <span class="text-[10px] font-bold text-emerald-600 uppercase">Live Floor</span>
            </div>
        </div>
        
        <?php if(empty($my_jobs)): ?>
            <div class="py-20 text-center bg-white rounded-[2.5rem] border border-dashed border-slate-200">
                <i class="fa-solid fa-mug-hot text-slate-200 text-4xl mb-4"></i>
                <p class="text-xs font-bold text-slate-400 italic">No active jobs in your queue.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($my_jobs as $job): 
            $isPending = ($job['status'] === 'pending_release');
            $start = new DateTime($job['created_at']);
            $diff = $start->diff(new DateTime());
            $hours = ($diff->days * 24) + $diff->h;
        ?>
        <div onclick="openJob(<?= htmlspecialchars(json_encode($job)) ?>)" 
             class="job-card bg-white rounded-[2rem] p-6 shadow-sm hover:shadow-md cursor-pointer relative overflow-hidden transition-all border-2 <?= $isPending ? 'border-emerald-100' : 'border-transparent' ?>">
            
            <div class="flex justify-between items-start mb-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center border transition-colors <?= $isPending ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-indigo-50 text-indigo-600 border-indigo-100' ?>">
                        <i class="fa-solid <?= $isPending ? 'fa-clipboard-check' : 'fa-car-side' ?>"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 text-lg leading-tight"><?= $job['make'] ?> <?= $job['model'] ?></h3>
                        <p class="text-xs font-bold text-slate-400"><?= $job['plate_no'] ?></p>
                    </div>
                </div>
                <span class="text-[9px] font-black px-2.5 py-1 <?= $isPending ? 'bg-emerald-500' : 'bg-indigo-600' ?> text-white rounded-lg uppercase shadow-lg">
                    <?= $isPending ? 'Waiting QC' : 'Ongoing' ?>
                </span>
            </div>

            <div class="progress-line h-1.5 w-full rounded-full mb-4 bg-slate-100">
                <div class="h-full rounded-full transition-all <?= $isPending ? 'bg-emerald-500 w-full' : 'bg-indigo-600 w-[45%]' ?>"></div>
            </div>

            <div class="flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-clock text-slate-300 text-[10px]"></i>
                    <span class="text-[10px] font-bold text-slate-500 uppercase">
                        <?= $isPending ? 'Completed' : $hours . 'h ' . $diff->i . 'm' ?>
                    </span>
                </div>
                <div class="text-right">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-0.5">Billables</p>
                    <p class="text-xs font-black text-slate-900">KES <?= number_format($job['total_billable'] ?? 0) ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="historySection" class="hidden space-y-4">
        <div class="flex justify-between items-center px-2">
            <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Job Archives</h2>
            <span class="text-[8px] font-bold text-slate-300 uppercase">Technical Logs</span>
        </div>

        <div class="px-2 space-y-3">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 text-xs"></i>
                <input type="text" id="historySearch" onkeyup="filterHistory()" 
                       placeholder="Search Plate or Model..." 
                       class="w-full bg-white border border-slate-200 pl-10 pr-4 py-3 rounded-2xl text-[10px] font-bold uppercase tracking-widest outline-none focus:ring-2 focus:ring-indigo-500 transition-all shadow-sm">
            </div>
            
            <div class="flex gap-2 overflow-x-auto no-scrollbar">
                <button onclick="filterByDate('all', this)" class="date-filter-btn active-filter bg-indigo-600 text-white px-4 py-2 rounded-lg text-[8px] font-black uppercase whitespace-nowrap shadow-md shadow-indigo-100">All Jobs</button>
                <button onclick="filterByDate('today', this)" class="date-filter-btn bg-white text-slate-400 border border-slate-100 px-4 py-2 rounded-lg text-[8px] font-black uppercase whitespace-nowrap">Today</button>
                <button onclick="filterByDate('week', this)" class="date-filter-btn bg-white text-slate-400 border border-slate-100 px-4 py-2 rounded-lg text-[8px] font-black uppercase whitespace-nowrap">This Week</button>
            </div>
        </div>
        
        <div id="historyCardsContainer" class="space-y-4">
            <?php if(empty($history_jobs)): ?>
                <div class="py-12 text-center bg-white rounded-[2rem] border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">No completed records</p>
                </div>
            <?php endif; ?>

            <?php foreach ($history_jobs as $h_job): ?>
                <div class="history-card bg-white border border-slate-200 rounded-[2rem] p-5 shadow-sm transition-all" 
                     data-search="<?= strtolower($h_job['plate_no'] . ' ' . $h_job['make'] . ' ' . $h_job['model']) ?>"
                     data-date="<?= date('Y-m-d', strtotime($h_job['completed_at'])) ?>">
                    
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 border border-slate-100">
                                <i class="fa-solid fa-box-archive text-xs"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-800 leading-none"><?= $h_job['make'] ?> <?= $h_job['model'] ?></h4>
                                <p class="text-[9px] font-bold text-slate-400 uppercase mt-1">
                                    <span class="plate-text"><?= $h_job['plate_no'] ?></span> • <?= date('d M, Y', strtotime($h_job['completed_at'])) ?>
                                </p>
                            </div>
                        </div>
                        <span class="text-[8px] font-black px-3 py-1 bg-slate-100 text-slate-500 rounded-full uppercase">Released</span>
                    </div>

                    <button onclick="viewTechnicalSummary(<?= $h_job['id'] ?>)" 
                            class="w-full py-3 bg-indigo-50 hover:bg-indigo-100 rounded-xl text-[9px] font-black uppercase tracking-widest text-indigo-600 transition-colors border border-indigo-100">
                        <i class="fa-solid fa-magnifying-glass mr-2"></i> View Work Log
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<script>
/**
 * Switch between Active Bay and Work History tabs
 */
function switchMainTab(tab) {
    const activeSec = document.getElementById('activeSection');
    const historySec = document.getElementById('historySection');
    const btnActive = document.getElementById('btnActive');
    const btnHistory = document.getElementById('btnHistory');

    if (tab === 'active') {
        activeSec.classList.remove('hidden');
        historySec.classList.add('hidden');
        btnActive.className = "flex-1 py-3 text-[10px] font-black uppercase rounded-xl bg-white text-slate-900 shadow-sm transition-all";
        btnHistory.className = "flex-1 py-3 text-[10px] font-black uppercase rounded-xl text-slate-400 transition-all";
    } else {
        activeSec.classList.add('hidden');
        historySec.classList.remove('hidden');
        btnHistory.className = "flex-1 py-3 text-[10px] font-black uppercase rounded-xl bg-white text-slate-900 shadow-sm transition-all";
        btnActive.className = "flex-1 py-3 text-[10px] font-black uppercase rounded-xl text-slate-400 transition-all";
    }
}

/**
 * Filter Work History by Text
 */
function filterHistory() {
    const input = document.getElementById('historySearch').value.toLowerCase();
    const cards = document.querySelectorAll('.history-card');
    
    cards.forEach(card => {
        const text = card.getAttribute('data-search');
        card.style.display = text.includes(input) ? 'block' : 'none';
    });
}
</script>



    <div id="jobDrawer" class="drawer fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex flex-col justify-end hidden">
    <div class="bg-white rounded-t-[3rem] max-h-[92vh] overflow-y-auto shadow-2xl no-scrollbar">
        <div class="sticky top-0 bg-white/80 backdrop-blur-md z-10 p-6 flex justify-between items-center border-b border-slate-50">
            <div onclick="closeDrawer()" class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center cursor-pointer hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-chevron-down text-slate-400"></i>
            </div>
            <div class="text-center">
                <h2 id="drawerTitle" class="font-black text-lg uppercase tracking-tight text-slate-900 leading-none">Vehicle</h2>
                <div id="drawerPlate" class="flex items-center justify-center gap-2 mt-1">
                    </div>
            </div>
            <div class="w-10"></div>
        </div>

        <div class="p-8 space-y-8">
            <div id="teamSection" class="bg-slate-900 rounded-[2rem] p-5 shadow-xl">
                <div class="flex justify-between items-center mb-3">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Active Team</p>
                    <span id="roleBadge" class="text-[8px] font-black px-2 py-0.5 rounded-md uppercase"></span>
                </div>
                <div id="mechanicList" class="flex -space-x-2 overflow-hidden">
                    </div>
            </div>

            <div class="flex p-1 bg-slate-100 rounded-2xl" id="drawerTabs">
                <button onclick="switchTab('parts')" id="tabParts" class="flex-1 py-3 text-[10px] font-black uppercase rounded-xl bg-white text-slate-900 shadow-sm transition-all">Add Parts</button>
                <button onclick="switchTab('labor')" id="tabLabor" class="flex-1 py-3 text-[10px] font-black uppercase rounded-xl text-slate-400 transition-all">Add Labor</button>
                <button onclick="switchTab('status')" id="tabStatus" class="flex-1 py-3 text-[10px] font-black uppercase rounded-xl text-slate-400 transition-all">Finish</button>
            </div>

            <div id="partsSection" class="space-y-4">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="job_id" id="formJobIdParts">
                    <div class="relative">
                        <select name="part_id" required class="w-full bg-slate-50 border-0 p-5 rounded-[1.5rem] font-bold text-sm appearance-none outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <option value="" disabled selected>Select Part from Stock</option>
                            <?php foreach($inventory as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['part_name'] ?> (KES <?= number_format($p['selling_price']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-6 top-1/2 -translate-y-1/2 text-slate-300 pointer-events-none text-xs"></i>
                    </div>
                    <div class="flex gap-3">
                        <input type="number" name="quantity" value="1" min="1" placeholder="Qty" class="w-20 bg-slate-50 border-0 p-5 rounded-[1.5rem] font-bold text-sm text-center outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="submit" name="add_inventory_part" class="flex-1 bg-indigo-600 text-white px-8 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest shadow-lg active:scale-95 transition-transform">Confirm Part</button>
                    </div>
                </form>
            </div>

            <div id="laborSection" class="hidden space-y-4">
    <form method="POST" class="space-y-4">
        <input type="hidden" name="job_id" id="formJobIdLabor">
        
        <div class="space-y-2">
            <label class="text-[9px] font-black text-slate-400 uppercase ml-4">Labor Details</label>
            <input type="text" name="description" required 
                placeholder="Work Description (e.g. Engine Oil Flush)" 
                class="w-full bg-slate-50 border-0 p-5 rounded-[1.5rem] font-bold text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
        </div>

        <div class="flex gap-3">
            <div class="flex-1 space-y-2">
                <label class="text-[9px] font-black text-slate-400 uppercase ml-4">Unit Price (KES)</label>
                <input type="number" name="unit_price" required placeholder="0.00" 
                    class="w-full bg-slate-50 border-0 p-5 rounded-[1.5rem] font-bold text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
            </div>
            <div class="w-24 space-y-2">
                <label class="text-[9px] font-black text-slate-400 uppercase ml-4 text-center block">Qty</label>
                <input type="number" name="quantity" value="1" min="1" 
                    class="w-full bg-slate-50 border-0 p-5 rounded-[1.5rem] font-bold text-sm text-center outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
            </div>
        </div>

        <button type="submit" name="add_labor" 
            class="w-full bg-indigo-600 text-white p-5 rounded-[1.5rem] font-black text-[11px] uppercase tracking-[0.2em] shadow-lg shadow-indigo-100 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-plus-circle text-sm"></i>
            Log Labor Entry
        </button>
    </form>
</div>


            <div id="statusSection" class="hidden space-y-4">
                <div id="leadOnlyControls">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="job_id" id="formJobIdStatus">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase ml-4 mb-2 block">Current Odometer (KM)</label>
                                <input type="number" name="mileage" required placeholder="Enter Current Mileage" class="w-full bg-slate-50 border-0 p-5 rounded-[1.5rem] font-bold text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label class="text-[10px] font-black text-slate-400 uppercase ml-4 mb-2 block">Technical Summary</label>
                                <textarea name="mechanic_notes" required placeholder="Lead Mechanic: Describe final state..." class="w-full bg-slate-50 border-0 p-6 rounded-[2rem] font-bold text-sm h-32 focus:ring-2 focus:ring-emerald-500 outline-none transition-all"></textarea>
                            </div>
                        </div>

                        <button name="complete_job" class="w-full bg-emerald-600 text-white p-6 rounded-[2rem] font-black text-xs uppercase tracking-[0.2em] shadow-xl shadow-emerald-100 active:scale-95 transition-transform flex items-center justify-center gap-3">
                            <i class="fa-solid fa-paper-plane"></i> Submit for QC
                        </button>
                    </form>
                </div>
                
                <div id="nonLeadView" class="hidden py-10 px-6 bg-slate-50 rounded-[2.5rem] border border-dashed border-slate-200 text-center">
                    <i class="fa-solid fa-lock text-slate-300 text-2xl mb-3"></i>
                    <p class="text-xs font-bold text-slate-500 italic">Only the Lead Mechanic can perform final submission.</p>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex justify-between items-center px-2">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Live Job Log</p>
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] font-black text-slate-900">Total: KES</span>
                        <span id="logRunningTotal" class="text-[10px] font-black text-indigo-600">0</span>
                    </div>
                </div>
                <div id="workLogItems" class="space-y-2"></div>
            </div>

            <hr class="border-slate-100">

            <div class="space-y-2">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Initial Request</p>
                <div class="bg-slate-50 p-6 rounded-[2rem] border border-slate-100">
                    <p id="drawerInstructions" class="text-xs font-bold text-slate-500 italic leading-relaxed"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="archiveModal" class="fixed inset-0 z-[60] bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-[2.5rem] overflow-hidden shadow-2xl transition-all transform">
        <div class="p-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Technical Archive</h3>
            <button onclick="closeArchiveModal()" class="w-8 h-8 flex items-center justify-center bg-white rounded-full shadow-sm hover:bg-slate-50">
                <i class="fa-solid fa-xmark text-slate-400 text-xs"></i>
            </button>
        </div>
        <div id="archiveContent" class="p-8 max-h-[70vh] overflow-y-auto no-scrollbar">
            </div>
    </div>
</div>

<script>
/**
 * --- CONFIG & GLOBAL STATE ---
 */
// Set the current user ID from the PHP session for ownership checks
const CURRENT_USER_ID = String(<?= $_SESSION['user_id'] ?>);
let currentSearchQuery = "";
let currentDateRange = "all";
let jobIsLead = false; 

/**
 * --- FILTER ENGINE ---
 */
function applyCombinedFilters() {
    const cards = document.querySelectorAll('.history-card');
    const today = new Date().toISOString().split('T')[0];
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);

    cards.forEach(card => {
        const searchText = (card.getAttribute('data-search') || "").toLowerCase();
        const cardDate = card.getAttribute('data-date') || "";
        const cardDateObj = new Date(cardDate);

        const matchesSearch = searchText.includes(currentSearchQuery);

        let matchesDate = false;
        if (currentDateRange === 'all') {
            matchesDate = true;
        } else if (currentDateRange === 'today') {
            matchesDate = (cardDate === today);
        } else if (currentDateRange === 'week') {
            matchesDate = (cardDateObj >= weekAgo);
        }

        if (matchesSearch && matchesDate) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });
}

function filterHistory() {
    currentSearchQuery = document.getElementById('historySearch').value.toLowerCase();
    applyCombinedFilters();
}

function filterByDate(range, btn) {
    currentDateRange = range;
    document.querySelectorAll('.date-filter-btn').forEach(b => {
        b.className = "date-filter-btn bg-white text-slate-400 border border-slate-100 px-4 py-2 rounded-lg text-[8px] font-black uppercase whitespace-nowrap";
    });
    btn.className = "date-filter-btn bg-indigo-600 text-white px-4 py-2 rounded-lg text-[8px] font-black uppercase whitespace-nowrap shadow-md shadow-indigo-100";
    applyCombinedFilters();
}

/**
 * --- TAB & MODAL CONTROLS ---
 */
function switchMainTab(tab) {
    const activeSec = document.getElementById('activeSection');
    const historySec = document.getElementById('historySection');
    const btnActive = document.getElementById('btnActive');
    const btnHistory = document.getElementById('btnHistory');

    if (tab === 'active') {
        activeSec.classList.remove('hidden');
        historySec.classList.add('hidden');
        btnActive.className = "flex-1 py-3 text-[10px] font-black uppercase rounded-xl bg-white text-slate-900 shadow-sm transition-all";
        btnHistory.className = "flex-1 py-3 text-[10px] font-black uppercase rounded-xl text-slate-400 transition-all";
    } else {
        activeSec.classList.add('hidden');
        historySec.classList.remove('hidden');
        btnHistory.className = "flex-1 py-3 text-[10px] font-black uppercase rounded-xl bg-white text-slate-900 shadow-sm transition-all";
        btnActive.className = "flex-1 py-3 text-[10px] font-black uppercase rounded-xl text-slate-400 transition-all";
    }
}

function viewTechnicalSummary(jobId) {
    const modal = document.getElementById('archiveModal');
    const content = document.getElementById('archiveContent');
    modal.classList.remove('hidden');
    content.innerHTML = `<div class="py-10 text-center"><i class="fa-solid fa-circle-notch animate-spin text-indigo-500 text-xl"></i></div>`;

    fetch(`get_job_details.php?job_id=${jobId}`)
        .then(res => res.json())
        .then(data => {
            const itemsHtml = data.items.map(item => `
                <div class="flex justify-between py-2.5 border-b border-slate-50 last:border-0">
                    <span class="text-[10px] font-bold text-slate-700 uppercase">${item.description}</span>
                    <span class="text-[9px] font-black text-slate-400 uppercase">Qty: ${item.qty}</span>
                </div>
            `).join('');

            content.innerHTML = `
                <div class="space-y-6">
                    <div>
                        <p class="text-[9px] font-black text-indigo-500 uppercase mb-2 tracking-widest text-center">Technician's Report</p>
                        <div class="bg-indigo-50/50 p-5 rounded-3xl border border-indigo-50">
                            <p class="text-xs italic font-bold text-slate-600 leading-relaxed text-center">
                                "${data.notes || 'No report filed for this job.'}"
                            </p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase mb-3 tracking-widest">Inventory & Labor Log</p>
                        <div class="bg-white border border-slate-100 rounded-3xl p-4">
                            ${itemsHtml || '<p class="text-center text-[9px] text-slate-400 py-4 uppercase font-black">No items logged</p>'}
                        </div>
                    </div>
                </div>`;
        })
        .catch(() => {
            content.innerHTML = `<p class="text-center text-xs font-bold text-red-400">Error loading report.</p>`;
        });
}

function closeArchiveModal() {
    document.getElementById('archiveModal').classList.add('hidden');
}

/**
 * --- JOB DRAWER LOGIC ---
 */
function openJob(job) {
    const drawer = document.getElementById('jobDrawer');
    const isLocked = (job.status === 'pending_release');
    jobIsLead = parseInt(job.is_lead) === 1; 
    const tabContainer = document.getElementById('drawerTabs');
    
    // 1. Role UI Handling
    const roleBadge = document.getElementById('roleBadge');
    const leadControls = document.getElementById('leadOnlyControls');
    const nonLeadView = document.getElementById('nonLeadView');

    if (jobIsLead) {
        roleBadge.innerText = "Lead Technician";
        roleBadge.className = "text-[8px] font-black px-2 py-0.5 rounded-md uppercase bg-indigo-500 text-white";
        leadControls.classList.remove('hidden');
        nonLeadView.classList.add('hidden');
    } else {
        roleBadge.innerText = "Team Member";
        roleBadge.className = "text-[8px] font-black px-2 py-0.5 rounded-md uppercase bg-slate-700 text-slate-300";
        leadControls.classList.add('hidden');
        nonLeadView.classList.remove('hidden');
    }

    // 2. Load Metadata
    fetchTeam(job.id);
    document.getElementById('drawerTitle').innerText = `${job.make} ${job.model}`;
    document.getElementById('drawerInstructions').innerText = `"${job.requested_service}"`;
    document.getElementById('formJobIdParts').value = job.id;
    document.getElementById('formJobIdLabor').value = job.id;
    document.getElementById('formJobIdStatus').value = job.id;

    // 3. Status/Lock Logic
    if (isLocked) {
        tabContainer.classList.add('hidden');
        ['partsSection', 'laborSection', 'statusSection'].forEach(s => document.getElementById(s).classList.add('hidden'));
        document.getElementById('drawerPlate').innerHTML = `<span class="text-indigo-900 font-bold">${job.plate_no}</span> <span class="text-[8px] bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded ml-2 font-black uppercase">Awaiting QC</span>`;
    } else {
        tabContainer.classList.remove('hidden');
        switchTab('parts');
        document.getElementById('drawerPlate').innerHTML = `<span class="text-indigo-900 font-bold">${job.plate_no}</span>`;
    }

    drawer.classList.remove('hidden');
    setTimeout(() => drawer.classList.add('open'), 10);
    
    refreshWorkLog(job.id, isLocked);
}

function closeDrawer() {
    const drawer = document.getElementById('jobDrawer');
    drawer.classList.remove('open');
    setTimeout(() => drawer.classList.add('hidden'), 300);
}

function switchTab(tab) {
    const sections = ['partsSection', 'laborSection', 'statusSection'];
    const tabs = ['tabParts', 'tabLabor', 'tabStatus'];
    
    sections.forEach(s => document.getElementById(s).classList.add('hidden'));
    tabs.forEach(t => {
        document.getElementById(t).classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
        document.getElementById(t).classList.add('text-slate-400');
    });

    document.getElementById(tab + 'Section').classList.remove('hidden');
    const activeBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    activeBtn.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
    activeBtn.classList.remove('text-slate-400');
}

/**
 * --- TEAM & LOG FETCHING ---
 */
function fetchTeam(jobId) {
    const listContainer = document.getElementById('mechanicList');
    listContainer.innerHTML = '';

    fetch(`get_job_team.php?job_id=${jobId}`)
        .then(res => res.json())
        .then(mechanics => {
            mechanics.forEach(tech => {
                const img = document.createElement('img');
                img.src = tech.avatar ? `../${tech.avatar}` : '../assets/default-avatar.png';
                img.className = "inline-block h-8 w-8 rounded-full ring-2 ring-slate-900 object-cover";
                img.title = `${tech.name} (${parseInt(tech.is_lead) ? 'Lead' : 'Member'})`;
                listContainer.appendChild(img);
            });
        })
        .catch(err => console.error("Error fetching team:", err));
}

async function refreshWorkLog(jobId, isLocked = false) {
    const container = document.getElementById('workLogItems');
    const totalDisplay = document.getElementById('logRunningTotal');
    container.innerHTML = '<div class="py-4 text-center"><i class="fa-solid fa-circle-notch fa-spin text-slate-200"></i></div>';
    
    try {
        const response = await fetch(`get_job_log.php?job_id=${jobId}`);
        const data = await response.json();
        container.innerHTML = '';
        let runningTotal = 0;

        if (!data || data.length === 0) {
            container.innerHTML = '<p class="text-[10px] font-bold text-slate-300 uppercase text-center py-6">No work logged yet</p>';
            totalDisplay.innerText = '0';
            return;
        }

        data.forEach(item => {
            const lineTotal = parseFloat(item.quantity) * parseFloat(item.unit_price);
            runningTotal += lineTotal;
            const type = item.type ? item.type.toLowerCase().trim() : '';
            const iconClass = (type === 'part') ? 'fa-box text-amber-500' : 'fa-wrench text-indigo-500';
            const techName = item.added_by_name || 'System';
            
            // STRICT ID COMPARISON
            const isOwner = String(item.added_by_id) === String(CURRENT_USER_ID);
            
            let deleteBtn = '';
            if (!isLocked && (isOwner || jobIsLead)) {
                deleteBtn = `
                    <button onclick="deleteLogItem(${item.id}, ${jobId})" 
                        class="w-8 h-8 rounded-lg bg-red-50 text-red-400 flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-sm">
                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                    </button>`;
            }

            const itemHTML = `
                <div class="flex justify-between items-center bg-slate-50 p-4 rounded-2xl border border-slate-100 transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-sm border border-slate-50">
                            <i class="fa-solid ${iconClass} fa-fw text-xs"></i>
                        </div>
                        <div>
                            <p class="text-xs font-black text-slate-900">${item.description}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tight">
                                    ${parseFloat(item.quantity)} Unit(s) @ ${parseFloat(item.unit_price).toLocaleString()}
                                </p>
                                <span class="text-[8px] text-slate-300">•</span>
                                <p class="text-[9px] font-black text-indigo-400 uppercase tracking-tighter flex items-center gap-1">
                                    <i class="fa-solid fa-user text-[7px]"></i> ${techName}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <p class="text-xs font-black text-slate-900">KES ${lineTotal.toLocaleString()}</p>
                        </div>
                        ${deleteBtn}
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', itemHTML);
        });
        totalDisplay.innerText = runningTotal.toLocaleString();
    } catch (error) {
        container.innerHTML = '<p class="text-xs text-red-400 font-bold text-center">Error loading logs</p>';
    }
}

async function deleteLogItem(itemId, jobId) {
    if (!confirm("Are you sure you want to remove this item? Stock will be returned automatically.")) return;
    try {
        const response = await fetch(`delete_job_item.php?id=${itemId}`);
        const result = await response.json();
        if (result.success) {
            refreshWorkLog(jobId, false);
        } else {
            alert("Error: " + result.message);
        }
    } catch (error) {
        alert("Failed to delete item.");
    }
}

// Close modal/drawer on background click
window.onclick = function(event) {
    const archiveModal = document.getElementById('archiveModal');
    if (event.target == archiveModal) closeArchiveModal();
}

/**
 * --- AUTO-REOPEN & TAB PERSISTENCE ---
 */
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const lastJobId = urlParams.get('last_job');
    const successMsg = urlParams.get('success');
    
    if (lastJobId) {
        // Regex search to find the card with the specific job ID in its onclick string
        const targetCard = Array.from(document.querySelectorAll('[onclick*="openJob"]')).find(card => {
            return card.getAttribute('onclick').includes(`'id': ${lastJobId}`) || 
                   card.getAttribute('onclick').includes(`'id':${lastJobId}`) ||
                   card.getAttribute('onclick').includes(`"id": ${lastJobId}`);
        });
        
        if (targetCard) {
            targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            setTimeout(() => {
                targetCard.click(); 

                // Ensure tab selection happens after a short delay so switchTab finds elements
                setTimeout(() => {
                    if (successMsg === 'part_added') {
                        switchTab('parts');
                    } else if (successMsg === 'labor_logged') {
                        switchTab('labor');
                    } else if (successMsg === 'submitted') {
                        switchTab('status');
                    }
                }, 50);
            }, 400); 
        }
    }
});
</script>



</body>
</html>
