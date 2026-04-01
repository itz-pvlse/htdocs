<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php'; // PDO connection
date_default_timezone_set('Africa/Nairobi');
if (!isset($_GET['plate']) || empty($_GET['plate'])) {
    die("No vehicle specified.");
}

$plate_no = trim($_GET['plate']);

// --------------------
// Fetch Vehicle
// --------------------
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE plate_no = ?");
$stmt->execute([$plate_no]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vehicle) die("Vehicle not found.");

// --------------------
// Fetch Current Owner
// --------------------
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$vehicle['user_id']]);
$current_owner = $stmt->fetch(PDO::FETCH_ASSOC);

// --------------------
// Fetch Ownership History
// --------------------
$stmt = $pdo->prepare("
    SELECT o.*, 
           u1.name AS previous_owner_name, 
           u2.name AS new_owner_name
    FROM ownership_transfers o
    LEFT JOIN users u1 ON o.previous_owner_id = u1.id
    LEFT JOIN users u2 ON o.new_owner_id = u2.id
    WHERE o.vehicle_id = ? ORDER BY o.transfer_date ASC
");
$stmt->execute([$vehicle['id']]);
$ownerships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------
// Fetch Service Logs
// --------------------
$stmt = $pdo->prepare("
    SELECT service_date, service_type, service_description
    FROM service_logs 
    WHERE vehicle_id = ? ORDER BY service_date DESC
");
$stmt->execute([$vehicle['id']]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------
// Generate Reference Number
// --------------------
function generateRefNo($pdo) {
    do {
        $ref_no = "AL-" . date('Ymd-His') . "-" . rand(1000, 9999);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicle_reports WHERE ref_no = ?");
        $stmt->execute([$ref_no]);
        $exists = $stmt->fetchColumn();
    } while($exists > 0);
    return $ref_no;
}

// --------------------
// Capture Generator Info
// --------------------
$generated_by = $_SESSION['user_id'] ?? null;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$timestamp = date('Y-m-d H:i:s');

// --------------------
// Generate Snapshot Hash
// --------------------
$report_data = json_encode([
    'vehicle' => $vehicle,
    'current_owner' => $current_owner,
    'ownership_history' => $ownerships,
    'services' => $services
]);
$report_hash = hash('sha256', $report_data);

// --------------------
// Check for existing report
// --------------------
$stmt = $pdo->prepare("SELECT * FROM vehicle_reports WHERE vehicle_id = ? ORDER BY generated_at DESC LIMIT 1");
$stmt->execute([$vehicle['id']]);
$existing_report = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_report) {
    $ref_no = $existing_report['ref_no'];
} else {
    $ref_no = generateRefNo($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO vehicle_reports 
        (vehicle_id, ref_no, generated_by, ip_address, user_agent, snapshot_hash, generated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $vehicle['id'],
        $ref_no,
        $generated_by,
        $ip_address,
        $user_agent,
        $report_hash,
        $timestamp
    ]);
}

// --------------------
// QR Code URL
// --------------------
$verify_url = 'https://autolog.xo.je/verify_report.php?ref=' . $ref_no;

// --------------------
// Vehicle Image Path
// --------------------
$image_path = $vehicle['image_path'] ? '/' . ltrim($vehicle['image_path'], '/') : '/assets/default_vehicle.jpg';
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">

<title>Official Vehicle Record - <?= $plate_no ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
    :root {
        --registry-blue: #0f172a;
        --border-gold: #e2e8f0;
        --accent-blue: #2563eb;
    }

    @media print { 
        .no-print { display: none; } 
        body { padding: 0; background: white; }
        .doc-container { box-shadow: none; border: none; width: 100%; max-width: 100%; margin: 0; }
    }

    body { 
        background-color: #f8fafc; 
        padding: 40px 20px; 
        font-family: 'Inter', sans-serif; 
        color: var(--registry-blue);
    }

    .doc-container { 
        background: white; 
        max-width: 1000px; 
        margin: auto; 
        padding: 60px; 
        position: relative;
        border-top: 8px solid var(--registry-blue);
        box-shadow: 0 20px 50px rgba(0,0,0,0.05);
        border-radius: 4px;
    }

    /* Watermark Effect */
    .doc-container::after {
        content: "AUTOLOG";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 150px;
        font-weight: 900;
        color: rgba(0,0,0,0.02);
        pointer-events: none;
        z-index: 0;
    }

    .header-rule {
        height: 2px;
        background: linear-gradient(to right, transparent, var(--registry-blue), transparent);
        margin: 20px 0;
    }

    .section-title {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        color: var(--accent-blue);
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .section-title::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }

    .data-grid-item {
        padding: 12px 0;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
    }

    .label-text { font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
    .value-text { font-size: 0.85rem; font-weight: 700; color: var(--registry-blue); }

    .plate-hero {
        font-family: 'JetBrains Mono', monospace;
        background: #1e293b;
        color: white;
        padding: 10px 25px;
        border-radius: 8px;
        display: inline-block;
        font-size: 1.5rem;
        letter-spacing: 4px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    table { width: 100%; border-radius: 8px; overflow: hidden; }
    th { 
        background: #f8fafc; 
        font-size: 0.65rem; 
        font-weight: 800; 
        text-transform: uppercase; 
        color: #475569; 
        padding: 12px 15px;
        border-bottom: 2px solid #e2e8f0;
    }
    td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; }

    .ref-badge {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.7rem;
        background: #f1f5f9;
        padding: 4px 12px;
        border-radius: 4px;
        color: #475569;
    }
</style>
</head>
<body>

<div class="doc-container">

    <div class="flex justify-between items-start mb-8 relative z-10">
        <div class="flex items-center gap-4">
            <img src="assets/toplogo.png" alt="Logo" class="h-14 w-14 rounded-full border-2 border-slate-100 shadow-sm">
            <div>
                <h4 class="font-black text-xl tracking-tighter">AUTOLOG</h4>
                <p class="text-[10px] text-slate-400 uppercase font-bold tracking-widest">Digital Registry Division</p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Document Reference</p>
            <span class="ref-badge"><?= $ref_no ?></span>
        </div>
    </div>

    <header class="text-center mb-12 relative z-10">
        <h1 class="text-3xl font-black text-slate-900 uppercase tracking-tight">Copy of Records</h1>
        <p class="text-slate-500 text-sm italic">Verification Certificate for Motor Vehicle Registration</p>
        <div class="header-rule"></div>
        <div class="mt-6">
            <div class="plate-hero mb-2"><?= $vehicle['plate_no'] ?></div>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 relative z-10">
        
        <div class="lg:col-span-1">
            <div class="section-title">Visual Identity</div>
            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 mb-6 shadow-inner">
    <div class="w-full h-56 flex items-center justify-center bg-white rounded-xl">
        <img src="<?= htmlspecialchars($image_path) ?>" 
             alt="Vehicle" 
             class="max-h-full max-w-full object-contain rounded-xl shadow-md">
    </div>
</div>

            <div class="section-title">Certification</div>
            <div class="flex justify-center bg-white p-4 border border-dashed border-slate-200 rounded-xl mb-6">
                <canvas id="qrcode" class="h-32 w-32"></canvas>
            </div>
            <p class="text-[10px] text-slate-400 text-center leading-relaxed">
                Scan QR code to verify the authenticity of this digital snapshot on the AutoLog Verification Server.
            </p>
        </div>

        <div class="lg:col-span-2 space-y-10">
            
            <section>
                <div class="section-title">Technical Particulars</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8">
                    <?php
                    $details = [
                        'Make / Model' => $vehicle['make'] . ' ' . $vehicle['model'],
                        'Body Colour' => $vehicle['color'],
                        'Chassis No.' => '<span class="font-mono text-[0.8rem]">'.strtoupper($vehicle['chassis_number']).'</span>',
                        'Engine No.' => '<span class="font-mono text-[0.8rem]">'.$vehicle['engine_number'].'</span>',
                        'Manufacture Year' => $vehicle['manufacture_year'] ?? 'N/A',
                        'Fuel / Trans' => $vehicle['fuel_type'] . ' / ' . $vehicle['transmission_type'],
                        'Current Mileage' => number_format($vehicle['current_mileage'] ?? 0) . ' KM',
                        'Caveat Status' => '<span class="'.($vehicle['under_caveat'] == 'No' ? 'text-green-600' : 'text-red-600').'">'.$vehicle['under_caveat'].'</span>',
                        'Insurance Exp' => $vehicle['insurance_expiry_date'] ?? 'N/A',
                        'Engine CC' => $vehicle['rating_cc'] ?? 'N/A'
                    ];
                    foreach ($details as $label => $value): ?>
                        <div class="data-grid-item">
                            <span class="label-text"><?= $label ?></span>
                            <span class="value-text"><?= $value ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section>
                <div class="section-title">Ownership Summary</div>
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 mb-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="label-text">Current Registered Owner</p>
                            <p class="text-lg font-black text-slate-900"><?= $current_owner['name'] ?? 'N/A' ?></p>
                        </div>
                        <span class="bg-green-100 text-green-700 text-[10px] font-black px-3 py-1 rounded-full uppercase">Verified</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="text-left">
                        <thead>
                            <tr><th>Previous Holder</th><th>New Holder</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if(empty($ownerships)): ?>
                                <tr><td colspan="3" class="text-center text-slate-400 py-4 italic">No transfer records found.</td></tr>
                            <?php endif; ?>
                            <?php foreach($ownerships as $own): ?>
                            <tr>
                                <td class="font-semibold"><?= $own['previous_owner_name'] ?? 'N/A' ?></td>
                                <td class="font-semibold"><?= $own['new_owner_name'] ?? 'N/A' ?></td>
                                <td class="text-slate-500 font-mono"><?= date('d M Y', strtotime($own['transfer_date'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <div class="section-title">Service Maintenance Log</div>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Type</th><th>Observation</th></tr>
                        </thead>
                        <tbody>
                            <?php if(empty($services)): ?>
                                <tr><td colspan="3" class="text-center text-slate-400 py-4 italic">No service logs available for this vehicle.</td></tr>
                            <?php endif; ?>
                            <?php foreach($services as $svc): ?>
                            <tr>
                                <td class="font-mono text-blue-600"><?= date('d M Y', strtotime($svc['service_date'])) ?></td>
                                <td class="font-bold"><?= $svc['service_type'] ?></td>
                                <td class="text-slate-600"><?= $svc['service_description'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
    window.onload = function() {
        var qr = new QRious({
            element: document.getElementById('qrcode'),
            value: "<?= addslashes($verify_url) ?>",
            size: 200,
            foreground: '#0f172a'
        });
    };
    </script>

    <footer class="mt-16 pt-8 border-t border-slate-100 relative z-10 text-center">
        <div class="no-print mb-8">
            <button onclick="window.print()" class="bg-slate-900 text-white px-8 py-3 rounded-full font-bold shadow-xl hover:bg-slate-800 transition transform hover:-translate-y-1">
                Download PDF / Print Report
            </button>
        </div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4">Official Disclaimer</p>
        <p class="text-[10px] text-slate-400 leading-relaxed max-w-2xl mx-auto italic">
            This document is a digital representation of vehicle records maintained by AutoLog. It is provided for informational purposes only. For legal matters, transfer of ownership, or court proceedings, refer only to the official records provided by the <strong>National Transport and Safety Authority (NTSA)</strong>. AutoLog does not assume liability for omissions or discrepancies in third-party data.
        </p>
        <div class="mt-6 font-mono text-[9px] text-slate-300">
            GENERATED_ON: <?= date('Y-m-d H:i:s T') ?> | HASH: <?= substr($report_hash, 0, 16) ?>...
        </div>
    </footer>

</div>

</body>
</html>
