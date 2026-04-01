<?php
// 1. Error Reporting & Sessions
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$job_id = $_GET['job_id'] ?? null;
if (!$job_id) { die("Critical Error: Job ID missing."); }

try {
    // UPDATED SELECT: Including M-Pesa audit fields and Team Subquery
    $stmt = $pdo->prepare("
        SELECT j.*, v.plate_no, v.make, v.model, v.year,
               sr.requested_service, sr.created_at as booked_at,
               u_cust.name as customer_name, u_cust.phone as customer_phone,
               -- Workforce Team Fix: Get all mechanics assigned to this job
               (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') 
                FROM job_assignments ja 
                JOIN users u ON ja.mechanic_id = u.id 
                WHERE ja.job_id = j.id) as technician_names,
               j.payment_ref, j.payment_acc_name, j.payment_phone
        FROM jobs j
        LEFT JOIN service_requests sr ON j.request_id = sr.id
        LEFT JOIN vehicles v ON sr.vehicle_id = v.id
        LEFT JOIN users u_cust ON v.user_id = u_cust.id
        WHERE j.id = ?
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) { die("Record Not Found: Job ID #$job_id does not exist."); }

    // Fetch Line Items with Technician Audit (who added what)
    $stmt_items = $pdo->prepare("
        SELECT ji.*, u.name as added_by_name 
        FROM job_items ji 
        LEFT JOIN users u ON ji.added_by_id = u.id 
        WHERE ji.job_id = ?
    ");
    $stmt_items->execute([$job_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Audit Failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Audit - <?= htmlspecialchars($job['plate_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen pb-10">

    <nav class="bg-white border-b border-slate-200 px-6 py-4 mb-8 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="job_history.php" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                </a>
                <div>
                    <h1 class="text-sm font-black uppercase italic tracking-tighter text-slate-900">Internal Audit</h1>
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Job #<?= $job['id'] ?></p>
                </div>
            </div>
            <div class="flex gap-2">
                <span class="text-[10px] font-black px-4 py-1.5 bg-indigo-50 text-indigo-600 border border-indigo-100 rounded-full uppercase italic">
                    <i class="fa-solid fa-wallet mr-1"></i> <?= htmlspecialchars($job['payment_method'] ?? 'N/A') ?>
                </span>
                <span class="text-[10px] font-black px-4 py-1.5 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-full uppercase italic">Archived</span>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="space-y-6">
                <div class="bg-slate-900 text-white p-6 rounded-[2.5rem] shadow-xl relative overflow-hidden">
                    <i class="fa-solid fa-car absolute -right-4 -bottom-4 text-7xl opacity-10"></i>
                    <p class="text-[9px] font-black text-slate-500 uppercase mb-1 tracking-widest">Vehicle Identity</p>
                    <h2 class="text-3xl font-black italic mb-4 tracking-tighter"><?= $job['plate_no'] ?></h2>
                    <div class="space-y-2 border-t border-white/10 pt-4">
                        <p class="text-xs font-bold text-slate-200 uppercase"><?= $job['make'] ?> <?= $job['model'] ?> (<?= $job['year'] ?>)</p>
                       <p class="text-[10px] text-slate-500 font-black italic">Ref: <?= htmlspecialchars($job['make']) ?> Archive</p>
                    </div>
                </div>

                <div class="bg-emerald-600 p-6 rounded-[2.5rem] shadow-xl relative overflow-hidden border-b-4 border-emerald-700">
                    <i class="fa-solid fa-receipt absolute -right-2 -bottom-2 text-6xl text-white opacity-10"></i>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-2 h-2 bg-emerald-300 rounded-full"></div>
                        <h4 class="text-[10px] font-black text-emerald-200 uppercase tracking-widest">Payment Audit</h4>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <p class="text-[8px] font-black text-emerald-300 uppercase opacity-70">Reference / Receipt No</p>
                            <p class="text-xl font-black text-white italic tracking-widest uppercase"><?= htmlspecialchars($job['payment_ref'] ?: 'N/A') ?></p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 border-t border-white/10 pt-4">
                            <div>
                                <p class="text-[8px] font-black text-emerald-300 uppercase opacity-70">Account Name</p>
                                <p class="text-[11px] font-bold text-white uppercase truncate"><?= htmlspecialchars($job['payment_acc_name'] ?: 'N/A') ?></p>
                            </div>
                            <?php if(!empty($job['payment_phone'])): ?>
                            <div>
                                <p class="text-[8px] font-black text-emerald-300 uppercase opacity-70">Sender Phone</p>
                                <p class="text-[11px] font-bold text-white"><?= htmlspecialchars($job['payment_phone']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-[2.5rem] border border-slate-200 shadow-sm">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase mb-4 tracking-widest">Client Contact</h4>
                    <p class="text-sm font-black text-slate-900"><?= $job['customer_name'] ?></p>
                    <p class="text-xs text-slate-500 mb-6"><?= $job['customer_phone'] ?></p>
                    <a href="tel:<?= $job['customer_phone'] ?>" class="block text-center py-4 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 transition-all shadow-lg shadow-indigo-100">
                        <i class="fa-solid fa-phone mr-2"></i> Call Customer
                    </a>
                </div>
            </div>

            <div class="md:col-span-2 space-y-6">
                <div class="bg-white border-l-8 border-indigo-500 p-8 rounded-[2.5rem] shadow-sm relative">
                    <div class="absolute top-6 right-8 opacity-10">
                        <i class="fa-solid fa-quote-right text-4xl"></i>
                    </div>
                    <h4 class="text-[10px] font-black text-slate-400 uppercase mb-3 tracking-widest">Internal Tech Report</h4>
                    <p class="text-sm italic font-bold text-slate-700 leading-relaxed pr-10">
                        "<?= nl2br(htmlspecialchars($job['mechanic_notes'] ?: 'No internal notes provided.')) ?>"
                    </p>
                    <div class="mt-6 pt-4 border-t border-slate-50 flex items-center gap-2">
                        <div class="w-6 h-6 bg-slate-100 rounded-full flex items-center justify-center text-[10px] text-slate-400">
                            <i class="fa-solid fa-users-gear"></i>
                        </div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-tighter">Workforce: <span class="text-indigo-600 uppercase"><?= $job['technician_names'] ?: 'System' ?></span></p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-[2.5rem] overflow-hidden shadow-sm">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Billing Audit Trail</h4>
                        <span class="text-[10px] font-black text-slate-900 italic tracking-tighter">TOTAL: KES <?= number_format($job['final_total'], 2) ?></span>
                    </div>
                    <div class="p-8 space-y-4">
                        <?php foreach($items as $item): ?>
                        <div class="flex justify-between items-center py-2 border-b border-slate-50 last:border-0">
                            <div class="flex gap-3 items-center">
                                <div class="w-8 h-8 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 border border-slate-100">
                                    <i class="fa-solid <?= $item['type'] == 'part' ? 'fa-box-open' : 'fa-screwdriver-wrench' ?> text-[10px]"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-black text-slate-800 uppercase leading-none mb-1"><?= $item['description'] ?></p>
                                    <p class="text-[9px] text-slate-400 font-bold uppercase">
                                        <?= (float)$item['quantity'] ?> units • KES <?= number_format($item['unit_price']) ?>
                                        <span class="ml-2 text-indigo-400 italic">By: <?= $item['added_by_name'] ?: 'Garage' ?></span>
                                    </p>
                                </div>
                            </div>
                            <p class="font-black text-slate-900 italic text-sm">KES <?= number_format($item['quantity'] * $item['unit_price'], 2) ?></p>
                        </div>
                        <?php endforeach; ?>

                        <div class="pt-6 mt-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center">
                                <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Tax Status</p>
                                <p class="text-[10px] font-black text-slate-700"><?= $job['tax_applied'] ? '16% VAT' : 'EXEMPT' ?></p>
                            </div>
                            <div class="bg-rose-50 p-4 rounded-2xl border border-rose-100 text-center">
                                <p class="text-[8px] font-black text-rose-400 uppercase mb-1">Deductions</p>
                                <p class="text-[10px] font-black text-rose-600">- KES <?= number_format($job['discount'], 2) ?></p>
                            </div>
                            <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
                                <p class="text-[8px] font-black text-indigo-400 uppercase mb-1">Final Payment</p>
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid <?= (strtoupper($job['payment_method'] ?? '') == 'M-PESA') ? 'fa-mobile-screen-button' : 'fa-money-bill-1' ?> text-[10px] text-indigo-600"></i>
                                    <div class="overflow-hidden">
                                        <p class="text-[10px] font-black text-indigo-900 uppercase tracking-tight truncate"><?= htmlspecialchars($job['payment_method'] ?? 'N/A') ?></p>
                                        <?php if (!empty($job['payment_ref'])): ?>
                                            <p class="text-[7px] font-black text-indigo-400 uppercase truncate"><?= $job['payment_ref'] ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-[2.5rem] border border-slate-200 shadow-sm relative overflow-hidden">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase mb-8 tracking-[0.2em] text-center italic">Service Velocity Audit</h4>
                    <div class="flex justify-between items-center px-4 relative">
                        <div class="absolute top-5 left-0 w-full h-[2px] bg-slate-100 -z-10"></div>
                        <div class="text-center bg-white px-2">
                            <div class="w-10 h-10 rounded-full bg-slate-100 border-2 border-white flex items-center justify-center mx-auto mb-2 text-slate-400">
                                <i class="fa-solid fa-calendar-check text-xs"></i>
                            </div>
                            <p class="text-[9px] font-black uppercase text-slate-900">Booked</p>
                            <p class="text-[8px] font-bold text-slate-400"><?= date('d M, H:i', strtotime($job['booked_at'])) ?></p>
                        </div>
                        <div class="text-center bg-white px-2">
                            <div class="w-10 h-10 rounded-full bg-indigo-50 border-2 border-white flex items-center justify-center mx-auto mb-2 text-indigo-600">
                                <i class="fa-solid fa-wrench text-xs"></i>
                            </div>
                            <p class="text-[9px] font-black uppercase text-slate-900">Started</p>
                            <p class="text-[8px] font-bold text-slate-400"><?= date('d M, H:i', strtotime($job['created_at'])) ?></p>
                        </div>
                        <div class="text-center bg-white px-2">
                            <div class="w-10 h-10 rounded-full bg-emerald-500 border-2 border-white flex items-center justify-center mx-auto mb-2 text-white shadow-xl">
                                <i class="fa-solid fa-flag-checkered text-xs"></i>
                            </div>
                            <p class="text-[9px] font-black uppercase text-slate-900">Released</p>
                            <p class="text-[8px] font-bold text-slate-400"><?= date('d M, H:i', strtotime($job['completed_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

</body>
</html>
