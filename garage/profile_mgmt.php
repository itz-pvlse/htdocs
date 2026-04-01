<?php
// 1. SYSTEM SETTINGS
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. SESSION & DB
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php'; 

// 3. AUTHENTICATION
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php");
    exit();
}

$garage_id = $_SESSION['user_id'];

// --- STATUS MESSAGE HANDLER ---
$status = $_GET['status'] ?? '';
$success_msg = "";
if ($status === 'synced') $success_msg = "Terminal records updated and synchronized.";
if ($status === 'deleted') $success_msg = "Asset purged from terminal.";
if ($status === 'error') $success_msg = "Error: " . htmlspecialchars($_GET['msg'] ?? 'Operation failed');

// 4. UPDATE LOGIC (Relational Transaction + Users Sync)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $pdo->beginTransaction();

        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $location = $_POST['location'];
        $description = $_POST['description'];

        // Logo Upload
        $logo_query = "";
        $new_logo_name = null;
        if (!empty($_FILES['logo']['name'])) {
            $target_dir = "../uploads/logo/";
            $file_ext = pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION);
            $new_logo_name = "garage_" . $garage_id . "_" . time() . "." . $file_ext;
            if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_dir . $new_logo_name)) {
                $logo_query = ", logo = '$new_logo_name'";
            }
        }

        // 4a. Update Original Garage Table
        $stmt = $pdo->prepare("UPDATE garages SET name = ?, email = ?, phone = ?, location = ?, description = ? $logo_query WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $location, $description, $garage_id]);

        // 4b. Sync to Users Table (For the Social Feed)
        $user_sql = "UPDATE users SET name = ?, email = ?, phone = ?";
        $user_params = [$name, $email, $phone];
        if ($new_logo_name) {
            $user_sql .= ", profile_photo = ?";
            $user_params[] = $new_logo_name;
        }
        $user_sql .= " WHERE id = ?";
        $user_params[] = $garage_id;
        $pdo->prepare($user_sql)->execute($user_params);

        // 4c. Update Hours
        $pdo->prepare("DELETE FROM garage_hours WHERE garage_id = ?")->execute([$garage_id]);
        $hour_stmt = $pdo->prepare("INSERT INTO garage_hours (garage_id, day_of_week, open_time, close_time, is_closed) VALUES (?, ?, ?, ?, ?)");
        foreach ($_POST['hours'] as $day => $h) {
            $is_closed = isset($h['is_closed']) ? 1 : 0;
            $hour_stmt->execute([$garage_id, $day, $h['open'], $h['close'], $is_closed]);
        }

        // 4d. Update Services
        $pdo->prepare("DELETE FROM garage_services WHERE garage_id = ?")->execute([$garage_id]);
        if (!empty($_POST['services'])) {
            $svc_stmt = $pdo->prepare("INSERT INTO garage_services (garage_id, service_name, base_price) VALUES (?, ?, ?)");
            foreach ($_POST['services'] as $svc) {
                if (!empty($svc['name'])) $svc_stmt->execute([$garage_id, $svc['name'], $svc['price']]);
            }
        }

        // 4e. Update Specialties
        $pdo->prepare("DELETE FROM garage_specialties WHERE garage_id = ?")->execute([$garage_id]);
        $spec_stmt = $pdo->prepare("INSERT INTO garage_specialties (garage_id, specialty_name) VALUES (?, ?)");
        if (!empty($_POST['specialty_list'])) {
            $specs = explode(',', $_POST['specialty_list']);
            foreach ($specs as $s) { if (trim($s)) $spec_stmt->execute([$garage_id, trim($s)]); }
        }

        // 4f. Update Socials
        $pdo->prepare("DELETE FROM garage_socials WHERE garage_id = ?")->execute([$garage_id]);
        $social_stmt = $pdo->prepare("INSERT INTO garage_socials (garage_id, whatsapp, facebook, instagram, website) VALUES (?, ?, ?, ?, ?)");
        $social_stmt->execute([$garage_id, $_POST['whatsapp'], $_POST['facebook'], $_POST['instagram'], $_POST['website']]);

        // 4g. Update Certifications
        if (!empty($_FILES['cert_file']['name'])) {
            $cert_dir = "../uploads/certs/";
            if (!is_dir($cert_dir)) mkdir($cert_dir, 0777, true);
            $cert_name = "cert_" . time() . "_" . $_FILES['cert_file']['name'];
            if (move_uploaded_file($_FILES['cert_file']['tmp_name'], $cert_dir . $cert_name)) {
                $cert_stmt = $pdo->prepare("INSERT INTO garage_certifications (garage_id, title, file_path) VALUES (?, ?, ?)");
                $cert_stmt->execute([$garage_id, $_POST['cert_title'] ?: 'Certification', $cert_name]);
            }
        }

        // 4h. Update Gallery
        if (!empty($_FILES['gallery_image']['name'])) {
            $gallery_dir = "../uploads/gallery/";
            if (!is_dir($gallery_dir)) mkdir($gallery_dir, 0777, true);
            $img_name = "gal_" . time() . "_" . $_FILES['gallery_image']['name'];
            $caption = $_POST['gallery_caption'] ?? '';
            if (move_uploaded_file($_FILES['gallery_image']['tmp_name'], $gallery_dir . $img_name)) {
                $gal_stmt = $pdo->prepare("INSERT INTO garage_gallery (garage_id, image_path, caption) VALUES (?, ?, ?)");
                $gal_stmt->execute([$garage_id, $img_name, $caption]);
            }
        }

        $pdo->commit();
        header("Location: profile_mgmt.php?status=synced");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: profile_mgmt.php?status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
}

// Handle Gallery Image Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gallery_item'])) {
    $img_id = $_POST['delete_gallery_item'];
    $path_stmt = $pdo->prepare("SELECT image_path FROM garage_gallery WHERE id = ? AND garage_id = ?");
    $path_stmt->execute([$img_id, $garage_id]);
    $img = $path_stmt->fetch();

    if ($img) {
        $full_path = "../uploads/gallery/" . $img['image_path'];
        $delete_stmt = $pdo->prepare("DELETE FROM garage_gallery WHERE id = ? AND garage_id = ?");
        if ($delete_stmt->execute([$img_id, $garage_id])) {
            if (file_exists($full_path)) { unlink($full_path); }
            header("Location: profile_mgmt.php?status=deleted");
            exit();
        }
    }
}

// 5. DATA FETCHING (Fixed variable naming to prevent warnings)
$stmt = $pdo->prepare("SELECT * FROM garages WHERE id = ?");
$stmt->execute([$garage_id]);
$garage = $stmt->fetch(PDO::FETCH_ASSOC);

$is_location_set = false;
if (!empty($garage['latitude']) && !empty($garage['longitude'])) {
    if (floatval($garage['latitude']) != 0 && floatval($garage['longitude']) != 0) {
        $is_location_set = true;
    }
}

$saved_hours_stmt = $pdo->prepare("SELECT * FROM garage_hours WHERE garage_id = ?");
$saved_hours_stmt->execute([$garage_id]);
$saved_hours = $saved_hours_stmt->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);

$services_stmt = $pdo->prepare("SELECT * FROM garage_services WHERE garage_id = ?");
$services_stmt->execute([$garage_id]);
$saved_services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

$specs_stmt = $pdo->prepare("SELECT GROUP_CONCAT(specialty_name) as list FROM garage_specialties WHERE garage_id = ?");
$specs_stmt->execute([$garage_id]);
$saved_specs = $specs_stmt->fetch();

$social_stmt = $pdo->prepare("SELECT * FROM garage_socials WHERE garage_id = ?");
$social_stmt->execute([$garage_id]);
$social = $social_stmt->fetch(PDO::FETCH_ASSOC);

$certs_stmt = $pdo->prepare("SELECT * FROM garage_certifications WHERE garage_id = ? ORDER BY uploaded_at DESC");
$certs_stmt->execute([$garage_id]);
$certs = $certs_stmt->fetchAll(PDO::FETCH_ASSOC);

$gallery_stmt = $pdo->prepare("SELECT * FROM garage_gallery WHERE garage_id = ? ORDER BY uploaded_at DESC");
$gallery_stmt->execute([$garage_id]);
$gallery_imgs = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Config | <?= htmlspecialchars($garage['name'] ?? 'Garage') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; background: #FFFFFF; color: #111827; }
        .input-group { position: relative; border-bottom: 1.5px solid #F3F4F6; transition: border-color 0.3s ease; }
        .input-group:focus-within { border-color: #111827; }
        .clean-input { width: 100%; background: transparent; border: none; padding: 0.75rem 0; font-size: 0.95rem; font-weight: 500; color: #111827; outline: none; }
        .label-text { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: #9CA3AF; }
        .btn-black { background: #000; color: #fff; padding: 1rem 2rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; transition: 0.2s; }
        .btn-black:hover { opacity: 0.8; transform: translateY(-1px); }
        .time-row { display: grid; grid-template-columns: 100px 1fr auto; align-items: center; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #F9FAFB; }
        .cb-container input { display: none; }
        .cb-custom { width: 18px; height: 18px; border: 2px solid #E5E7EB; border-radius: 4px; display: inline-block; position: relative; transition: 0.2s; cursor: pointer; }
        .cb-container input:checked + .cb-custom { background: #000; border-color: #000; }
        .cb-container input:checked + .cb-custom::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; color: #fff; font-size: 10px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .sticky-action { position: fixed; bottom: 0; left: 0; width: 100%; background: rgba(255,255,255,0.9); backdrop-filter: blur(8px); border-top: 1px solid #F3F4F6; padding: 1.25rem; z-index: 50; }
    </style>
</head>
<body class="pb-32">

    <?php if($success_msg): ?>
    <div id="toast" class="fixed top-6 left-1/2 -translate-x-1/2 z-[100] w-[90%] max-w-sm bg-black text-white px-6 py-4 rounded-full shadow-2xl flex items-center justify-center gap-3 animate-bounce">
        <span class="text-[10px] font-bold tracking-widest uppercase"><?= $success_msg ?></span>
    </div>
    <script>setTimeout(() => document.getElementById('toast').remove(), 3000);</script>
    <?php endif; ?>

    <nav class="max-w-6xl mx-auto px-6 py-8 flex justify-between items-center">
        <button onclick="history.back()" class="text-[10px] font-extrabold uppercase tracking-tighter flex items-center gap-2 hover:opacity-50 transition">
            <i class="fa-solid fa-arrow-left-long"></i> Return
        </button>
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-gray-50 border border-gray-100 rounded-full overflow-hidden">
                <img src="../uploads/logo/<?= htmlspecialchars($garage['logo'] ?? '') ?>" class="w-full h-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=G&background=f9fafb'">
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-6 mt-8">
        <header class="mb-16">
            <h1 class="text-5xl font-extrabold tracking-tightest mb-4">Core Config.</h1>
            <div class="h-1 w-12 bg-black"></div>
        </header>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-16">
            
            <section class="grid grid-cols-1 md:grid-cols-3 gap-12 items-start">
                <div><h2 class="text-xs font-black uppercase tracking-widest">01. Identity</h2></div>
                <div class="md:col-span-2 flex items-center gap-8">
                    <div class="w-24 h-24 rounded-2xl bg-gray-50 border border-gray-100 flex-shrink-0 overflow-hidden relative group">
                        <img id="logo-preview" src="../uploads/logo/<?= htmlspecialchars($garage['logo'] ?? '') ?>" class="w-full h-full object-cover">
                        <label for="logo-upload" class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer transition">
                            <i class="fa-solid fa-camera text-white"></i>
                        </label>
                        <input type="file" name="logo" id="logo-upload" class="hidden" onchange="previewImage(this)">
                    </div>
                    <div class="flex-1 space-y-4">
                        <div class="input-group">
                            <label class="label-text">Terminal Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($garage['name'] ?? '') ?>" class="clean-input" required>
                        </div>
                    </div>
                </div>
            </section>

            <hr class="border-gray-50">

            <section class="grid grid-cols-1 md:grid-cols-3 gap-12">
    <div>
        <h2 class="text-xs font-black uppercase tracking-widest">02. Communication</h2>
        <div class="mt-6 p-4 rounded-2xl border <?php echo $is_location_set ? 'border-emerald-100 bg-emerald-50/30' : 'border-rose-100 bg-rose-50/30'; ?>">
            <p class="text-[8px] font-black uppercase tracking-widest <?php echo $is_location_set ? 'text-emerald-600' : 'text-rose-500'; ?> mb-2">
                Geographic Signal
            </p>
            <?php if ($is_location_set): ?>
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-location-dot text-emerald-500 text-xs"></i>
                    <span class="text-[10px] font-bold text-slate-700 uppercase">Coordinates Locked</span>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-rose-500 text-xs animate-pulse"></i>
                        <span class="text-[10px] font-bold text-slate-700 uppercase">Signal Missing</span>
                    </div>
                    <a href="settings.php" class="block text-center py-2 px-3 bg-white border border-rose-200 rounded-lg text-[9px] font-black uppercase text-rose-600 hover:bg-rose-500 hover:text-white transition-all">
                        Map Precision
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
        <div class="input-group">
            <label class="label-text">Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($garage['email'] ?? '') ?>" class="clean-input" required>
        </div>
        <div class="input-group">
            <label class="label-text">Phone String</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($garage['phone'] ?? '') ?>" class="clean-input" required>
        </div>
        <div class="input-group sm:col-span-2">
            <label class="label-text">Physical Address</label>
            <input type="text" name="location" value="<?= htmlspecialchars($garage['location'] ?? '') ?>" class="clean-input" required>
        </div>
        <div class="input-group sm:col-span-2">
            <label class="label-text">Operational Description</label>
            <textarea name="description" rows="2" class="clean-input resize-none"><?= htmlspecialchars($garage['description'] ?? '') ?></textarea>
        </div>
    </div>
</section>


            <hr class="border-gray-50">

            <section class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <div><h2 class="text-xs font-black uppercase tracking-widest">03. Availability</h2></div>
                <div class="md:col-span-2">
                    <?php foreach($days as $day): 
                        $h = $saved_hours[$day] ?? ['open_time'=>'08:00', 'close_time'=>'18:00', 'is_closed'=>0];
                    ?>
                    <div class="time-row">
                        <span class="text-[10px] font-bold uppercase tracking-tight"><?= $day ?></span>
                        <div class="flex items-center gap-3">
                            <input type="time" name="hours[<?= $day ?>][open]" value="<?= $h['open_time'] ?>" class="text-xs font-bold border-b border-gray-100 outline-none p-1">
                            <span class="text-gray-300">—</span>
                            <input type="time" name="hours[<?= $day ?>][close]" value="<?= $h['close_time'] ?>" class="text-xs font-bold border-b border-gray-100 outline-none p-1">
                        </div>
                        <label class="cb-container flex items-center gap-2">
                            <input type="checkbox" name="hours[<?= $day ?>][is_closed]" <?= $h['is_closed'] ? 'checked' : '' ?>>
                            <span class="cb-custom"></span>
                            <span class="text-[9px] font-black text-gray-400 uppercase">Offline</span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <hr class="border-gray-50">

            <section class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <div><h2 class="text-xs font-black uppercase tracking-widest">04. Capabilities</h2></div>
                <div class="md:col-span-2 space-y-6">
                    <div id="services-container" class="space-y-4">
                        <?php foreach($saved_services as $i => $s): ?>
                        <div class="flex gap-4 items-end animate-fade-in">
                            <div class="input-group flex-1">
                                <label class="label-text">Service Name</label>
                                <input type="text" name="services[<?= $i ?>][name]" value="<?= htmlspecialchars($s['service_name']) ?>" class="clean-input">
                            </div>
                            <div class="input-group w-24">
                                <label class="label-text">Price</label>
                                <input type="number" name="services[<?= $i ?>][price]" value="<?= $s['base_price'] ?>" class="clean-input">
                            </div>
                            <button type="button" onclick="this.parentElement.remove()" class="pb-2 text-gray-300 hover:text-black transition"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addServiceRow()" class="text-[9px] font-black uppercase tracking-widest border border-gray-200 px-4 py-2 rounded-full hover:bg-black hover:text-white transition">+ Register New Service</button>
                </div>
            </section>

            <hr class="border-gray-50">

            <section class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <div><h2 class="text-xs font-black uppercase tracking-widest">05. Expertise</h2></div>
                <div class="md:col-span-2">
                    <div class="input-group">
                        <label class="label-text">Specialties (Comma Separated)</label>
                        <input type="text" name="specialty_list" value="<?= htmlspecialchars($saved_specs['list'] ?? '') ?>" placeholder="Engines, Hybrids, Auto Body" class="clean-input">
                    </div>
                </div>
            </section>

            <hr class="border-gray-50">

<section class="grid grid-cols-1 md:grid-cols-3 gap-12">
    <div>
        <h2 class="text-xs font-black uppercase tracking-widest">07. Gallery</h2>
        <p class="text-[9px] text-gray-400 mt-1 uppercase">Add images with captions</p>
    </div>
    
    <div class="md:col-span-2 space-y-6">
        <div class="p-6 rounded-2xl border border-gray-100 bg-gray-50/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="input-group">
                    <label class="label-text">Select Image</label>
                    <input type="file" name="gallery_image" id="gallery_image" class="clean-input text-[10px]">
                </div>
                <div class="input-group">
                    <label class="label-text">Caption</label>
                    <input type="text" name="gallery_caption" placeholder="Enter image caption" class="clean-input">
                </div>
            </div>
            <p class="text-[8px] text-gray-400 mt-2 uppercase tracking-tighter italic">* Image uploads when you click Sync Terminal below</p>
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-4 gap-4">
            <?php foreach($gallery_imgs as $img): ?>
                <div class="group relative aspect-square rounded-xl bg-gray-50 overflow-hidden border border-gray-100">
                    <img src="../uploads/gallery/<?= $img['image_path'] ?>" class="w-full h-full object-cover">
                    
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <button type="submit" 
                                name="delete_gallery_item" 
                                value="<?= $img['id'] ?>" 
                                formaction="profile_mgmt.php"
                                onclick="return confirm('Delete this image?');"
                                class="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center hover:bg-red-700 transition transform hover:scale-110">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>

                    <?php if(!empty($img['caption'])): ?>
                        <div class="absolute bottom-0 left-0 right-0 bg-black/60 p-1 pointer-events-none">
                            <p class="text-[8px] text-white text-center truncate uppercase"><?= htmlspecialchars($img['caption']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>




            <hr class="border-gray-50">

            <section class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <div><h2 class="text-xs font-black uppercase tracking-widest">08. Compliance</h2></div>
                <div class="md:col-span-2 space-y-6">
                    <div class="flex gap-4 items-end">
                        <div class="input-group flex-1">
                            <label class="label-text">Certificate Title</label>
                            <input type="text" name="cert_title" placeholder="e.g. Master Tech Cert" class="clean-input">
                        </div>
                        <label class="btn-black cursor-pointer py-3 px-6 h-fit">
                            <i class="fa-solid fa-file-arrow-up mr-2"></i> File
                            <input type="file" name="cert_file" class="hidden">
                        </label>
                    </div>
                    <?php foreach($certs as $c): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <span class="text-[10px] font-bold uppercase tracking-widest"><?= htmlspecialchars($c['title']) ?></span>
                            <a href="../uploads/certs/<?= $c['file_path'] ?>" target="_blank" class="text-[9px] font-black uppercase text-gray-400 hover:text-black">View Document</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="sticky-action">
                <div class="max-w-4xl mx-auto flex justify-between items-center">
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Awaiting Sync...</p>
                    <button type="submit" name="update_profile" class="btn-black">
                        Sync Terminal <i class="fa-solid fa-chevron-right ml-2 text-[8px]"></i>
                    </button>
                </div>
            </div>

        </form>
    </main>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('logo-preview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }

        let svcCount = <?= count($saved_services) ?>;
        function addServiceRow() {
            const container = document.getElementById('services-container');
            const row = document.createElement('div');
            row.className = 'flex gap-4 items-end opacity-0 transform translate-y-2 transition-all duration-300';
            row.innerHTML = `
                <div class="input-group flex-1"><label class="label-text">Service Name</label><input type="text" name="services[${svcCount}][name]" class="clean-input"></div>
                <div class="input-group w-24"><label class="label-text">Price</label><input type="number" name="services[${svcCount}][price]" class="clean-input"></div>
                <button type="button" onclick="this.parentElement.remove()" class="pb-2 text-gray-300 hover:text-black"><i class="fa-solid fa-xmark"></i></button>`;
            container.appendChild(row);
            setTimeout(() => { row.classList.remove('opacity-0', 'translate-y-2'); }, 10);
            svcCount++;
        }
    </script>
</body>
</html>
