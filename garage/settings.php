<?php
/**
 * GarageOS - Account Settings & Terminal Configurations
 * Logic: Identity, Security, and Geographic Handshake
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php"); exit();
}
$garage_id = $_SESSION['user_id'];

// --- 1. FETCH DATA FROM BOTH TABLES ---
$stmt = $pdo->prepare("
    SELECT u.email as auth_email, u.password, g.* FROM users u 
    JOIN garages g ON u.id = g.id 
    WHERE u.id = ?
");
$stmt->execute([$garage_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Logo & Business Update
if (isset($_POST['update_business'])) {
    $name = $_POST['biz_name'];
    $phone = $_POST['biz_phone'];
    $email = $_POST['biz_email'];
    $logo_path = $data['logo'];

    if (!empty($_FILES['logo']['name'])) {
        $target_dir = "../uploads/logo/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = "logo_" . time() . "." . pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_dir . $file_name)) {
            $logo_path = $file_name;
        }
    }

    $pdo->prepare("UPDATE garages SET name = ?, phone = ?, email = ?, logo = ? WHERE id = ?")
        ->execute([$name, $phone, $email, $logo_path, $garage_id]);
    $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $garage_id]);
    
    header("Location: settings.php?msg=Identity+Updated"); exit();
}

// Handle Coordinate Update (AJAX)
if (isset($_POST['update_location'])) {
    $lat = filter_var($_POST['lat'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $lng = filter_var($_POST['lng'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    
    $stmt = $pdo->prepare("UPDATE garages SET latitude = ?, longitude = ? WHERE id = ?");
    $result = $stmt->execute([$lat, $lng, $garage_id]);
    
    echo json_encode(['success' => $result]);
    exit();
}

// Handle Security Update
if (isset($_POST['update_security'])) {
    if (password_verify($_POST['curr_pass'], $data['password'])) {
        $new_pass = password_hash($_POST['new_pass'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_pass, $garage_id]);
        $success_sec = "Security Credentials Updated";
    } else {
        $error_sec = "Current access key is incorrect";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal Settings | GarageOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; color: #0F172A; }
        .input-focus { transition: all 0.2s ease; border: 2px solid transparent; }
        .input-focus:focus { border-color: #4F46E5; background: white; box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.05); }
        #map { height: 300px; width: 100%; border-radius: 2rem; z-index: 1; }
    </style>
</head>
<body class="bg-[#F8FAFC]">

    <main class="max-w-4xl mx-auto p-8 lg:p-12">
        
        <div class="mb-12 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">Terminal Settings</h1>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-2">Configuring account settings » Logic </p>
            </div>
            <a href="../garage.php" class="bg-white border border-slate-200 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                <i class="fa-solid fa-arrow-left mr-2"></i> Dashboard
            </a>
        </div>

        <div class="space-y-8">
            
            <section class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-8 border-b border-slate-100 flex items-center gap-4 bg-slate-50/50">
                    <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg">
                        <i class="fa-solid fa-briefcase text-xs"></i>
                    </div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-700">Business Identity</h2>
                </div>
                
                <form action="" method="POST" enctype="multipart/form-data" class="p-8">
                    <div class="flex items-center gap-8 mb-10 pb-10 border-b border-slate-50">
                        <div class="relative group">
                            <div class="w-24 h-24 rounded-[2rem] bg-slate-100 overflow-hidden border-2 border-dashed border-slate-200 flex items-center justify-center">
                                <img id="logoPreview" src="../uploads/logo/<?= $data['logo'] ?>" class="w-full h-full object-cover <?= empty($data['logo']) ? 'hidden' : '' ?>" onerror="this.src='https://ui-avatars.com/api/?name=Garage'">
                                <i id="logoIcon" class="fa-solid fa-image text-slate-300 <?= !empty($data['logo']) ? 'hidden' : '' ?>"></i>
                            </div>
                            <label class="absolute -bottom-2 -right-2 bg-white border border-slate-200 text-slate-600 w-8 h-8 rounded-xl flex items-center justify-center cursor-pointer shadow-sm hover:bg-slate-900 hover:text-white transition-all">
                                <i class="fa-solid fa-camera text-[10px]"></i>
                                <input type="file" name="logo" class="hidden" accept="image/*" onchange="previewLogo(this)">
                            </label>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest mb-1">Brand Assets</p>
                            <p class="text-[9px] font-bold text-slate-400 uppercase leading-relaxed">Update your garage logo.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase ml-2 tracking-widest">Garage Name</label>
                            <input type="text" name="biz_name" value="<?= htmlspecialchars($data['name']) ?>" class="input-focus w-full p-4 bg-slate-50 rounded-2xl text-xs font-bold outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase ml-2 tracking-widest">Support Line</label>
                            <input type="text" name="biz_phone" value="<?= htmlspecialchars($data['phone']) ?>" class="input-focus w-full p-4 bg-slate-50 rounded-2xl text-xs font-bold outline-none">
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase ml-2 tracking-widest">Business Email</label>
                            <input type="email" name="biz_email" value="<?= htmlspecialchars($data['email']) ?>" class="input-focus w-full p-4 bg-slate-50 rounded-2xl text-xs font-bold outline-none">
                        </div>
                    </div>

                    <button type="submit" name="update_business" class="mt-8 w-full md:w-auto bg-slate-900 text-white px-10 py-4 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] shadow-xl hover:bg-indigo-600 transition-all">
                        Commit Identity Changes
                    </button>
                </form>
            </section>

            <section class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-8 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-indigo-500 rounded-xl flex items-center justify-center text-white shadow-lg">
                            <i class="fa-solid fa-satellite-dish text-xs"></i>
                        </div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-slate-700">Geographic Pin</h2>
                    </div>
                    <?php if(!empty($data['latitude']) && floatval($data['latitude']) != 0): ?>
                        <span class="text-[8px] font-black bg-emerald-100 text-emerald-600 px-3 py-1 rounded-full uppercase tracking-widest">Position Locked</span>
                    <?php endif; ?>
                </div>
                
                <div class="p-8">
                    <div id="map" class="mb-6 border border-slate-200"></div>
                    
                    <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 flex-1 w-full">
                            <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Live Coordinates</p>
                            <p id="coord-display" class="text-[11px] font-mono font-bold text-slate-700 uppercase">
                                <?= (!empty($data['latitude']) && floatval($data['latitude']) != 0) ? $data['latitude'].", ".$data['longitude'] : "Signal Offline" ?>
                            </p>
                        </div>
                        <button onclick="captureLocation()" id="syncBtn" class="bg-slate-900 text-white px-8 py-5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-xl flex items-center gap-3">
                            <i class="fa-solid fa-crosshairs"></i> Sync GPS Position
                        </button>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-8 border-b border-slate-100 flex items-center gap-4 bg-slate-50/50">
                    <div class="w-10 h-10 bg-rose-500 rounded-xl flex items-center justify-center text-white shadow-lg">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                    </div>
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-700">Security Access Key</h2>
                </div>

                <form action="" method="POST" class="p-8 space-y-6">
                    <?php if(isset($success_sec)): ?>
                        <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-emerald-100"><?= $success_sec ?></div>
                    <?php endif; ?>
                    <?php if(isset($error_sec)): ?>
                        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-rose-100"><?= $error_sec ?></div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase ml-2 tracking-widest">Current Key</label>
                            <input type="password" name="curr_pass" required class="input-focus w-full p-4 bg-slate-50 rounded-2xl text-xs font-bold outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-slate-400 uppercase ml-2 tracking-widest">New Security Key</label>
                            <input type="password" name="new_pass" required class="input-focus w-full p-4 bg-slate-50 rounded-2xl text-xs font-bold outline-none">
                        </div>
                    </div>
                    <button type="submit" name="update_security" class="bg-rose-500 text-white px-10 py-4 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] shadow-xl hover:bg-slate-900 transition-all">
                        Rotate Security Keys
                    </button>
                </form>
            </section>

        </div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Init Map
        const initialLat = <?= (!empty($data['latitude']) && floatval($data['latitude']) != 0) ? $data['latitude'] : -1.286389 ?>;
        const initialLng = <?= (!empty($data['longitude']) && floatval($data['longitude']) != 0) ? $data['longitude'] : 36.817223 ?>;
        
        const map = L.map('map').setView([initialLat, initialLng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        let marker = L.marker([initialLat, initialLng]).addTo(map);

        function captureLocation() {
            const btn = document.getElementById('syncBtn');
            btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> LOCKING SIGNAL...';
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    
                    // Update Local Map
                    map.setView([lat, lng], 18);
                    marker.setLatLng([lat, lng]);
                    document.getElementById('coord-display').innerText = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

                    // AJAX to DB
                    const formData = new FormData();
                    formData.append('update_location', true);
                    formData.append('lat', lat);
                    formData.append('lng', lng);

                    fetch('settings.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                btn.innerHTML = '<i class="fa-solid fa-check"></i> POSITION SYNCED';
                                btn.classList.replace('bg-slate-900', 'bg-emerald-600');
                                setTimeout(() => location.reload(), 1500);
                            }
                        });
                }, () => alert("GPS Access Denied. Please enable location."));
            }
        }

        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    document.getElementById('logoPreview').src = e.target.result;
                    document.getElementById('logoPreview').classList.remove('hidden');
                    document.getElementById('logoIcon').classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
