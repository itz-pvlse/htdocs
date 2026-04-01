<?php
/**
 * GarageOS - Workforce Command Center (PRO)
 * Features: Efficiency Tracking, Revenue Analytics, Avatar Enrollment
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

// --- 1. HANDLE ENROLLMENT WITH AVATAR ---
if (isset($_POST['add_mechanic'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $avatar_path = NULL;

    // Handle Image Upload
    if (!empty($_FILES['avatar']['name'])) {
        $target_dir = "../uploads/avatars/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION);
        $file_name = "mech_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $file_ext;
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            $avatar_path = "uploads/avatars/" . $file_name;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, avatar, role, garage_id) VALUES (?, ?, ?, ?, ?, 'mechanic', ?)");
    if($stmt->execute([$name, $email, $phone, $password, $avatar_path, $garage_id])) {
        header("Location: mechanics_section.php?msg=Personnel+Enrolled"); exit();
    }
}

// --- 2. HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id=? AND garage_id=?")->execute([(int)$_GET['delete'], $garage_id]);
    header("Location: mechanics_section.php?msg=Personnel+Removed"); exit();
}

// --- 3. FETCH PERSONNEL WITH INTELLIGENT METRICS ---
$stmt = $pdo->prepare("
    SELECT 
        u.*, 
        (SELECT COUNT(DISTINCT ja.job_id) FROM job_assignments ja JOIN jobs j ON ja.job_id = j.id WHERE ja.mechanic_id = u.id AND j.status != 'completed') as active_tasks,
        (SELECT COUNT(DISTINCT ja.job_id) FROM job_assignments ja JOIN jobs j ON ja.job_id = j.id WHERE ja.mechanic_id = u.id AND j.status = 'completed') as finished_tasks,
        (SELECT SUM(quantity * unit_price) FROM job_items WHERE added_by_id = u.id) as personal_revenue,
        (SELECT COUNT(*) FROM job_assignments WHERE mechanic_id = u.id) as total_assignments
    FROM users u 
    WHERE u.role = 'mechanic' AND u.garage_id = ? 
    ORDER BY personal_revenue DESC, active_tasks ASC
");
$stmt->execute([$garage_id]);
$mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_revenue = array_sum(array_column($mechanics, 'personal_revenue'));
$total_active = array_sum(array_column($mechanics, 'active_tasks'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workforce Command | GarageOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F1F5F9; color: #0F172A; }
        .glass-nav { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(14px); }
        .tech-card { transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease; }
        .tech-card:hover { transform: translateY(-8px); }
        .efficiency-bar { transition: width 1s ease-in-out; }
    </style>
</head>
<body class="pb-24">

    <nav class="glass-nav border-b border-slate-200 sticky top-0 z-50 px-8 py-5">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="../garage.php" class="group flex items-center gap-3">
                    <div class="w-11 h-11 bg-white border border-slate-200 rounded-2xl flex items-center justify-center text-slate-400 group-hover:bg-slate-900 group-hover:text-white transition-all shadow-sm">
                        <i class="fa-solid fa-arrow-left-long text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-sm font-black uppercase tracking-tighter leading-none">Command Center</h1>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-1">Workforce Management</p>
                    </div>
                </a>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden lg:flex items-center bg-slate-100 rounded-2xl px-4 py-2 border border-slate-200">
                    <i class="fa-solid fa-magnifying-glass text-slate-400 text-xs mr-3"></i>
                    <input type="text" id="techSearch" onkeyup="filterTechs()" placeholder="Quick filter personnel..." 
                    class="bg-transparent border-none text-[11px] font-bold w-56 focus:ring-0 outline-none uppercase tracking-widest placeholder:text-slate-400">
                </div>
                <button onclick="openModal()" class="bg-indigo-600 text-white px-7 py-3.5 rounded-2xl text-[10px] font-black uppercase tracking-[0.15em] shadow-lg shadow-indigo-100 hover:bg-slate-900 hover:shadow-none transition-all active:scale-95">
                    Enroll Personnel
                </button>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-8 mt-12">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm relative overflow-hidden">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Deployed Workforce</p>
                <h2 class="text-4xl font-black italic tracking-tighter"><?= count($mechanics) ?></h2>
                <i class="fa-solid fa-users-gear absolute -right-4 -bottom-4 text-slate-50 text-7xl"></i>
            </div>
            
            <div class="bg-slate-900 p-7 rounded-[2.5rem] text-white shadow-2xl relative overflow-hidden">
                <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Live Queue Load</p>
                <div class="flex items-end gap-2">
                    <h2 class="text-4xl font-black italic text-emerald-400 leading-none"><?= $total_active ?></h2>
                    <span class="text-[10px] font-black uppercase text-slate-500 pb-1 italic">Tasks</span>
                </div>
                <div class="absolute top-6 right-6 flex gap-1"><span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span></div>
            </div>

            <div class="bg-white p-7 rounded-[2.5rem] border border-slate-200 shadow-sm lg:col-span-2">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Fleet Revenue Impact</p>
                <h2 class="text-3xl font-black italic tracking-tighter">KES <?= number_format($total_revenue) ?></h2>
            </div>
        </div>

        <div id="techContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($mechanics as $m): 
                $efficiency = $m['total_assignments'] > 0 ? round(($m['finished_tasks'] / $m['total_assignments']) * 100) : 0;
            ?>
            <div class="tech-card bg-white rounded-[3rem] p-8 border border-slate-200 shadow-sm hover:shadow-2xl group relative overflow-hidden" data-name="<?= strtolower($m['name']) ?>">
                
                <div class="absolute top-8 right-8">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full <?= $m['active_tasks'] > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $m['active_tasks'] > 0 ? 'bg-amber-500' : 'bg-emerald-500' ?>"></span>
                        <span class="text-[9px] font-black uppercase tracking-tighter"><?= $m['active_tasks'] > 0 ? 'Busy' : 'Available' ?></span>
                    </div>
                </div>

                <div class="flex items-center gap-6 mb-8">
                    <div class="w-20 h-20 rounded-[2rem] bg-slate-900 flex items-center justify-center text-3xl font-black text-white italic shadow-xl border-4 border-white group-hover:scale-110 transition-transform overflow-hidden">
                        <?php if(!empty($m['avatar'])): ?>
                            <img src="../<?= $m['avatar'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?= strtoupper(substr($m['name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-900 uppercase italic tracking-tighter text-2xl leading-none"><?= htmlspecialchars($m['name']) ?></h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">Technician</p>
                    </div>
                </div>

                <div class="mb-8 space-y-3">
                    <div class="flex justify-between items-end">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Efficiency Rating</p>
                        <p class="text-xs font-black text-indigo-600"><?= $efficiency ?>%</p>
                    </div>
                    <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="efficiency-bar h-full bg-indigo-500 rounded-full" style="width: <?= $efficiency ?>%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-8">
                    <div class="bg-slate-50 p-5 rounded-[2rem] border border-slate-100">
                        <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Total Revenue</p>
                        <p class="text-sm font-black text-slate-900">KES <?= number_format($m['personal_revenue'] ?? 0) ?></p>
                    </div>
                    <div class="bg-slate-50 p-5 rounded-[2rem] border border-slate-100">
                        <p class="text-[8px] font-black text-slate-400 uppercase mb-1">Queue</p>
                        <p class="text-sm font-black text-slate-900"><?= $m['active_tasks'] ?> <span class="text-[9px] text-slate-400 font-bold italic">Jobs</span></p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="mechanic_profile.php?id=<?= $m['id'] ?>" class="flex-1 bg-slate-900 text-white py-4 rounded-2xl text-[10px] font-black uppercase text-center tracking-[0.2em] hover:bg-indigo-600 transition-all shadow-lg">View Intelligence</a>
                    <button onclick="confirmDelete(<?= $m['id'] ?>)" class="w-14 h-14 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all shadow-sm">
                        <i class="fa-solid fa-trash-can text-sm"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <div id="addMechanicModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-900/40 backdrop-blur-xl">
        <div class="bg-white w-full max-w-lg rounded-[3.5rem] p-10 shadow-2xl scale-95 animate-in zoom-in duration-300">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="text-3xl font-black italic uppercase tracking-tighter">Deploy Tech</h3>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Enroll new service professional</p>
                </div>
                <button onclick="closeModal()" class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 hover:text-rose-500 transition-all"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <div class="flex flex-col items-center mb-4">
                    <div class="relative group">
                        <div class="w-24 h-24 rounded-[2rem] bg-slate-100 border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden transition-all group-hover:border-indigo-400">
                            <img id="avatarPreview" src="#" class="hidden w-full h-full object-cover">
                            <i id="avatarPlaceholder" class="fa-solid fa-user-plus text-2xl text-slate-300"></i>
                        </div>
                        <label class="absolute -bottom-2 -right-2 bg-indigo-600 text-white w-8 h-8 rounded-xl flex items-center justify-center cursor-pointer shadow-lg hover:bg-slate-900 transition-colors">
                            <i class="fa-solid fa-camera text-[10px]"></i>
                            <input type="file" name="avatar" class="hidden" accept="image/*" onchange="previewFile(this)">
                        </label>
                    </div>
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-3">Upload Profile Image</p>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-4 tracking-widest">Technician Identity</label>
                    <input type="text" name="name" placeholder="FULL NAME" required class="w-full p-4 bg-slate-50 rounded-2xl text-[11px] font-black uppercase tracking-widest outline-none border-2 border-transparent focus:border-indigo-500 focus:bg-white transition-all">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-4 tracking-widest">Email</label>
                        <input type="email" name="email" placeholder="EMAIL" required class="w-full p-4 bg-slate-50 rounded-2xl text-[11px] font-black uppercase tracking-widest outline-none border-2 border-transparent focus:border-indigo-500 focus:bg-white transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-slate-400 uppercase ml-4 tracking-widest">Phone</label>
                        <input type="text" name="phone" placeholder="PHONE" required class="w-full p-4 bg-slate-50 rounded-2xl text-[11px] font-black uppercase tracking-widest outline-none border-2 border-transparent focus:border-indigo-500 focus:bg-white transition-all">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black text-slate-400 uppercase ml-4 tracking-widest">Security Access Key</label>
                    <input type="password" name="password" placeholder="PASSWORD" required class="w-full p-4 bg-slate-50 rounded-2xl text-[11px] font-black uppercase tracking-widest outline-none border-2 border-transparent focus:border-indigo-500 focus:bg-white transition-all">
                </div>

                <button type="submit" name="add_mechanic" class="w-full bg-slate-900 text-white py-5 rounded-[2rem] font-black text-xs uppercase tracking-[0.3em] mt-2 shadow-2xl hover:bg-indigo-600 transition-all">
                    Commit Deployment
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal() { 
            const m = document.getElementById('addMechanicModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
        function closeModal() { 
            const m = document.getElementById('addMechanicModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
        }
        
        function previewFile(input) {
            const preview = document.getElementById('avatarPreview');
            const placeholder = document.getElementById('avatarPlaceholder');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function filterTechs() {
            const query = document.getElementById('techSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.tech-card');
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                card.style.display = name.includes(query) ? 'block' : 'none';
            });
        }

        function confirmDelete(id) {
            if(confirm('TERMINAL ACTION: Offboard this personnel?')) {
                window.location.href = 'mechanics_section.php?delete=' + id;
            }
        }
    </script>
</body>
</html>
