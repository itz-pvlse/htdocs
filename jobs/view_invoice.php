<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Get job ID from URL
$job_id = $_GET['job_id'] ?? 0;
if (!$job_id) die("Job ID not specified.");

// Base query
$sql = "
    SELECT 
        i.id AS invoice_id,
        i.labor_cost,
        i.parts_cost,
        i.additional_charges,
        i.discount,
        i.total,
        i.created_at,
        j.id AS job_id,
        j.completed_at,
        v.plate_no,
        v.make,
        v.model,
        u.name AS owner_name,
        sr.requested_service,
        g.name AS garage_name,
        g.logo AS garage_logo,
        g.email AS garage_email,
        g.phone AS garage_phone,
        m.name AS mechanic_name
    FROM invoices i
    JOIN jobs j ON j.id = i.job_id
    JOIN service_requests sr ON sr.id = j.request_id
    JOIN vehicles v ON sr.vehicle_id = v.id
    JOIN users u ON sr.owner_id = u.id
    LEFT JOIN garages g ON g.id = j.garage_id
    LEFT JOIN users m ON m.id = j.mechanic_id
    WHERE j.id = ?
";

// If the user is a car owner, restrict access to only their jobs
if ($role === 'user') {
    $sql .= " AND sr.owner_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$job_id, $user_id]);
} else {
    // Garage staff or admin can view any job
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$job_id]);
}

$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) die("Invoice not found or you do not have permission to view it.");
// Fetch parts used
$stmt = $pdo->prepare("
    SELECT 
        pu.id,
        pi.part_name,
        pu.quantity_used,
        pi.unit_price,
        (pu.quantity_used * pi.unit_price) AS total_cost
    FROM parts_used pu
    JOIN parts_inventory pi ON pu.part_id = pi.id
    WHERE pu.job_id = ?
");
$stmt->execute([$job_id]);
$parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice #<?= htmlspecialchars($invoice['invoice_id']) ?> — AutoLog</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
body {
    background: #f4f6f8;
    color: #000;
    font-family: 'Inter', sans-serif;
}
.invoice-container {
    max-width: 800px;
    margin: auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    border: 1px solid #ddd;
}
.header, .footer {
    text-align: center;
}
.header img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}
h2, h4, h5 { margin: 0; }
.section { margin-top: 20px; }
.table thead { background: #39a2ff; color: #fff; }
.table tbody tr:hover { background: #eaf6ff; }
.total-row { font-weight: 700; font-size: 1.1rem; }
.total-box {
    background: #6c5ce7;
    padding: 12px;
    border-radius: 6px;
    color: #fff;
    text-align: right;
    font-size: 1.2rem;
    font-weight: 700;
}
.btn-print { background: #39a2ff; color: #fff; border: none; margin: 0 5px; }
.btn-print:hover { opacity: 0.9; }
.no-pdf { display: block; }
.footer-contact { font-size: 0.9rem; color: #555; margin-top: 20px; }
.platform-note { font-size: 0.8rem; color: #888; margin-top: 5px; }
@media print { .no-pdf { display: none; } }
</style>
</head>
<body>

<div class="invoice-container" id="invoiceContent">
    <!-- Header -->
    <div class="header mb-4">
        <?php if($invoice['garage_logo']): ?>
        <img src="../uploads/logo/<?= htmlspecialchars($invoice['garage_logo']) ?>" class="garage-logo" alt="Garage Logo">
        <?php endif; ?>
        <h2><?= htmlspecialchars($invoice['garage_name'] ?? 'Garage') ?></h2>
        <h5>Invoice #<?= htmlspecialchars($invoice['invoice_id']) ?></h5>
        <small>Created at: <?= htmlspecialchars($invoice['created_at']) ?></small>
    </div>

    <!-- Vehicle & Job Details -->
    <div class="section">
        <h5>Vehicle & Job Details</h5>
        <p><strong>Job ID:</strong> <?= htmlspecialchars($invoice['job_id']) ?></p>
        <p><strong>Plate Number:</strong> <?= htmlspecialchars($invoice['plate_no']) ?></p>
        <p><strong>Make & Model:</strong> <?= htmlspecialchars($invoice['make'] ?? '-') ?> <?= htmlspecialchars($invoice['model']) ?></p>
        <p><strong>Owner:</strong> <?= htmlspecialchars($invoice['owner_name']) ?></p>
        <p><strong>Service Requested:</strong> <?= htmlspecialchars($invoice['requested_service']) ?></p>
        <p><strong>Mechanic:</strong> <?= htmlspecialchars($invoice['mechanic_name'] ?? '-') ?></p>
        <p><strong>Job Completed At:</strong> <?= htmlspecialchars($invoice['completed_at']) ?></p>
    </div>

    <!-- Parts Used -->
    <div class="section">
        <h5>Parts Used</h5>
        <?php if($parts): ?>
        <table class="table table-bordered mb-0">
            <thead>
                <tr>
                    <th>Part</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($parts as $part): ?>
                <tr>
                    <td><?= htmlspecialchars($part['part_name']) ?></td>
                    <td><?= $part['quantity_used'] ?></td>
                    <td><?= number_format($part['unit_price'],2) ?></td>
                    <td><?= number_format($part['total_cost'],2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3" class="text-end">Parts Subtotal</td>
                    <td><?= number_format($invoice['parts_cost'],2) ?></td>
                </tr>
            </tbody>
        </table>
        <?php else: ?>
        <p>No parts used for this job.</p>
        <?php endif; ?>
    </div>

    <!-- Cost Summary -->
    <div class="section">
        <h5>Cost Summary</h5>
        <table class="table table-bordered mb-3">
            <tbody>
                <tr>
                    <td>Labor Cost</td>
                    <td><?= number_format($invoice['labor_cost'],2) ?></td>
                </tr>
                <tr>
                    <td>Additional Charges</td>
                    <td><?= number_format($invoice['additional_charges'],2) ?></td>
                </tr>
                <tr>
                    <td>Discount</td>
                    <td><?= number_format($invoice['discount'],2) ?></td>
                </tr>
            </tbody>
        </table>
        <div class="total-box">
            Total: <?= number_format($invoice['total'],2) ?>
        </div>
    </div>

    <!-- Footer Contact & Platform Promotion -->
    <div class="footer mt-4">
        <div class="footer-contact">
            <p>Contact: <?= htmlspecialchars($invoice['garage_email'] ?? '-') ?> | <?= htmlspecialchars($invoice['garage_phone'] ?? '-') ?></p>
        </div>
        <div class="platform-note">
            <p>Generated by <strong>AutoLog</strong> — Kenya’s Car History & Maintenance Platform</p>
        </div>
        <p>Thank you for choosing <?= htmlspecialchars($invoice['garage_name'] ?? 'our garage') ?>!</p>
    </div>
</div>

<!-- Buttons (excluded from PDF) -->
<div class="text-center mt-3 no-pdf">
    <button class="btn btn-print" onclick="window.print()">Print Invoice</button>
    <button class="btn btn-print" onclick="downloadPDF()">Download PDF</button>
</div>

<script>
function downloadPDF() {
    document.querySelectorAll('.no-pdf').forEach(el => el.style.display = 'none');
    const element = document.getElementById('invoiceContent');
    const opt = {
        margin: 0.25,
        filename: 'Invoice_<?= htmlspecialchars($invoice['invoice_id']) ?>.pdf',
        image: { type: 'jpeg', quality: 1 },
        html2canvas: { scale: 2, scrollY: -window.scrollY },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save().then(() => {
        document.querySelectorAll('.no-pdf').forEach(el => el.style.display = '');
    });
}
</script>

</body>
</html>