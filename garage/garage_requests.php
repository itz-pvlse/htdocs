<?php
// 1. SYSTEM SETTINGS & ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

// --- 2. AUTH & CONTEXT ---
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'garage') {
    $garage_id = (int)$_SESSION['user_id'];
} elseif (isset($_GET['garage_id']) && is_numeric($_GET['garage_id'])) {
    $garage_id = (int)$_GET['garage_id'];
} else {
    die("ACCESS_DENIED: No Garage Context found. Please log in.");
}

$_SESSION['garage_id'] = $garage_id;

// --- 3. LOGIC: START JOB (Updated for MULTIPLE Technicians) ---
if (isset($_POST['start_job'])) {
    $req_id = (int)$_POST['req_id'];
    $mechanic_ids = $_POST['mechanic_ids'] ?? []; // Array of IDs from multi-select
    
    if (empty($mechanic_ids)) {
        header("Location: garage_requests.php?msg=Error:+Select+at+least+one+tech");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 3a. Create the Job Entry
        $stmt = $pdo->prepare("INSERT INTO jobs (request_id, status, created_at) VALUES (?, 'ongoing', NOW())");
        $stmt->execute([$req_id]);
        $new_job_id = $pdo->lastInsertId();

        // 3b. Assign Multiple Technicians to the Junction Table
        $stmt_assign = $pdo->prepare("INSERT INTO job_assignments (job_id, mechanic_id, is_lead) VALUES (?, ?, ?)");
        foreach ($mechanic_ids as $index => $m_id) {
            // First mechanic selected is marked as Lead (1), subsequent ones as assistants (0)
            $is_lead = ($index === 0) ? 1 : 0;
            $stmt_assign->execute([$new_job_id, (int)$m_id, $is_lead]);
        }

        // 3c. Update the Request Status to Approved
        $stmt = $pdo->prepare("UPDATE service_requests SET status = 'approved' WHERE id = ? AND garage_id = ?");
        $stmt->execute([$req_id, $garage_id]);

        $pdo->commit();
        header("Location: jobs_mgmt.php?msg=Job+Started+with+Team");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("TRANSITION_ERROR: " . $e->getMessage());
    }
}

// --- 4. LOGIC: STATUS TRANSITIONS (Generic) ---
if (isset($_GET['update_status'], $_GET['req_id'])) {
    $new_status = $_GET['update_status']; 
    $req_id = (int)$_GET['req_id'];
    
    $stmt = $pdo->prepare("UPDATE service_requests SET status = ? WHERE id = ? AND garage_id = ?");
    $stmt->execute([$new_status, $req_id, $garage_id]);
    
    header("Location: garage_requests.php?msg=Status+Updated");
    exit;
}

// --- 5. LOGIC: REJECT REQUEST ---
if (isset($_GET['reject_id'])) {
    $req_id = (int)$_GET['reject_id'];
    $stmt = $pdo->prepare("UPDATE service_requests SET status = 'rejected' WHERE id = ? AND garage_id = ?");
    $stmt->execute([$req_id, $garage_id]);
    
    header("Location: garage_requests.php?msg=Rejected");
    exit;
}

// --- 6. FETCH MECHANICS (For Assignment Dropdown) ---
$stmt_mech = $pdo->prepare("SELECT id, name FROM users WHERE role = 'mechanic' AND garage_id = ?");
$stmt_mech->execute([$garage_id]);
$mechanics = $stmt_mech->fetchAll(PDO::FETCH_ASSOC);

// --- 7. FETCH QUEUE DATA ---
$stmt = $pdo->prepare("
    SELECT 
        r.*, 
        u.name as customer_name,
        u.email as customer_email,
        v.make as car_make,
        v.model as car_model,
        v.plate_no,
        v.year as car_year
    FROM service_requests r 
    LEFT JOIN users u ON r.user_id = u.id 
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    WHERE r.garage_id = ? 
    ORDER BY r.request_date DESC
");
$stmt->execute([$garage_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$garage_name = $_SESSION['garage_name'] ?? "GarageOS Terminal";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Queue | <?= htmlspecialchars($garage_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; color: #1E293B; }
        .status-pending { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
        .status-approved { background: #DBEAFE; color: #1E40AF; border: 1px solid #BFDBFE; }
        .status-completed { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .status-rejected { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        
        /* Tom Select Styling to match Dashboard Theme */
        .ts-control { border-radius: 0.75rem !important; padding: 0.5rem !important; font-size: 10px !important; font-weight: 800 !important; text-transform: uppercase !important; border: 1px solid #e2e8f0 !important; }
        .ts-dropdown { border-radius: 1rem !important; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1) !important; }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="flex min-h-screen">

    <main class="flex-1 flex flex-col">
        <header class="p-6 lg:p-10">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-8">
                <div class="flex items-center gap-5">
                    <a href="../garage.php" class="group w-12 h-12 bg-white border border-slate-200 rounded-2xl flex items-center justify-center hover:bg-slate-900 hover:border-slate-900 transition-all duration-300 shadow-sm">
                        <i class="fa-solid fa-arrow-left text-slate-400 group-hover:text-white transition-colors"></i>
                    </a>
                    <div>
                        <h2 class="text-4xl font-black italic tracking-tighter uppercase leading-none text-slate-900">Service Queue</h2>
                        <p class="text-[9px] font-bold text-indigo-500 uppercase tracking-[0.3em] mt-1 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-pulse"></span>
                            Multi-Tech Mode Active
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                    <div class="relative flex-1 sm:w-64">
                        <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="reqSearch" placeholder="Search..." class="w-full pl-10 pr-4 py-3 bg-white border border-slate-200 rounded-2xl text-[11px] font-bold outline-none focus:border-indigo-500 transition-all">
                    </div>
                    <select id="statusFilter" onchange="filterRequests()" class="bg-white border border-slate-200 rounded-2xl px-4 py-3 text-[9px] font-black uppercase tracking-widest outline-none cursor-pointer">
                        <option value="all">All Jobs</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Active</option>
                        <option value="completed">Finished</option>
                    </select>
                </div>
            </div>

            <div class="space-y-3" id="requestContainer">
                <?php if ($requests): foreach ($requests as $req): ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col md:flex-row gap-4 items-center hover:border-indigo-400 transition-all request-card group" 
                     data-status="<?= $req['status'] ?>" 
                     data-search="<?= strtolower(($req['customer_name'] ?? '').' '.($req['car_make'] ?? '').' '.($req['car_model'] ?? '').' '.($req['plate_no'] ?? '')) ?>">
                    
                    <div class="w-full md:w-28 h-12 bg-slate-900 rounded-xl flex items-center justify-center text-white px-3 shrink-0">
                        <span class="text-[10px] font-black tracking-widest"><?= htmlspecialchars($req['plate_no'] ?? 'NO-PLATE') ?></span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <h3 class="font-black uppercase text-sm text-slate-900 truncate"><?= htmlspecialchars($req['customer_name'] ?? 'Guest') ?></h3>
                            <span class="px-2 py-0.5 text-[7px] font-black uppercase rounded-md shadow-sm <?= 'status-'.$req['status'] ?>">
                                <?= $req['status'] ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-3 text-[10px] font-bold">
                            <span class="text-indigo-600"><i class="fa-solid fa-car mr-1"></i> <?= htmlspecialchars(($req['car_make'] ?? '').' '.($req['car_model'] ?? '')) ?></span>
                            <span class="text-slate-400"><i class="fa-solid fa-gauge mr-1"></i> <?= number_format($req['mileage'] ?? 0) ?> KM</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <?php if ($req['status'] == 'pending'): ?>
                            <form method="POST" class="flex items-center gap-2 w-full md:w-72 lg:w-96">
                                <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                <div class="flex-1">
                                    <select name="mechanic_ids[]" multiple placeholder="Assign Team..." class="multi-tech-select" required>
                                        <?php foreach ($mechanics as $m): ?>
                                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="start_job" class="bg-indigo-600 text-white px-4 py-2.5 rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-slate-900 transition-all flex items-center gap-2">
                                    <span>Start</span> <i class="fa-solid fa-bolt text-[8px]"></i>
                                </button>
                            </form>
                        <?php elseif ($req['status'] == 'approved'): ?>
                            <a href="jobs_mgmt.php" class="flex-1 md:flex-none bg-indigo-100 text-indigo-600 px-6 py-2.5 rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all text-center">
                               View Floor
                            </a>
                        <?php endif; ?>
                        
                        <div class="flex items-center gap-2">
                            <button onclick='showDetails(<?= json_encode($req, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="w-10 h-10 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all">
                                <i class="fa-solid fa-expand text-xs"></i>
                            </button>

                            <?php if ($req['status'] == 'pending'): ?>
                            <a href="?reject_id=<?= $req['id'] ?>" onclick="return confirm('Reject?')" class="w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="py-20 text-center bg-white border border-dashed border-slate-200 rounded-3xl">
                    <p class="text-slate-300 font-black uppercase text-xs tracking-widest">Queue Empty</p>
                </div>
                <?php endif; ?>
            </div>
        </header>
    </main>

    <div id="detailModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-6 bg-slate-900/80 backdrop-blur-sm">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-10 relative shadow-2xl">
            <button onclick="closeModal()" class="absolute top-8 right-8 text-slate-300 hover:text-rose-500 transition-all">
                <i class="fa-solid fa-circle-xmark text-2xl"></i>
            </button>
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center text-indigo-600"><i class="fa-solid fa-file-invoice"></i></div>
                <h2 id="m_title" class="text-xl font-black italic uppercase tracking-tighter text-slate-900">Job Detail</h2>
            </div>
            <div class="space-y-4 mb-8">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl">
                        <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Customer</p>
                        <p id="m_customer" class="text-xs font-black text-slate-800"></p>
                    </div>
                    <div class="bg-slate-50 p-4 rounded-xl">
                        <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Vehicle</p>
                        <p id="m_vehicle" class="text-xs font-black text-indigo-600 uppercase"></p>
                    </div>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl">
                    <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Requested Work</p>
                    <p id="m_service" class="text-xs font-bold text-slate-600 italic"></p>
                </div>
            </div>
            <div class="flex gap-2">
                <button onclick="window.print()" class="flex-1 bg-slate-100 text-slate-600 py-4 rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-slate-200 flex items-center justify-center gap-2"><i class="fa-solid fa-print"></i> Print</button>
                <button onclick="closeModal()" class="flex-1 bg-slate-900 text-white py-4 rounded-xl font-black text-[9px] uppercase tracking-widest">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Initialize Multi-Select for Team Assignment
        document.querySelectorAll('.multi-tech-select').forEach((el) => {
            new TomSelect(el, {
                plugins: ['remove_button'],
                maxItems: 4,
                persist: false,
                create: false,
                onItemAdd: function() {
                    this.setTextboxValue('');
                    this.refreshOptions();
                }
            });
        });

        function filterRequests() {
            const searchTerm = document.getElementById('reqSearch').value.toLowerCase();
            const statusTerm = document.getElementById('statusFilter').value;
            const cards = document.querySelectorAll('.request-card');
            cards.forEach(card => {
                const text = card.getAttribute('data-search');
                const status = card.getAttribute('data-status');
                card.style.display = (text.includes(searchTerm) && (statusTerm === 'all' || status === statusTerm)) ? 'flex' : 'none';
            });
        }
        document.getElementById('reqSearch').addEventListener('input', filterRequests);

        function showDetails(data) {
            document.getElementById('m_title').innerText = 'Request #' + data.id;
            document.getElementById('m_customer').innerText = data.customer_name || 'Guest User';
            document.getElementById('m_vehicle').innerText = (data.car_make || '') + ' ' + (data.car_model || '');
            document.getElementById('m_service').innerText = data.requested_service || 'N/A';
            document.getElementById('detailModal').classList.remove('hidden');
        }
        function closeModal() { document.getElementById('detailModal').classList.add('hidden'); }
    </script>
</body>
</html>
