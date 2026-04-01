<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/db.php';

// Ensure user is garage/admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['garage','admin'])) {
    header("Location: ../login.php");
    exit();
}

// Get job ID from query string
$job_id = $_GET['job_id'] ?? $_POST['job_id'] ?? null;
if (!$job_id) {
    die("Job ID not specified.");
}

// Fetch job & request info
$stmt = $pdo->prepare("
    SELECT 
        j.id AS job_id,
        j.request_id,
        j.job_status,
        j.completed_at,
        j.invoice_generated,
        v.plate_no,
        v.model,
        u.name AS owner_name,
        sr.requested_service,
        sr.request_date
    FROM jobs j
    JOIN service_requests sr ON sr.id = j.request_id
    JOIN vehicles v ON sr.vehicle_id = v.id
    JOIN users u ON sr.owner_id = u.id
    WHERE j.id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) die("Job not found.");

// Fetch parts used for this job
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

// Calculate parts subtotal
$parts_subtotal = array_reduce($parts, fn($carry, $item) => $carry + $item['total_cost'], 0);

// Handle AJAX invoice submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $labor_cost = floatval($_POST['labor_cost'] ?? 0);
    $additional_charges = floatval($_POST['additional_charges'] ?? 0);
    $discount = floatval($_POST['discount'] ?? 0);

    $total = $parts_subtotal + $labor_cost + $additional_charges - $discount;

    // Insert invoice
    $stmt = $pdo->prepare("
        INSERT INTO invoices (job_id, labor_cost, parts_cost, additional_charges, discount, total, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$job_id, $labor_cost, $parts_subtotal, $additional_charges, $discount, $total]);

    $invoice_id = $pdo->lastInsertId();

    // Update job to mark invoice generated
    $stmt = $pdo->prepare("UPDATE jobs SET invoice_generated = 1 WHERE id = ?");
    $stmt->execute([$job_id]);

    // Return invoice ID for AJAX
    echo $invoice_id;
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice — AutoLog</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* ===============================
   Global Styles — AutoLog Theme
=============================== */
body {
  background-color: #0b1724;
  color: #eaf6ff;
  font-family: 'Inter', 'Segoe UI', sans-serif;
  min-height: 100vh;
}

.card {
  background-color: #12263f;
  border: none;
  border-radius: 16px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.3);
  color: #eaf6ff;
}

h2, h5 {
  color: #6fc3ff;
  font-weight: 700;
}

p {
  margin-bottom: 0.4rem;
  color: #cfddee;
}

/* ===============================
   Navbar Styling
=============================== */
.glass-navbar {
  background: rgba(20, 40, 65, 0.6);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255,255,255,0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}

.btn-primary-glow {
  background: linear-gradient(90deg, #007bff, #00bfff);
  border: none;
  color: white !important;
  border-radius: 8px;
  font-weight: 600;
  transition: 0.3s;
}
.btn-primary-glow:hover {
  box-shadow: 0 0 12px #00bfff88;
  transform: translateY(-1px);
}

/* ===============================
   Table Styling
=============================== */
.table {
  background-color: transparent;
  color: #eaf6ff;
  border-collapse: separate;
  border-spacing: 0 6px;
}

.table thead tr {
  background-color: #1e3a5c;
  color: #a9d4ff;
}

.table tbody tr {
  background-color: #15304b;
  transition: background 0.2s;
}
.table tbody tr:hover {
  background-color: #1e3a5c;
}

.table td, .table th {
  border: none;
  padding: 12px;
}

.total-row {
  background-color: #102538;
  font-weight: 700;
}

.table-dark.table-striped tbody tr:nth-of-type(odd) {
  background-color: #122b45;
}

/* ===============================
   Input Styling
=============================== */
.form-control {
  background-color: #0d2037;
  border: 1px solid #204060;
  color: #eaf6ff;
  border-radius: 8px;
  transition: all 0.2s;
}
.form-control:focus {
  border-color: #00bfff;
  box-shadow: 0 0 5px #00bfff66;
}

/* ===============================
   Buttons
=============================== */
.btn-primary {
  background: linear-gradient(90deg, #0099ff, #00ccff);
  border: none;
  font-weight: 600;
  color: #fff;
  border-radius: 10px;
  transition: all 0.25s ease;
}
.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 0 10px #00bfffaa;
}

/* ===============================
   Total Box
=============================== */
.total-box {
  background-color: #0e2238;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 1.2rem;
  font-weight: 700;
  color: #80d0ff;
  border: 1px solid #1f4366;
}

/* ===============================
   Alerts
=============================== */
.alert {
  border-radius: 10px;
  font-weight: 600;
}
.alert-success {
  background-color: #0d4022;
  border-color: #28a745;
  color: #92ffb1;
}
.alert-danger {
  background-color: #401010;
  border-color: #ff3c3c;
  color: #ffaaaa;
}
</style>
</head>

<nav class="navbar glass-navbar py-2 px-3">
    <div class="container d-flex align-items-center justify-content-between" style="max-width:1200px;">
      <div class="d-flex align-items-center gap-3">
        <a href="<?= $base ?? '/' ?>index.php" class="d-flex align-items-center gap-2 text-decoration-none" aria-label="AutoLog Home">
          <div class="brand-circle">
            <img src="<?= $base ?? '/' ?>assets/toplogo.png" alt="logo" style="width:22px;height:22px;object-fit:cover;border-radius:50%;">
          </div>
          <div style="line-height:1;">
            <div style="font-weight:700;color:#eaf6ff;font-size:0.98rem;">AutoLog</div>
            
        </a>
      </div>

      <div class="d-flex align-items-center gap-2">
        <?php if (isset($_SESSION['garage_name'])): ?>
          <div class="text-end d-none d-md-block me-2">
            <div style="font-weight:700;color:#eaf6ff;"><?= htmlspecialchars($_SESSION['garage_name']) ?></div>
            <div style="font-size:0.78rem;color:var(--muted);">Welcome back</div>
          </div>
        <?php endif; ?>

        <button class="btn btn-outline-light btn-sm d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-expanded="false" aria-label="Toggle menu">
          <i class="bi bi-list"></i>
        </button>

        <div class="collapse d-md-flex" id="mobileMenu">
          <div class="d-flex align-items-center gap-2">
            <a href="../garage.php" class="btn btn-primary-glow btn-sm d-flex align-items-center gap-2">
              <i class="bi bi-arrow-left"></i> Back
            </a>
          </div>
        </div>

      </div>
    </div>
  </nav>
  
<body class="p-3">

<div class="container"><!-- NAV -->
  
<h2 class="mb-4">Generate Invoice for Job #<?= htmlspecialchars($job['job_id']) ?></h2>

<div class="card mb-4 p-3">
<p><strong>Vehicle Plate:</strong> <?= htmlspecialchars($job['plate_no']) ?></p>
<p><strong>Model:</strong> <?= htmlspecialchars($job['model']) ?></p>
<p><strong>Owner:</strong> <?= htmlspecialchars($job['owner_name']) ?></p>
<p><strong>Job Details:</strong> <?= htmlspecialchars($job['requested_service']) ?></p>
<p><strong>Completed At:</strong> <?= htmlspecialchars($job['completed_at'] ?? '-') ?></p>
</div>

<div class="card mb-4 p-3">
<h5>Parts Used</h5>
<?php if($parts): ?>
<table class="table table-dark table-striped mb-0">
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
<td colspan="3" class="text-end">Parts Subtotal:</td>
<td><?= number_format($parts_subtotal,2) ?></td>
</tr>
</tbody>
</table>
<?php else: ?>
<p>No parts recorded for this job.</p>
<?php endif; ?>
</div>

<div id="invoiceMessage"></div>

<form method="post" class="card p-3 mb-4" id="invoiceForm">
<h5>Invoice Details</h5>
<div class="mb-3">
<label class="form-label">Labor Cost</label>
<input type="number" step="0.01" name="labor_cost" id="labor_cost" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label">Additional Charges</label>
<input type="number" step="0.01" name="additional_charges" id="additional_charges" class="form-control">
</div>
<div class="mb-3">
<label class="form-label">Discount</label>
<input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0">
</div>

<div class="mb-3">
<label class="form-label">Total</label>
<div class="total-box">
<span id="total"><?= number_format($parts_subtotal,2) ?></span>
</div>
</div>

<button type="submit" class="btn btn-primary mt-2" id="GenerateInvoiceBtn">Generate Invoice</button>
</form>
</div>


  <!-- FOOTER INCLUDE -->
  <?php include '../includes/footer.php'; ?>

<script>
const laborInput = document.getElementById('labor_cost');
const additionalInput = document.getElementById('additional_charges');
const discountInput = document.getElementById('discount');
const totalField = document.getElementById('total');
const partsSubtotal = <?= $parts_subtotal ?>;

function calculateTotal(){
    const labor = parseFloat(laborInput.value)||0;
    const additional = parseFloat(additionalInput.value)||0;
    const discount = parseFloat(discountInput.value)||0;
    totalField.textContent = (partsSubtotal+labor+additional-discount).toFixed(2);
}

[laborInput,additionalInput,discountInput].forEach(el=>el.addEventListener('input',calculateTotal));
calculateTotal();

const invoiceForm = document.getElementById('invoiceForm');
const invoiceMessage = document.getElementById('invoiceMessage');

invoiceForm.addEventListener('submit',function(e){
    e.preventDefault();
    invoiceMessage.innerHTML = '';

    const formData = new FormData(invoiceForm);
    formData.append('job_id', <?= $job_id ?>); // ✅ explicitly include job_id in POST

    fetch('<?= $_SERVER['PHP_SELF'] ?>', { // ✅ no ?job_id=...
        method:'POST',
        body: formData
    })
    .then(res=>res.text())
    .then(invoiceId=>{
        invoiceId = invoiceId.trim();
        if(invoiceId){
            // ✅ include both invoice ID and job ID when opening the invoice
            window.open("view_invoice.php?id="+invoiceId+"&job_id=<?= $job_id ?>", "_blank");
            invoiceMessage.innerHTML='<div class="alert alert-success">Invoice generated successfully! Redirecting...</div>';
            setTimeout(()=>window.location.href="list_job.php",1500);
        } else {
            invoiceMessage.innerHTML='<div class="alert alert-danger">Failed to generate invoice.</div>';
        }
    })
    .catch(err=>{
        console.error(err);
        invoiceMessage.innerHTML='<div class="alert alert-danger">Error occurred. Try again.</div>';
    });
});
</script>
</body>
</html>