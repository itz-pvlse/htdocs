<?php
// vehicle_details.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$vehicle_id = $_GET['id'] ?? null;

if (!$vehicle_id) {
    header("Location: dashboard.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND user_id = ?");
$stmt->execute([$vehicle_id, $user_id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$car) {
    die("Vehicle not found or access denied.");
}

$stmtLogs = $pdo->prepare("
    SELECT sr.*, g.name as garage_name 
    FROM service_requests sr 
    JOIN garages g ON sr.garage_id = g.id 
    WHERE sr.vehicle_id = ? 
    ORDER BY sr.created_at DESC
");
$stmtLogs->execute([$vehicle_id]);
$history = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function getStatusColor($status) {
    return match(strtolower($status)) {
        'pending'   => 'text-amber-500 bg-amber-50',
        'approved'  => 'text-emerald-500 bg-emerald-50',
        'completed' => 'text-indigo-500 bg-indigo-50',
        default      => 'text-slate-400 bg-slate-50',
    };
}

// Public URL for QR Generation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$public_url = $protocol . $_SERVER['HTTP_HOST'] . "/v.php?id=" . $car['id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($car['make']) ?> Details | AutoLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .hero-grad { background: linear-gradient(180deg, rgba(15,23,42,0) 0%, rgba(15,23,42,0.9) 100%); }
        .modal { transition: all 0.3s ease; pointer-events: none; opacity: 0; visibility: hidden; z-index: 1000; }
        .modal.active { pointer-events: auto; opacity: 1; visibility: visible; }
        #qrcode img { display: inline-block; }
    </style>
</head>
<body class="pb-12">

    <div class="relative h-[400px] overflow-hidden bg-slate-900">
        <?php if(!empty($car['image_path']) && file_exists($car['image_path'])): ?>
            <img src="<?= e($car['image_path']) ?>" class="w-full h-full object-cover opacity-80">
        <?php else: ?>
            <div class="w-full h-full flex flex-col items-center justify-center bg-slate-800">
                <i class="fa-solid fa-car-side text-slate-700 text-8xl mb-2"></i>
            </div>
        <?php endif; ?>
        
        <div class="absolute inset-0 hero-grad flex flex-col justify-between p-6 md:p-12">
            <div class="flex justify-between items-center max-w-7xl mx-auto w-full">
                <a href="javascript:history.back()" class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-white backdrop-blur-md active:scale-95 transition-all">
    <i class="fa-solid fa-chevron-left text-xs"></i>
</a>

                <div class="flex gap-3">
                    <button onclick="toggleModal('editModal')" class="px-6 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center gap-2 text-white border border-white/20 hover:bg-white/20 transition-all font-bold text-sm">
                        <i class="fa-solid fa-pen-to-square text-xs"></i> <span class="hidden md:inline">Edit Machine</span>
                    </button>
                    <button onclick="toggleModal('deleteModal')" class="w-12 h-12 rounded-2xl bg-rose-500/20 backdrop-blur-md flex items-center justify-center text-rose-400 border border-rose-500/30">
                        <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="max-w-7xl mx-auto w-full text-center md:text-left pb-10">
                <h2 class="text-5xl md:text-7xl font-black text-white italic uppercase tracking-tighter mb-4">
                    <?= e($car['make']) ?> <span class="text-indigo-400"><?= e($car['model']) ?></span>
                </h2>
                <div class="flex justify-center md:justify-start gap-3">
                    <span class="px-4 py-2 bg-indigo-600 rounded-xl text-[10px] font-black text-white uppercase tracking-widest shadow-lg">
                        <?= e($car['plate_no']) ?>
                    </span>
                    <?php if($car['verification_status'] === 'approved'): ?>
                        <span class="px-4 py-2 bg-emerald-500 rounded-xl text-[10px] font-black text-white uppercase tracking-widest shadow-lg">
                            <i class="fa-solid fa-certificate mr-1"></i> Verified
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-6 -mt-10 relative z-30">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 p-8 grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Year</p>
                        <p class="text-lg font-black text-slate-900"><?= e($car['year']) ?></p>
                    </div>
                    <div class="border-x border-slate-100">
                        <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Engine</p>
                        <p class="text-lg font-black text-slate-900"><?= e($car['rating_cc']) ?>cc</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Logs</p>
                        <p class="text-lg font-black text-slate-900"><?= count($history) ?></p>
                    </div>
                </div>

                <div class="bg-white rounded-[3rem] p-8 shadow-sm border border-slate-100">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Service History</h3>
                        <a href="garages.php" class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">+ New Entry</a>
                    </div>
                    <div class="space-y-6">
                        <?php if(empty($history)): ?>
                            <div class="text-center py-10">
                                <p class="text-[10px] font-black text-slate-300 uppercase italic">No logs found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($history as $log): ?>
                                <div class="flex items-start gap-4 p-4 rounded-[2rem] hover:bg-slate-50 transition-all">
                                    <div class="w-12 h-12 rounded-2xl <?= getStatusColor($log['status']) ?> flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid <?= $log['status'] === 'completed' ? 'fa-check-double' : 'fa-wrench' ?> text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="text-[11px] font-black text-slate-900 uppercase italic"><?= e($log['requested_service']) ?></h4>
                                                <p class="text-[9px] font-bold text-indigo-600 uppercase mt-1"><?= e($log['garage_name']) ?></p>
                                            </div>
                                            <span class="text-[9px] font-black text-slate-400 uppercase bg-slate-100 px-2 py-1 rounded-lg">
                                                <?= date('M d', strtotime($log['created_at'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-8">
                <div class="bg-white p-10 rounded-[3rem] border border-slate-100 text-center shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-6 text-center">Digital Passport</p>
                    <div class="bg-slate-50 p-4 rounded-[2.5rem] mb-6 inline-block border border-slate-100 shadow-inner">
                        <div id="qrcode"></div>
                    </div>
                    <p class="text-[11px] font-bold text-slate-500 px-4 leading-relaxed mb-6">
                        Scan to view the <span class="text-indigo-600">Verified History</span> publicly.
                    </p>
                    <div class="flex flex-col gap-3">
                        <button onclick="copyShareLink()" class="w-full bg-indigo-600 text-white py-4 rounded-2xl text-[9px] font-black uppercase tracking-widest shadow-xl shadow-indigo-100">Copy Link</button>
                        <button onclick="printQR()" class="w-full bg-slate-900 text-white py-4 rounded-2xl text-[9px] font-black uppercase tracking-widest">Print Sticker</button>
                    </div>
                </div>

                <div class="bg-indigo-900 p-8 rounded-[2.5rem] text-white overflow-hidden relative shadow-xl shadow-indigo-200">
                    <i class="fa-solid fa-shield-halved absolute -right-4 -bottom-4 text-7xl text-indigo-800 opacity-50"></i>
                    <h4 class="text-[11px] font-black uppercase italic mb-2 relative z-10 tracking-widest">Verification Tip</h4>
                    <p class="text-[10px] text-indigo-100 font-medium leading-relaxed relative z-10">
                        Verified status is only awarded when a certified garage confirms your service logs.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <div id="deleteModal" class="modal fixed inset-0 flex items-center justify-center px-6">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('deleteModal')"></div>
        <div class="relative bg-white w-full max-w-sm rounded-[3rem] p-10 text-center shadow-2xl">
            <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-trash-can text-3xl"></i>
            </div>
            <h3 class="text-xl font-black uppercase italic mb-2">Delete Vehicle?</h3>
            <p class="text-[10px] text-slate-400 font-bold mb-8 uppercase tracking-widest">This cannot be undone.</p>
            <div class="flex flex-col gap-3">
                <a href="actions/delete_vehicle.php?id=<?= $car['id'] ?>" class="w-full bg-rose-500 py-4 rounded-2xl text-white font-black uppercase text-[10px] tracking-widest">Delete Forever</a>
                <button onclick="toggleModal('deleteModal')" class="w-full bg-slate-100 py-4 rounded-2xl text-slate-500 font-black uppercase text-[10px] tracking-widest">Go Back</button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal fixed inset-0 flex items-center justify-center px-6">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="toggleModal('editModal')"></div>
        <div class="relative bg-white w-full max-w-xl rounded-[3rem] p-10 shadow-2xl overflow-y-auto max-h-[90vh]">
            <h3 class="text-2xl font-black uppercase italic mb-8 text-indigo-600">Update Specs</h3>
            <form action="actions/edit_vehicle.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="id" value="<?= $car['id'] ?>">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-2">Make</label>
                        <input type="text" name="make" value="<?= e($car['make']) ?>" required class="w-full p-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-indigo-500 font-bold">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-2">Model</label>
                        <input type="text" name="model" value="<?= e($car['model']) ?>" required class="w-full p-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-indigo-500 font-bold">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-2">Year</label>
                        <input type="number" name="year" value="<?= e($car['year']) ?>" required class="w-full p-4 bg-slate-50 rounded-2xl border-none font-bold">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-2">CC</label>
                        <input type="number" name="rating_cc" value="<?= e($car['rating_cc']) ?>" required class="w-full p-4 bg-slate-50 rounded-2xl border-none font-bold">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-2">Plate</label>
                        <input type="text" name="plate_no" value="<?= e($car['plate_no']) ?>" required class="w-full p-4 bg-slate-50 rounded-2xl border-none font-bold uppercase">
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase mb-2 block ml-2">Change Image</label>
                    <input type="file" name="vehicle_image" accept="image/*" class="w-full p-4 bg-slate-50 rounded-2xl border-2 border-dashed border-slate-200">
                </div>
                <button type="submit" class="w-full bg-slate-900 py-5 rounded-2xl text-white font-black uppercase text-[10px] tracking-widest shadow-xl">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        // Initialize QR
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= $public_url ?>",
            width: 180,
            height: 180,
            colorDark : "#0f172a",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        function toggleModal(id) { 
            document.getElementById(id).classList.toggle('active'); 
        }

        function copyShareLink() {
            navigator.clipboard.writeText("<?= $public_url ?>").then(() => { alert('Public URL Copied!'); });
        }

        function printQR() {
            const canvas = document.querySelector('#qrcode canvas');
            if(!canvas) return;
            const dataUrl = canvas.toDataURL();
            const printWin = window.open('', '', 'width=400,height=500');
            printWin.document.write(`
                <div style="text-align:center; padding:40px; font-family:sans-serif;">
                    <h1 style="font-size:12px; text-transform:uppercase;">AutoLog QR Passport</h1>
                    <img src="${dataUrl}" style="width:250px;">
                    <p style="font-size:14px; font-weight:bold; margin-top:10px;"><?= e($car['make'] . ' ' . $car['model']) ?></p>
                </div>
            `);
            printWin.document.close();
            printWin.focus();
            setTimeout(() => { printWin.print(); printWin.close(); }, 500);
        }
    </script>
</body>
</html>