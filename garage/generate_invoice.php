<?php
// 1. Error Reporting & Sessions
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$job_id = $_GET['job_id'] ?? null;

if (!$job_id) {
    die("Invalid Request: No Job ID provided.");
}

try {
    // 2. FETCH JOB DATA (Fixed for Workforce Team assignments)
    $stmt = $pdo->prepare("
        SELECT j.*, v.plate_no, v.make, v.model,
               sr.mileage,
               u_cust.name as customer_name, u_cust.phone as customer_phone,
               g.name as garage_name, g.phone as garage_phone, g.email as garage_email, 
               g.logo as garage_logo, g.location as garage_location,
               -- Use subquery to get all technicians assigned to this job
               (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') 
                FROM job_assignments ja 
                JOIN users u ON ja.mechanic_id = u.id 
                WHERE ja.job_id = j.id) as technician_names
        FROM jobs j
        LEFT JOIN service_requests sr ON j.request_id = sr.id
        LEFT JOIN vehicles v ON sr.vehicle_id = v.id
        LEFT JOIN users u_cust ON v.user_id = u_cust.id
        LEFT JOIN garages g ON sr.garage_id = g.id
        WHERE j.id = ?
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        die("Invoice Error: Job record #$job_id not found.");
    }

    // 3. GLOBAL NULL FIX
    foreach ($job as $key => $value) {
        $job[$key] = $value ?? '';
    }

    // Fetch Line Items
    $stmt_items = $pdo->prepare("SELECT * FROM job_items WHERE job_id = ?");
    $stmt_items->execute([$job_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // SAFE CALCULATION
    $subtotal = 0;
    foreach($items as $calc_item) {
        $subtotal += ($calc_item['quantity'] * $calc_item['unit_price']);
    }
    
    $tax = $job['tax_applied'] ? ($subtotal * 0.16) : 0;

} catch (PDOException $e) {
    die("Invoice Generation Failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt_<?= htmlspecialchars($job['plate_no']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap');
        body { background: #f1f5f9; font-family: 'Courier Prime', monospace; -webkit-print-color-adjust: exact; }
        .thermal-paper { width: 350px; background: white; padding: 20px; margin: 20px auto; color: #000; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border: 1px dashed #ccc; }
        .dashed-line { border-top: 1px dashed #000; margin: 10px 0; }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .thermal-paper { margin: 0; border: none; width: 100%; box-shadow: none; }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print flex justify-center gap-4 py-5">
        <a href="job_history.php" class="bg-slate-800 text-white px-5 py-2 rounded text-xs uppercase font-bold hover:bg-black transition-all">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to History
        </a>
        <button onclick="window.print()" class="bg-indigo-600 text-white px-8 py-2 rounded text-xs uppercase font-bold shadow-lg hover:bg-indigo-700">
            <i class="fa-solid fa-print mr-2"></i> Print POS Receipt
        </button>
    </div>

    <div class="thermal-paper">
        <div class="text-center uppercase">
            <h1 class="text-lg font-bold leading-tight"><?= htmlspecialchars($job['garage_name'] ?: 'AUTOLOG GARAGE') ?></h1>
            <p class="text-[10px]"><?= htmlspecialchars($job['garage_location']) ?></p>
            <p class="text-[10px]">TEL: <?= htmlspecialchars($job['garage_phone']) ?></p>
        </div>

        <div class="dashed-line"></div>

        <div class="text-[11px] space-y-0.5">
            <p>RCPT: #INV-<?= str_pad($job['id'], 5, '0', STR_PAD_LEFT) ?></p>
            <p>DATE: <?= date('d/m/Y H:i', strtotime($job['completed_at'])) ?></p>
            <p>CUST: <?= htmlspecialchars($job['customer_name']) ?></p>
            <p>VEH : <?= htmlspecialchars($job['plate_no']) ?> (<?= htmlspecialchars($job['make']) ?>)</p>
            <p>ODO : <?= number_format((float)$job['mileage'], 0) ?> KMS</p>
        </div>

        <div class="dashed-line"></div>

        <table class="w-full text-[11px] uppercase">
            <thead>
                <tr class="text-left border-b border-black">
                    <th class="pb-1">DESCRIPTION</th>
                    <th class="pb-1 text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="py-1 leading-tight">
                        <?= htmlspecialchars($item['description']) ?><br>
                        <span class="text-[9px]"><?= (float)$item['quantity'] ?> x <?= number_format($item['unit_price'], 0) ?></span>
                    </td>
                    <td class="py-1 text-right align-top"><?= number_format($item['quantity'] * $item['unit_price'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        <div class="text-[11px] space-y-1">
            <div class="flex justify-between">
                <span>SUBTOTAL:</span>
                <span><?= number_format($subtotal, 0) ?></span>
            </div>
            
            <?php if($job['discount'] > 0): ?>
            <div class="flex justify-between">
                <span>DISCOUNT:</span>
                <span>-<?= number_format($job['discount'], 0) ?></span>
            </div>
            <?php endif; ?>

            <?php if($job['tax_applied']): ?>
            <div class="flex justify-between">
                <span>VAT (16%):</span>
                <span><?= number_format($tax, 0) ?></span>
            </div>
            <?php endif; ?>

            <div class="flex justify-between text-base font-bold border-t border-black pt-1 mt-1">
                <span>TOTAL:</span>
                <span>KES <?= number_format($job['final_total'], 0) ?></span>
            </div>
        </div>

        <div class="dashed-line"></div>

        <div class="text-[11px] space-y-0.5">
            <p class="font-bold">PAYMENT: <?= strtoupper($job['payment_method']) ?></p>
            
            <?php if(!empty($job['payment_ref'])): ?>
                <p>REF NO: <?= htmlspecialchars($job['payment_ref']) ?></p>
            <?php endif; ?>

            <?php if(!empty($job['payment_acc_name'])): ?>
                <p>NAME  : <?= htmlspecialchars($job['payment_acc_name']) ?></p>
            <?php endif; ?>
            
            <p>STATUS: <?= strtoupper($job['payment_status']) ?></p>
        </div>

        <div class="dashed-line"></div>

        <div class="text-center space-y-3 pt-2">
            <div class="bg-black text-white py-1 px-2 text-[10px] font-bold inline-block uppercase">
                Next Service Due: <?= date('d/m/Y', strtotime($job['completed_at'] . ' + 3 months')) ?>
            </div>

            <?php 
                $qr_data = "INV:" . $job['id'] . "|VEH:" . $job['plate_no'] . "|AMT:" . $job['final_total'] . "|REF:" . ($job['payment_ref'] ?: 'N/A');
                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);
            ?>
            <div class="flex justify-center">
                <img src="<?= $qr_url ?>" alt="Verification QR" class="w-28 h-28 grayscale contrast-200">
            </div>
            
            <p class="text-[9px] font-bold">*** DRIVE SAFE & THANK YOU ***</p>
            <p class="text-[8px] uppercase">Tech: <?= htmlspecialchars($job['technician_names'] ?: 'Workshop Staff') ?></p>
        </div>
    </div>

</body>
</html>
