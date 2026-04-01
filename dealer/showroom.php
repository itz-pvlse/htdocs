<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header("Location: ../login.php"); exit;
}

$user_id = $_SESSION['user_id'];

// 1. UPDATE SOCIALS (Using your exact DB columns)
if (isset($_POST['update_socials'])) {
    $fb = $_POST['facebook'];
    $ig = $_POST['instagram'];
    $tk = $_POST['tiktok'];
    $tw = $_POST['twitter'];
    $wb = $_POST['website'];
    
    $upd = $pdo->prepare("UPDATE dealers SET facebook = ?, instagram = ?, tiktok = ?, twitter = ?, website = ? WHERE user_id = ?");
    $upd->execute([$fb, $ig, $tk, $tw, $wb, $user_id]);
    header("Location: showroom.php?saved=1"); exit;
}

// 2. GET DEALER DATA
$stmt = $pdo->prepare("SELECT * FROM dealers WHERE user_id = ?");
$stmt->execute([$user_id]);
$dealer = $stmt->fetch();
$dealer_id = $dealer['id'];

// 3. HANDLE UPDATES (Direct Featured Toggle - As requested)
if (isset($_POST['toggle_featured'])) {
    $listing_id = $_POST['listing_id'];
    $current = $_POST['current_state'];
    $new_state = ($current == 1) ? 0 : 1;
    
    $update = $pdo->prepare("UPDATE dealer_listings SET is_featured = ? WHERE id = ? AND dealer_id = ?");
    $update->execute([$new_state, $listing_id, $user_id]);
    header("Location: showroom.php?success=1"); exit;
}

// 4. FETCH INVENTORY
$inventory = $pdo->prepare("SELECT id, make, model, year, price, main_image, is_featured FROM dealer_listings WHERE dealer_id = ? AND status = 'active' ORDER BY created_at DESC");
$inventory->execute([$user_id]);
$cars = $inventory->fetchAll();

$featured = array_filter($cars, function($car) { return $car['is_featured'] == 1; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Showroom Manager | AutoLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="pb-32">

    <div class="p-6">
        <header class="flex justify-between items-start mb-8">
            <div class="flex items-center gap-4">
                <a href="dealer.php" class="w-10 h-10 bg-white border border-slate-100 rounded-2xl flex items-center justify-center text-slate-900 shadow-sm">
                    <i class="fas fa-chevron-left text-xs"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-[800] tracking-tighter uppercase italic text-slate-900 leading-none">Showroom</h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Storefront Management</p>
                </div>
            </div>
            <a href="dealer-profile.php?id=<?= $user_id ?>" target="_blank" class="flex items-center gap-2 bg-slate-900 text-white px-4 py-2.5 rounded-2xl shadow-xl shadow-slate-200 active:scale-95 transition-transform">
    <span class="text-[10px] font-black uppercase">View Profile</span>
    <i class="fas fa-external-link-alt text-[10px]"></i>
</a>

        </header>

        <div class="mb-10 bg-white border border-slate-100 rounded-[2.5rem] p-6 shadow-sm">
            <h2 class="text-xs font-black uppercase text-slate-900 mb-4 tracking-widest italic">Business Socials</h2>
            <form method="POST" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 p-2 rounded-2xl border border-slate-100">
                        <label class="text-[8px] font-black uppercase text-slate-400 ml-2">Facebook</label>
                        <input type="text" name="facebook" value="<?= htmlspecialchars($dealer['facebook'] ?? '') ?>" class="w-full bg-transparent text-xs font-bold px-2 outline-none">
                    </div>
                    <div class="bg-slate-50 p-2 rounded-2xl border border-slate-100">
                        <label class="text-[8px] font-black uppercase text-slate-400 ml-2">Instagram</label>
                        <input type="text" name="instagram" value="<?= htmlspecialchars($dealer['instagram'] ?? '') ?>" class="w-full bg-transparent text-xs font-bold px-2 outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 p-2 rounded-2xl border border-slate-100">
                        <label class="text-[8px] font-black uppercase text-slate-400 ml-2">TikTok</label>
                        <input type="text" name="tiktok" value="<?= htmlspecialchars($dealer['tiktok'] ?? '') ?>" class="w-full bg-transparent text-xs font-bold px-2 outline-none">
                    </div>
                    <div class="bg-slate-50 p-2 rounded-2xl border border-slate-100">
                        <label class="text-[8px] font-black uppercase text-slate-400 ml-2">Twitter</label>
                        <input type="text" name="twitter" value="<?= htmlspecialchars($dealer['twitter'] ?? '') ?>" class="w-full bg-transparent text-xs font-bold px-2 outline-none">
                    </div>
                </div>
                <div class="bg-slate-50 p-2 rounded-2xl border border-slate-100">
                    <label class="text-[8px] font-black uppercase text-slate-400 ml-2">Website</label>
                    <input type="text" name="website" value="<?= htmlspecialchars($dealer['website'] ?? '') ?>" class="w-full bg-transparent text-xs font-bold px-2 outline-none">
                </div>
                <button type="submit" name="update_socials" class="w-full py-3 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg">
                    Save Details
                </button>
            </form>
        </div>

        <div class="mb-10">
            <h2 class="text-xs font-black uppercase text-slate-900 mb-4 px-2 tracking-widest italic">Featured Units (<?= count($featured) ?>/4)</h2>
            <?php if(empty($featured)): ?>
                <div class="bg-indigo-50 border-2 border-dashed border-indigo-200 rounded-[2rem] p-10 text-center">
                    <p class="text-sm font-bold text-indigo-400">No cars featured yet.</p>
                </div>
            <?php else: ?>
                <div class="flex gap-4 overflow-x-auto no-scrollbar pb-2">
                    <?php foreach($featured as $f): ?>
                    <div class="min-w-[200px] bg-white rounded-[2rem] p-3 border border-indigo-100 shadow-sm relative">
                        <img src="../<?= $f['main_image'] ?>" class="w-full h-28 object-cover rounded-2xl mb-3">
                        <h4 class="font-bold text-xs text-slate-900 truncate"><?= $f['year'] ?> <?= $f['make'] ?></h4>
                        <p class="text-[10px] font-black text-indigo-600 uppercase">KSH <?= number_format($f['price']) ?></p>
                        <form method="POST" class="absolute top-5 right-5">
                            <input type="hidden" name="listing_id" value="<?= $f['id'] ?>">
                            <input type="hidden" name="current_state" value="1">
                            <button type="submit" name="toggle_featured" class="w-7 h-7 bg-white/90 backdrop-blur rounded-full text-rose-500 shadow-lg flex items-center justify-center">
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <h2 class="text-xs font-black uppercase text-slate-900 mb-4 px-2 tracking-widest italic">Inventory Picker</h2>
        <div class="space-y-3">
            <?php foreach($cars as $car): ?>
            <div class="flex items-center gap-4 bg-white border border-slate-100 p-4 rounded-[2rem] shadow-sm">
                <img src="../<?= $car['main_image'] ?>" class="w-14 h-14 rounded-2xl object-cover">
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-slate-900 truncate text-sm"><?= $car['year'] ?> <?= $car['make'] ?></h4>
                    <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-tighter">KSH <?= number_format($car['price']) ?></p>
                </div>
                <form method="POST">
                    <input type="hidden" name="listing_id" value="<?= $car['id'] ?>">
                    <input type="hidden" name="current_state" value="<?= $car['is_featured'] ?>">
                    <button type="submit" name="toggle_featured" 
                        class="px-4 py-2 rounded-xl text-[9px] font-black uppercase transition-all <?= $car['is_featured'] ? 'bg-rose-50 text-rose-500 border border-rose-100' : 'bg-slate-900 text-white shadow-lg' ?>">
                        <?= $car['is_featured'] ? 'Remove' : 'Feature' ?>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <nav class="fixed bottom-0 left-0 right-0 h-20 bg-white/90 backdrop-blur-xl border-t border-slate-100 flex justify-around items-center px-6 pb-2 z-50">
        <a href="dealer.php" class="text-slate-300 flex flex-col items-center gap-1"><i class="fas fa-layer-group text-xl"></i><span class="text-[8px] font-bold uppercase">Leads</span></a>
        <a href="showroom.php" class="text-indigo-600 flex flex-col items-center gap-1"><i class="fas fa-store text-xl"></i><span class="text-[8px] font-bold uppercase">Showroom</span></a>
        <a href="inventory.php" class="text-slate-300 flex flex-col items-center gap-1"><i class="fas fa-car text-xl"></i><span class="text-[8px] font-bold uppercase">Stock</span></a>
    </nav>

</body>
</html>
