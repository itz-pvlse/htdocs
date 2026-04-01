<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';
include 'includes/modals.php';
// --- 1. AUTHENTICATION & REDIRECT LOGIC ---
if (!isset($_SESSION['user_id'])) {
    // Capture the current page URL + any query parameters (like garage_id)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $current_page = $_SERVER['REQUEST_URI']; 
    $full_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $current_page;

    // Redirect to login with the return target
    header("Location: login.php?redirect=" . urlencode($full_url));
    exit;
}

$user_id = $_SESSION['user_id'];
$garage_id = $_GET['garage_id'] ?? 0;

// --- 2. FETCH GARAGE INFO (Optional Branding) ---
$garage_name = "All Garages";
if ($garage_id > 0) {
    $g_stmt = $pdo->prepare("SELECT name FROM garages WHERE id = ?");
    $g_stmt->execute([$garage_id]);
    $g_data = $g_stmt->fetch();
    if ($g_data) $garage_name = $g_data['name'];
}

// --- 3. FETCH AGGREGATE STATS ---
$stats_query = "
    SELECT 
        SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END) as total_paid,
        SUM(CASE WHEN payment_status = 'unpaid' THEN grand_total ELSE 0 END) as total_pending,
        COUNT(i.id) as total_count
    FROM invoices i
    JOIN jobs j ON i.job_id = j.id
    JOIN service_requests sr ON j.request_id = sr.id
    WHERE sr.user_id = ?" . ($garage_id > 0 ? " AND sr.garage_id = ?" : "");

$stmt_stats = $pdo->prepare($stats_query);
$params = [$user_id];
if ($garage_id > 0) $params[] = $garage_id;
$stmt_stats->execute($params);
$stats = $stmt_stats->fetch();

// --- 4. MAIN INVOICE QUERY ---
$query = "SELECT i.*, 
                 sr.requested_service, 
                 v.plate_no, v.make, v.model,
                 g.name as garage_name,
                 m.name as mechanic_name,
                 m.profile_photo as mechanic_avatar
          FROM invoices i 
          JOIN jobs j ON i.job_id = j.id 
          JOIN service_requests sr ON j.request_id = sr.id 
          JOIN garages g ON sr.garage_id = g.id 
          JOIN users m ON j.mechanic_id = m.id
          LEFT JOIN vehicles v ON sr.vehicle_id = v.id 
          WHERE sr.user_id = ?" . ($garage_id > 0 ? " AND sr.garage_id = ?" : "") . "
          ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// --- 5. EAGER LOAD LINE ITEMS ---
$items_by_job = [];
if (!empty($invoices)) {
    $job_ids = array_column($invoices, 'job_id');
    $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
    $items_query = "SELECT * FROM job_items WHERE job_id IN ($placeholders)";
    $items_stmt = $pdo->prepare($items_query);
    $items_stmt->execute($job_ids);
    $all_items = $items_stmt->fetchAll();
    foreach ($all_items as $item) { $items_by_job[$item['job_id']][] = $item; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices | <?= htmlspecialchars($garage_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;900&display=swap');
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; }
        .invoice-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .breakdown-wrapper { display: grid; grid-template-rows: 0fr; transition: grid-template-rows 0.4s ease-in-out; }
        .breakdown-wrapper.active { grid-template-rows: 1fr; }
        .breakdown-inner { overflow: hidden; }
    </style>
</head>
<body class="antialiased text-slate-900 pb-20">

    <div class="max-w-2xl mx-auto px-6 pt-8 md:pt-16">
        
        <div class="flex items-center justify-between mb-8">
            <button onclick="history.back()" class="group flex items-center justify-center w-12 h-12 md:w-16 md:h-16 bg-white border border-slate-200 rounded-2xl shadow-sm hover:bg-slate-50 transition-all">
                <i class="fas fa-chevron-left text-slate-600 group-hover:-translate-x-1 transition-transform md:text-2xl"></i>
            </button>
            
            <div class="text-right">
                <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Records for</p>
                <h2 class="text-xl md:text-2xl font-black text-slate-900 uppercase italic leading-none"><?= htmlspecialchars($garage_name) ?></h2>
            </div>
        </div>

        <div class="bg-slate-900 rounded-[2.5rem] p-8 md:p-12 mb-10 shadow-2xl relative overflow-hidden text-white">
            <div class="relative z-10">
                <span class="text-[10px] md:text-xs font-bold uppercase tracking-[0.3em] text-indigo-400 block mb-6">Spending Overview</span>
                <div class="grid grid-cols-2 gap-8 md:gap-16">
                    <div>
                        <p class="text-white/40 text-[10px] md:text-xs uppercase font-bold mb-2">Total Paid</p>
                        <p class="text-2xl md:text-4xl font-black">KES <?= number_format($stats['total_paid'] ?? 0, 0) ?></p>
                    </div>
                    <div class="border-l border-white/10 pl-8 md:pl-16">
                        <p class="text-white/40 text-[10px] md:text-xs uppercase font-bold mb-2">Pending</p>
                        <p class="text-indigo-400 text-2xl md:text-4xl font-black">KES <?= number_format($stats['total_pending'] ?? 0, 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-600/20 rounded-full blur-[80px]"></div>
        </div>

        <div class="relative mb-8">
            <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 md:text-xl"></i>
            <input type="text" id="invoiceSearch" placeholder="Search vehicle or service..." 
                   class="w-full bg-white border border-slate-200 rounded-3xl py-4 md:py-6 pl-14 pr-6 text-sm focus:ring-4 focus:ring-indigo-500/10 shadow-sm outline-none transition-all">
        </div>

        <div class="space-y-4" id="invoiceList">
            <?php foreach ($invoices as $row): 
                $isPaid = ($row['payment_status'] == 'paid');
                $statusColor = $isPaid ? 'bg-emerald-500' : 'bg-amber-500';
            ?>
                <div class="searchable-item invoice-card bg-white border border-slate-100 rounded-[2.5rem] shadow-sm overflow-hidden" 
                     data-search="<?= strtolower($row['plate_no'] . ' ' . $row['requested_service'] . ' ' . $row['make']) ?>">
                    
                    <div class="p-6 md:p-8 flex items-center justify-between">
                        <div class="flex items-center gap-6">
                            <div class="w-1.5 h-14 <?= $statusColor ?> rounded-full"></div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-[10px] font-black text-indigo-600 uppercase italic"><?= htmlspecialchars($row['make']) ?></span>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter"><?= $row['plate_no'] ?></span>
                                </div>
                                <h3 class="text-sm md:text-lg font-black text-slate-900 uppercase leading-none"><?= htmlspecialchars($row['requested_service']) ?></h3>
                                <p class="text-[10px] text-slate-400 font-bold mt-2 uppercase"><?= date('d M, Y', strtotime($row['created_at'])) ?></p>
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="text-lg md:text-xl font-black text-slate-900 tracking-tighter mb-1">KES <?= number_format($row['grand_total'], 0) ?></p>
                            <button onclick="toggleInvoice(<?= (int)$row['id'] ?>)" class="text-[9px] font-bold text-slate-300 uppercase tracking-widest hover:text-indigo-600 transition-colors">
                                BREAKDOWN <i class="fas fa-chevron-down ml-1 transition-transform" id="icon-<?= $row['id'] ?>"></i>
                            </button>
                        </div>
                    </div>

                    <div id="breakdown-<?= $row['id'] ?>" class="breakdown-wrapper bg-slate-50/50">
                        <div class="breakdown-inner">
                            <div class="p-8 md:p-10 border-t border-slate-100">
                                
                                <div class="flex items-center gap-4 mb-8 bg-white p-4 rounded-2xl border border-slate-200/50 shadow-sm">
                                    <img src="<?= htmlspecialchars($row['mechanic_avatar']) ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['mechanic_name']) ?>&background=EEF2FF&color=4F46E5'" class="w-10 h-10 rounded-full">
                                    <div>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase">Mechanic</p>
                                        <p class="text-xs font-black text-slate-800 uppercase"><?= htmlspecialchars($row['mechanic_name']) ?></p>
                                    </div>
                                </div>

                                <div class="space-y-3 mb-8">
                                    <?php 
                                    $items = $items_by_job[$row['job_id']] ?? [];
                                    foreach ($items as $item): ?>
                                        <div class="flex justify-between text-xs">
                                            <span class="text-slate-500"><?= htmlspecialchars($item['description']) ?></span>
                                            <span class="font-bold text-slate-900">KES <?= number_format($item['subtotal'], 0) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button onclick="window.open('view_receipt.php?id=<?= $row['id'] ?>', '_blank')" 
                                        class="w-full py-4 bg-slate-900 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition-all">
                                    Download PDF Receipt
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    function toggleInvoice(id) {
        const breakdown = document.getElementById('breakdown-' + id);
        const icon = document.getElementById('icon-' + id);
        breakdown.classList.toggle('active');
        icon.style.transform = breakdown.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    document.getElementById('invoiceSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        document.querySelectorAll('.searchable-item').forEach(item => {
            item.style.display = item.getAttribute('data-search').includes(searchTerm) ? 'block' : 'none';
        });
    });
    </script>
</body>
</html>