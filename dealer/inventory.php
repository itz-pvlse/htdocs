<?php
session_start();
require_once '../config/db.php';

// Auth Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header("Location: ../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// --- 1. FULL TEMPLATE DOWNLOAD HANDLER ---
// Updated to include new professional fields
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="full_inventory_template.csv"');
    $output = fopen('php://output', 'w');
    $headers = [
        'Make', 'Model', 'Year', 'Condition', 'Grade', 'Owners', 'Price', 'Cost Price', 
        'Mileage', 'Transmission', 'Fuel Type', 'Engine CC', 'VIN', 'Drive Type', 
        'Color', 'Doors', 'Seats', 'Airbags', 'Location', 'Warranty', 'Duty Paid', 'Description'
    ];
    fputcsv($output, $headers);
    $sample = [
        'Toyota', 'Prado', '2022', 'Foreign Used', '4.5', '1', '8500000', '7200000', 
        '45000', 'Automatic', 'Diesel', '3000', 'JTFB123456789', '4WD', 
        'White', '5', '7', '8', 'Nairobi', '6 Months', '1', 'Clean unit, first owner in Kenya'
    ];
    fputcsv($output, $sample);
    fclose($output);
    exit;
}

// --- 2. GET HANDLERS (Fetch Gallery) ---
if (isset($_GET['action']) && $_GET['action'] === 'get_gallery') {
    header('Content-Type: application/json');
    $listing_id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT gi.id, gi.image_path FROM dealer_listing_images gi 
                           JOIN dealer_listings dl ON gi.listing_id = dl.id 
                           WHERE gi.listing_id = ? AND dl.dealer_id = ?");
    $stmt->execute([$listing_id, $user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- 3. BACKEND POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // ACTION: DELETE FULL LISTING
    if ($_POST['action'] === 'delete_cars') {
        try {
            $ids = json_decode($_POST['ids']);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM dealer_listings WHERE id IN ($placeholders) AND dealer_id = ?");
                $stmt->execute([...$ids, $user_id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No IDs provided']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ACTION: DELETE INDIVIDUAL GALLERY IMAGE
    if ($_POST['action'] === 'delete_gallery_image') {
        try {
            $img_id = $_POST['image_id'];
            $stmt = $pdo->prepare("SELECT gi.image_path FROM dealer_listing_images gi 
                                   JOIN dealer_listings dl ON gi.listing_id = dl.id 
                                   WHERE gi.id = ? AND dl.dealer_id = ?");
            $stmt->execute([$img_id, $user_id]);
            $img = $stmt->fetch();

            if ($img) {
                if (file_exists("../" . $img['image_path'])) {
                    unlink("../" . $img['image_path']);
                }
                $del = $pdo->prepare("DELETE FROM dealer_listing_images WHERE id = ?");
                $del->execute([$img_id]);
                echo json_encode(['success' => true]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ACTION: SAVE/UPDATE CAR
    if ($_POST['action'] === 'save_car') {
        try {
            $car_id = $_POST['car_id'] ?? '';
            $condition = $_POST['vehicle_condition'] ?? 'Used';
            
            // --- IMAGE HANDLING LOGIC ---
            $main_image_to_save = null; 
            if (isset($_FILES['main_image_file']) && $_FILES['main_image_file']['error'] === 0) {
                $target_dir = "../uploads/dealer_listings/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                
                $file_ext = strtolower(pathinfo($_FILES["main_image_file"]["name"], PATHINFO_EXTENSION));
                $new_file_name = time() . "_main_" . bin2hex(random_bytes(4)) . "." . $file_ext;
                
                if (move_uploaded_file($_FILES["main_image_file"]["tmp_name"], $target_dir . $new_file_name)) {
                    $main_image_to_save = "uploads/dealer_listings/" . $new_file_name;
                }
            }

            // --- 1. PRE-PROCESS PARAMS (DEPENDABLE LOGIC) ---
            $params = [
                'dealer_id'         => $user_id,
                'title'             => trim(($_POST['make'] ?? '') . ' ' . ($_POST['model'] ?? '') . ' (' . ($_POST['year'] ?? '') . ')'),
                'make'              => $_POST['make'],
                'model'             => $_POST['model'],
                'year'              => $_POST['year'],
                'vehicle_condition' => $condition,
                'import_grade'      => ($condition === 'New') ? null : ($_POST['import_grade'] ?? null),
                'previous_owners'   => ($condition === 'New') ? 0 : ($_POST['previous_owners'] ?? 1),
                'duty_paid'         => $_POST['duty_paid'] ?? 1,
                'registration_number' => $_POST['registration_number'] ?? null,
                'price'             => $_POST['price'],
                'cost_price'        => $_POST['cost_price'] ?? 0,
                'mileage'           => ($condition === 'New') ? 0 : ($_POST['mileage'] ?? 0),
                'transmission'      => $_POST['transmission'],
                'fuel_type'         => $_POST['fuel_type'],
                'vin'               => strtoupper($_POST['vin'] ?? ''),
                'engine_no'         => $_POST['engine_no'] ?? '',
                'engine_cc'         => $_POST['engine_cc'],
                'drive_type'        => $_POST['drive_type'],
                'doors'             => $_POST['doors'] ?? 4,
                'seats'             => $_POST['seats'] ?? 5,
                'airbags'           => $_POST['airbags'] ?? 0,
                'color'             => $_POST['color'],
                'service_history'   => $_POST['service_history'] ?? null,
                'accident_history'  => $_POST['accident_history'] ?? null,
                'warranty'          => $_POST['warranty'] ?? null,
                'spare_key'         => isset($_POST['spare_key']) ? 1 : 0,
                'is_import'         => ($condition === 'Foreign Used') ? 1 : 0,
                'location'          => $_POST['location'] ?? '',
                'fuel_tank_capacity'=> $_POST['fuel_tank_capacity'] ?? null,
                'description'       => $_POST['description'] ?? '',
                'status'            => $_POST['status'] ?? 'active'
            ];

            if (!empty($car_id)) {
                // UPDATE MODE
                $params['id'] = $car_id;
                $img_sql = "";
                if ($main_image_to_save) {
                    $params['main_image'] = $main_image_to_save;
                    $img_sql = ", main_image=:main_image";
                }
                
                $sql = "UPDATE dealer_listings SET 
                        title=:title, make=:make, model=:model, year=:year, 
                        vehicle_condition=:vehicle_condition, import_grade=:import_grade, 
                        previous_owners=:previous_owners, duty_paid=:duty_paid, 
                        registration_number=:registration_number, price=:price, 
                        cost_price=:cost_price, mileage=:mileage, transmission=:transmission, 
                        fuel_type=:fuel_type, vin=:vin, engine_no=:engine_no, 
                        engine_cc=:engine_cc, drive_type=:drive_type, doors=:doors, 
                        seats=:seats, airbags=:airbags, color=:color, 
                        service_history=:service_history, accident_history=:accident_history, 
                        warranty=:warranty, spare_key=:spare_key, is_import=:is_import, 
                        location=:location, fuel_tank_capacity=:fuel_tank_capacity, 
                        description=:description, status=:status $img_sql 
                        WHERE id=:id AND dealer_id=:dealer_id";
            } else {
                // INSERT MODE
                $params['main_image'] = $main_image_to_save ?: 'uploads/dealer_listings/default_car.png';
                $sql = "INSERT INTO dealer_listings (
                            dealer_id, title, make, model, year, vehicle_condition, 
                            import_grade, previous_owners, duty_paid, registration_number, 
                            price, cost_price, mileage, transmission, fuel_type, vin, 
                            engine_no, engine_cc, drive_type, doors, seats, airbags, 
                            color, service_history, accident_history, warranty, spare_key, 
                            is_import, location, fuel_tank_capacity, description, status, main_image
                        ) VALUES (
                            :dealer_id, :title, :make, :model, :year, :vehicle_condition, 
                            :import_grade, :previous_owners, :duty_paid, :registration_number, 
                            :price, :cost_price, :mileage, :transmission, :fuel_type, :vin, 
                            :engine_no, :engine_cc, :drive_type, :doors, :seats, :airbags, 
                            :color, :service_history, :accident_history, :warranty, :spare_key, 
                            :is_import, :location, :fuel_tank_capacity, :description, :status, :main_image
                        )";
            }
            
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute($params);
            $final_car_id = !empty($car_id) ? $car_id : $pdo->lastInsertId();

            // --- GALLERY HANDLING ---
            if ($success && !empty($_FILES['gallery_images']['name'][0])) {
                $gallery_dir = "../uploads/dealer_listings/gallery/";
                if (!is_dir($gallery_dir)) mkdir($gallery_dir, 0777, true);
                foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['gallery_images']['error'][$key] === 0) {
                        $g_original_name = preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['gallery_images']['name'][$key]));
                        $name = time() . "_" . $key . "_" . $g_original_name;
                        if (move_uploaded_file($tmp_name, $gallery_dir . $name)) {
                            $img_path = "uploads/dealer_listings/gallery/" . $name;
                            $pdo->prepare("INSERT INTO dealer_listing_images (listing_id, image_path) VALUES (?, ?)")->execute([$final_car_id, $img_path]);
                        }
                    }
                }
            }
            echo json_encode(['success' => true, 'id' => $final_car_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ACTION: BULK UPLOAD (Updated for 41-column structure)
    if ($_POST['action'] === 'bulk_upload_listings') {
        if (isset($_FILES['inventory_file']) && $_FILES['inventory_file']['error'] == 0) {
            $handle = fopen($_FILES['inventory_file']['tmp_name'], "r");
            fgetcsv($handle); // Skip headers
            $pdo->beginTransaction();
            try {
                $sql = "INSERT INTO dealer_listings (
                            dealer_id, make, model, year, vehicle_condition, import_grade, 
                            previous_owners, price, cost_price, mileage, transmission, 
                            fuel_type, engine_cc, vin, drive_type, color, doors, seats, 
                            airbags, location, warranty, duty_paid, description, title, status, main_image
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                while (($csv_data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if(empty($csv_data[0])) continue;
                    $title = trim($csv_data[0]) . " " . trim($csv_data[1]) . " (" . trim($csv_data[2]) . ")";
                    // Map CSV columns to SQL placeholders
                    $params = array_merge(
                        [$user_id], 
                        array_slice($csv_data, 0, 22), // All CSV fields
                        [$title, 'active', 'uploads/dealer_listings/default_car.png']
                    );
                    $stmt->execute($params);
                }
                $pdo->commit();
                fclose($handle);
                header("Location: inventory.php?success=bulk_imported");
                exit;
            } catch (Exception $e) { 
                $pdo->rollBack(); 
                die("Bulk Error: " . $e->getMessage()); 
            }
        }
    }
    exit; 
}

// Default Inventory Fetch
$stmt = $pdo->prepare("SELECT * FROM dealer_listings WHERE dealer_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Inventory | AutoCore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
    
    body { 
        font-family: 'Plus Jakarta Sans', sans-serif; 
        background: #F8FAFC; 
        color: #0F172A; 
    }

    [x-cloak] { display: none !important; }
    
    /* Core Professional Input */
    .form-input { 
        @apply w-full p-4 rounded-2xl text-sm border-2 transition-all outline-none text-slate-900 font-semibold; 
    }
    
    /* Focus State stays consistent across the form */
    .form-input:focus { 
        @apply border-slate-400 bg-white ring-4 ring-slate-100; 
    }

    /* Standard Label Styling */
    .label-cap { 
        @apply text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] mb-2 block ml-1; 
    }

    /* Utility */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    
    /* Layout Containers */
    .table-container { 
        @apply bg-white rounded-[2rem] lg:rounded-[3rem] border border-slate-100 shadow-sm overflow-hidden; 
    }
    
    .mobile-scroll { 
        overflow-x: auto; 
        -webkit-overflow-scrolling: touch; 
    }
</style>

</head>

<body x-data="inventoryApp()" class="antialiased">

    <div class="max-w-7xl mx-auto p-4 lg:p-10">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8 lg:mb-12">
            <div>
                <div class="flex items-center gap-3 text-slate-400 mb-2">
                    <a href="dealer.php" class="hover:text-indigo-600 transition-colors font-bold text-xs uppercase tracking-widest">Dashboard</a>
                    <i class="fas fa-chevron-right text-[10px]"></i>
                    <span class="font-bold text-xs uppercase tracking-widest text-slate-900">Inventory</span>
                </div>
                <h1 class="text-3xl lg:text-4xl font-black tracking-tight">Fleet Manager <span class="text-indigo-600">.</span></h1>
            </div>

            <div class="flex items-center gap-3">
                <button @click="openModal()" class="flex items-center gap-3 bg-slate-900 text-white px-6 py-3 rounded-2xl hover:bg-slate-800 transition-all shadow-lg shadow-slate-200">
                    <i class="fas fa-plus text-xs"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest">New Unit</span>
                </button>

                <button @click="isBulkModalOpen = true" class="flex items-center gap-3 bg-white border border-slate-200 text-slate-600 px-6 py-3 rounded-2xl hover:bg-slate-50 transition-all">
                    <i class="fas fa-file-upload text-xs"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest">Bulk Import</span>
                </button>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-4 mb-8">
            <div class="relative flex-grow">
                <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    x-model="searchQuery" 
                    placeholder="Search Make, Model, or VIN..." 
                    class="w-full pl-12 pr-4 py-4 rounded-[1.5rem] border border-slate-200 focus:ring-4 focus:ring-indigo-50/50 focus:border-indigo-500 outline-none transition-all text-sm font-medium shadow-sm"
                >
            </div>

            <div class="flex flex-col md:flex-row gap-4">
    <div class="w-full md:w-48">
        <select x-model="statusFilter" class="w-full px-6 py-4 rounded-[1.5rem] border border-slate-200 focus:ring-4 focus:ring-indigo-50/50 outline-none transition-all text-xs font-black uppercase tracking-widest text-slate-600 appearance-none bg-white shadow-sm cursor-pointer">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="sold">Sold</option>
        </select>
    </div>

    <div class="w-full md:w-64">
        <select x-model="conditionFilter" class="w-full px-6 py-4 rounded-[1.5rem] border border-slate-200 focus:ring-4 focus:ring-indigo-50/50 outline-none transition-all text-xs font-black uppercase tracking-widest text-slate-600 appearance-none bg-white shadow-sm cursor-pointer">
            <option value="all">All Conditions</option>
            <option value="New">Brand New</option>
            <option value="Foreign Used">Foreign Used</option>
            <option value="Local Used">Local Used</option>
        </select>
    </div>
</div>

        </div>
        
        <div class="table-container">
    <div x-show="selectedRows.length > 0" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-10"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-cloak
         class="fixed bottom-10 left-1/2 -translate-x-1/2 z-50 bg-slate-900 text-white px-8 py-4 rounded-[2rem] shadow-2xl flex items-center gap-8 border border-slate-700">
        <span class="text-xs font-black uppercase tracking-widest text-slate-400">
            <span class="text-white" x-text="selectedRows.length"></span> Units Selected
        </span>
        <div class="h-4 w-px bg-slate-700"></div>
        <button @click="deleteMultiple()" class="text-rose-400 hover:text-rose-300 font-black text-xs uppercase tracking-widest flex items-center gap-2">
            <i class="fas fa-trash-alt"></i> Delete Selected
        </button>
    </div>

    <div class="mobile-scroll overflow-x-auto w-full no-scrollbar rounded-[2rem] border border-slate-100 bg-white">
        <table class="w-full text-left border-collapse min-w-[1000px]">
            <thead>
                <tr class="bg-slate-50/50">
                    <th class="p-6 lg:p-8 w-12 text-center">
                        <input type="checkbox" 
                            @change="toggleAll($event)" 
                            :checked="selectedRows.length > 0 && selectedRows.length === document.querySelectorAll('.row-checkbox').length"
                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    </th>
                    <th class="p-6 lg:p-8 label-cap w-[35%]">Asset Information</th>
                    <th class="p-6 lg:p-8 label-cap w-[20%]">Specs & ID</th>
                    <th class="p-6 lg:p-8 label-cap w-[20%]">Valuation</th>
                    <th class="p-6 lg:p-8 label-cap text-right w-[20%]">Control</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach($inventory as $car): 
                    $profit = (int)$car['price'] - (int)$car['cost_price'];
                    
                    // Sanitize strings for Alpine.js x-show logic
                    $make = addslashes($car['make']);
                    $model = addslashes($car['model']);
                    $vin = addslashes($car['vin'] ?? '');
                    $status = addslashes($car['status'] ?? 'active');
                    $condition = addslashes($car['vehicle_condition'] ?? 'Foreign Used');
                ?>
                <tr 
                    x-show="shouldShow('<?= $make ?>', '<?= $model ?>', '<?= $vin ?>', '<?= $status ?>', '<?= $condition ?>')"
                    x-transition:enter="transition ease-out duration-200"
                    class="group hover:bg-slate-50/50 transition-colors" 
                    :class="selectedRows.includes('<?= $car['id'] ?>') ? 'bg-indigo-50/30' : ''"
                >
                    <td class="p-6 lg:p-8 text-center">
                        <input type="checkbox" value="<?= $car['id'] ?>" x-model="selectedRows" class="row-checkbox rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    </td>
                    
                    <td class="p-6 lg:p-8">
                        <div class="flex items-center gap-4 lg:gap-6">
                            <div class="relative flex-shrink-0">
                                <img src="../<?= $car['main_image'] ?: 'assets/img/default.jpg' ?>" 
                                     class="w-16 h-16 lg:w-20 lg:h-20 rounded-2xl lg:rounded-[1.5rem] object-cover shadow-md bg-slate-100 ring-4 ring-white">
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-black text-slate-900 text-base lg:text-lg leading-tight mb-1 truncate">
                                    <?= htmlspecialchars($car['make']) ?> <?= htmlspecialchars($car['model']) ?>
                                </h3>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[9px] font-black uppercase px-2 py-0.5 bg-slate-100 rounded text-slate-500"><?= $car['year'] ?></span>
                                    
                                    <?php 
                                        $condStyle = 'bg-slate-100 text-slate-500 border-slate-200';
                                        if($car['vehicle_condition'] === 'New') $condStyle = 'bg-blue-50 text-blue-600 border-blue-100';
                                        if($car['vehicle_condition'] === 'Foreign Used') $condStyle = 'bg-indigo-50 text-indigo-600 border-indigo-100';
                                        if($car['vehicle_condition'] === 'Local Used') $condStyle = 'bg-amber-50 text-amber-600 border-amber-100';
                                    ?>
                                    <span class="text-[8px] font-black uppercase px-2 py-0.5 rounded border <?= $condStyle ?>">
                                        <?= htmlspecialchars($car['vehicle_condition']) ?>
                                    </span>

                                    <span class="text-[9px] font-black uppercase text-slate-400"><?= htmlspecialchars($car['color']) ?></span>
                                    
                                    <span class="text-[8px] font-bold px-1.5 py-0.5 rounded border border-slate-200 <?= $car['status'] === 'sold' ? 'text-rose-500 bg-rose-50 border-rose-100' : 'text-slate-400' ?> uppercase tracking-tighter">
                                        <?= htmlspecialchars($car['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </td>

                    <td class="p-6 lg:p-8 whitespace-nowrap">
                        <div class="font-mono text-[10px] font-bold text-slate-500 mb-1 tracking-wider uppercase"><?= $car['vin'] ?: 'NO-VIN' ?></div>
                        <div class="text-[10px] font-black uppercase text-slate-400"><?= htmlspecialchars($car['fuel_type']) ?> • <?= htmlspecialchars($car['transmission']) ?></div>
                    </td>

                    <td class="p-6 lg:p-8 whitespace-nowrap">
                        <div class="text-lg font-black text-slate-900 mb-1">Ksh <?= number_format($car['price']) ?></div>
                        <div class="text-[9px] font-bold uppercase text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg inline-flex items-center gap-1">
                            <i class="fas fa-arrow-up"></i> +<?= number_format($profit) ?>
                        </div>
                    </td>

                   <td class="p-6 lg:p-8 text-right">
    <div class="flex items-center justify-end gap-2">
        <button 
            @click.stop="openShareModal(<?= $car['id'] ?>)" 
            title="Share to Community Feed"
            class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white transition-all shadow-sm">
            <i class="fas fa-share-alt text-xs"></i>
        </button>

        <button 
            @click='editCar(<?= htmlspecialchars(json_encode($car), ENT_QUOTES, "UTF-8") ?>)' 
            class="w-10 h-10 rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-indigo-600 transition-all shadow-sm">
            <i class="fas fa-edit text-xs"></i>
        </button>

        <button @click="deleteSingle('<?= $car['id'] ?>')" class="w-10 h-10 rounded-xl bg-white border border-slate-100 text-slate-400 hover:text-rose-600 transition-all shadow-sm">
            <i class="fas fa-trash-alt text-xs"></i>
        </button>
    </div>
</td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    
    
    
    
    

    <div class="md:hidden flex justify-center items-center gap-2 mt-4 text-slate-400">
        <i class="fas fa-arrows-left-right text-[10px]"></i>
        <span class="text-[9px] font-bold uppercase tracking-widest">Swipe for Valuation</span>
    </div>
</div>
 </div>

    
    
    
    
    
<div id="shareModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[999] hidden items-center justify-center p-4">
    <div class="bg-white rounded-[2.5rem] w-full max-w-md shadow-2xl overflow-hidden transform transition-all border border-white">
        <div class="p-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
            <div>
                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-900">Promote Asset</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase mt-1">Share to Community Feed</p>
            </div>
            <button onclick="closeShareModal()" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-slate-600 flex items-center justify-center transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        
        <div class="p-8">
            <input type="hidden" id="share_listing_id">
            
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3 ml-1">Write a caption</label>
            <textarea id="share_caption" 
                class="w-full bg-slate-50 border border-slate-100 rounded-[1.5rem] p-5 text-sm focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 min-h-[140px] transition-all resize-none text-slate-700 font-medium"
                placeholder="Ex: Just arrived! Cleanest 2018 Mazda CX-5 in town. Negotiable for serious buyers..."></textarea>
            
            <button onclick="submitShare(this)" 
                class="w-full mt-6 bg-blue-600 hover:bg-indigo-700 text-white font-black uppercase text-[11px] tracking-widest py-5 rounded-[1.5rem] shadow-xl shadow-blue-500/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                <span>Post to Community Feed</span>
                <i class="fas fa-paper-plane text-[10px]"></i>
            </button>
        </div>
    </div>
</div>


    
    
    
    
    
    

    <div x-show="isModalOpen" x-cloak class="fixed inset-0 z-[100] flex justify-end">
        <div x-show="isModalOpen" x-transition.opacity @click="isModalOpen = false" class="absolute inset-0 bg-slate-900/60 backdrop-blur-md"></div>
        
        <div x-show="isModalOpen" 
             x-transition:enter="transition ease-out duration-400" 
             x-transition:enter-start="translate-x-full" 
             x-transition:enter-end="translate-x-0"
             class="relative bg-white w-full max-w-2xl h-full shadow-2xl flex flex-col">
            
            <div class="p-6 lg:p-10 border-b flex justify-between items-center bg-white">
                <div>
                    <h2 class="text-2xl lg:text-3xl font-black text-slate-900" x-text="editMode ? 'Refine Asset' : 'New Asset'"></h2>
                    <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest mt-1">Inventory Synchronization</p>
                </div>
                <button @click="isModalOpen = false" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
                    <i class="fas fa-times"></i>
                </button>
            </div>

<form id="carForm" @submit.prevent="saveCar" enctype="multipart/form-data" 
    class="flex-1 overflow-y-auto p-6 lg:p-10 space-y-12 no-scrollbar bg-white" 
    x-data="{
        loadingVin: false,
        loadingAI: false,
        mainPreview: formData.main_image ? '/' + formData.main_image : null,
        galleryPreviews: [],
        // The formData object should be passed from your parent component 
        // Ensure these keys exist in your initialization logic
        async decodeVin() {
            if (!formData.vin || formData.vin.length < 11) return;
            this.loadingVin = true;
            try {
                const response = await fetch(`https://vpic.nhtsa.dot.gov/api/vehicles/DecodeVin/${formData.vin}?format=json`);
                const data = await response.json();
                const getVal = (id) => data.Results.find(r => r.VariableId === id)?.Value;

                const make = getVal(26);
                const model = getVal(28);
                const year = getVal(29);

                if (make) {
                    formData.make = make;
                    formData.model = model || '';
                    formData.year = year || '';
                    if(!formData.title) formData.title = `${year} ${make} ${model}`.trim();
                    
                    let fuel = getVal(24);
                    if(fuel) formData.fuel_type = fuel.includes('Diesel') ? 'Diesel' : (fuel.includes('Petrol') ? 'Petrol' : formData.fuel_type);
                }
            } catch (e) { console.error(e); }
            finally { this.loadingVin = false; }
        },
        async askAI(task) {
            if (!formData.make || !formData.model) {
                alert('Please enter Manufacturer and Model first.');
                return;
            }
            this.loadingAI = true;
            // Added Condition and Grade to the AI context for better sales descriptions
            const carContext = `${formData.year} ${formData.make} ${formData.model} (${formData.vehicle_condition}, Grade: ${formData.import_grade || 'N/A'})`;
            let prompt = task === 'description' 
                ? `Write a professional, 3-sentence sales description for a ${carContext} for a Kenyan dealership. Focus on prestige and the specific condition.`
                : `Give me a quick competitive price range in KSH for a ${carContext} in Kenya.`;

            try {
                const response = await fetch('/includes/ai_carhelp.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        conversation: [
                            { role: 'system', content: 'You are a luxury car expert in Nairobi.' },
                            { role: 'user', content: prompt }
                        ]
                    })
                });
                const data = await response.json();
                if(task === 'description') formData.description = data.reply;
                else alert('AI Market Insight: ' + data.reply);
            } catch (e) { console.error(e); }
            finally { this.loadingAI = false; }
        }
    }">
    
    <input type="hidden" name="action" value="save_car">
    <input type="hidden" name="car_id" :value="formData.id">
    <input type="hidden" name="dealer_id" value="<?= $_SESSION['user_id'] ?>">

    <div class="space-y-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-5">
            <div>
                <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">01. Listing Identity</h4>
                <p class="text-xs text-slate-600 font-bold mt-1">Public Display Configuration</p>
            </div>
            <div class="relative">
                <select name="status" x-model="formData.status" class="appearance-none bg-slate-100 text-slate-900 text-[10px] font-black uppercase tracking-widest px-8 py-3 rounded-xl border border-slate-200 outline-none focus:bg-white focus:ring-4 focus:ring-slate-100 transition-all cursor-pointer pr-12">
                    <option value="active">Active</option>
                    <option value="draft">Draft</option>
                    <option value="sold">Sold</option>
                </select>
                <i class="fas fa-circle absolute left-4 top-1/2 -translate-y-1/2 text-[6px] text-emerald-500" x-show="formData.status == 'active'"></i>
                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8">
            <div>
                <label class="label-cap text-slate-500">Public Listing Title</label>
                <input type="text" name="title" x-model="formData.title" placeholder="e.g. 2018 Toyota Land Cruiser V8" required 
                class="form-input !bg-slate-50 !border-slate-200 focus:!bg-white focus:!border-slate-400 focus:!ring-0 text-lg py-5 w-full">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="relative p-2 rounded-[2.2rem] border-2 border-dashed border-slate-200 bg-slate-50/50 group hover:border-indigo-300 transition-all min-h-[180px] flex items-center justify-center overflow-hidden">
                    <input type="file" id="main_image_file" name="main_image_file" class="hidden" @change="mainPreview = URL.createObjectURL($event.target.files[0])">
                    <template x-if="!mainPreview">
                        <label for="main_image_file" class="flex flex-col items-center space-y-3 cursor-pointer w-full py-10">
                            <i class="fas fa-camera text-slate-300 group-hover:text-indigo-500 text-2xl transition-colors"></i>
                            <span class="text-[10px] font-black uppercase text-slate-600 tracking-widest text-center">Upload Cover Photo</span>
                        </label>
                    </template>
                    <template x-if="mainPreview">
                        <div class="relative w-full h-full">
                            <img :src="mainPreview" class="w-full h-44 object-cover rounded-[1.8rem] shadow-sm">
                            <label for="main_image_file" class="absolute top-3 right-3 cursor-pointer">
                                <span class="text-white text-[10px] font-black uppercase tracking-widest bg-slate-900/60 backdrop-blur-md px-4 py-2 rounded-full hover:bg-slate-900 transition-all shadow-lg">Change Photo</span>
                            </label>
                        </div>
                    </template>
                </div>

                <div class="relative p-4 rounded-[2.2rem] border-2 border-dashed border-slate-200 bg-slate-50/50 group hover:border-indigo-300 transition-all min-h-[180px]">
                    <div class="h-full w-full flex flex-col items-center justify-center" x-show="galleryPreviews.length === 0 && existingGallery.length === 0">
                        <i class="fas fa-images text-slate-300 text-2xl mb-3"></i>
                        <label class="cursor-pointer text-center">
                            <span class="text-[10px] font-black uppercase text-slate-600 tracking-widest">Additional Photos</span>
                            <input type="file" name="gallery_images[]" multiple class="hidden" @change="galleryPreviews = Array.from($event.target.files).map(file => URL.createObjectURL(file))">
                        </label>
                    </div>
                    <div class="grid grid-cols-4 gap-2 w-full" x-show="galleryPreviews.length > 0 || existingGallery.length > 0">
                        <template x-for="(img, index) in existingGallery" :key="'old-'+img.id">
                            <div class="relative aspect-square">
                                <img :src="img.path" class="w-full h-full object-cover rounded-xl border border-slate-200">
                                <button type="button" @click="deleteGalleryImage(img.id, index)" class="absolute -top-1 -right-1 w-5 h-5 bg-rose-500 text-white rounded-full flex items-center justify-center shadow-lg hover:bg-rose-600"><i class="fas fa-trash text-[8px]"></i></button>
                            </div>
                        </template>
                        <template x-for="(src, index) in galleryPreviews" :key="'new-'+index">
                            <div class="relative aspect-square">
                                <img :src="src" class="w-full h-full object-cover rounded-xl border-2 border-indigo-400">
                                <button type="button" @click="galleryPreviews.splice(index, 1)" class="absolute -top-1 -right-1 w-5 h-5 bg-slate-800 text-white rounded-full flex items-center justify-center shadow-lg"><i class="fas fa-times text-[8px]"></i></button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="min-w-0">
                <label class="label-cap text-slate-500 truncate">Chassis / VIN</label>
                <div class="relative">
                    <input type="text" name="vin" x-model="formData.vin" placeholder="VIN-NUMBER"
                        class="form-input !bg-slate-50 !border-slate-200 focus:!bg-white uppercase font-mono text-sm w-full py-4 pr-24">
                    <button type="button" @click="decodeVin()" :disabled="loadingVin || !formData.vin"
                        class="absolute right-2 top-1/2 -translate-y-1/2 bg-slate-900 text-white text-[9px] font-black uppercase px-4 py-2 rounded-lg hover:bg-indigo-600 transition-all disabled:opacity-50">
                        <span x-show="!loadingVin">Verify</span>
                        <i class="fas fa-circle-notch animate-spin" x-show="loadingVin"></i>
                    </button>
                </div>
            </div>
            <div class="min-w-0">
                <label class="label-cap text-slate-500 truncate">Exterior Color</label>
                <div class="relative">
                    <div class="absolute left-4 top-1/2 -translate-y-1/2 w-3 h-3 rounded-full border border-slate-300 shadow-inner" 
                         :style="`background-color: ${formData.color}`"></div>
                    <input type="text" name="color" x-model="formData.color" placeholder="e.g. Pearl White" required
                        class="form-input !bg-slate-50 !border-slate-200 focus:!bg-white w-full py-4 pl-10">
                </div>
            </div>
        </div>
        
    
    
    
    
    <div class="space-y-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-100 pb-5 gap-4">
            <div>
                <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">02. Engineering & Condition</h4>
                <p class="text-xs text-slate-600 font-bold mt-1">Vehicle State & Authentication</p>
            </div>
            
            <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200 self-start">
                <template x-for="cond in ['New', 'Foreign Used', 'Local Used']">
                    <button type="button" 
                        @click="formData.vehicle_condition = cond"
                        :class="formData.vehicle_condition === cond ? 'bg-white text-slate-900 shadow-sm border border-slate-200' : 'text-slate-500 hover:text-slate-700'"
                        class="px-4 py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">
                        <span x-text="cond"></span>
                    </button>
                </template>
                <input type="hidden" name="vehicle_condition" :value="formData.vehicle_condition">
            </div>
        </div>

        <div x-show="formData.vehicle_condition !== 'New'" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6 bg-indigo-50/50 rounded-[2rem] border border-indigo-100">
            
            <div class="min-w-0">
                <label class="label-cap text-indigo-600/70">Auction / Import Grade</label>
                <select name="import_grade" x-model="formData.import_grade" class="form-input !bg-white !border-indigo-100 w-full">
                    <option value="">N/A (Local)</option>
                    <option value="5">Grade 5 (As New)</option>
                    <option value="4.5">Grade 4.5 (Excellent)</option>
                    <option value="4">Grade 4 (Good)</option>
                    <option value="3.5">Grade 3.5 (Fair)</option>
                    <option value="R">Grade R (Repaired)</option>
                </select>
            </div>

            <div class="min-w-0">
                <label class="label-cap text-indigo-600/70">Previous Owners</label>
                <select name="previous_owners" x-model="formData.previous_owners" class="form-input !bg-white !border-indigo-100 w-full">
                    <option value="0">0 (New Import)</option>
                    <option value="1">1 Previous Owner</option>
                    <option value="2">2 Previous Owners</option>
                    <option value="3">3+ Owners</option>
                </select>
            </div>

            <div class="min-w-0">
                <label class="label-cap text-indigo-600/70">Registration Number</label>
                <input type="text" name="registration_number" x-model="formData.registration_number" placeholder="KDK 123A" 
                    class="form-input !bg-white !border-indigo-100 uppercase w-full">
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="min-w-0">
                <label class="label-cap text-slate-500 truncate">Chassis / VIN</label>
                <div class="relative">
                    <input type="text" name="vin" x-model="formData.vin" placeholder="VIN-NUMBER"
                        class="form-input !bg-slate-50 !border-slate-200 focus:!bg-white uppercase font-mono text-sm w-full py-4 pr-20">
                    <button type="button" @click="decodeVin()" :disabled="loadingVin || !formData.vin"
                        class="absolute right-2 top-1/2 -translate-y-1/2 bg-slate-900 text-white text-[9px] font-black uppercase px-3 py-2 rounded-lg hover:bg-indigo-600 transition-all">
                        <i class="fas fa-circle-notch animate-spin" x-show="loadingVin"></i>
                        <span x-show="!loadingVin">Verify</span>
                    </button>
                </div>
            </div>
            <div class="min-w-0">
                <label class="label-cap text-slate-500">Manufacturer</label>
                <input type="text" name="make" x-model="formData.make" required class="form-input !bg-slate-50 w-full">
            </div>
            <div class="min-w-0">
                <label class="label-cap text-slate-500">Model Variant</label>
                <input type="text" name="model" x-model="formData.model" required class="form-input !bg-slate-50 w-full">
            </div>
            <div class="min-w-0">
                <label class="label-cap text-slate-500">Year</label>
                <input type="number" name="year" x-model="formData.year" required class="form-input !bg-slate-50 w-full">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 p-8 bg-slate-100/50 rounded-[2rem] border border-slate-200">
    <div class="space-y-2">
        <label class="label-cap text-slate-400">Transmission</label>
        <select name="transmission" x-model="formData.transmission" class="form-input !bg-white w-full">
            <option value="Automatic">Automatic</option>
            <option value="Manual">Manual</option>
        </select>
    </div>

    <div class="space-y-2">
        <label class="label-cap text-slate-400">Fuel</label>
        <select name="fuel_type" x-model="formData.fuel_type" class="form-input !bg-white w-full">
            <option value="Petrol">Petrol</option>
            <option value="Diesel">Diesel</option>
            <option value="Hybrid">Hybrid</option>
            <option value="Electric">Electric</option>
        </select>
    </div>

    <div class="space-y-2">
        <label class="label-cap text-slate-400">Engine (CC)</label>
        <input type="number" name="engine_cc" x-model="formData.engine_cc" placeholder="e.g. 2500" class="form-input !bg-white w-full">
    </div>

    <div class="space-y-2">
        <label class="label-cap text-slate-400">Drivetrain</label>
        <select name="drive_type" x-model="formData.drive_type" class="form-input !bg-white w-full">
            <option value="FWD">FWD</option>
            <option value="RWD">RWD</option>
            <option value="AWD">AWD</option>
            <option value="4WD">4WD</option>
        </select>
    </div>

    <div class="space-y-2">
        <label class="label-cap text-slate-400">Mileage (KM)</label>
        <input type="number" name="mileage" x-model="formData.mileage" 
            :disabled="formData.vehicle_condition === 'New'" 
            :class="formData.vehicle_condition === 'New' ? 'opacity-50 cursor-not-allowed' : ''" 
            class="form-input !bg-white w-full">
    </div>

    <div class="space-y-2">
        <label class="label-cap text-slate-400">Duty Status</label>
        <select name="duty_paid" x-model="formData.duty_paid" class="form-input !bg-white w-full">
            <option value="1">Duty Paid</option>
            <option value="0">Duty Pending</option>
        </select>
    </div>
</div>


    <div class="space-y-8">
        <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 border-b border-slate-100 pb-5">03. Financial Control</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="p-8 rounded-[2.5rem] bg-slate-50 border border-slate-200">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 block">Buying Price (Internal)</label>
                <div class="flex items-center">
                    <span class="text-slate-400 font-bold mr-3">KSH</span>
                    <input type="number" step="0.01" name="cost_price" x-model="formData.cost_price" class="w-full bg-transparent border-none text-slate-900 text-2xl font-black focus:ring-0 outline-none p-0">
                </div>
            </div>

            <div class="p-8 rounded-[2.5rem] bg-slate-900 shadow-xl shadow-slate-200">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Selling Price</label>
                        <button type="button" @click="askAI('price')" class="flex items-center gap-1 px-2 py-1 rounded bg-slate-800 hover:bg-indigo-600 transition-colors">
                            <i class="fas fa-magic text-[8px] text-white"></i>
                            <span class="text-[8px] font-black text-white uppercase">Market Check</span>
                        </button>
                    </div>
                    <span class="text-[9px] font-black text-emerald-500" x-text="'PROFIT: ' + Number(formData.price - formData.cost_price).toLocaleString()"></span>
                </div>
                <div class="flex items-center">
                    <span class="text-slate-500 font-bold mr-3">KSH</span>
                    <input type="number" step="0.01" name="price" x-model="formData.price" required class="w-full bg-transparent border-none text-white text-3xl font-black focus:ring-0 outline-none p-0">
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-8 pb-10">
        <div class="flex justify-between items-center border-b border-slate-100 pb-5">
            <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">04. Narrative & Inventory</h4>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <input type="checkbox" name="spare_key" x-model="formData.spare_key" value="1" class="hidden peer">
                    <div class="w-4 h-4 rounded border-2 border-slate-200 peer-checked:bg-indigo-600 peer-checked:border-indigo-600 flex items-center justify-center transition-all">
                        <i class="fas fa-check text-[8px] text-white"></i>
                    </div>
                    <span class="text-[9px] font-black uppercase text-slate-500 group-hover:text-slate-900">Spare Key</span>
                </label>
                <button type="button" @click="askAI('description')" class="text-[9px] font-black uppercase text-indigo-600 flex items-center gap-1.5 hover:opacity-70 transition-all">
                    <i class="fas" :class="loadingAI ? 'fa-circle-notch animate-spin' : 'fa-magic'"></i>
                    <span x-text="loadingAI ? 'Writing...' : 'AI Generate Description'"></span>
                </button>
            </div>
        </div>
        
        <div class="grid grid-cols-1 gap-6">
            <textarea name="description" x-model="formData.description" rows="4" placeholder="Detailed car features..." 
                class="form-input !bg-slate-50 w-full p-4 min-h-[120px]"></textarea>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="space-y-2">
                <label class="label-cap text-slate-500">Service History</label>
                <select name="service_history" x-model="formData.service_history" class="form-input !bg-slate-50 w-full">
                    <option value="Full Service History">Full Service History</option>
                    <option value="Partial History">Partial History</option>
                    <option value="Not Specified">Not Specified</option>
                </select>
            </div>
            <div class="space-y-2">
                <label class="label-cap text-slate-500">Accident History</label>
                <select name="accident_history" x-model="formData.accident_history" class="form-input !bg-slate-50 w-full">
                    <option value="No Accidents">No Accidents</option>
                    <option value="Minor Repairs">Minor Repairs</option>
                    <option value="Previous Accident">Previous Accident</option>
                </select>
            </div>
            <div class="space-y-2">
                <label class="label-cap text-slate-500">Warranty</label>
                <input type="text" name="warranty" x-model="formData.warranty" placeholder="e.g. 6 Months" class="form-input !bg-slate-50 w-full">
            </div>
            <div class="space-y-2">
                <label class="label-cap text-slate-500">Location</label>
                <input type="text" name="location" x-model="formData.location" placeholder="e.g. Karen, Nairobi" class="form-input !bg-slate-50 w-full">
            </div>
        </div>
    </div>
</form>









            <div class="p-6 lg:p-10 border-t bg-white flex gap-4">
                <button @click="isModalOpen = false" class="flex-1 py-4 text-slate-400 font-bold text-xs uppercase tracking-widest">Abort</button>
                <button 
    form="carForm" 
    :disabled="submitting"
    class="flex-[2] py-4 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-3 transition-all"
>
    <span x-show="!submitting" x-text="editMode ? 'Update Asset' : 'Publish Asset'"></span>

    <template x-if="submitting">
        <div class="flex items-center gap-2">
            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Processing...</span>
        </div>
    </template>
</button>
            </div>
        </div>
    </div>

  <div x-show="isBulkModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-900/60 backdrop-blur-sm" x-cloak>
    <div class="bg-white w-full max-w-md rounded-[2.5rem] p-10 shadow-2xl overflow-y-auto max-h-[90vh] no-scrollbar">
        
        <div class="text-center mb-8">
            <h3 class="text-xl font-black text-slate-900">Bulk Import</h3>
            <p class="text-slate-500 text-xs mt-2">Download the full template to see all 18 required columns.</p>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-3xl p-6 mb-8">
            <span class="text-[9px] font-black uppercase tracking-widest text-indigo-500 block mb-4">Key Column Mapping:</span>
            
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <span class="text-[10px] font-bold text-slate-700 uppercase">A - F</span>
                    <span class="text-[10px] text-slate-500">Make, Model, Year, Price, Cost, Mileage</span>
                </div>
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <span class="text-[10px] font-bold text-slate-700 uppercase">G - L</span>
                    <span class="text-[10px] text-slate-500">Trans, Fuel, CC, VIN, Drive, Color</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-slate-700 uppercase">M - R</span>
                    <span class="text-[10px] text-slate-500">Doors, Seats, Airbags, Loc, Warranty, Desc</span>
                </div>
            </div>
        </div>

<form action="" method="POST" enctype="multipart/form-data" x-data="{ fileName: '', showPreview:false }" class="space-y-4">
    <input type="hidden" name="action" value="bulk_upload_listings">

    <a href="?action=download_template" class="w-full flex items-center justify-center gap-3 py-4 border-2 border-dashed border-slate-200 rounded-2xl text-[10px] font-black uppercase text-slate-400 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50/30 transition-all group">
        <i class="fas fa-file-csv text-lg group-hover:scale-110 transition-transform"></i>
        Download 18-Column Template
    </a>

    <div class="space-y-2">
        <button type="button"
            @click="$refs.fileInput.click()"
            class="w-full bg-slate-900 text-white text-center py-5 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 active:scale-[0.98] transition-all shadow-xl shadow-slate-200 flex items-center justify-center gap-2">
            <i class="fas fa-upload mr-1"></i> 
            <span x-text="fileName ? 'Change CSV File' : 'Select Completed CSV'"></span>
        </button>
        
        <input type="file"
            x-ref="fileInput"
            name="inventory_file"
            class="hidden"
            accept=".csv, text/csv, application/vnd.ms-excel"
            @change="fileName = $el.files[0]?.name; showPreview=true;">
    </div>

    <template x-if="showPreview">
        <div class="p-6 bg-gradient-to-br from-slate-50 to-white rounded-3xl border border-slate-200 space-y-4 text-center shadow-inner animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="space-y-1">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">
                    Ready for Synchronization
                </p>
                <div class="flex items-center justify-center gap-2 text-indigo-600 bg-indigo-50/50 py-2 px-4 rounded-xl border border-indigo-100/50">
                    <i class="fas fa-file-alt text-xs"></i>
                    <p class="text-[11px] font-bold truncate max-w-[200px]">
                        <span x-text="fileName"></span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="submit"
                    class="flex-[2] bg-indigo-600 text-white py-3 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-200 transition-all active:scale-95">
                    Process Import
                </button>

                <button type="button"
                    class="flex-1 bg-white border border-slate-200 text-slate-500 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-50 hover:text-rose-500 hover:border-rose-100 transition-all"
                    @click="showPreview=false; fileName=''">
                    Discard
                </button>
            </div>
        </div>
    </template>

    <button type="button" @click="isBulkModalOpen = false" class="w-full py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest hover:text-slate-600 transition-colors">
        Close Window
    </button>
</form>

    </div>
</div>



<script>

function openShareModal(listingId) {
    document.getElementById('share_listing_id').value = listingId;
    document.getElementById('share_caption').value = ''; 
    
    const modal = document.getElementById('shareModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeShareModal() {
    const modal = document.getElementById('shareModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function submitShare(btn) {
    const listingId = document.getElementById('share_listing_id').value;
    const caption = document.getElementById('share_caption').value;

    if (!caption.trim()) {
        alert("Please add a caption to engage your community!");
        return;
    }

    // Visual feedback
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> <span>Sharing...</span>';

    const formData = new FormData();
    formData.append('listing_id', listingId);
    formData.append('caption', caption);

    fetch('../feeds/share_listing.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            closeShareModal();
            // You could trigger a Toast notification here
            alert("Success! Your car is now visible in the community feed.");
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(err => {
        console.error("Error:", err);
        alert("Failed to share listing.");
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}


function inventoryApp() {
    return {
        isModalOpen: false,
        editMode: false,
        submitting: false, 
        selectedRows: [],
        mainPreview: null,
        galleryPreviews: [], 
        existingGallery: [], 
        isBulkModalOpen: false,
        searchQuery: '',
        
        // --- UPDATED FILTERS ---
        statusFilter: 'all',
        conditionFilter: 'all', 

        formData: { 
            id: '', make: '', model: '', year: 2026, price: 0, 
            cost_price: 0, mileage: 0, transmission: 'Automatic', 
            fuel_type: 'Petrol', engine_cc: '', engine_no: '', 
            vin: '', drive_type: 'FWD', color: '', doors: 4, 
            seats: 5, description: '', status: 'active',
            location: '', warranty: '', airbags: 0,
            service_history: 'Not Specified', accident_history: 'No Accidents',
            vehicle_condition: 'Foreign Used',
            import_grade: '',
            previous_owners: 1,
            registration_number: '',
            duty_paid: 1,
            spare_key: 0,
            fuel_tank_capacity: ''
        },

        // --- UPDATED FILTER LOGIC ---
        shouldShow(make, model, vin, status, condition) {
            const matchesSearch = (make + ' ' + model + ' ' + (vin || ''))
                .toLowerCase()
                .includes(this.searchQuery.toLowerCase());
            
            const matchesStatus = this.statusFilter === 'all' || status === this.statusFilter;
            
            // Checks if condition matches or if filter is set to 'all'
            const matchesCondition = this.conditionFilter === 'all' || condition === this.conditionFilter;
            
            return matchesSearch && matchesStatus && matchesCondition;
        },

        async editCar(car) { 
            // 1. Immediate UI Trigger
            this.isModalOpen = true; 
            this.editMode = true; 
            this.submitting = false;

            try {
                // 2. Defensive Data Mapping (Ensures "New" listings with NULLs don't crash the button)
                this.formData = { 
                    ...this.resetFormObject(), // Start with clean defaults
                    ...car,
                    // Force clean data types for the UI
                    price: Number(car.price || 0), 
                    cost_price: Number(car.cost_price || 0),
                    year: Number(car.year || 2026),
                    mileage: Number(car.mileage || 0),
                    airbags: Number(car.airbags || 0),
                    duty_paid: parseInt(car.duty_paid || 1),
                    spare_key: parseInt(car.spare_key || 0),
                    // Ensure strings are never null
                    vehicle_condition: car.vehicle_condition || 'Foreign Used',
                    color: car.color || '',
                    vin: car.vin || ''
                }; 

                // 3. Preview Logic
                if (car.main_image && typeof car.main_image === 'string') {
                    this.mainPreview = '../' + car.main_image;
                } else {
                    this.mainPreview = '../assets/img/default-car.jpg';
                }
                
                // 4. Background Gallery Load
                this.galleryPreviews = [];
                this.existingGallery = [];
                const res = await fetch(`?action=get_gallery&id=${car.id}`);
                const images = await res.json();
                this.existingGallery = images.map(img => ({
                    id: img.id,
                    path: '../' + img.image_path
                }));
            } catch (e) {
                console.error("Data Load Error:", e);
            }
        },

        resetFormObject() {
            return { 
                id: '', make: '', model: '', year: 2026, price: 0, 
                cost_price: 0, mileage: 0, transmission: 'Automatic', 
                fuel_type: 'Petrol', status: 'active', vin: '', color: '',
                description: '', location: '', warranty: '', airbags: 0,
                service_history: 'Not Specified', accident_history: 'No Accidents',
                doors: 4, seats: 5, drive_type: 'FWD',
                vehicle_condition: 'Foreign Used', import_grade: '',
                previous_owners: 1, registration_number: '',
                duty_paid: 1, spare_key: 0, fuel_tank_capacity: ''
            };
        },

        resetForm() {
            this.formData = this.resetFormObject();
        },

        

        openModal() { 
            this.editMode = false; 
            this.submitting = false;
            this.mainPreview = null; 
            this.galleryPreviews = []; 
            this.existingGallery = [];
            this.resetForm();
            this.isModalOpen = true; 
        },

        async editCar(car) { 
            // 1. UI FIRST: Open modal immediately so the user sees action
            this.isModalOpen = true; 
            this.editMode = true; 
            this.submitting = false;

            try {
                // 2. DATA: Map with safe fallbacks for NULL values
                this.formData = { 
                    ...car, 
                    price: Number(car.price || 0), 
                    cost_price: Number(car.cost_price || 0),
                    year: Number(car.year || 2026),
                    mileage: Number(car.mileage || 0),
                    airbags: Number(car.airbags || 0),
                    // Ensure strings are never null
                    vehicle_condition: car.vehicle_condition || 'Foreign Used',
                    import_grade: car.import_grade || '',
                    previous_owners: car.previous_owners || 1,
                    registration_number: car.registration_number || '',
                    duty_paid: car.duty_paid ?? 1,
                    spare_key: car.spare_key ?? 0,
                    color: car.color || '',
                    vin: car.vin || '',
                    description: car.description || ''
                }; 

                // 3. PREVIEW FIX: Defensive check for null/undefined main_image
                if (car.main_image && typeof car.main_image === 'string' && !car.main_image.includes('default-car.jpg')) {
                    this.mainPreview = '../' + car.main_image;
                } else {
                    this.mainPreview = '../assets/img/default-car.jpg';
                }
                
                // 4. GALLERY: Fetch without blocking UI
                this.galleryPreviews = [];
                this.existingGallery = [];
                
                const res = await fetch(`?action=get_gallery&id=${car.id}`);
                if (res.ok) {
                    const images = await res.json();
                    this.existingGallery = images.map(img => ({
                        id: img.id,
                        path: '../' + img.image_path
                    }));
                }
            } catch (e) {
                console.error("Edit Mode Logic Error:", e);
                // The modal is already open, so the user can still try to edit
            }
        },

        resetForm() {
            this.formData = { 
                id: '', make: '', model: '', year: 2026, price: 0, 
                cost_price: 0, mileage: 0, transmission: 'Automatic', 
                fuel_type: 'Petrol', status: 'active', vin: '', color: '',
                description: '', location: '', warranty: '', airbags: 0,
                service_history: 'Not Specified', accident_history: 'No Accidents',
                doors: 4, seats: 5, drive_type: 'FWD',
                vehicle_condition: 'Foreign Used', import_grade: '',
                previous_owners: 1, registration_number: '',
                duty_paid: 1, spare_key: 0, fuel_tank_capacity: ''
            };
        },

        async saveCar() {
            this.submitting = true; 
            const formElement = document.getElementById('carForm');
            if (!formElement) {
                console.error("Form element 'carForm' not found");
                this.submitting = false;
                return;
            }

            const data = new FormData(formElement);
            data.set('action', 'save_car'); 
            if (this.editMode && this.formData.id) {
                data.set('car_id', this.formData.id);
            }
            
            try {
                const res = await fetch('', { method: 'POST', body: data });
                const result = await res.json();
                
                if (result.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                    this.submitting = false;
                }
            } catch (e) { 
                console.error(e);
                alert('Connection Failed. Check file sizes or server timeout.');
                this.submitting = false;
            }
        },

        async deleteGalleryImage(imageId, index) {
            if(!confirm('Permanently delete this photo?')) return;
            const data = new FormData();
            data.append('action', 'delete_gallery_image');
            data.append('image_id', imageId);
            try {
                const res = await fetch('', { method: 'POST', body: data });
                const result = await res.json();
                if (result.success) this.existingGallery.splice(index, 1);
            } catch (e) { alert('Failed to delete image'); }
        },

        toggleAll(e) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            this.selectedRows = e.target.checked ? Array.from(checkboxes).map(el => el.value) : [];
        },

        async deleteSingle(id) {
            if (confirm('Permanently remove this asset?')) await this.executeDeletion([id]);
        },

        async deleteMultiple() {
            if (confirm(`Delete ${this.selectedRows.length} assets?`)) await this.executeDeletion(this.selectedRows);
        },

        async executeDeletion(ids) {
            const formData = new FormData();
            formData.append('action', 'delete_cars');
            formData.append('ids', JSON.stringify(ids));
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) window.location.reload();
            } catch (e) { console.error('Fetch Error:', e); }
        }
    }
}
</script>







</body>
</html>


