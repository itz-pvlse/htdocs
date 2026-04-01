<?php
/**
 * admin_suppliers.php - GarageOS Supplier Management
 * Mapped to if0_40096378_autolog_db structure
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php"); exit();
}

// --- LOGIC: ADD SUPPLIER ---
if (isset($_POST['add_supplier'])) {
    $name     = trim($_POST['name']);
    $contact  = trim($_POST['contact']); // Matches your DB 'contact'
    $phone    = trim($_POST['phone']);
    $email    = trim($_POST['email']);
    $address  = trim($_POST['address']); // Matches your DB 'address'
    $category = $_POST['supply_category'];

    // Note: Added phone and supply_category via ALTER TABLE in step 1
    $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact, phone, email, address, supply_category) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $contact, $phone, $email, $address, $category]);
    header("Location: admin_suppliers.php?success=1"); exit();
}

// --- FETCH ALL SUPPLIERS ---
$suppliers = $pdo->query("SELECT s.*, 
    (SELECT COUNT(*) FROM parts_inventory WHERE supplier_id = s.id) as total_parts 
    FROM suppliers s ORDER BY s.name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers | GarageOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; }
        .supplier-card { background: white; border: 1px solid #e2e8f0; border-radius: 1.5rem; transition: 0.2s; }
        .supplier-card:hover { border-color: #cbd5e1; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="pb-24">

    <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-50 px-6 py-4 mb-6">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="admin_parts.php" class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                </a>
                <h1 class="text-lg font-black italic uppercase tracking-tighter">Suppliers</h1>
            </div>
            <button onclick="toggleModal('addSupplierModal')" class="bg-slate-900 text-white px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg active:scale-95 transition-all">
                + Add Vendor
            </button>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach($suppliers as $s): ?>
            <div class="supplier-card p-6">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-slate-900 flex items-center justify-center text-white shadow-inner">
                            <i class="fa-solid fa-truck-fast text-xs"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black uppercase italic tracking-tight text-slate-900"><?= htmlspecialchars($s['name']) ?></h4>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"><?= htmlspecialchars($s['supply_category'] ?? 'General Spares') ?></p>
                        </div>
                    </div>
                    <span class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-[8px] font-black uppercase"><?= $s['total_parts'] ?> SKU</span>
                </div>

                <div class="grid grid-cols-2 gap-y-3 mb-6">
                    <div class="flex items-center gap-3 text-[10px] font-bold text-slate-600">
                        <i class="fa-solid fa-user text-slate-300 w-4"></i>
                        <span><?= htmlspecialchars($s['contact'] ?? 'No Contact') ?></span>
                    </div>
                    <div class="flex items-center gap-3 text-[10px] font-bold text-slate-600">
                        <i class="fa-solid fa-phone text-slate-300 w-4"></i>
                        <a href="tel:<?= htmlspecialchars($s['phone'] ?? '') ?>" class="text-indigo-600"><?= htmlspecialchars($s['phone'] ?? 'Add Phone') ?></a>
                    </div>
                    <div class="col-span-2 flex items-center gap-3 text-[10px] font-bold text-slate-600">
                        <i class="fa-solid fa-location-dot text-slate-300 w-4"></i>
                        <span class="truncate"><?= htmlspecialchars($s['address'] ?? 'No Address Recorded') ?></span>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-50 flex gap-2">
                    <button class="flex-1 bg-slate-50 text-slate-900 py-3.5 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-slate-100 transition-colors">Catalog</button>
                    <a href="mailto:<?= htmlspecialchars($s['email'] ?? '') ?>" class="w-12 h-12 bg-slate-50 flex items-center justify-center rounded-xl text-slate-400 hover:text-indigo-600 transition-colors border border-transparent hover:border-indigo-100">
                        <i class="fa-solid fa-envelope"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="addSupplierModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] p-10 shadow-2xl animate-in fade-in zoom-in duration-300">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black italic uppercase tracking-tighter">New Partner</h3>
                <button onclick="toggleModal('addSupplierModal')" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Company Name</label>
                    <input type="text" name="name" required placeholder="e.g. AutoXpress Kenya" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none border border-transparent focus:border-indigo-500 focus:bg-white transition-all">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Contact Person</label>
                        <input type="text" name="contact" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Category</label>
                        <select name="supply_category" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none focus:border-indigo-500">
                            <option value="General Spares">General Spares</option>
                            <option value="Tyres">Tyres & Alignment</option>
                            <option value="Lubricants">Lubricants / Oils</option>
                            <option value="Body & Paint">Body & Paint</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Phone</label>
                        <input type="text" name="phone" required placeholder="+254" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Email</label>
                        <input type="email" name="email" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-2">Physical Address</label>
                    <input type="text" name="address" placeholder="e.g. Lusaka Rd, Industrial Area" class="w-full p-4 mt-1 bg-slate-50 rounded-2xl text-xs font-bold outline-none focus:border-indigo-500">
                </div>
                <button type="submit" name="add_supplier" class="w-full bg-slate-900 text-white py-5 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl mt-4 active:scale-95 transition-all">Enroll Supplier</button>
            </form>
        </div>
    </div>

    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 px-8 py-5 flex justify-between items-center lg:hidden z-50">
        <a href="dealer.php" class="text-slate-400"><i class="fa-solid fa-house text-lg"></i></a>
        <a href="admin_parts.php" class="text-slate-400"><i class="fa-solid fa-boxes-stacked text-lg"></i></a>
        <button onclick="location.href='scanner.php'" class="w-14 h-14 bg-slate-900 text-white rounded-2xl flex items-center justify-center -mt-12 shadow-2xl border-4 border-white active:scale-90 transition-all"><i class="fa-solid fa-barcode"></i></button>
        <a href="inventory_history.php" class="text-slate-400"><i class="fa-solid fa-receipt text-lg"></i></a>
        <a href="profile.php" class="text-slate-400"><i class="fa-solid fa-user-gear text-lg"></i></a>
    </div>

    <script>
        function toggleModal(id) {
            document.getElementById(id).classList.toggle('hidden');
        }
    </script>
</body>
</html>
