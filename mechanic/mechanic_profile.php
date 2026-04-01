<?php
/**
 * GarageOS - Personnel Performance Profile
 * Features: Search, Filters, Full Job Modals, Profile Editing (including Password)
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

// Auth & Context
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php"); exit();
}

$mechanic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$garage_id = $_SESSION['user_id'];

// --- 1. HANDLE PROFILE UPDATE ---
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Update basic info
    $sql = "UPDATE users SET name = ?, phone = ?, email = ? WHERE id = ? AND garage_id = ?";
    $params = [$name, $phone, $email, $mechanic_id, $garage_id];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Update password if provided
    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $mechanic_id]);
    }

    // Handle Avatar Upload
    if (!empty($_FILES['avatar']['name'])) {
        $target_dir = "../uploads/avatars/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_ext = pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION);
        $file_name = "mech_" . $mechanic_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            $db_path = "uploads/avatars/" . $file_name;
            $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$db_path, $mechanic_id]);
        }
    }
    header("Location: mechanic_profile.php?id=$mechanic_id&success=1");
    exit();
}

// --- 2. FETCH MECHANIC IDENTITY ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND garage_id = ? AND role = 'mechanic'");
$stmt->execute([$mechanic_id, $garage_id]);
$mechanic = $stmt->fetch();

if (!$mechanic) { die("Personnel record not found."); }

// --- 3. PERFORMANCE METRICS ---
$stmt_metrics = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT ja.job_id) as total_jobs,
        SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) as jobs_completed,
        (SELECT SUM(quantity * unit_price) FROM job_items WHERE added_by_id = ?) as personal_billing
    FROM job_assignments ja
    JOIN jobs j ON ja.job_id = j.id
    WHERE ja.mechanic_id = ?
");
$stmt_metrics->execute([$mechanic_id, $mechanic_id]);
$stats = $stmt_metrics->fetch();

// --- 4. WORK HISTORY FEED ---
$stmt_history = $pdo->prepare("
    SELECT 
        j.*, 
        v.plate_no, v.make, v.model, v.color, v.year,
        sr.requested_service, sr.mileage,
        u_cust.name as customer_name, u_cust.phone as customer_phone,
        ja.is_lead,
        (SELECT JSON_ARRAYAGG(JSON_OBJECT('desc', description, 'qty', quantity, 'price', unit_price, 'type', type)) 
         FROM job_items WHERE job_id = j.id AND added_by_id = ?) as modal_items,
        (SELECT SUM(quantity * unit_price) FROM job_items WHERE job_id = j.id AND added_by_id = ?) as contribution_value
    FROM job_assignments ja
    JOIN jobs j ON ja.job_id = j.id
    JOIN service_requests sr ON j.request_id = sr.id
    JOIN vehicles v ON sr.vehicle_id = v.id
    JOIN users u_cust ON v.user_id = u_cust.id
    WHERE ja.mechanic_id = ?
    ORDER BY j.created_at DESC
");
$stmt_history->execute([$mechanic_id, $mechanic_id, $mechanic_id]);
$work_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mechanic['name'] ?> | Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; }
        .modal-blur { backdrop-filter: blur(8px); }
        .status-pill { font-size: 8px; font-weight: 900; text-transform: uppercase; padding: 4px 10px; border-radius: 99px; }
        .filter-btn.active { background: #0f172a; color: white; border-color: #0f172a; }
    </style>
</head>
<body class="pb-20">

    <nav class="bg-white border-b border-slate-200 px-6 py-4 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <a href="mechanics_section.php" class="flex items-center gap-2 text-slate-400 hover:text-slate-900 transition-colors">
                <i class="fa-solid fa-chevron-left text-xs"></i>
                <span class="text-[10px] font-black uppercase tracking-widest">Back to Team</span>
            </a>
            <h1 class="text-sm font-black italic uppercase tracking-tighter">Personnel Profile</h1>
            <button onclick="openEditModal()" class="w-10 h-10 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center text-slate-600 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                <i class="fa-solid fa-pen-to-square text-xs"></i>
            </button>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-6 mt-8">
        
        <div class="bg-white rounded-[2.5rem] p-8 border border-slate-200 shadow-sm mb-8 relative overflow-hidden">
            <?php if(isset($_GET['success'])): ?>
                <div class="absolute top-4 right-8 bg-emerald-500 text-white text-[8px] font-black uppercase px-4 py-1.5 rounded-full animate-bounce">Update Successful</div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row items-center gap-8">
                <div class="w-28 h-28 rounded-[2rem] bg-slate-900 flex items-center justify-center text-3xl font-black text-white italic overflow-hidden shadow-xl border-4 border-white">
                    <?php if(!empty($mechanic['avatar'])): ?>
                        <img src="../<?= htmlspecialchars($mechanic['avatar']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= strtoupper(substr($mechanic['name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="text-center md:text-left flex-1">
                    <h2 class="text-3xl font-black italic uppercase tracking-tighter text-slate-900"><?= htmlspecialchars($mechanic['name']) ?></h2>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Authorized Service Technician</p>
                    <div class="flex flex-wrap justify-center md:justify-start gap-3 mt-4">
                        <div class="bg-slate-50 px-4 py-2 rounded-full border border-slate-100 text-[9px] font-black uppercase text-slate-600">
                            <i class="fa-solid fa-phone mr-2 text-emerald-500"></i> <?= $mechanic['phone'] ?>
                        </div>
                        <div class="bg-slate-50 px-4 py-2 rounded-full border border-slate-100 text-[9px] font-black uppercase text-slate-600">
                            <i class="fa-solid fa-envelope mr-2 text-indigo-500"></i> <?= $mechanic['email'] ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mt-8 pt-8 border-t border-slate-50">
                <div class="text-center">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Assigned</p>
                    <p class="text-xl font-black text-slate-900"><?= $stats['total_jobs'] ?></p>
                </div>
                <div class="text-center border-x border-slate-100 px-4">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Personal Rev</p>
                    <p class="text-xl font-black text-emerald-600">KES <?= number_format($stats['personal_billing'] ?? 0) ?></p>
                </div>
                <div class="text-center">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest">In Progress</p>
                    <p class="text-xl font-black text-indigo-600"><?= ($stats['total_jobs'] - $stats['jobs_completed']) ?></p>
                </div>
            </div>
        </div>

        <div class="mb-6 space-y-4 px-2">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="jobSearch" onkeyup="filterJobs()" placeholder="SEARCH PLATE OR SERVICE..." 
                class="w-full bg-white border border-slate-200 rounded-2xl py-4 pl-12 pr-6 text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-indigo-500 outline-none transition-all shadow-sm">
            </div>

            <div class="flex items-center justify-between">
                <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400">Deployment History</h3>
                <div class="flex bg-white p-1 rounded-xl border border-slate-200 shadow-sm">
                    <button onclick="setStatusFilter('all', this)" class="filter-btn active px-4 py-1.5 rounded-lg text-[8px] font-black uppercase tracking-tighter transition-all">All</button>
                    <button onclick="setStatusFilter('ongoing', this)" class="filter-btn px-4 py-1.5 rounded-lg text-[8px] font-black uppercase tracking-tighter text-slate-400 transition-all">Ongoing</button>
                    <button onclick="setStatusFilter('completed', this)" class="filter-btn px-4 py-1.5 rounded-lg text-[8px] font-black uppercase tracking-tighter text-slate-400 transition-all">Completed</button>
                </div>
            </div>
        </div>
        
        <div id="jobHistoryFeed" class="space-y-4">
            <?php foreach($work_history as $job): ?>
            <div onclick='viewJobDetails(<?= json_encode($job) ?>)' 
                 data-status="<?= $job['status'] ?>"
                 data-search="<?= strtolower($job['plate_no'] . ' ' . $job['requested_service']) ?>"
                 class="job-card bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm flex items-center justify-between group hover:border-indigo-500 cursor-pointer transition-all">
                <div class="flex items-center gap-5">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                        <i class="fa-solid fa-car-side text-lg"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-black text-slate-900 uppercase italic tracking-tighter"><?= $job['plate_no'] ?></span>
                            <?php 
                                $s_class = match($job['status']) {
                                    'completed' => 'bg-emerald-100 text-emerald-600',
                                    'ongoing' => 'bg-amber-100 text-amber-600',
                                    'pending_release' => 'bg-indigo-100 text-indigo-600',
                                    default => 'bg-slate-100 text-slate-500'
                                };
                            ?>
                            <span class="status-pill <?= $s_class ?>"><?= str_replace('_', ' ', $job['status']) ?></span>
                        </div>
                        <p class="text-xs font-bold text-slate-600 mt-1"><?= htmlspecialchars($job['requested_service']) ?></p>
                    </div>
                </div>

                <div class="text-right">
                    <p class="text-[8px] font-black text-slate-400 uppercase">Billing</p>
                    <p class="text-sm font-black text-slate-900 mt-1">KES <?= number_format($job['contribution_value'] ?? 0) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

   <div id="editModal" class="hidden fixed inset-0 z-[100] bg-slate-900/40 modal-blur flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-8 md:p-10 shadow-2xl relative overflow-hidden">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-2xl font-black italic uppercase tracking-tighter text-slate-900">Edit Profile</h3>
                <button onclick="closeEditModal()" class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="flex items-center gap-4 mb-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <div class="w-16 h-16 rounded-2xl bg-slate-200 overflow-hidden relative group">
                        <img id="avatar_preview" src="../<?= $mechanic['avatar'] ?: 'assets/default_avatar.png' ?>" class="w-full h-full object-cover">
                        <label class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer transition-all">
                            <i class="fa-solid fa-camera text-white"></i>
                            <input type="file" name="avatar" class="hidden" onchange="previewImage(this)">
                        </label>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-900 uppercase">Profile Image</p>
                        <p class="text-[8px] font-bold text-slate-400 uppercase">Click image to change</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[8px] font-black text-slate-400 uppercase ml-2">Technician Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($mechanic['name']) ?>" required 
                        class="w-full bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="text-[8px] font-black text-slate-400 uppercase ml-2">Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($mechanic['phone']) ?>" required 
                        class="w-full bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="text-[8px] font-black text-slate-400 uppercase ml-2">Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($mechanic['email']) ?>" required 
                    class="w-full bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="bg-indigo-50 p-6 rounded-3xl border border-indigo-100">
                    <label class="text-[8px] font-black text-indigo-400 uppercase ml-2 tracking-widest">Security Update</label>
                    <div class="relative mt-2">
                        <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-indigo-300"></i>
                        <input type="password" name="password" placeholder="NEW PASSWORD (LEAVE BLANK TO KEEP)" 
                        class="w-full bg-white border-none rounded-xl py-4 pl-12 pr-4 text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <button type="submit" class="w-full bg-slate-900 text-white py-5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-xl">
                    Save Profile Changes
                </button>
            </form>
        </div>
   </div>

   <div id="jobModal" class="hidden fixed inset-0 z-[100] bg-slate-900/40 modal-blur flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white w-full max-w-xl rounded-[2.5rem] p-8 md:p-10 shadow-2xl my-auto">
        <div class="flex justify-between items-start mb-8">
            <div>
                <h3 id="m_plate" class="text-3xl font-black italic uppercase tracking-tighter text-slate-900">...</h3>
                <p id="m_vehicle" class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">...</p>
            </div>
            <button onclick="closeModal()" class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-400 hover:text-rose-500 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="bg-slate-50 p-5 rounded-3xl border border-slate-100">
                <p class="text-[7px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Customer Context</p>
                <p id="m_cust_name" class="text-[11px] font-black text-slate-900 uppercase">...</p>
                <p id="m_cust_phone" class="text-[10px] font-bold text-slate-500 mt-1">...</p>
            </div>
            <div class="bg-slate-50 p-5 rounded-3xl border border-slate-100">
                <p class="text-[7px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Primary Request</p>
                <p id="m_request" class="text-[11px] font-black text-indigo-600 uppercase leading-tight">...</p>
            </div>
            <div class="bg-slate-900 p-5 rounded-3xl col-span-2 grid grid-cols-3 gap-4">
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase mb-1">Current Mileage</p>
                    <p id="m_mileage" class="text-[10px] font-black text-white">...</p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase mb-1">Vehicle Specs</p>
                    <p id="m_specs" class="text-[10px] font-black text-white uppercase">...</p>
                </div>
                <div>
                    <p class="text-[7px] font-black text-slate-500 uppercase mb-1">Date Logged</p>
                    <p id="m_created" class="text-[10px] font-black text-white">...</p>
                </div>
            </div>
        </div>

        <div class="space-y-3 mb-8">
            <label class="text-[9px] font-black text-slate-300 uppercase tracking-[0.3em] ml-2">Items Logged by this Tech</label>
            <div id="m_items_container" class="space-y-2 max-h-[250px] overflow-y-auto pr-2"></div>
        </div>

        <div class="flex justify-between items-center pt-8 border-t border-slate-100">
            <div class="flex items-center gap-3">
                <span id="m_lead_badge" class="hidden px-3 py-1 bg-amber-100 text-amber-600 text-[8px] font-black uppercase rounded-lg">Lead Tech</span>
                <span id="m_status" class="status-pill">...</span>
            </div>
            <div class="text-right">
                <p class="text-[8px] font-black text-slate-400 uppercase">Billing Impact</p>
                <p id="m_total" class="text-2xl font-black text-slate-900">KES 0</p>
            </div>
        </div>
    </div>
</div>

<script>
let currentStatusFilter = 'all';

function openEditModal() { document.getElementById('editModal').classList.remove('hidden'); }
function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }

function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { document.getElementById('avatar_preview').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

function setStatusFilter(status, btn) {
    currentStatusFilter = status;
    document.querySelectorAll('.filter-btn').forEach(b => {
        b.classList.remove('active', 'text-white');
        b.classList.add('text-slate-400');
    });
    btn.classList.add('active');
    btn.classList.remove('text-slate-400');
    filterJobs();
}

function filterJobs() {
    const query = document.getElementById('jobSearch').value.toLowerCase();
    const cards = document.querySelectorAll('.job-card');
    cards.forEach(card => {
        const status = card.dataset.status;
        const searchText = card.dataset.search;
        const matchesSearch = searchText.includes(query);
        const matchesStatus = currentStatusFilter === 'all' || status === currentStatusFilter;
        if (matchesSearch && matchesStatus) {
            card.classList.remove('hidden');
            card.classList.add('flex');
        } else {
            card.classList.add('hidden');
            card.classList.remove('flex');
        }
    });
}

function viewJobDetails(job) {
    document.getElementById('m_plate').innerText = job.plate_no;
    document.getElementById('m_vehicle').innerText = `${job.make} ${job.model}`;
    document.getElementById('m_cust_name').innerText = job.customer_name;
    document.getElementById('m_cust_phone').innerText = job.customer_phone;
    document.getElementById('m_request').innerText = job.requested_service;
    document.getElementById('m_mileage').innerText = `${parseInt(job.mileage).toLocaleString()} KM`;
    document.getElementById('m_specs').innerText = `${job.year || 'N/A'} • ${job.color || 'N/A'}`;
    document.getElementById('m_created').innerText = new Date(job.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    const statusEl = document.getElementById('m_status');
    statusEl.innerText = job.status.replace('_', ' ');
    statusEl.className = 'status-pill ' + (job.status === 'completed' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600');
    document.getElementById('m_lead_badge').classList.toggle('hidden', !parseInt(job.is_lead));

    const container = document.getElementById('m_items_container');
    container.innerHTML = '';
    let total = 0;
    const items = JSON.parse(job.modal_items || '[]');
    if(items.length === 0) {
        container.innerHTML = '<div class="p-6 bg-slate-50 rounded-2xl text-center text-[9px] font-black text-slate-300 uppercase italic border border-dashed border-slate-200">No specific line items logged</div>';
    } else {
        items.forEach(item => {
            const sub = item.qty * item.price;
            total += sub;
            container.innerHTML += `
                <div class="flex justify-between items-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div>
                        <p class="text-[10px] font-black text-slate-900 uppercase tracking-tight">${item.desc}</p>
                        <p class="text-[8px] font-bold text-slate-400 uppercase">${item.qty} x KES ${parseInt(item.price).toLocaleString()}</p>
                    </div>
                    <p class="text-xs font-black text-slate-900">KES ${sub.toLocaleString()}</p>
                </div>`;
        });
    }
    document.getElementById('m_total').innerText = 'KES ' + total.toLocaleString();
    document.getElementById('jobModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('jobModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}
</script>

</body>
</html>
