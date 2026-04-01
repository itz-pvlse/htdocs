<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php");
    exit();
}

$garage_id = $_SESSION['user_id'];

// --- FILTERS ---
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// --- BUILD QUERY (Fixed for Workforce Team assignments) ---
$query = "
    SELECT 
        j.*, 
        v.plate_no, v.make, v.model,
        sr.requested_service,
        u_cust.name as customer_name,
        -- Subquery to get the list of all mechanics assigned to this job
        (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') 
         FROM job_assignments ja 
         JOIN users u ON ja.mechanic_id = u.id 
         WHERE ja.job_id = j.id) as technician_names
    FROM jobs j
    JOIN service_requests sr ON j.request_id = sr.id
    JOIN vehicles v ON sr.vehicle_id = v.id
    JOIN users u_cust ON v.user_id = u_cust.id
    WHERE sr.garage_id = ? AND j.status = 'completed'
";

$params = [$garage_id];

if (!empty($search)) {
    $query .= " AND (v.plate_no LIKE ? OR u_cust.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) { $query .= " AND DATE(j.completed_at) >= ?"; $params[] = $date_from; }
if (!empty($date_to)) { $query .= " AND DATE(j.completed_at) <= ?"; $params[] = $date_to; }

$query .= " ORDER BY j.completed_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_revenue = array_sum(array_column($history, 'final_total'));
$job_count = count($history);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service History | Garage Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen font-sans pb-20">

    <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200 px-4 py-4">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="jobs_mgmt.php" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center hover:bg-slate-900 hover:text-white transition-all">
                    <i class="fa-solid fa-arrow-left text-xs"></i>
                </a>
                <div>
                    <h1 class="text-sm font-black uppercase tracking-tight text-slate-900">Service History</h1>
                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-tighter">Completed Job Logs</p>
                </div>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 bg-indigo-50 rounded-full border border-indigo-100">
                <i class="fa-solid fa-box-archive text-[10px] text-indigo-600"></i>
                <span class="text-[9px] font-black uppercase text-indigo-600"><?= $job_count ?> Records</span>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto p-4 md:p-6">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <div class="bg-white border border-slate-200 p-5 rounded-3xl shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fa-solid fa-money-bill-trend-up"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Historical Revenue</p>
                    <p class="text-2xl font-black text-slate-900 italic">KES <?= number_format($total_revenue, 2) ?></p>
                </div>
            </div>
            <div class="bg-slate-900 p-5 rounded-3xl shadow-lg flex items-center gap-4 text-white">
                <div class="w-12 h-12 bg-slate-800 text-indigo-400 rounded-2xl flex items-center justify-center text-xl">
                    <i class="fa-solid fa-check-double"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Successful Releases</p>
                    <p class="text-2xl font-black italic"><?= $job_count ?> Units</p>
                </div>
            </div>
        </div>

        <section class="mb-8 bg-white border border-slate-200 p-5 rounded-[2rem] shadow-sm">
            <form method="GET" class="flex flex-col gap-4">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search Plate or Customer..." 
                           class="w-full pl-10 pr-4 py-4 bg-slate-50 border border-slate-100 rounded-2xl text-xs font-bold focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input type="date" name="date_from" value="<?= $date_from ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-[10px] font-bold outline-none">
                    <input type="date" name="date_to" value="<?= $date_to ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-100 rounded-xl text-[10px] font-bold outline-none">
                </div>
                <div class="flex gap-2 mt-2">
                    <button type="submit" class="flex-1 bg-slate-900 text-white py-4 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-indigo-600 transition-all">
                        Filter Results
                    </button>
                    <?php if(!empty($search) || !empty($date_from)): ?>
                        <a href="job_history.php" class="p-4 bg-rose-50 text-rose-500 rounded-2xl hover:bg-rose-500 hover:text-white transition-all">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <div class="hidden md:block bg-white border border-slate-200 rounded-[3rem] overflow-hidden shadow-sm">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase">Date</th>
                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase">Unit & Customer</th>
                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase text-right">Total (KES)</th>
                        <th class="p-6 text-[10px] font-black text-slate-400 uppercase text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($history as $job): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="p-6">
                            <p class="text-xs font-black text-slate-900"><?= date('d M Y', strtotime($job['completed_at'])) ?></p>
                            <p class="text-[9px] text-indigo-500 font-bold italic uppercase tracking-tight">
                                <i class="fa-solid fa-wrench mr-1"></i><?= $job['technician_names'] ?: 'System Release' ?>
                            </p>
                        </td>
                        <td class="p-6">
                            <div class="flex items-center gap-3">
                                <span class="bg-slate-900 text-white text-[9px] font-black px-2 py-1 rounded italic"><?= $job['plate_no'] ?></span>
                                <div>
                                    <p class="text-xs font-extrabold text-slate-800 uppercase"><?= $job['make'] ?> <?= $job['model'] ?></p>
                                    <p class="text-[10px] text-indigo-500 font-bold"><?= $job['customer_name'] ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="p-6 text-right">
                            <p class="text-sm font-black text-slate-900 italic"><?= number_format($job['final_total'], 2) ?></p>
                        </td>
                        <td class="p-6">
                            <div class="flex items-center justify-center gap-2">
                                <a href="generate_invoice.php?job_id=<?= $job['id'] ?>" class="w-10 h-10 flex items-center justify-center bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-900 hover:text-white transition-all">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                </a>
                                <button onclick="window.open('generate_invoice.php?job_id=<?= $job['id'] ?>', '_blank')" class="w-10 h-10 flex items-center justify-center bg-indigo-50 text-indigo-500 rounded-xl hover:bg-indigo-600 hover:text-white transition-all">
                                    <i class="fa-solid fa-print text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-4">
            <?php if(empty($history)): ?>
                <div class="text-center py-20 bg-white border border-dashed border-slate-300 rounded-[2rem]">
                    <i class="fa-solid fa-folder-open text-slate-200 text-5xl mb-3"></i>
                    <p class="text-xs font-black text-slate-400 uppercase">No History Found</p>
                </div>
            <?php endif; ?>

            <?php foreach ($history as $job): ?>
            <div class="bg-white border border-slate-200 p-5 rounded-[2.5rem] shadow-sm">
                <div class="flex justify-between items-start mb-4">
                    <div class="bg-slate-900 text-white text-[10px] font-black px-3 py-1.5 rounded-xl italic">
                        <?= $job['plate_no'] ?>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest italic">Completed</p>
                        <p class="text-xs font-black text-slate-900"><?= date('d M Y', strtotime($job['completed_at'])) ?></p>
                    </div>
                </div>
                
                <div class="mb-5">
                    <h3 class="font-black text-slate-900 uppercase text-lg leading-tight"><?= $job['make'] ?> <?= $job['model'] ?></h3>
                    <p class="text-[10px] font-bold text-indigo-500 mt-1 uppercase tracking-tight">Owner: <?= $job['customer_name'] ?></p>
                    <div class="mt-2 flex items-center gap-2">
                         <i class="fa-solid fa-users-gear text-[10px] text-slate-300"></i>
                         <p class="text-[9px] font-black text-slate-400 uppercase"><?= $job['technician_names'] ?: 'System' ?></p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <div>
                        <p class="text-[8px] font-black text-slate-400 uppercase italic">Paid Amount</p>
                        <p class="text-lg font-black text-slate-900 italic">KES <?= number_format($job['final_total'], 2) ?></p>
                    </div>
                    <div class="flex gap-2">
                       <a href="view_job_details.php?job_id=<?= $job['id'] ?>" class="w-12 h-12 flex items-center justify-center bg-slate-900 text-white rounded-2xl shadow-lg hover:bg-indigo-600 transition-all">
    <i class="fa-solid fa-eye"></i>
</a>

                        <button onclick="window.open('generate_invoice.php?job_id=<?= $job['id'] ?>', '_blank')" class="w-12 h-12 flex items-center justify-center bg-indigo-600 text-white rounded-2xl shadow-lg">
                            <i class="fa-solid fa-print"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </main>

</body>
</html>
