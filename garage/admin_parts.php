<?php
/**
 * admin_parts.php - GarageOS Tier-1 Inventory Terminal
 * Architecture: 100% Audit-Log Ledger System
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php"); exit();
}
$user_id = $_SESSION['user_id'];
$status_msg = $_GET['msg'] ?? null;
$error_msg = $_GET['error'] ?? null;

/** * HELPER: THE MASTER LEDGER ENGINE
 * Updates Master Table + Creates the 100% Transaction Trail
 */
function recordTransaction($pdo, $part_id, $user_id, $type, $qty_change, $cost_price, $ref = null, $notes = '') {
    try {
        $pdo->beginTransaction();

        // 1. Get snapshot of stock BEFORE change
        $stmt = $pdo->prepare("SELECT quantity FROM parts_inventory WHERE id = ? FOR UPDATE");
        $stmt->execute([$part_id]);
        $qty_before = (int)$stmt->fetchColumn();

        // --- MATH SAFETY OVERRIDE ---
        // Force INITIAL and RESTOCK to be positive additions.
        // Force USAGE to be a negative deduction.
        if (in_array($type, ['initial', 'restock'])) {
            $actual_change = abs($qty_change); 
        } elseif ($type === 'usage') {
            $actual_change = -abs($qty_change);
        } else {
            $actual_change = $qty_change; // Manual adjustments keep their original sign
        }

        $qty_after = $qty_before + $actual_change;

        // 2. Update Master Inventory (Stock & Price)
        $update = $pdo->prepare("UPDATE parts_inventory SET quantity = ?, unit_price = ?, last_restocked_at = NOW() WHERE id = ?");
        $update->execute([$qty_after, $cost_price, $part_id]);

        // 3. Log to the 100% Transaction Table
        // We log 'actual_change' to ensure the ledger matches the math
        $log = $pdo->prepare("INSERT INTO inventory_transactions 
            (part_id, user_id, type, qty_before, qty_change, qty_after, cost_price, reference_id, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $log->execute([$part_id, $user_id, $type, $qty_before, $actual_change, $qty_after, $cost_price, $ref, $notes]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}




// --- POST HANDLERS ---

// A. Handle Restock
if (isset($_POST['execute_restock'])) {
    $part_id = (int)$_POST['part_id'];
    $qty_add = (int)$_POST['qty_to_add'];
    $unit_cost = (float)$_POST['unit_cost'];
    $invoice = trim($_POST['invoice_ref']);

    if ($qty_add > 0) {
        // Correctly identify as 'restock'
        if (recordTransaction($pdo, $part_id, $user_id, 'restock', $qty_add, $unit_cost, $invoice, "Batch Restock Inbound")) {
            header("Location: admin_parts.php?msg=Stock+In+Verified");
        } else {
            header("Location: admin_parts.php?error=Ledger+Sync+Failed");
        }
    }
    exit();
}

// B. Handle New Asset Registration
if (isset($_POST['register_part'])) {
    $name = trim($_POST['part_name']);
    $sku  = trim($_POST['part_number']);
    $qty  = (int)$_POST['quantity'];
    $cost = (float)$_POST['unit_price'];
    
    // 1. Initial Insert into inventory table with 0 balance
    $stmt = $pdo->prepare("INSERT INTO parts_inventory (part_name, part_number, category, quantity, unit_price, reorder_level, supplier_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if($stmt->execute([$name, $sku, $_POST['category'], 0, $cost, (int)$_POST['reorder_level'], $_POST['supplier_id']])) {
        $new_id = $pdo->lastInsertId();
        
        // 2. Record the initial stock movement using 'initial' type
        // This ensures the Ledger displays it as a fresh load, not a correction
        if (recordTransaction($pdo, $new_id, $user_id, 'initial', $qty, $cost, 'INITIAL', "Initial System Inventory Load")) {
            header("Location: admin_parts.php?msg=Asset+Deployed");
        } else {
            header("Location: admin_parts.php?error=Ledger+Sync+Failed");
        }
    } else {
        header("Location: admin_parts.php?error=Registration+Failed");
    }
    exit();
}


// C. Handle Part Update (Edit Logic)
if (isset($_POST['update_part'])) {
    $part_id = (int)$_POST['part_id'];
    $price = (float)$_POST['unit_price'];
    
    // Fetch old price for audit check
    $old = $pdo->prepare("SELECT unit_price FROM parts_inventory WHERE id = ?");
    $old->execute([$part_id]);
    $old_price = (float)$old->fetchColumn();

    $stmt = $pdo->prepare("UPDATE parts_inventory SET part_name = ?, part_number = ?, category = ?, unit_price = ?, selling_price = ?, reorder_level = ?, supplier_id = ? WHERE id = ?");
    
    if($stmt->execute([trim($_POST['part_name']), trim($_POST['part_number']), $_POST['category'], $price, (float)$_POST['selling_price'], (int)$_POST['reorder_level'], $_POST['supplier_id'], $part_id])) {
        
        // If the buying price was changed manually via edit, log it as an adjustment
        if ($old_price !== $price) {
            $pdo->prepare("INSERT INTO inventory_transactions (part_id, user_id, type, qty_before, qty_change, qty_after, cost_price, notes) SELECT id, ?, 'adjustment', quantity, 0, quantity, ?, ? FROM parts_inventory WHERE id = ?")
                ->execute([$user_id, $price, "Manual Price Override from KES $old_price", $part_id]);
        }
        
        header("Location: admin_parts.php?msg=Asset+Sync+Complete");
    } else {
        header("Location: admin_parts.php?error=Update+Failed");
    }
    exit();
}

// --- DATA FETCHING ---
$health = $pdo->query("SELECT 
    SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as 'crit',
    SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END) as 'low',
    SUM(quantity * unit_price) as 'val' 
    FROM parts_inventory")->fetch(PDO::FETCH_ASSOC);

$suppliers = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM parts_inventory WHERE supplier_id = s.id) as sku_count FROM suppliers s")->fetchAll(PDO::FETCH_ASSOC);

$search = $_GET['search'] ?? '';
$cat_f = $_GET['category'] ?? '';
$sql = "SELECT p.*, s.name as supplier_name FROM parts_inventory p 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        WHERE (p.part_name LIKE ? OR p.part_number LIKE ?)";
$params = ["%$search%", "%$search%"];
if($cat_f) { $sql .= " AND p.category = ?"; $params[] = $cat_f; }
$sql .= " ORDER BY (p.quantity <= p.reorder_level) DESC, p.part_name ASC";
$parts = $pdo->prepare($sql);
$parts->execute($params);
$parts_list = $parts->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inventory HQ | GarageOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #0f172a; }
        .bento-card { background: white; border: 1px solid #e2e8f0; border-radius: 1.5rem; transition: all 0.2s; }
        .bento-card:hover { border-color: #cbd5e1; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="pb-24 lg:pb-12">

    <?php if($status_msg): ?>
        <div class="fixed top-20 left-1/2 -translate-x-1/2 z-[200] bg-emerald-500 text-white px-6 py-3 rounded-2xl text-xs font-black shadow-lg animate-bounce">
            <i class="fa-solid fa-check-circle mr-2"></i> <?= htmlspecialchars($status_msg) ?>
        </div>
    <?php endif; ?>

    <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-50 px-6 py-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="../garage.php" class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-200 transition-colors">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>
                <div>
                    <h1 class="text-xl font-black italic uppercase tracking-tighter leading-none">Inventory Terminal</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">System Online</p>
                    </div>
                </div>
            </div>
            <button onclick="toggleModal('addPartModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl flex items-center gap-2 shadow-lg shadow-indigo-200 transition-all active:scale-95">
                <i class="fa-solid fa-plus text-xs"></i>
                <span class="text-[10px] font-black uppercase tracking-wider">New Asset</span>
            </button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 mt-8">
        <div class="flex overflow-x-auto gap-4 mb-10 no-scrollbar pb-2">
            <div class="min-w-[240px] flex-1 bento-card p-6 bg-slate-900 text-white">
                <div class="flex justify-between items-start mb-6">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Asset Value</p>
                    <i class="fa-solid fa-chart-line text-emerald-400"></i>
                </div>
                <h3 class="text-3xl font-black italic">KES <?= number_format(($health['val'] ?? 0)) ?></h3>
                <p class="text-[10px] text-slate-500 mt-2 font-bold tracking-tight">Active liquidity in parts</p>
            </div>
            <div class="min-w-[180px] bento-card p-6 border-rose-100 bg-rose-50/50">
                <p class="text-[10px] font-black text-rose-400 uppercase tracking-widest mb-6">Stock Out</p>
                <h3 class="text-3xl font-black italic text-rose-600"><?= $health['crit'] ?? 0 ?></h3>
                <div class="h-1 w-12 bg-rose-200 rounded-full mt-4"></div>
            </div>
            <div class="min-w-[180px] bento-card p-6 border-orange-100 bg-orange-50/50">
                <p class="text-[10px] font-black text-orange-400 uppercase tracking-widest mb-6">Low Stock</p>
                <h3 class="text-3xl font-black italic text-orange-600"><?= $health['low'] ?? 0 ?></h3>
                <div class="h-1 w-12 bg-orange-200 rounded-full mt-4"></div>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-8">
            <div class="col-span-12">
                <div class="bento-card p-2 bg-white/50 backdrop-blur-sm">
                    <form method="GET" class="flex flex-col md:flex-row gap-2">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search SKU, Part Name, or Serial..." class="w-full pl-12 pr-4 py-4 bg-white rounded-2xl text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500/20 border border-slate-100">
                        </div>
                        <select name="category" class="py-4 px-6 bg-white rounded-2xl text-xs font-bold outline-none border border-slate-100">
                            <option value="">All Categories</option>
                            <option value="Engine" <?= $cat_f=='Engine'?'selected':'' ?>>Engine</option>
                            <option value="Brakes" <?= $cat_f=='Brakes'?'selected':'' ?>>Brakes</option>
                            <option value="Service" <?= $cat_f=='Service'?'selected':'' ?>>Service</option>
                            <option value="Electrical" <?= $cat_f=='Electrical'?'selected':'' ?>>Electrical</option>
                        </select>
                        <button type="submit" class="bg-slate-900 text-white py-4 px-10 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-black transition-colors">Filter Result</button>
                    </form>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-8">
                <div class="space-y-4">
                    <?php if(empty($parts_list)): ?>
                        <div class="p-20 text-center bento-card">
                            <i class="fa-solid fa-box-open text-4xl text-slate-200 mb-4"></i>
                            <p class="text-xs font-bold text-slate-400 uppercase">No parts found matching your query</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach($parts_list as $p): 
                        $isCrit = $p['quantity'] <= 0;
                        $isLow = ($p['quantity'] > 0 && $p['quantity'] <= $p['reorder_level']);
                    ?>
                    <div class="bento-card p-5 group">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center group-hover:bg-slate-900 group-hover:text-white transition-all duration-300">
                                    <i class="fa-solid fa-component-plus text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black uppercase tracking-tight italic"><?= htmlspecialchars($p['part_name']) ?></h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[9px] font-black text-slate-400 bg-slate-100 px-2 py-0.5 rounded uppercase tracking-widest"><?= $p['part_number'] ?></span>
                                        <span class="text-[9px] font-bold text-indigo-500 uppercase"><?= $p['category'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if($isCrit): ?>
                                <span class="status-pill bg-rose-500 text-white shadow-lg shadow-rose-200">Out of Stock</span>
                            <?php elseif($isLow): ?>
                                <span class="status-pill bg-orange-100 text-orange-600">Low Stock</span>
                            <?php else: ?>
                                <span class="status-pill bg-emerald-100 text-emerald-600">Optimal</span>
                            <?php endif; ?>
                        </div>

                        <div class="grid grid-cols-4 gap-4 mt-6 pt-5 border-t border-slate-50">
                            <div>
                                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Current Stock</p>
                                <p class="text-sm font-black mt-1 <?= $isCrit ? 'text-rose-600' : ($isLow ? 'text-orange-600' : 'text-slate-900') ?>">
                                    <?= number_format($p['quantity']) ?> <span class="text-[10px] text-slate-300">PCS</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Unit Price</p>
                                <p class="text-sm font-black mt-1">KES <?= number_format($p['unit_price']) ?></p>
                            </div>
                            <div class="col-span-2 flex justify-end gap-2">
                               <button 
    onclick='openEditModal(<?= json_encode($p) ?>)' 
    class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-slate-900 hover:text-white transition-all">
    <i class="fa-solid fa-pen-to-square text-xs"></i>
</button>

                                <button 
    onclick='openRestockModal(<?= json_encode($p) ?>)' 
    class="h-10 px-4 rounded-xl bg-indigo-600 text-white flex items-center justify-center gap-2 hover:bg-indigo-700 shadow-md transition-all active:scale-95">
    <i class="fa-solid fa-plus text-[10px]"></i>
    <span class="text-[9px] font-black uppercase">Restock</span>
</button>

                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 space-y-6">
                <div class="bento-card p-8">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-8 flex items-center gap-2">
                        <i class="fa-solid fa-truck-fast"></i> Strategic Suppliers
                    </h3>
                    <div class="space-y-4">
                        <?php foreach($suppliers as $s): ?>
                        <div class="flex items-center justify-between p-4 bg-slate-50/50 rounded-2xl border border-transparent hover:border-slate-100 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-xs font-black shadow-sm ring-2 ring-slate-100 uppercase">
                                    <?= substr($s['name'], 0, 1) ?>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-tight"><?= htmlspecialchars($s['name']) ?></p>
                                    <p class="text-[8px] font-bold text-slate-400 mt-1 uppercase"><?= $s['sku_count'] ?> Active SKUs</p>
                                </div>
                            </div>
                            <?php if (!empty($s['contact'])): ?>
                                <a href="tel:<?= $s['contact'] ?>" class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-indigo-600 shadow-sm border border-slate-100">
                                    <i class="fa-solid fa-phone text-[10px]"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bento-card p-8 bg-indigo-600 text-white">
                    <p class="text-[10px] font-black text-indigo-200 uppercase tracking-widest">Inventory Health</p>
                    <div class="mt-6 flex items-end gap-2">
                        <h4 class="text-4xl font-black italic">94%</h4>
                        <p class="text-[10px] font-bold text-indigo-200 mb-2">Availability</p>
                    </div>
                    <p class="text-[10px] mt-4 text-indigo-100 leading-relaxed font-semibold">Your parts-to-service ratio is optimal. Low risk of delay.</p>
                </div>
            </div>
        </div>
    </main>

    <div id="addPartModal" class="fixed inset-0 z-[100] <?= isset($_GET['new_barcode']) ? '' : 'hidden' ?> flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md">
    <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-10 shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h3 class="text-2xl font-black italic uppercase tracking-tighter">Register Asset</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Inventory Enrollment</p>
            </div>
            <button onclick="toggleModal('addPartModal')" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Part Designation</label>
                    <input type="text" name="part_name" id="reg_part_name" required placeholder="e.g. Brembo Brake Pad Front" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500 focus:bg-white transition-all">
                </div>
                
                <div class="col-span-1">
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Serial / SKU</label>
                    <div class="flex gap-2">
                        <input type="text" name="part_number" id="reg_sku" required 
                               value="<?= htmlspecialchars($_GET['new_barcode'] ?? '') ?>" 
                               placeholder="Scan or Type..." 
                               class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500 transition-all">
                        <a href="scanner.php?mode=register" class="w-12 h-12 mt-1 bg-slate-900 text-white rounded-2xl flex items-center justify-center shadow-lg active:scale-90 transition-all">
                            <i class="fa-solid fa-barcode"></i>
                        </a>
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Category</label>
                    <select name="category" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500 focus:bg-white transition-all">
                        <optgroup label="Fast Moving">
                            <option value="Service">Service (Filters, Plugs, Belts)</option>
                            <option value="Fluids">Fluids (Oil, ATF, Coolant)</option>
                        </optgroup>
                        <optgroup label="Mechanical">
                            <option value="Engine">Engine & Components</option>
                            <option value="Brakes">Braking System</option>
                            <option value="Suspension">Suspension & Steering</option>
                            <option value="Drivetrain">Drivetrain & Gearbox</option>
                            <option value="Cooling">Cooling & Heating</option>
                        </optgroup>
                        <optgroup label="Electrical & Body">
                            <option value="Electrical">Electrical & Electronics</option>
                            <option value="Body">Body, Trim & Glass</option>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Initial Qty</label>
                    <input type="number" name="quantity" required placeholder="0" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Cost (KES)</label>
                    <input type="number" name="unit_price" required placeholder="0.00" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Alert Level</label>
                    <input type="number" name="reorder_level" value="5" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center px-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase">Authorized Supplier</label>
                    <a href="admin_suppliers.php" class="text-[8px] font-black text-indigo-600 uppercase tracking-tighter hover:underline">+ New Supplier</a>
                </div>
                <select name="supplier_id" required class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                    <option value="">Choose Supplier...</option>
                    <?php foreach($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="register_part" class="w-full bg-slate-900 text-white py-5 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl hover:bg-black transition-all mt-4">Deploy to Inventory</button>
        </form>
    </div>
</div>


    
    
    <div id="editPartModal" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md">
    <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-10 shadow-2xl animate-in fade-in slide-in-from-bottom-4 duration-300">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h3 class="text-2xl font-black italic uppercase tracking-tighter text-indigo-600">Modify Asset</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Audit Record ID: <span id="display_id">#0</span></p>
            </div>
            <button onclick="toggleModal('editPartModal')" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="part_id" id="edit_id">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Part Designation</label>
                    <input type="text" name="part_name" id="edit_name" required class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">SKU / Serial</label>
                    <input type="text" name="part_number" id="edit_sku" required class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Category</label>
                    <select name="category" id="edit_cat" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                        <option value="Engine">Engine</option>
                        <option value="Brakes">Brakes</option>
                        <option value="Service">Service</option>
                        <option value="Electrical">Electrical</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Buying Price (KES)</label>
                    <input type="number" name="unit_price" id="edit_price" required class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Selling Price (KES)</label>
                    <input type="number" name="selling_price" id="edit_selling" required class="w-full p-4 mt-1 bg-emerald-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Stock Alert Level</label>
                    <input type="number" name="reorder_level" id="edit_reorder" required class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Current Supplier</label>
                    <select name="supplier_id" id="edit_supplier" required class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500">
                        <?php foreach($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" name="update_part" class="w-full bg-indigo-600 text-white py-5 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl hover:bg-indigo-700 transition-all mt-4">
                Confirm & Sync Asset
            </button>
        </form>
    </div>
</div>




<div id="restockModal" class="fixed inset-0 z-[120] hidden flex items-center justify-center p-4 bg-slate-900/90 backdrop-blur-sm">
    <div class="bg-white w-full max-w-sm rounded-[2rem] p-8 shadow-2xl">
        <h3 class="text-xl font-black italic uppercase tracking-tighter mb-1">Stock Inbound</h3>
        <p id="restock_part_name" class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest mb-6"></p>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="part_id" id="restock_id">
            
            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Quantity to Add</label>
                <input type="number" name="qty_to_add" required placeholder="0" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-sm font-black outline-none border-2 border-transparent focus:border-indigo-500">
            </div>

            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Current Unit Cost (KES)</label>
                <input type="number" name="unit_cost" id="restock_cost" required class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-sm font-black outline-none border-2 border-transparent focus:border-indigo-500">
            </div>

            <div>
                <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Supplier Invoice #</label>
                <input type="text" name="invoice_ref" placeholder="REF-001" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border-2 border-transparent focus:border-indigo-500">
            </div>

            <button type="submit" name="execute_restock" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl hover:bg-black transition-all mt-2">
                Verify & Commit Stock
            </button>
            <button type="button" onclick="toggleModal('restockModal')" class="w-full text-[9px] font-black text-slate-400 uppercase tracking-widest py-2">Cancel</button>
        </form>
    </div>
</div>





    <div class="fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-lg border-t border-slate-200 px-8 py-5 flex justify-between items-center lg:hidden z-50">
        <a href="../garage.php" class="text-slate-400 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-house-chimney text-lg"></i></a>
        <a href="admin_parts.php" class="text-indigo-600"><i class="fa-solid fa-boxes-stacked text-lg"></i></a>
        <button onclick="location.href='scanner.php'" class="w-14 h-14 bg-slate-900 text-white rounded-2xl flex items-center justify-center -mt-12 shadow-2xl border-4 border-white active:scale-90 transition-all">
    <i class="fa-solid fa-barcode"></i>
</button>

        <a href="inventory_history.php" class="text-slate-400 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-receipt text-lg"></i></a>
        <a href="profile.php" class="text-slate-400 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-user-gear text-lg"></i></a>
    </div>

    <script>
        function toggleModal(id) {
            document.getElementById(id).classList.toggle('hidden');
        }
        // Auto-hide success messages
        setTimeout(() => {
            const msg = document.querySelector('.animate-bounce');
            if (msg) msg.style.display = 'none';
        }, 3000);
        
        function openEditModal(part) {
    // Show Modal
    document.getElementById('editPartModal').classList.remove('hidden');
    
    // Map Data to Form
    document.getElementById('edit_id').value = part.id;
    document.getElementById('display_id').innerText = '#' + part.id;
    document.getElementById('edit_name').value = part.part_name;
    document.getElementById('edit_sku').value = part.part_number;
    document.getElementById('edit_cat').value = part.category;
    document.getElementById('edit_price').value = part.unit_price;
    document.getElementById('edit_selling').value = part.selling_price || 0;
    document.getElementById('edit_reorder').value = part.reorder_level;
    document.getElementById('edit_supplier').value = part.supplier_id;
}

function toggleModal(id) {
    document.getElementById(id).classList.toggle('hidden');
}

        
        
        
        function openRestockModal(part) {
    document.getElementById('restockModal').classList.remove('hidden');
    document.getElementById('restock_id').value = part.id;
    document.getElementById('restock_part_name').innerText = part.part_name;
    document.getElementById('restock_cost').value = part.unit_price;
}

        
    </script>
    
    
    <script>
    // If the scanner redirected here and found exactly one part
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('autofocus')) {
        const partsCount = document.querySelectorAll('.part-card').length;
        if (partsCount === 1) {
            // Automatically trigger the first part's click action
            document.querySelector('.part-card').click();
        }
    }
</script>

    
    
</body>
</html>
