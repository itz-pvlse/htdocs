<?php
/**
 * GarageOS - Trust Center & Verification
 * Logic: Updated with high-fidelity "Verified" tick and synchronized completion states
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

// --- FETCH GARAGE STATUS ---
try {
    $stmt = $pdo->prepare("SELECT name, location, logo, is_verified, is_pending, latitude, longitude FROM garages WHERE id = ?");
    $stmt->execute([$garage_id]);
    $garage = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $garage = ['name' => 'Garage', 'is_verified' => 0, 'is_pending' => 0, 'latitude' => 0, 'longitude' => 0];
}

// --- LOGIC: Check Completion States ---
$is_location_set = false;
if (!empty($garage['latitude']) && !empty($garage['longitude'])) {
    if (floatval($garage['latitude']) != 0 && floatval($garage['longitude']) != 0) {
        $is_location_set = true;
    }
}

// Step 3 is considered "complete" if pending review or already verified
$is_docs_complete = ($garage['is_pending'] == 1 || $garage['is_verified'] == 1);

// --- HANDLE DOCUMENT SUBMISSION ---
$msg = "";
if (isset($_POST['submit_docs'])) {
    $target_dir = "../uploads/verify/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $stmt = $pdo->prepare("UPDATE garages SET is_pending = 1 WHERE id = ?");
    if($stmt->execute([$garage_id])) {
        $msg = "Audit Request Received. We are reviewing your credentials.";
        $garage['is_pending'] = 1;
        $is_docs_complete = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trust Center | GarageOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F8FAFC; color: #0F172A; }
    </style>
</head>
<body class="bg-[#F8FAFC]">

    <main class="max-w-5xl mx-auto p-8 lg:p-12">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-12">
            <div class="flex items-center gap-5">
                <a href="../garage.php" 
                   class="w-12 h-12 bg-white border border-slate-200 rounded-full flex items-center justify-center text-slate-900 hover:bg-slate-900 hover:text-white transition-all shadow-sm group flex-shrink-0"
                   title="Back to Dashboard">
                    <i class="fa-solid fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-black italic uppercase tracking-tighter leading-none">Trust Center</h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mt-3">Identity & Market Authority</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4 bg-white p-3 pr-6 rounded-[2.5rem] border border-slate-200 shadow-sm">
                <div class="w-10 h-10 bg-slate-900 rounded-2xl flex items-center justify-center text-white overflow-hidden shadow-inner flex-shrink-0">
                     <img src="../uploads/logo/<?= $garage['logo'] ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($garage['name']) ?>&background=0F172A&color=fff'">
                </div>
                <div>
                    <p class="text-[8px] font-black uppercase text-slate-400 tracking-tighter mb-1">Current Status</p>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase italic tracking-tight whitespace-nowrap">
                            <?= $garage['is_verified'] ? 'Verified' : ($garage['is_pending'] ? 'Under Review' : 'Standard') ?>
                        </span>
                        <?php if($garage['is_verified']): ?>
                            <i class="fa-solid fa-circle-check text-emerald-500"></i>
                        <?php elseif($garage['is_pending']): ?>
                            <i class="fa-solid fa-clock text-indigo-500 animate-pulse"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-dot text-amber-500 animate-pulse"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!empty($msg)): ?>
            <div class="mb-10 p-6 bg-slate-900 rounded-[2.5rem] text-white shadow-2xl flex items-center justify-between border-b-4 border-indigo-500">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-indigo-500/20 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-shield-check text-indigo-400"></i>
                    </div>
                    <p class="text-[10px] font-black uppercase tracking-[0.1em]"><?= $msg ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-1 space-y-4">
                <div class="p-6 rounded-[2.5rem] border-2 border-emerald-500 bg-emerald-50/50 relative overflow-hidden transition-all">
                    <i class="fa-solid fa-check-circle absolute -right-2 -bottom-2 text-emerald-200/50 text-5xl"></i>
                    <p class="text-[8px] font-black text-emerald-600 uppercase tracking-widest mb-1">Step 01</p>
                    <h3 class="text-xs font-black uppercase text-slate-700">Identity Confirmed</h3>
                </div>

                <div class="p-6 rounded-[2.5rem] border-2 <?= $is_location_set ? 'border-emerald-500 bg-emerald-50/50' : 'border-rose-200 bg-white' ?> relative overflow-hidden transition-all">
                    <?php if($is_location_set): ?>
                        <i class="fa-solid fa-check-circle absolute -right-2 -bottom-2 text-emerald-200/50 text-5xl"></i>
                    <?php endif; ?>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[8px] font-black <?= $is_location_set ? 'text-emerald-600' : 'text-rose-500' ?> uppercase tracking-widest mb-1">Step 02</p>
                            <h3 class="text-xs font-black uppercase text-slate-700">Geographic Pin</h3>
                        </div>
                        <?php if(!$is_location_set): ?>
                             <span class="text-[7px] font-bold bg-rose-500 text-white px-2 py-0.5 rounded uppercase">Required</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-6 rounded-[2.5rem] border-2 <?= $is_docs_complete ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-200 bg-white' ?> relative overflow-hidden transition-all">
                    <?php if($is_docs_complete): ?>
                        <i class="fa-solid fa-check-circle absolute -right-2 -bottom-2 text-emerald-200/50 text-5xl"></i>
                    <?php endif; ?>
                    <p class="text-[8px] font-black <?= $is_docs_complete ? 'text-emerald-600' : 'text-slate-400' ?> uppercase tracking-widest mb-1">Step 03</p>
                    <h3 class="text-xs font-black uppercase text-slate-700">Documentation</h3>
                </div>
            </div>

            <div class="lg:col-span-2 bg-white rounded-[3.5rem] border border-slate-200 shadow-sm p-12">
                <?php if(!$is_location_set): ?>
                    <div class="text-center py-6">
                         <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">
                            <i class="fa-solid fa-location-crosshairs animate-bounce"></i>
                        </div>
                        <h2 class="text-xl font-black italic uppercase tracking-tighter">Coordinate Signal Missing</h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2 mb-8">You must lock your terminal's GPS position before submitting documents.</p>
                        <a href="settings.php" class="inline-block bg-slate-900 text-white px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition-all">
                            Go to Geographic Precision
                        </a>
                    </div>

                <?php elseif($garage['is_verified']): ?>
                    <div class="text-center py-10">
                        <div class="w-24 h-24 bg-emerald-500 text-white rounded-full flex items-center justify-center mx-auto mb-6 text-4xl shadow-[0_0_30px_rgba(16,185,129,0.3)]">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <h2 class="text-2xl font-black italic uppercase tracking-tighter">Terminal Verified</h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">You have full market authority.</p>
                    </div>

                <?php elseif($garage['is_pending']): ?>
                    <div class="text-center py-10">
                        <div class="w-24 h-24 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center mx-auto mb-6 text-4xl animate-pulse">
                            <i class="fa-solid fa-magnifying-glass-chart"></i>
                        </div>
                        <h2 class="text-2xl font-black italic uppercase tracking-tighter">Under Audit</h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2">Our team is reviewing your documents.</p>
                    </div>

                <?php else: ?>
                    <div class="mb-10">
                        <h2 class="text-xl font-black italic uppercase tracking-tighter">Market Compliance</h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2 leading-relaxed">
                            Upload your Business Permit and National ID to unlock the blue badge and priority ranking.
                        </p>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <label class="block p-10 border-2 border-dashed border-slate-200 rounded-[2.5rem] text-center hover:border-indigo-500 hover:bg-slate-50 transition-all cursor-pointer group relative">
                                <i class="fa-solid fa-file-contract text-2xl text-slate-300 group-hover:text-indigo-500 mb-4 block"></i>
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 block">Business Permit</span>
                                <p id="permitName" class="mt-2 text-[9px] font-bold text-indigo-600 uppercase break-all"></p>
                                <input type="file" name="permit" class="hidden" required onchange="updateFileName(this, 'permitName')">
                            </label>

                            <label class="block p-10 border-2 border-dashed border-slate-200 rounded-[2.5rem] text-center hover:border-indigo-500 hover:bg-slate-50 transition-all cursor-pointer group relative">
                                <i class="fa-solid fa-id-card text-2xl text-slate-300 group-hover:text-indigo-500 mb-4 block"></i>
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 block">Director's ID</span>
                                <p id="idName" class="mt-2 text-[9px] font-bold text-indigo-600 uppercase break-all"></p>
                                <input type="file" name="owner_id" class="hidden" required onchange="updateFileName(this, 'idName')">
                            </label>
                        </div>

                        <div class="bg-slate-50 p-6 rounded-[2rem] border border-slate-100 flex items-start gap-4">
                            <i class="fa-solid fa-circle-info text-indigo-500 mt-1"></i>
                            <p class="text-[9px] font-bold text-slate-500 uppercase leading-loose tracking-widest">
                                Verification typically takes <span class="text-slate-900 font-black">24-48 Hours</span>. Documents must be clear and valid.
                            </p>
                        </div>

                        <button type="submit" name="submit_docs" class="w-full bg-slate-900 text-white py-6 rounded-2xl font-black text-xs uppercase tracking-[0.3em] shadow-2xl hover:bg-indigo-600 transition-all">
                            Submit for Audit
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function updateFileName(input, targetId) {
            const fileName = input.files[0] ? input.files[0].name : "";
            const display = document.getElementById(targetId);
            if (fileName) {
                display.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> ${fileName}`;
            } else {
                display.innerText = "";
            }
        }
    </script>
</body>
</html>
