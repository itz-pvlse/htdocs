<?php
/**
 * inventory_history.php - The GarageOS Forensic Ledger
 * Architecture: Real-time Asset Valuation & Movement Audit
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php"); exit();
}

// --- 1. LIVE ASSET VALUATION (Current Shelf Value) ---
$warehouse_val = $pdo->query("SELECT SUM(quantity * unit_price) FROM parts_inventory")->fetchColumn() ?: 0;

// --- 2. PERFORMANCE METRICS (Current Month) ---
$metrics_sql = "SELECT 
    -- Total Investment: Value of everything added (Initial + Restock)
    SUM(CASE WHEN type IN ('restock', 'initial') THEN (ABS(qty_change) * cost_price) ELSE 0 END) as total_investment,
    -- Usage Value: Value of parts consumed/fitted
    SUM(CASE WHEN type = 'usage' THEN (ABS(qty_change) * cost_price) ELSE 0 END) as usage_value,
    -- Volume Stats
    SUM(CASE WHEN type IN ('restock', 'initial') THEN ABS(qty_change) ELSE 0 END) as items_in,
    SUM(CASE WHEN type = 'usage' THEN ABS(qty_change) ELSE 0 END) as items_out
    FROM inventory_transactions 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE)";
$m = $pdo->query($metrics_sql)->fetch(PDO::FETCH_ASSOC);

// --- 3. MAIN LEDGER QUERY ---
$f_type   = $_GET['type'] ?? '';
$f_search = $_GET['search'] ?? ''; 
$f_start  = $_GET['start_date'] ?? '';
$f_end    = $_GET['end_date'] ?? '';

$sql = "SELECT t.*, p.part_name, p.part_number, u.name as staff_name
        FROM inventory_transactions t
        JOIN parts_inventory p ON t.part_id = p.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE 1=1";
$params = [];

if ($f_type) { $sql .= " AND t.type = ?"; $params[] = $f_type; }
if ($f_search) { 
    $sql .= " AND (p.part_name LIKE ? OR p.part_number LIKE ? OR t.reference_id LIKE ?)";
    $s = "%$f_search%"; $params = array_merge($params, [$s, $s, $s]);
}
if ($f_start) { $sql .= " AND DATE(t.created_at) >= ?"; $params[] = $f_start; }
if ($f_end) { $sql .= " AND DATE(t.created_at) <= ?"; $params[] = $f_end; }

$sql .= " ORDER BY t.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger | GarageOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; color: #1e293b; }
        .ledger-card { background: white; border: 1px solid #e2e8f0; border-radius: 1.5rem; transition: all 0.2s; }
        .stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 1.25rem; }
        .filter-input { background: white; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; outline: none; }
        ::-webkit-scrollbar { display: none; }
        
        .bg-initial-500 { background-color: #3b82f6; } 
        .bg-initial-50 { background-color: #eff6ff; }
        .text-initial-600 { color: #2563eb; }
        .bg-initial-100 { background-color: #dbeafe; }
        .text-initial-700 { color: #1d4ed8; }
    </style>
</head>
<body class="pb-24">

    <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-50 px-6 py-4">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="admin_parts.php" class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                </a>
                <h1 class="text-lg font-black italic uppercase tracking-tighter">Forensic Ledger</h1>
            </div>
            <button onclick="window.print()" class="text-slate-400 hover:text-slate-900"><i class="fa-solid fa-print"></i></button>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-6 mt-6">
        
        <div class="space-y-3 mb-8">
            <div class="stat-card p-6 bg-slate-900 text-white shadow-xl shadow-slate-200">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Warehouse Equity</p>
                        <h2 class="text-3xl font-black italic text-emerald-400">KES <?= number_format($warehouse_val) ?></h2>
                    </div>
                    <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center backdrop-blur-md">
                        <i class="fa-solid fa-vault text-emerald-400 text-xs"></i>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-white/10 flex justify-between">
                    <div>
                        <p class="text-[7px] font-bold text-slate-500 uppercase">Monthly Investment</p>
                        <p class="text-xs font-black text-indigo-300">KES <?= number_format($m['total_investment']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[7px] font-bold text-slate-500 uppercase">Usage Value</p>
                        <p class="text-xs font-black text-rose-400">KES <?= number_format($m['usage_value']) ?></p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="stat-card p-4 border-l-4 border-l-emerald-500">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Items Added</p>
                    <h2 class="text-xl font-black text-slate-900">+<?= number_format($m['items_in']) ?></h2>
                </div>
                <div class="stat-card p-4 border-l-4 border-l-rose-500">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Items Used</p>
                    <h2 class="text-xl font-black text-slate-900">-<?= number_format($m['items_out']) ?></h2>
                </div>
            </div>
        </div>

        <form method="GET" class="space-y-3 mb-8">
            <div class="flex gap-2">
                <input type="text" name="search" value="<?= htmlspecialchars($f_search) ?>" placeholder="Search Part, SKU or Ref..." class="filter-input flex-1">
                <button type="submit" class="w-12 h-12 bg-slate-900 text-white rounded-xl flex items-center justify-center"><i class="fa-solid fa-magnifying-glass text-xs"></i></button>
            </div>
            
            <div class="flex gap-2 overflow-x-auto pb-2">
                <select name="type" onchange="this.form.submit()" class="filter-input uppercase">
                    <option value="">All Transactions</option>
                    <option value="initial" <?= $f_type == 'initial' ? 'selected' : '' ?>>Initial Load</option>
                    <option value="restock" <?= $f_type == 'restock' ? 'selected' : '' ?>>Restock</option>
                    <option value="usage" <?= $f_type == 'usage' ? 'selected' : '' ?>>Usage</option>
                    <option value="adjustment" <?= $f_type == 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                </select>
                <input type="date" name="start_date" value="<?= $f_start ?>" onchange="this.form.submit()" class="filter-input uppercase">
                <?php if($f_type || $f_search || $f_start || $f_end): ?>
                    <a href="inventory_history.php" class="bg-rose-100 text-rose-600 px-4 py-2 rounded-xl text-[10px] font-black flex items-center">RESET</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="space-y-4">
            <?php foreach($logs as $l): 
                $isPlus = in_array($l['type'], ['restock', 'initial', 'return']);
                $color = 'slate'; $icon = 'fa-question';
                
                if ($l['type'] == 'initial') { $color = 'initial'; $icon = 'fa-box-open'; }
                elseif ($l['type'] == 'restock') { $color = 'emerald'; $icon = 'fa-plus'; }
                elseif ($l['type'] == 'adjustment') { $color = 'amber'; $icon = 'fa-sliders'; }
                elseif ($l['type'] == 'usage') { $color = 'rose'; $icon = 'fa-minus'; }
                
                $borderCol = ($color == 'initial') ? 'blue-500' : $color . '-500';
                $bgLight = ($color == 'initial') ? 'blue-50' : $color . '-50';
                $textCol = ($color == 'initial') ? 'blue-600' : $color . '-600';
                $badgeBg = ($color == 'initial') ? 'blue-100' : $color . '-100';
                $badgeText = ($color == 'initial') ? 'blue-700' : $color . '-700';
            ?>
            <div class="ledger-card p-5 relative overflow-hidden">
                <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-<?= $borderCol ?>"></div>

                <div class="flex justify-between items-start mb-4">
                    <div class="flex gap-4">
                        <div class="w-10 h-10 rounded-xl bg-<?= $bgLight ?> flex items-center justify-center text-<?= $textCol ?>">
                            <i class="fa-solid <?= $icon ?> text-xs"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-black uppercase italic tracking-tight"><?= htmlspecialchars($l['part_name']) ?></h4>
                            <p class="text-[9px] font-bold text-slate-400 uppercase">
                                SKU: <?= htmlspecialchars($l['part_number']) ?> • <?= date('M d, H:i', strtotime($l['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                    <span class="px-2 py-1 rounded text-[8px] font-black uppercase tracking-widest bg-<?= $badgeBg ?> text-<?= $badgeText ?>"><?= $l['type'] ?></span>
                </div>

                <div class="grid grid-cols-3 gap-2 bg-slate-50 rounded-2xl p-4 border border-slate-100 mb-4">
                    <div class="text-center border-r border-slate-200">
                        <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Before</p>
                        <p class="text-xs font-black"><?= $l['qty_before'] ?></p>
                    </div>
                    <div class="text-center border-r border-slate-200">
                        <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Change</p>
                        <p class="text-xs font-black text-<?= $textCol ?>"><?= ($isPlus ? '+' : '-') . abs($l['qty_change']) ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Balance</p>
                        <p class="text-xs font-black"><?= $l['qty_after'] ?></p>
                    </div>
                </div>

                <div class="flex justify-between items-center text-[9px] font-bold uppercase mb-2">
                    <p class="text-slate-400 italic">Ref: <?= htmlspecialchars($l['reference_id'] ?? 'N/A') ?></p>
                    <p class="text-slate-900 font-black">Val: KES <?= number_format(abs($l['qty_change']) * $l['cost_price']) ?></p>
                </div>

                <?php if($l['notes']): ?>
                <div class="p-2 bg-slate-50 rounded-lg border border-dashed border-slate-200 mb-2">
                    <p class="text-[9px] text-slate-500 italic leading-relaxed">
                        <span class="font-black uppercase not-italic text-slate-400 mr-1">Note:</span> <?= htmlspecialchars($l['notes']) ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <p class="text-[8px] font-bold text-slate-300 uppercase text-right">Processed by: <?= htmlspecialchars($l['staff_name'] ?? 'System') ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 px-8 py-5 flex justify-between items-center lg:hidden">
        <a href="dealer.php" class="text-slate-400"><i class="fa-solid fa-house text-lg"></i></a>
        <a href="admin_parts.php" class="text-slate-400"><i class="fa-solid fa-boxes-stacked text-lg"></i></a>
        <button onclick="location.href='scanner.php'" class="w-12 h-12 bg-slate-900 text-white rounded-2xl flex items-center justify-center -mt-10 shadow-xl border-4 border-white"><i class="fa-solid fa-barcode"></i></button>
        <a href="inventory_history.php" class="text-indigo-600"><i class="fa-solid fa-receipt text-lg"></i></a>
        <a href="profile.php" class="text-slate-400"><i class="fa-solid fa-user-gear text-lg"></i></a>
    </div>

</body>
</html>
