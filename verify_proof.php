<?php
// verify_proof.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/**
 * Security Helper: Escape HTML output
 */
function e($s) { 
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); 
}

// Get the vehicle ID from the URL if it exists (from Dashboard "Verify +" link)
$preselected_vehicle_id = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

/**
 * FETCH VEHICLES
 * We only show vehicles that are NOT yet 'Verified' and NOT already 'Pending' review
 */
$stmt = $pdo->prepare("SELECT id, plate_no, make, model FROM vehicles WHERE user_id = ? AND verification_status NOT IN ('Verified', 'Pending')");
$stmt->execute([$user_id]);
$my_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * HANDLE FILE UPLOAD
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logbook'])) {
    $vehicle_id = $_POST['vehicle_id'];
    
    // Create directory if it doesn't exist
    $target_dir = "uploads/proofs/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $file_ext = strtolower(pathinfo($_FILES["logbook"]["name"], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

    if (in_array($file_ext, $allowed)) {
        $file_name = "proof_" . $vehicle_id . "_" . uniqid() . "." . $file_ext;
        $target_path = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['logbook']['tmp_name'], $target_path)) {
            // Update the record to 'Pending'
            $update = $pdo->prepare("UPDATE vehicles SET logbook_proof_path = ?, verification_status = 'Pending' WHERE id = ? AND user_id = ?");
            if ($update->execute([$target_path, $vehicle_id, $user_id])) {
                $success_msg = "Application submitted! Our team will review your logbook shortly.";
            } else {
                $error_msg = "Database update failed.";
            }
        } else {
            $error_msg = "Failed to upload file. Check folder permissions.";
        }
    } else {
        $error_msg = "Invalid file type. Please upload a PDF or Image (JPG/PNG).";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Verification | AutoLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="p-6">

    <div class="max-w-xl mx-auto mb-10">
        <div class="flex items-center gap-4 mb-6">
            <a href="dashboard.php" class="w-10 h-10 rounded-full bg-white border flex items-center justify-center text-slate-400 hover:text-indigo-600 transition-all shadow-sm">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-black italic uppercase tracking-tighter">Official <span class="text-indigo-600">Review</span></h1>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Upgrade to Verified Status</p>
            </div>
        </div>

        <?php if($success_msg): ?>
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 p-5 rounded-[2rem] mb-8 flex items-center gap-4">
                <i class="fa-solid fa-circle-check text-xl"></i>
                <div class="text-xs font-bold uppercase tracking-wide"><?= $success_msg ?></div>
                <script>setTimeout(() => { window.location.href='dashboard.php'; }, 2500);</script>
            </div>
        <?php endif; ?>

        <?php if($error_msg): ?>
            <div class="bg-rose-50 border border-rose-100 text-rose-600 p-5 rounded-[2rem] mb-8 flex items-center gap-4">
                <i class="fa-solid fa-circle-exclamation text-xl"></i>
                <div class="text-xs font-bold uppercase tracking-wide"><?= $error_msg ?></div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="bg-white p-6 rounded-[2.5rem] border border-slate-100 shadow-sm">
                <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-2 block">Select Vehicle</label>
                <select name="vehicle_id" required 
                        class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 font-bold text-slate-900 focus:border-indigo-500 outline-none appearance-none">
                    <option value="">Choose a vehicle...</option>
                    <?php foreach($my_vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= ($v['id'] == $preselected_vehicle_id) ? 'selected' : '' ?>>
                            <?= e($v['make']) ?> <?= e($v['model']) ?> (<?= e($v['plate_no']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="bg-slate-900 p-8 rounded-[2.5rem] shadow-xl text-center relative group overflow-hidden">
                <div class="relative z-10">
                    <div class="w-16 h-16 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-file-pdf text-2xl text-indigo-400"></i>
                    </div>
                    <h3 class="text-white font-black uppercase italic tracking-wider">Upload Logbook</h3>
                    <p class="text-[9px] text-slate-400 font-bold uppercase mt-1">PDF, JPG, or PNG (Max 5MB)</p>
                    
                    <input type="file" name="logbook" id="logbook_input" required accept=".pdf, .jpg, .jpeg, .png" 
                           class="absolute inset-0 opacity-0 cursor-pointer" onchange="updateFileName(this)">
                </div>
                <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-indigo-600/20 blur-3xl rounded-full"></div>
            </div>

            <div id="file-name-display" class="hidden text-center text-[10px] font-black text-indigo-600 uppercase bg-indigo-50 py-2 rounded-full">
            </div>

            <button type="submit" class="w-full bg-indigo-600 py-6 rounded-[2.5rem] text-white font-black uppercase tracking-widest shadow-xl active:scale-95 transition-all hover:bg-indigo-700">
                Submit for Verification
            </button>
        </form>

        <div class="mt-8 p-6 bg-slate-100 rounded-[2rem] border border-slate-200">
            <h4 class="text-[10px] font-black text-slate-500 uppercase mb-2 flex items-center gap-2">
                <i class="fa-solid fa-circle-info"></i> Why verify?
            </h4>
            <p class="text-[10px] leading-relaxed text-slate-500 font-medium">
                Official verification increases your vehicle's trust score. Verified vehicles are eligible for premium logs, 
                official service stamps, and higher resale visibility within the AutoLog ecosystem.
            </p>
        </div>
    </div>

    <script>
        /**
         * Update UI when file is chosen
         */
        function updateFileName(input) {
            const display = document.getElementById('file-name-display');
            if (input.files && input.files[0]) {
                display.innerText = "Selected: " + input.files[0].name;
                display.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>