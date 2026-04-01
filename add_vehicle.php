<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

/**
 * Server-side VIN Checksum Validation
 */
function is_valid_vin_checksum($vin) {
    $vin = strtoupper($vin);
    if (strlen($vin) != 17) return false;

    $weights = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];
    $transliterations = [
        'A'=>1, 'B'=>2, 'C'=>3, 'D'=>4, 'E'=>5, 'F'=>6, 'G'=>7, 'H'=>8,
        'J'=>1, 'K'=>2, 'L'=>3, 'M'=>4, 'N'=>5, 'P'=>7, 'R'=>9, 'S'=>2,
        'T'=>3, 'U'=>4, 'V'=>5, 'W'=>6, 'X'=>7, 'Y'=>8, 'Z'=>9
    ];

    $sum = 0;
    for ($i = 0; $i < 17; $i++) {
        $char = $vin[$i];
        $val = is_numeric($char) ? (int)$char : ($transliterations[$char] ?? null);
        if ($val === null) return false; 
        $sum += $val * $weights[$i];
    }

    $checkDigit = $sum % 11;
    $expected = ($checkDigit == 10) ? 'X' : (string)$checkDigit;
    return $vin[8] === $expected;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_car'])) {
    $image_path = null;

    $vin = strtoupper(trim($_POST['chassis_number'] ?? ''));
    $engine_no = strtoupper(trim($_POST['engine_number'] ?? ''));
    $plate_no = strtoupper(trim($_POST['plate_no'] ?? ''));
    $clean_plate = str_replace(' ', '', $plate_no); 
    
    $blacklist = ['123456', '000000', '111111', 'ABCDEF', 'KAA000A'];

    $plate_pattern = "/^[K][A-Z]{2}\d{3}[A-Z]$/i";
    
    if (!preg_match($plate_pattern, $clean_plate)) {
        $error_msg = "Invalid Plate Format.";
    } elseif (strlen($vin) !== 17) {
        $error_msg = "Invalid VIN Length.";
    } elseif (in_array($vin, $blacklist) || in_array($clean_plate, $blacklist)) {
        $error_msg = "Restricted dummy data detected.";
    }

    if (empty($error_msg)) {
        $is_system_verified = is_valid_vin_checksum($vin) ? 1 : 0;

        if (isset($_FILES['car_image']) && $_FILES['car_image']['error'] === 0) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $file_ext = strtolower(pathinfo($_FILES["car_image"]["name"], PATHINFO_EXTENSION));
            $image_path = $target_dir . uniqid() . "." . $file_ext;
            move_uploaded_file($_FILES["car_image"]["tmp_name"], $image_path);
        }

        $data = [
            'user_id'           => $user_id,
            'plate_no'          => $plate_no,
            'make'              => trim($_POST['make'] ?? ''),
            'model'             => trim($_POST['model'] ?? ''),
            'year'              => $_POST['year'] ?? date('Y'),
            'color'             => trim($_POST['color'] ?? 'Not Specified'),
            'chassis_number'    => $vin,
            'engine_number'     => $engine_no,
            'fuel_type'         => $_POST['fuel_type'] ?? 'Petrol',
            'transmission_type' => $_POST['transmission_type'] ?? 'Manual',
            'rating_cc'         => (int)($_POST['rating_cc'] ?? 0),
            'current_mileage'   => (int)($_POST['current_mileage'] ?? 0),
            'image_path'        => $image_path,
            'is_system_verified' => $is_system_verified
        ];

        try {
            $sql = "INSERT INTO vehicles (
                        user_id, plate_no, make, model, year, color, 
                        chassis_number, engine_number, fuel_type, 
                        transmission_type, rating_cc, current_mileage, image_path, is_system_verified, created_at
                    ) VALUES (
                        :user_id, :plate_no, :make, :model, :year, :color, 
                        :chassis_number, :engine_number, :fuel_type, 
                        :transmission_type, :rating_cc, :current_mileage, :image_path, :is_system_verified, NOW()
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            $success_msg = "Vehicle Registered. Status: " . ($is_system_verified ? "System Verified ✅" : "Pending Proof ⚠️");
        } catch (PDOException $e) {
            $error_msg = ($e->getCode() == 23000) ? "Vehicle already exists." : "DB Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | AutoLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .form-input-styled:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); outline: none; }
    </style>
</head>
<body class="p-4 md:p-6 pb-20">

    <div class="max-w-2xl mx-auto mb-8 flex items-center justify-between">
        <div class="flex items-center gap-4">
<a href="javascript:history.back()" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-900 shadow-lg active:scale-90 transition-all hover:bg-slate-50">
    <i class="fa-solid fa-arrow-left text-xs"></i>
</a>

            <h1 class="text-xl md:text-2xl font-black italic uppercase">Register <span class="text-indigo-600">Vehicle</span></h1>
        </div>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="max-w-2xl mx-auto space-y-6">
        
        <?php if($success_msg): ?>
            <div class="bg-emerald-50 text-emerald-600 p-4 rounded-2xl border border-emerald-100 text-[10px] font-bold uppercase tracking-widest">
                <i class="fa-solid fa-circle-check mr-2"></i> <?= $success_msg ?>
                <script>setTimeout(() => { window.location.href = 'dashboard.php'; }, 2000);</script>
            </div>
        <?php endif; ?>

        <?php if($error_msg): ?>
            <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl border border-rose-100 text-[10px] font-bold uppercase tracking-widest">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <div class="bg-slate-900 p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <label class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Chassis Verification</label>
                <span id="vin-badge" class="text-[9px] font-bold text-slate-500 uppercase">17 Characters</span>
            </div>
            <input type="text" name="chassis_number" id="vin-input" maxlength="17" required placeholder="VIN NUMBER" 
                   class="w-full bg-white/5 border border-white/10 p-4 rounded-2xl text-white font-mono text-lg tracking-widest focus:border-indigo-500 outline-none uppercase">
        </div>

        <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Plate Number</label>
                    <input type="text" name="plate_no" required placeholder="KCA 123A" 
                           class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 font-black text-lg uppercase form-input-styled">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-black text-slate-400 uppercase ml-2">Engine Number</label>
                    <input type="text" name="engine_number" required placeholder="ENG-XXXX" 
                           class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 font-bold uppercase form-input-styled">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 md:gap-4">
                <input type="text" name="make" id="make-field" placeholder="Make" required class="p-4 rounded-2xl bg-slate-50 border border-slate-200 font-bold text-xs md:text-sm form-input-styled">
                <input type="text" name="model" id="model-field" placeholder="Model" required class="p-4 rounded-2xl bg-slate-50 border border-slate-200 font-bold text-xs md:text-sm form-input-styled">
                <input type="number" name="year" id="year-field" placeholder="Year" required class="p-4 rounded-2xl bg-slate-50 border border-slate-200 font-bold text-xs md:text-sm form-input-styled">
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
            <label class="text-[10px] font-black text-slate-400 uppercase ml-2 mb-4 block tracking-widest">Specs & Color</label>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-[8px] font-bold text-slate-400 uppercase ml-2">Fuel Type</label>
                    <select name="fuel_type" class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 font-bold text-xs outline-none">
                        <option value="Petrol">Petrol</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Electric">Electric</option>
                        <option value="Hybrid">Hybrid</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-[8px] font-bold text-slate-400 uppercase ml-2">Transmission</label>
                    <select name="transmission_type" class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 font-bold text-xs outline-none">
                        <option value="Automatic">Automatic</option>
                        <option value="Manual">Manual</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-[8px] font-bold text-slate-400 uppercase ml-2">Engine (CC)</label>
                    <input type="number" name="rating_cc" placeholder="1500" required class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 font-bold text-xs form-input-styled">
                </div>
                <div class="space-y-1">
                    <label class="text-[8px] font-bold text-slate-400 uppercase ml-2">Color</label>
                    <input type="text" name="color" placeholder="e.g. Blue" required class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 font-bold text-xs form-input-styled">
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-4 w-full md:w-auto">
                <div class="w-12 h-12 flex-shrink-0 rounded-2xl bg-slate-100 flex items-center justify-center overflow-hidden border border-slate-200">
                    <img id="preview" src="" class="hidden w-full h-full object-cover">
                    <i id="icon" class="fa-solid fa-camera text-slate-300"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase text-slate-400">Vehicle Photo</p>
                    <input type="file" name="car_image" accept="image/*" class="text-[10px] mt-1" onchange="previewImg(this)">
                </div>
            </div>
            <button type="submit" name="add_car" class="w-full md:w-auto bg-indigo-600 px-10 py-4 rounded-2xl text-white font-black uppercase tracking-widest text-xs shadow-lg active:scale-95 transition-all">
                Register Vehicle
            </button>
        </div>
    </form>

    <script>
        document.getElementById('vin-input').addEventListener('input', async function() {
            const vin = this.value.toUpperCase();
            const badge = document.getElementById('vin-badge');
            
            if(vin.length === 17) {
                badge.innerText = "Searching...";
                try {
                    const res = await fetch(`https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVin/${vin}?format=json`);
                    const data = await res.json();
                    const make = data.Results.find(r => r.Variable === "Make")?.Value;
                    if(make) {
                        document.getElementById('make-field').value = make;
                        document.getElementById('model-field').value = data.Results.find(r => r.Variable === "Model")?.Value;
                        document.getElementById('year-field').value = data.Results.find(r => r.Variable === "Model Year")?.Value;
                        badge.innerHTML = '<span class="text-emerald-500">VIN Verified</span>';
                    }
                } catch(e) { badge.innerText = "17 Characters"; }
            }
        });

        function previewImg(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('preview').classList.remove('hidden');
                    document.getElementById('icon').classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>