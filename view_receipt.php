<?php
// 1. SYSTEM SETTINGS & AUTHENTICATION
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config/db.php';

$invoice_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id === 0) {
    header("Location: login.php");
    exit;
}

/**
 * 2. FETCH MAIN DATA
 */
$query = "SELECT 
            i.id as invoice_id, i.labor_total, i.parts_total, i.tax_amount, i.discount, i.grand_total, i.created_at,
            sr.requested_service, v.plate_no, v.make, v.model,
            g.name as garage_name, g.location, g.phone as garage_phone,
            u.name as owner_name, m.name as mechanic_name
          FROM invoices i 
          JOIN jobs j ON i.job_id = j.id 
          JOIN service_requests sr ON j.request_id = sr.id 
          JOIN garages g ON sr.garage_id = g.id 
          JOIN users u ON sr.user_id = u.id
          JOIN users m ON j.mechanic_id = m.id
          LEFT JOIN vehicles v ON sr.vehicle_id = v.id
          WHERE i.id = ? AND sr.user_id = ?
          LIMIT 1";

$stmt = $pdo->prepare($query);
$stmt->execute([$invoice_id, $user_id]);
$inv = $stmt->fetch();

if (!$inv) { die("Invoice not found."); }

$items_stmt = $pdo->prepare("SELECT * FROM job_items WHERE job_id = (SELECT job_id FROM invoices WHERE id = ?) ORDER BY type DESC");
$items_stmt->execute([$invoice_id]);
$items = $items_stmt->fetchAll();

/**
 * 3. QR CODE
 */
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$verify_url = $protocol . $_SERVER['HTTP_HOST'] . "/verify_invoice.php?id=" . $inv['invoice_id'];
$qr_api_url = "https://quickchart.io/qr?text=" . urlencode($verify_url) . "&size=150&margin=0";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS_Receipt_#<?= $inv['invoice_id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Thermal printers use Monospace fonts for perfect alignment */
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap');
        
        body { 
            background: #f1f5f9; 
            font-family: 'Courier Prime', monospace; 
            -webkit-print-color-adjust: exact;
        }

        /* Standard Thermal Paper Width (approx 80mm) */
        .thermal-paper {
            width: 380px;
            background: white;
            padding: 20px;
            margin: 20px auto;
            color: #000;
            border: 1px dashed #ccc;
        }

        .dashed-line {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .thermal-paper { 
                margin: 0; 
                border: none; 
                width: 100%; /* Printer driver handles the width */
            }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

    <div class="thermal-paper">
        <div class="text-center uppercase">
            <h1 class="text-xl font-bold"><?= htmlspecialchars($inv['garage_name']) ?></h1>
            <p class="text-sm"><?= htmlspecialchars($inv['location']) ?></p>
            <p class="text-sm">TEL: <?= htmlspecialchars($inv['garage_phone']) ?></p>
        </div>

        <div class="dashed-line"></div>

        <div class="text-sm">
            <p>RCpt: #INV-<?= str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT) ?></p>
            <p>DATE: <?= date('d/m/Y H:i', strtotime($inv['created_at'])) ?></p>
            <p>CUST: <?= htmlspecialchars($inv['owner_name']) ?></p>
            <p>VEH : <?= htmlspecialchars($inv['plate_no']) ?></p>
            <p>DESC: <?= htmlspecialchars($inv['make'] . ' ' . $inv['model']) ?></p>
        </div>

        <div class="dashed-line"></div>

        <table class="w-full text-sm uppercase">
            <thead>
                <tr class="text-left">
                    <th class="pb-2">DESCRIPTION</th>
                    <th class="pb-2 text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="py-1"><?= htmlspecialchars($item['description']) ?></td>
                    <td class="py-1 text-right"><?= number_format($item['subtotal'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        <div class="text-sm space-y-1">
            <div class="flex justify-between">
                <span>LABOR:</span>
                <span><?= number_format($inv['labor_total'], 0) ?></span>
            </div>
            <div class="flex justify-between">
                <span>PARTS:</span>
                <span><?= number_format($inv['parts_total'], 0) ?></span>
            </div>
            <?php if($inv['discount'] > 0): ?>
            <div class="flex justify-between font-bold">
                <span>DISC:</span>
                <span>-<?= number_format($inv['discount'], 0) ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between text-lg font-bold border-t border-black pt-1 mt-1">
                <span>TOTAL:</span>
                <span>KES <?= number_format($inv['grand_total'], 0) ?></span>
            </div>
        </div>

        <div class="dashed-line"></div>

        <div class="text-center space-y-4">
            <div class="flex justify-center py-2">
                <img src="<?= $qr_api_url ?>" alt="QR" class="w-32 h-32 grayscale contrast-200">
            </div>
            <p class="text-[10px] font-bold">VERIFIED BY AUTOLOG</p>
            <p class="text-[10px] italic">*** THANK YOU FOR YOUR VISIT ***</p>
            <p class="text-[8px] opacity-50 uppercase tracking-tighter">Mech: <?= htmlspecialchars($inv['mechanic_name']) ?></p>
        </div>
    </div>

    <div class="text-center no-print pb-10">
        <button onclick="window.print()" class="bg-black text-white px-10 py-3 rounded font-bold uppercase text-xs">
            Print Thermal Receipt
        </button>
    </div>

</body>
</html>
