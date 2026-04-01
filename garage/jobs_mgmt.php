
<?php
// 1. AJAX HANDLER (Must be at the very top)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lock_totals') {
    require_once '../config/db.php';
    $job_id = intval($_POST['job_id']);
    try {
        // Update status to pending_payment in the database
        $stmt = $pdo->prepare("UPDATE jobs SET status = 'pending_payment' WHERE id = ?");
        $stmt->execute([$job_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
?>

<?php
// 1. SYSTEM SETTINGS & ERROR REPORTING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

// Auth Check: Owner Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php");
    exit();
}

$garage_id = $_SESSION['user_id'];
$current_page = 'jobs_mgmt.php'; 

// --- NEW AJAX ACTION: LOCK TOTALS (Fixes your button error) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lock_totals') {
    $job_id = intval($_POST['job_id']);
    try {
        // Update status to pending_payment so it survives refresh
        $stmt = $pdo->prepare("UPDATE jobs SET status = 'pending_payment' WHERE id = ? AND garage_id = ?");
        $stmt->execute([$job_id, $garage_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit; // Stop execution here for AJAX
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// --- ACTION: FINALIZE, PAYMENT & RELEASE ---
if (isset($_POST['finalize_invoice'])) {
    $job_id = $_POST['job_id'];
    $discount = floatval($_POST['discount_amount'] ?? 0);
    $apply_tax = isset($_POST['apply_tax']) ? 1 : 0;
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    
    // Capture user inputs
    $manual_ref = strtoupper(trim($_POST['payment_ref'] ?? ''));
    $payment_acc_name = trim($_POST['payment_acc_name'] ?? '');
    $payment_phone = trim($_POST['payment_phone'] ?? '');

    // Generate Background System ID (GRS-YYYYMMDD-ID)
    $system_ref = "GRS-" . date('Ymd') . "-" . $job_id;

    // Logic: Combine Manual Ref with System ID for dual-traceability
    if ($payment_method === 'Cash') {
        $payment_ref = !empty($manual_ref) ? $manual_ref . " | " . $system_ref : $system_ref;
        if (empty($payment_acc_name)) { $payment_acc_name = "Manager Received"; }
    } else {
        // For M-Pesa, the Manual Ref is the primary Transaction Code
        $payment_ref = $manual_ref ?: $system_ref;
    }

    // Security: Check for M-Pesa Code Duplication to prevent fraud/errors
    if ($payment_method === 'M-Pesa' && !empty($manual_ref)) {
        $check = $pdo->prepare("SELECT id FROM jobs WHERE payment_ref LIKE ? AND id != ?");
        $check->execute(["%$manual_ref%", $job_id]);
        if ($check->fetch()) {
            header("Location: dealer.php?error=duplicate_ref");
            exit();
        }
    }

    $items_input = $_POST['items'] ?? [];

    try {
        $pdo->beginTransaction();

        // Update individual item prices if they were audited/changed
        foreach ($items_input as $item_id => $data) {
            $upd = $pdo->prepare("UPDATE job_items SET unit_price = ? WHERE id = ? AND job_id = ?");
            $upd->execute([floatval($data['price']), $item_id, $job_id]);
        }

        // Calculate Final Totals
        $sum_stmt = $pdo->prepare("SELECT SUM(quantity * unit_price) FROM job_items WHERE job_id = ?");
        $sum_stmt->execute([$job_id]);
        $subtotal = (float)$sum_stmt->fetchColumn();
        
        $taxable_amount = $subtotal - $discount;
        $tax_val = $apply_tax ? ($taxable_amount * 0.16) : 0;
        $final_total = max(0, $taxable_amount + $tax_val); // Prevent negative totals

        // Final Database Update: Mark as PAID and COMPLETED
        $stmt = $pdo->prepare("
            UPDATE jobs 
            SET status = 'completed', 
                payment_method = ?,
                payment_ref = ?,
                payment_acc_name = ?,
                payment_phone = ?,
                payment_status = 'Paid',
                discount = ?, 
                tax_applied = ?, 
                final_total = ?, 
                completed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([
            $payment_method, $payment_ref, $payment_acc_name, $payment_phone, 
            $discount, $apply_tax, $final_total, $job_id
        ]);

        // Close the linked Service Request
        $req_id_stmt = $pdo->prepare("SELECT request_id FROM jobs WHERE id = ?");
        $req_id_stmt->execute([$job_id]);
        $request_id = $req_id_stmt->fetchColumn();
        
        if ($request_id) {
            $pdo->prepare("UPDATE service_requests SET status = 'completed' WHERE id = ?")->execute([$request_id]);
        }

        $pdo->commit();
        // Redirect to a receipt/invoice generation page
        header("Location: finances.php?msg=Job+Closed+Reference+".$payment_ref);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Transaction Failed: " . $e->getMessage());
    }
}

// --- ACTION: UPDATE TEAM ---
if (isset($_POST['update_team'])) {
    $job_id = (int)$_POST['job_id'];
    $new_techs = $_POST['mechanic_ids'] ?? [];
    $lead_id = (int)($_POST['lead_mechanic_id'] ?? 0);

    if ($lead_id > 0 && !in_array($lead_id, $new_techs)) {
        $new_techs[] = $lead_id;
    }

    if (empty($new_techs) || $lead_id === 0) {
        header("Location: jobs_mgmt.php?error=lead_required");
        exit();
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM job_assignments WHERE job_id = ?")->execute([$job_id]);
        $stmt = $pdo->prepare("INSERT INTO job_assignments (job_id, mechanic_id, is_lead) VALUES (?, ?, ?)");
        foreach ($new_techs as $m_id) {
            $is_lead = ($m_id == $lead_id) ? 1 : 0;
            $stmt->execute([$job_id, $m_id, $is_lead]);
        }
        $pdo->commit();
        header("Location: jobs_mgmt.php?msg=Workforce+Updated");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error updating workforce: " . $e->getMessage());
    }
}

// --- DATA FETCHING ---
$search_plate = $_GET['search_plate'] ?? '';
$filter_mech = $_GET['filter_mech'] ?? '';

$mech_list_stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'mechanic' AND garage_id = ? ORDER BY name ASC");
$mech_list_stmt->execute([$garage_id]);
$mechanics = $mech_list_stmt->fetchAll(PDO::FETCH_ASSOC);

// FETCH LIVE JOBS (Including 'pending_payment')
$query = "
    SELECT 
        j.*, 
        v.plate_no, v.make, v.model,
        sr.requested_service, sr.created_at as request_date,
        u_cust.name as customer_name,
        (SELECT GROUP_CONCAT(u.name ORDER BY ja.is_lead DESC SEPARATOR ', ') 
         FROM job_assignments ja 
         JOIN users u ON ja.mechanic_id = u.id 
         WHERE ja.job_id = j.id) as team_names
    FROM jobs j
    JOIN service_requests sr ON j.request_id = sr.id
    JOIN vehicles v ON sr.vehicle_id = v.id
    JOIN users u_cust ON v.user_id = u_cust.id
    WHERE sr.garage_id = ? AND j.status IN ('ongoing', 'pending_release', 'pending_payment')
";

$params = [$garage_id];
if (!empty($search_plate)) { $query .= " AND v.plate_no LIKE ?"; $params[] = "%$search_plate%"; }
if (!empty($filter_mech)) { $query .= " AND j.id IN (SELECT job_id FROM job_assignments WHERE mechanic_id = ?)"; $params[] = $filter_mech; }

$query .= " ORDER BY FIELD(j.status, 'pending_payment', 'pending_release', 'ongoing'), j.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Terminal | Floor Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; color: #1E293B; }
        .job-card.active { border-color: #6366F1; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); }
        .details-pane { display: none; }
        .job-card.active .details-pane { display: block; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 10px; }
        
        /* Pulse for active tracking */
        .pulse-dot { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }
    </style>
</head>
<body class="pb-12">

<nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200 px-6 py-4">
    <div class="max-w-6xl mx-auto flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="../garage.php" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all">
                <i class="fa-solid fa-arrow-left text-xs"></i>
            </a>
            <div>
                <h1 class="text-sm font-black uppercase tracking-[0.1em] text-slate-900">Floor Monitor</h1>
                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">Live Workshop Audit</p>
            </div>
        </div>

        <div class="hidden md:flex bg-slate-100 p-1 rounded-2xl border border-slate-200">
            <a href="jobs_mgmt.php" class="px-6 py-2 bg-white shadow-sm rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-900">
                Active Floor
            </a>
            <a href="job_history.php" class="px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-slate-600 transition-all">
                Archives
            </a>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 rounded-full border border-slate-200">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full pulse-dot"></span>
                <span class="text-[10px] font-black uppercase text-slate-600 tracking-tighter">System Live</span>
            </div>
        </div>
    </div>
</nav>

<?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate_payment'): ?>
<div class="max-w-6xl mx-auto px-4 mb-6">
    <div class="bg-rose-50 border-l-4 border-rose-600 p-4 rounded-2xl flex items-center gap-4 shadow-sm">
        <div class="w-12 h-12 bg-rose-600 text-white rounded-full flex items-center justify-center shrink-0">
            <i class="fa-solid fa-shield-halved text-xl"></i>
        </div>
        <div class="flex-1">
            <h4 class="text-sm font-black text-rose-900 uppercase italic">Audit Block: Duplicate Reference</h4>
            <p class="text-[11px] text-rose-700 font-bold uppercase tracking-tight">
                M-Pesa Code <span class="bg-rose-200 px-1 rounded"><?= htmlspecialchars($_GET['ref']) ?></span> has already been used. Please verify the customer's phone and try again.
            </p>
        </div>
        <a href="jobs_mgmt.php" class="text-rose-400 hover:text-rose-900 transition-colors">
            <i class="fa-solid fa-circle-xmark text-lg"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<main class="max-w-5xl mx-auto p-6">
    <div class="mb-6 flex items-center justify-between px-2">
        <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-slate-400">
            <span class="text-indigo-600">Garage</span>
            <i class="fa-solid fa-chevron-right text-[8px]"></i>
            <span class="text-slate-900">Workshop Floor</span>
        </div>
        <div class="text-[10px] font-bold text-slate-400 uppercase italic">
            Click any card to expand billing & payment
        </div>
    </div>

    <section class="mb-8 bg-white border border-slate-200 p-4 rounded-[2rem] shadow-sm flex flex-col md:flex-row items-center gap-4">
        <a href="job_history.php" class="md:hidden w-full flex items-center justify-center gap-3 p-4 bg-indigo-50 text-indigo-600 rounded-2xl border border-indigo-100 font-black text-[10px] uppercase tracking-widest">
            <i class="fa-solid fa-clock-rotate-left"></i>
            View Service History
        </a>

        <form method="GET" class="flex-1 flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px] relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search_plate" value="<?= htmlspecialchars($search_plate ?? '') ?>" 
                       placeholder="Search Plate Number..." 
                       class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-100 rounded-2xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>

            <div class="relative group">
                <select name="filter_mech" onchange="this.form.submit()" 
                        class="appearance-none pl-4 pr-10 py-3 bg-slate-50 border border-slate-100 rounded-2xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer group-hover:bg-slate-100 transition-colors">
                    <option value="">Filter by Technician</option>
                    <?php foreach($mechanics as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ($filter_mech ?? '') == $m['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] pointer-events-none"></i>
            </div>

            <button type="submit" class="bg-slate-900 text-white px-8 py-3 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-md active:scale-95">
                Apply Filters
            </button>
        </form>
    </section>

    <?php if(empty($jobs)): ?>
    <div class="text-center py-20 bg-white border-2 border-dashed border-slate-200 rounded-[3rem]">
        <i class="fa-solid fa-car-rear text-slate-200 text-6xl mb-4"></i>
        <p class="text-slate-400 font-black uppercase tracking-[0.2em] text-xs">No Active Units Found</p>
    </div>
    <?php endif; ?>

   
   
   
   
<div class="space-y-6">
    <?php foreach ($jobs as $job): 
        $isPending = ($job['status'] === 'pending_release');
        $isAwaitingPayment = ($job['status'] === 'pending_payment');
        
        $stmt_items = $pdo->prepare("
            SELECT ji.*, u.name as technician_name, u.avatar as technician_avatar 
            FROM job_items ji
            LEFT JOIN users u ON ji.added_by_id = u.id
            WHERE ji.job_id = ?
        ");
        $stmt_items->execute([$job['id']]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        $total_val = 0;
        foreach($items as $calc_item) {
            $total_val += ($calc_item['quantity'] * $calc_item['unit_price']);
        }
    ?>
        
    <div class="job-card bg-white border rounded-[2.5rem] overflow-hidden transition-all duration-300 group <?= ($isAwaitingPayment || $isPending) ? 'border-emerald-400 ring-2 ring-emerald-50 shadow-xl' : 'border-slate-200 hover:border-indigo-300' ?>" id="job-<?= $job['id'] ?>">
        
        <div onclick="toggleJob(this.parentElement)" class="p-6 cursor-pointer flex flex-wrap items-center justify-between gap-6 hover:bg-slate-50/80 transition-colors relative">
            <div class="flex items-center gap-6">
                <div class="<?= ($isAwaitingPayment || $isPending) ? 'bg-emerald-600 border-emerald-700' : 'bg-slate-900 border-slate-800 shadow-lg' ?> border-2 px-5 py-3 rounded-2xl text-center">
                    <p class="text-[7px] font-black uppercase <?= ($isAwaitingPayment || $isPending) ? 'text-emerald-100' : 'text-slate-400' ?> mb-0.5">Registration</p>
                    <p class="text-lg font-black tracking-widest uppercase italic text-white"><?= $job['plate_no'] ?></p>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="font-extrabold text-slate-900 text-xl leading-tight uppercase"><?= $job['make'] ?> <?= $job['model'] ?></h3>
                        <i class="fa-solid fa-circle-chevron-down text-slate-300 group-[.active]:rotate-180 transition-transform"></i>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span id="status-badge-<?= $job['id'] ?>" class="text-[9px] font-black uppercase px-2 py-0.5 rounded border <?= $isAwaitingPayment ? 'bg-amber-50 text-amber-600 border-amber-100' : ($isPending ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-indigo-50 text-indigo-600 border-indigo-100') ?>">
                            <?= $isAwaitingPayment ? 'Awaiting Payment' : ($isPending ? 'Ready for Release' : 'Live Repair') ?>
                        </span>
                        
                        <button type="button" onclick="event.stopPropagation(); openTeamModal(<?= $job['id'] ?>)" 
                                class="text-[9px] font-black uppercase px-2 py-0.5 rounded border bg-white text-slate-600 border-slate-200 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                            <i class="fa-solid fa-users-gear mr-1"></i> Team: <?= htmlspecialchars($job['team_names'] ?? 'Set Workforce') ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="text-right border-l border-slate-100 pl-8">
                <p class="text-[9px] font-black text-slate-400 uppercase mb-1 italic">Current Total</p>
                <p class="text-2xl font-black text-slate-900 italic">KES <span class="header-total-<?= $job['id'] ?>"><?= number_format($total_val, 2) ?></span></p>
            </div>
        </div>

        <form method="POST" action="jobs_mgmt.php" class="details-pane border-t-2 border-slate-100 bg-slate-50">
            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
            
            <div id="audit-section-<?= $job['id'] ?>" class="<?= $isAwaitingPayment ? 'hidden' : '' ?> p-8 bg-white">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6">Step 1: Line Item Audit</h4>

                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-2 mb-6 custom-scrollbar">
                    <?php foreach ($items as $item): ?>
                    <div class="item-row bg-slate-50 border border-slate-100 rounded-2xl p-4" data-qty="<?= $item['quantity'] ?>">
                        <div class="flex justify-between items-center">
                            <p class="text-[11px] font-black uppercase text-slate-800"><?= $item['description'] ?></p>
                            <div class="flex items-center gap-2">
                                <span class="text-[8px] font-black text-slate-300">KES</span>
                                <input type="number" name="items[<?= $item['id'] ?>][price]" value="<?= $item['unit_price'] ?>" 
                                       oninput="updateLiveTotals(<?= $job['id'] ?>)"
                                       class="unit-price-input-<?= $job['id'] ?> w-28 bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-black text-right outline-none">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="moveToPayment(<?= $job['id'] ?>)" 
                        class="w-full py-5 bg-slate-900 text-white rounded-2xl font-black text-[10px] uppercase tracking-[0.2em] hover:bg-black transition-all">
                    Lock Totals & Proceed to Payment <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>

            <div id="payment-section-<?= $job['id'] ?>" class="<?= $isAwaitingPayment ? '' : 'hidden' ?> grid grid-cols-1 lg:grid-cols-12">
                <div class="lg:col-span-5 p-8 bg-white border-r border-slate-100 space-y-6">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Step 2: Payment Details</h4>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="M-Pesa" checked 
                                   onclick="updatePaymentUI(<?= $job['id'] ?>, 'M-Pesa')" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-100 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 text-center transition-all">
                                <p class="text-[9px] font-black uppercase">M-Pesa</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method" value="Cash" 
                                   onclick="updatePaymentUI(<?= $job['id'] ?>, 'Cash')" class="peer sr-only">
                            <div class="p-4 rounded-2xl border-2 border-slate-100 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 text-center transition-all">
                                <p class="text-[9px] font-black uppercase">Cash</p>
                            </div>
                        </label>
                    </div>
                    
                    <div class="space-y-2">
                        <input type="text" name="payment_ref" id="ref-input-<?= $job['id'] ?>" 
                               placeholder="M-PESA CODE / RECEIPT NO" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-[10px] font-black uppercase outline-none focus:border-slate-300 transition-all">
                        
                        <input type="text" name="payment_acc_name" id="acc-name-input-<?= $job['id'] ?>" 
                               placeholder="SENDER NAME / RECEIVED BY" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-[10px] font-black uppercase outline-none focus:border-slate-300 transition-all">
                        
                        <div id="mpesa-only-<?= $job['id'] ?>">
                            <input type="text" name="payment_phone" placeholder="MPESA PHONE NUMBER" 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-[10px] font-black outline-none focus:border-slate-300 transition-all">
                        </div>
                    </div>

                    <div class="bg-rose-50 p-4 rounded-2xl flex items-center justify-between">
                        <span class="text-[10px] font-black text-rose-600 uppercase">Discount (KES)</span>
                        <input type="number" name="discount_amount" value="0" oninput="updateLiveTotals(<?= $job['id'] ?>)"
                               class="discount-input-<?= $job['id'] ?> w-32 bg-white border border-rose-200 rounded-xl px-4 py-2 text-xs font-black text-right outline-none">
                    </div>

                  <div class="flex items-center justify-between p-4 bg-slate-900 rounded-2xl text-white">
    <div class="flex flex-col">
        <span class="text-[10px] font-black uppercase text-indigo-400">Apply 16% VAT</span>
        <span id="vat-amount-display-<?= $job['id'] ?>" class="text-[9px] font-bold text-slate-400 mt-0.5 tracking-wider italic">
            + KES 0.00
        </span>
    </div>
    
    <label class="relative inline-flex items-center cursor-pointer">
        <input type="checkbox" name="apply_tax" 
               onchange="updateLiveTotals(<?= $job['id'] ?>)" 
               class="tax-toggle-<?= $job['id'] ?> sr-only peer">
        <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer 
                    peer-checked:after:translate-x-full peer-checked:after:border-white 
                    after:content-[''] after:absolute after:top-[2px] after:left-[2px] 
                    after:bg-white after:border-gray-300 after:border after:rounded-full 
                    after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500">
        </div>
    </label>
</div>

    </label>
</div>

                </div>

                <div class="lg:col-span-7 p-8 bg-slate-50 flex flex-col justify-center items-center text-center">
                    <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Final Amount Due</p>
                    <p class="text-5xl font-black text-slate-900 mb-8 italic tracking-tighter">KES <span class="grand-total-display-<?= $job['id'] ?>"><?= number_format($total_val, 2) ?></span></p>
                    
                    <div class="flex gap-4 w-full">
                        <button type="button" onclick="backToAudit(<?= $job['id'] ?>)" class="px-6 py-5 border-2 border-slate-200 rounded-2xl font-black text-[10px] uppercase text-slate-400 hover:bg-white transition-all">
                            Edit Prices
                        </button>
                        <button name="finalize_invoice" class="flex-1 py-5 bg-emerald-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl hover:bg-emerald-700 transition-all">
                            Confirm & Release
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<div id="workforceModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-[3rem] p-10 shadow-2xl animate-in zoom-in duration-200">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h2 class="text-2xl font-black uppercase italic tracking-tighter text-slate-900 leading-none">Modify Team</h2>
                <p class="text-[9px] font-bold text-slate-400 uppercase mt-2 tracking-widest">Assign Roles Precisely</p>
            </div>
            <button onclick="closeTeamModal()" class="bg-slate-50 w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-900 hover:text-white transition-all"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <form method="POST" action="jobs_mgmt.php"> 
            <input type="hidden" name="job_id" id="modal_job_id">
            
            <div class="mb-8">
                <div class="flex justify-between items-end mb-4 px-2">
                    <label class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Active Personnel</label>
                    <span class="text-[8px] font-bold text-indigo-500 uppercase">Mark one as Lead</span>
                </div>

                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                    <?php foreach($mechanics as $m): ?>
                    <div class="relative flex items-center gap-3 p-4 bg-slate-50 border border-slate-100 rounded-2xl hover:border-indigo-200 transition-all workforce-row">
                        <input type="checkbox" name="mechanic_ids[]" value="<?= $m['id'] ?>" 
                               class="mech-checkbox w-5 h-5 rounded-lg accent-indigo-600 border-slate-300 cursor-pointer">
                        
                        <div class="flex-1">
                            <span class="text-xs font-black uppercase text-slate-700 block"><?= htmlspecialchars($m['name']) ?></span>
                        </div>

                        <label class="flex items-center gap-1 cursor-pointer">
                            <input type="radio" name="lead_mechanic_id" value="<?= $m['id'] ?>" class="lead-radio sr-only peer" required>
                            <div class="px-3 py-1 rounded-full text-[8px] font-black uppercase border border-slate-200 text-slate-400 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 transition-all">
                                Lead
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mt-4 p-4 bg-indigo-50 rounded-2xl border border-indigo-100 mb-6">
                <p class="text-[9px] font-bold text-indigo-700 uppercase leading-relaxed flex gap-2">
                    <i class="fa-solid fa-lightbulb mt-0.5"></i>
                    <span>Assigning a "Lead" automatically adds them to the selected team.</span>
                </p>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeTeamModal()" class="flex-1 bg-slate-100 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest text-slate-400">Cancel</button>
                <button type="submit" name="update_team" class="flex-1 bg-slate-900 text-white py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg active:scale-95 transition-all">Update Team</button>
            </div>
        </form>
    </div>
</div>

<script>
// UI Management
function toggleJob(card) {
    const isActive = card.classList.contains('active');
    document.querySelectorAll('.job-card').forEach(c => c.classList.remove('active'));
    if (!isActive) card.classList.add('active');
}

// Modal Management
function openTeamModal(jobId) {
    document.getElementById('modal_job_id').value = jobId;
    document.getElementById('workforceModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeTeamModal() {
    document.getElementById('workforceModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Logic: Lead selection checks the row checkbox
document.querySelectorAll('.lead-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        if(this.checked) {
            const parentRow = this.closest('.workforce-row');
            parentRow.querySelector('.mech-checkbox').checked = true;
        }
    });
});

// Stop propagation on forms so clicking inputs doesn't close cards/modals
document.querySelectorAll('.details-pane, #workforceModal > div').forEach(el => {
    el.addEventListener('click', (e) => e.stopPropagation());
});

// AJAX: Lock Totals
function moveToPayment(jobId) {
    const formData = new FormData();
    formData.append('action', 'lock_totals'); 
    formData.append('job_id', jobId);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById(`audit-section-${jobId}`).classList.add('hidden');
            document.getElementById(`payment-section-${jobId}`).classList.remove('hidden');
            const badge = document.getElementById(`status-badge-${jobId}`);
            badge.innerText = 'Awaiting Payment';
            badge.className = 'text-[9px] font-black uppercase px-2 py-0.5 rounded border bg-amber-50 text-amber-600 border-amber-100';
            updateLiveTotals(jobId);
        } else {
            alert('Error updating status.');
        }
    });
}

// Payment UI Logic
function updatePaymentUI(jobId, method) {
    const mpesaSection = document.getElementById(`mpesa-only-${jobId}`);
    const refInput = document.getElementById(`ref-input-${jobId}`);
    const accNameInput = document.getElementById(`acc-name-input-${jobId}`);

    if (method === 'M-Pesa') {
        mpesaSection.style.display = 'block';
        refInput.placeholder = "M-PESA TRANSACTION CODE";
        accNameInput.placeholder = "SENDER NAME (AS PER MPESA)";
    } else {
        mpesaSection.style.display = 'none';
        refInput.placeholder = "CASH RECEIPT NO (OPTIONAL)";
        accNameInput.placeholder = "RECEIVED BY (STAFF NAME)";
    }
}

function updateLiveTotals(jobId) {
    let subtotal = 0;
    const priceInputs = document.querySelectorAll(`.unit-price-input-${jobId}`);
    
    priceInputs.forEach(input => {
        const qty = parseFloat(input.closest('.item-row').dataset.qty) || 0;
        subtotal += (qty * (parseFloat(input.value) || 0));
    });

    const discount = parseFloat(document.querySelector(`.discount-input-${jobId}`).value) || 0;
    const taxToggle = document.querySelector(`.tax-toggle-${jobId}`);
    const vatDisplay = document.getElementById(`vat-amount-display-${jobId}`);
    
    // 1. Calculate the Taxable Amount (After Discount)
    let taxable = subtotal - discount;
    
    // 2. Calculate VAT (16% of Taxable Amount)
    let taxAmount = taxToggle.checked ? (taxable * 0.16) : 0;
    
    // 3. Final Total
    let final = taxable + taxAmount;
    
    // Format for display
    const formattedTax = taxAmount.toLocaleString('en-KE', { minimumFractionDigits: 2 });
    const formattedTotal = Math.max(0, final).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    
    // Update the displays
    vatDisplay.innerText = `+ KES ${formattedTax}`;
    document.querySelector(`.grand-total-display-${jobId}`).innerText = formattedTotal;
    document.querySelector(`.header-total-${jobId}`).innerText = formattedTotal;

    // Optional: Highlight the tax color when active
    if (taxToggle.checked) {
        vatDisplay.classList.remove('text-slate-400');
        vatDisplay.classList.add('text-emerald-400');
    } else {
        vatDisplay.classList.remove('text-emerald-400');
        vatDisplay.classList.add('text-slate-400');
    }
}

</script>

<style>
    .job-card .details-pane { display: none; }
    .job-card.active .details-pane { display: block; animation: slideDown 0.4s ease-out; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

</body>
</html>
