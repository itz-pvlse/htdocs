<?php
/**
 * GarageOS - Finance & Billing Terminal
 * Integrated Features: Search, Date Filtering, Status Badges, and Cashflow Analytics
 */
ini_set('display_errors', 0); 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

// Auth Guard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php"); exit();
}
$garage_id = $_SESSION['user_id'];

// --- 1. FILTER INPUTS ---
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// --- 2. ACTION HANDLER (Finalize Transaction) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_paid'])) {
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);
    $pay_method = $_POST['payment_method'] ?? 'Cash';
    $pay_ref = $_POST['payment_ref'] ?? '';
    $pay_acc = $_POST['payment_acc_name'] ?? '';
    
    $update = $pdo->prepare("
        UPDATE jobs SET status = 'completed', payment_status = 'Paid', 
        payment_method = ?, payment_ref = ?, payment_acc_name = ?, completed_at = NOW() 
        WHERE id = ?
    ");
    $update->execute([$pay_method, $pay_ref, $pay_acc, $job_id]);
    $_SESSION['msg'] = "Ref #$job_id Settled.";
    header("Location: finances.php"); exit();
}

try {
    // --- 3. REVENUE TELEMETRY ---
    $revenue_stmt = $pdo->prepare("SELECT SUM(j.final_total) FROM jobs j JOIN service_requests sr ON j.request_id = sr.id WHERE sr.garage_id = ? AND j.status = 'completed'");
    $revenue_stmt->execute([$garage_id]);
    $revenue = $revenue_stmt->fetchColumn() ?: 0;

    $mpesa_stmt = $pdo->prepare("SELECT SUM(j.final_total) FROM jobs j JOIN service_requests sr ON j.request_id = sr.id WHERE sr.garage_id = ? AND j.status = 'completed' AND j.payment_method = 'M-Pesa'");
    $mpesa_stmt->execute([$garage_id]);
    $mpesa_sum = $mpesa_stmt->fetchColumn() ?: 0;

    $cash_stmt = $pdo->prepare("SELECT SUM(j.final_total) FROM jobs j JOIN service_requests sr ON j.request_id = sr.id WHERE sr.garage_id = ? AND j.status = 'completed' AND j.payment_method = 'Cash'");
    $cash_stmt->execute([$garage_id]);
    $cash_sum = $cash_stmt->fetchColumn() ?: 0;

    // --- 4. DYNAMIC QUERY BUILDING (Master Ledger) ---
    $query = "
        SELECT j.id, v.plate_no, j.final_total, j.status, j.payment_status, 
               j.payment_method, j.payment_ref, j.payment_acc_name, j.created_at
        FROM jobs j
        JOIN service_requests sr ON j.request_id = sr.id
        JOIN vehicles v ON sr.vehicle_id = v.id
        WHERE sr.garage_id = ?
    ";
    $params = [$garage_id];

    if ($search) {
        $query .= " AND (v.plate_no LIKE ? OR j.payment_ref LIKE ? OR j.payment_acc_name LIKE ?)";
        $search_param = "%$search%";
        array_push($params, $search_param, $search_param, $search_param);
    }
    if ($status_filter) {
        $query .= " AND j.status = ?";
        $params[] = $status_filter;
    }
    if ($date_from && $date_to) {
        $query .= " AND DATE(j.created_at) BETWEEN ? AND ?";
        array_push($params, $date_from, $date_to);
    }

    $query .= " ORDER BY FIELD(j.status, 'pending_payment', 'pending_release', 'ongoing', 'completed') ASC, j.created_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Workshop Load Counters
    $pending_count = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status IN ('ongoing', 'pending_payment', 'pending_release')")->fetchColumn() ?: 0;

} catch (PDOException $e) { die("Connectivity Offline."); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Terminal | GarageOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; color: #0F172A; }
        .finance-card { background: white; border-radius: 2.5rem; border: 1px solid rgba(226, 232, 240, 0.8); transition: all 0.3s ease; }
        .active-filter { background: #6366f1 !important; color: white !important; border-color: #6366f1 !important; }
        input[type="date"]::-webkit-calendar-picker-indicator { opacity: 0.4; }
    </style>
</head>
<body class="p-4 md:p-10">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-12">
            <a href="../garage.php" class="w-14 h-14 bg-white border border-slate-200 rounded-2xl flex items-center justify-center text-slate-900 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="text-center">
                <h1 class="text-2xl font-black italic uppercase tracking-tighter">Finance Ledger</h1>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Revenue & Performance Analytics</p>
            </div>
            <button onclick="window.print()" class="w-14 h-14 bg-white border border-slate-200 rounded-2xl flex items-center justify-center text-slate-400 hover:text-slate-900 shadow-sm">
                <i class="fa-solid fa-print"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="finance-card p-8 bg-slate-900 text-white border-none shadow-2xl">
                <p class="text-[10px] font-black uppercase text-indigo-400 mb-2">Total Revenue</p>
                <h2 class="text-4xl font-black italic tracking-tighter">KES <?= number_format($revenue, 2) ?></h2>
                <div class="mt-4 flex items-center gap-2 text-[9px] text-emerald-400 font-bold uppercase tracking-widest">
                    <i class="fa-solid fa-circle-check"></i> Finalized & Settled
                </div>
            </div>
            
            <div class="finance-card p-8 relative overflow-hidden">
                <div class="absolute -right-2 -top-2 text-slate-50 opacity-50">
                    <i class="fa-solid fa-car-side text-7xl"></i>
                </div>
                <p class="text-[10px] font-black uppercase text-slate-400 mb-2">Workshop Load</p>
                <h2 class="text-4xl font-black italic text-slate-900 tracking-tighter"><?= $pending_count ?> Units</h2>
                <p class="mt-4 text-[9px] font-bold text-slate-400 uppercase tracking-widest italic">Active Projects in Bay</p>
            </div>

            <div class="finance-card p-8">
                <p class="text-[10px] font-black uppercase text-slate-400 mb-4">Cashflow Split</p>
                <div class="space-y-4">
                    <?php 
                        $total_paid = $mpesa_sum + $cash_sum;
                        $mp_p = $total_paid > 0 ? ($mpesa_sum / $total_paid) * 100 : 0;
                        $ch_p = $total_paid > 0 ? ($cash_sum / $total_paid) * 100 : 0;
                    ?>
                    <div>
                        <div class="flex justify-between text-[10px] font-black uppercase mb-1">
                            <span class="text-emerald-600">M-Pesa (<?= round($mp_p) ?>%)</span>
                            <span class="text-slate-900">KES <?= number_format($mpesa_sum, 0) ?></span>
                        </div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-emerald-500 h-full" style="width: <?= $mp_p ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-[10px] font-black uppercase mb-1">
                            <span class="text-indigo-600">Cash (<?= round($ch_p) ?>%)</span>
                            <span class="text-slate-900">KES <?= number_format($cash_sum, 0) ?></span>
                        </div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-indigo-500 h-full" style="width: <?= $ch_p ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2.5rem] border border-slate-100 mb-8 shadow-sm">
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-1">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Plate, Ref or Name..." 
                           class="w-full px-5 py-3 bg-slate-50 border-none rounded-2xl text-xs font-bold outline-none ring-1 ring-slate-100 focus:ring-indigo-500 transition-all">
                </div>
                <div>
                    <input type="date" name="date_from" value="<?= $date_from ?>" 
                           class="w-full px-5 py-3 bg-slate-50 border-none rounded-2xl text-xs font-bold outline-none ring-1 ring-slate-100 focus:ring-indigo-500 transition-all text-slate-500">
                </div>
                <div>
                    <input type="date" name="date_to" value="<?= $date_to ?>" 
                           class="w-full px-5 py-3 bg-slate-50 border-none rounded-2xl text-xs font-bold outline-none ring-1 ring-slate-100 focus:ring-indigo-500 transition-all text-slate-500">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white rounded-2xl font-black text-[10px] uppercase hover:bg-slate-900 transition-all shadow-md shadow-indigo-100">Apply Filters</button>
                    <a href="finances.php" class="w-12 h-12 flex items-center justify-center bg-slate-100 text-slate-400 rounded-2xl hover:text-rose-500 transition-all"><i class="fa-solid fa-xmark"></i></a>
                </div>
            </form>

            <div class="flex gap-3 mt-6 overflow-x-auto pb-2">
                <a href="finances.php" class="px-5 py-2 rounded-full border border-slate-100 text-[9px] font-black uppercase whitespace-nowrap <?= !$status_filter ? 'active-filter' : 'bg-white text-slate-400' ?>">All Transactions</a>
                <a href="finances.php?status=completed" class="px-5 py-2 rounded-full border border-slate-100 text-[9px] font-black uppercase whitespace-nowrap <?= $status_filter == 'completed' ? 'active-filter' : 'bg-white text-slate-400' ?>">Settled</a>
                <a href="finances.php?status=pending_payment" class="px-5 py-2 rounded-full border border-slate-100 text-[9px] font-black uppercase whitespace-nowrap <?= $status_filter == 'pending_payment' ? 'active-filter' : 'bg-white text-slate-400' ?>">Awaiting Pay</a>
                <a href="finances.php?status=ongoing" class="px-5 py-2 rounded-full border border-slate-100 text-[9px] font-black uppercase whitespace-nowrap <?= $status_filter == 'ongoing' ? 'active-filter' : 'bg-white text-slate-400' ?>">In Bay</a>
            </div>
        </div>

        <div class="finance-card p-8 shadow-sm">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xs font-black uppercase tracking-widest italic text-slate-400">Transaction Ledger</h3>
                <?php if(isset($_SESSION['msg'])): ?>
                    <span class="text-[10px] font-black uppercase text-emerald-500 bg-emerald-50 px-3 py-1 rounded-lg">
                        <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-black uppercase text-slate-400 border-b border-slate-100">
                            <th class="pb-4">Reference</th>
                            <th class="pb-4">Audit Details</th>
                            <th class="pb-4">Status</th>
                            <th class="pb-4">Amount</th>
                            <th class="pb-4 text-right">Settlement</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php if(empty($transactions)): ?>
                            <tr><td colspan="5" class="py-20 text-center text-[10px] font-black uppercase text-slate-300 italic">No matching records found</td></tr>
                        <?php else: foreach($transactions as $t): ?>
                        <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                            <td class="py-5">
                                <div class="flex items-center gap-3">
                                    <span class="bg-slate-100 text-slate-900 font-mono text-[10px] px-2 py-1 rounded border border-slate-200">#<?= $t['id'] ?></span>
                                    <span class="font-black text-slate-900 text-xs tracking-widest uppercase"><?= htmlspecialchars($t['plate_no']) ?></span>
                                </div>
                                <p class="text-[8px] text-slate-300 font-bold mt-1"><?= date('d M, Y H:i', strtotime($t['created_at'])) ?></p>
                            </td>
                            <td class="py-5">
                                <p class="font-black text-slate-900 text-[10px] uppercase"><?= $t['payment_method'] ?: 'Pending' ?></p>
                                <p class="text-[9px] text-slate-400 font-bold uppercase truncate max-w-[120px]"><?= $t['payment_ref'] ?: 'N/A' ?></p>
                            </td>
                            <td class="py-5">
                                <span class="text-[9px] font-black uppercase px-3 py-1 rounded-full border <?= $t['status'] === 'completed' ? 'text-emerald-500 bg-emerald-50 border-emerald-100' : 'text-amber-500 bg-amber-50 border-amber-100' ?>">
                                    <?= $t['status'] === 'completed' ? 'Paid' : ($t['status'] === 'pending_payment' ? 'Invoiced' : 'Active') ?>
                                </span>
                            </td>
                            <td class="py-5 font-black text-slate-900 italic">KES <?= number_format($t['final_total'], 2) ?></td>
                            <td class="py-5 text-right">
                                <?php if($t['status'] !== 'completed'): ?>
                                    <a href="dealer.php#job-<?= $t['id'] ?>" class="inline-block bg-indigo-600 text-white text-[9px] font-black uppercase px-4 py-2 rounded-xl hover:bg-slate-900 transition-all shadow-md group">
                                        Release <i class="fa-solid fa-arrow-up-right-from-square ml-1 text-[7px] group-hover:translate-x-0.5 transition-transform"></i>
                                    </a>
                                <?php else: ?>
                                    <div class="flex flex-col items-end">
                                        <span class="text-[8px] text-emerald-600 font-black uppercase"><?= htmlspecialchars($t['payment_acc_name'] ?: 'Settled') ?></span>
                                        <i class="fa-solid fa-circle-check text-emerald-500 text-xs mt-1"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
