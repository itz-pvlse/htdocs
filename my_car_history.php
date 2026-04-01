<?php
// customer_job_status.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php';
include 'includes/header.php';

// ---------- Access control ----------
if (!isset($_SESSION['user_id'])) {
    echo "<p>Please <a href='login.php'>log in</a> to view your service status.</p>";
    exit;
}
$user_id = (int) $_SESSION['user_id'];

// ---------- Input filters ----------
$garage_id = isset($_GET['garage_id']) ? (int) $_GET['garage_id'] : 0;
$from_date = trim($_GET['from_date'] ?? '');
$to_date   = trim($_GET['to_date'] ?? '');
$search    = trim($_GET['search'] ?? '');

// ---------- Helpers ----------
function h($s) {
    return htmlspecialchars($s ?? '-', ENT_QUOTES);
}
function friendlyDate($d) {
    if (empty($d) || $d === '0000-00-00' || $d === null) return '-';
    $ts = strtotime($d);
    return $ts ? date('d M, Y', $ts) : '-';
}

// ---------- Fetch jobs ----------
$sql = "
    SELECT j.*,
           sr.vehicle_id,
           sr.requested_service,
           v.plate_no, v.make, v.model,
           g.name AS garage_name
    FROM jobs j
    INNER JOIN service_requests sr ON sr.id = j.request_id
    LEFT JOIN vehicles v ON v.id = sr.vehicle_id
    LEFT JOIN garages g ON g.id = j.garage_id
    WHERE sr.owner_id = :user_id
";
$params = ['user_id' => $user_id];

// Optional filters
if ($garage_id > 0) {
    $sql .= " AND j.garage_id = :garage_id";
    $params['garage_id'] = $garage_id;
}
if ($from_date && $to_date) {
    $sql .= " AND j.created_at BETWEEN :from_date AND :to_date";
    $params['from_date'] = $from_date." 00:00:00";
    $params['to_date']   = $to_date." 23:59:59";
}
if ($search !== '') {
    $sql .= " AND (
        v.plate_no LIKE :search OR v.make LIKE :search OR v.model LIKE :search
        OR j.job_type LIKE :search
    )";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY j.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Fetch invoices ----------
$jobIds = array_column($jobs, 'id');
$invoicesMap = [];
if (!empty($jobIds)) {
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $invSql = "SELECT * FROM invoices WHERE job_id IN ($placeholders)";
    $stmt = $pdo->prepare($invSql);
    $stmt->execute($jobIds);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($invoices as $inv) {
        $invoicesMap[$inv['job_id']] = $inv;
    }
}

// ---------- Group jobs ----------
$groups = [
    'Pending' => [],
    'InProgress' => [],
    'Completed' => [],
    'Other' => []
];

foreach ($jobs as $job) {
    $status = strtolower($job['job_status'] ?? '');
    $hasInvoice = isset($invoicesMap[$job['id']]);
    
    if ($status === 'completed' && $job['invoice_generated'] == 1) {
        $groups['Completed'][] = $job;
    } elseif ($status === 'in_progress' || $status === 'assigned') {
        $groups['InProgress'][] = $job;
    } elseif ($status === 'pending') {
        $groups['Pending'][] = $job;
    } else {
        $groups['Other'][] = $job;
    }
}

// ---------- Counts ----------
$countPending   = count($groups['Pending']);
$countProgress  = count($groups['InProgress']);
$countCompleted = count($groups['Completed']);
$countOther     = count($groups['Other']);
$total = count($jobs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Car Jobs Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f4f6f8; font-family:'Segoe UI',sans-serif; }
    h2 { margin-bottom:30px; color:#333; }
    .card-summary { border-radius:12px; padding:20px; color:#fff; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1); text-align:center; }
    .job-card { border-radius:12px; box-shadow:0 3px 12px rgba(0,0,0,0.08); margin-bottom:20px; transition:0.2s; }
    .job-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(0,0,0,0.12); }
    .job-card-header { padding:15px; border-top-left-radius:12px; border-top-right-radius:12px; display:flex; justify-content:space-between; align-items:center; background:#343a40; color:#fff; }
    .job-card-body { padding:15px; background:#fff; border-bottom-left-radius:12px; border-bottom-right-radius:12px; color:#333; }
    .status-badge { padding:5px 12px; border-radius:20px; font-size:0.85rem; font-weight:500; color:#fff; }
    .status-completed { background:#28a745; }
    .status-inprogress { background:#ffc107; color:#212529; }
    .status-pending { background:#dc3545; }
    .status-other { background:#6c757d; }
    .view-invoice-btn {
    background: linear-gradient(135deg, #28a745, #218838);
    border: none;
    color: #fff;
    font-weight: 500;
    transition: transform 0.2s, box-shadow 0.2s;
}

.view-invoice-btn:hover {
    background: linear-gradient(135deg, #218838, #1e7e34);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.2);
    text-decoration: none;
}

</style>
</head>
<body>
<div class="container my-4">

    <h2 class="text-center">My Car Jobs Dashboard</h2>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-6"><div class="card-summary bg-primary"><h5>Total Jobs</h5><p><?= $total ?></p></div></div>
        <div class="col-md-3 col-6"><div class="card-summary bg-success"><h5>Completed</h5><p><?= $countCompleted ?></p></div></div>
        <div class="col-md-3 col-6"><div class="card-summary bg-warning text-dark"><h5>In Progress</h5><p><?= $countProgress ?></p></div></div>
        <div class="col-md-3 col-6"><div class="card-summary bg-danger"><h5>Pending</h5><p><?= $countPending ?></p></div></div>
    </div>

    <!-- Filters -->
    <form class="row g-2 mb-4" method="get">
        <input type="hidden" name="garage_id" value="<?= h($garage_id) ?>">
        <div class="col-md-3 col-6"><input type="date" class="form-control" name="from_date" value="<?= h($from_date) ?>"></div>
        <div class="col-md-3 col-6"><input type="date" class="form-control" name="to_date" value="<?= h($to_date) ?>"></div>
        <div class="col-md-4 col-12"><input type="text" class="form-control" name="search" value="<?= h($search) ?>" placeholder="Search plate, make, model or job type"></div>
        <div class="col-md-2 col-12"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
    </form>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pending">Pending (<?= $countPending ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#progress">In Progress (<?= $countProgress ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#completed">Completed (<?= $countCompleted ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#other">Other (<?= $countOther ?>)</button></li>
    </ul>

    <div class="tab-content mt-3">
        <?php foreach (['Pending'=>'pending','InProgress'=>'inprogress','Completed'=>'completed','Other'=>'other'] as $key=>$class): ?>
            <div class="tab-pane fade <?= $key==='Pending'?'show active':'' ?>" id="<?= strtolower($key) ?>">
                <div class="row">
                    <?php if (empty($groups[$key])): ?>
                        <div class="alert alert-info text-center py-2">No jobs found.</div>
                    <?php else: ?>
                        <?php foreach ($groups[$key] as $job): 
                            $jid = $job['id'];
                            $statusBadge = $class;
                            $vehicleName = h(($job['make']??'').' '.($job['model']??'').' ('.($job['plate_no']??'-').')');
                            $inv = $invoicesMap[$jid] ?? null;
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="job-card">
                                    <div class="job-card-header">
                                        <div><strong><?= $vehicleName ?></strong><br><?= h($job['garage_name']??'-') ?></div>
                                        <span class="status-badge status-<?= $statusBadge ?>"><?= ucfirst($key) ?></span>
                                    </div>
                                    <div class="job-card-body">
                                        <p><strong>Job ID:</strong> <?= h($jid) ?></p>
                                       <p class="mb-1"><strong>Service:</strong> <?= htmlspecialchars($job['requested_service'] ?? '-') ?></p>
                                        <p><strong>Created At:</strong> <?= friendlyDate($job['created_at']??null) ?></p>
                                        <?php if ($key==='Completed' && $inv): ?>
                                            <p>
                                                <strong>Invoice:</strong> 
                                                <a href="jobs/view_invoice.php?job_id=<?= htmlspecialchars($jid) ?>" 
   class="btn btn-success btn-sm rounded-pill shadow-sm view-invoice-btn" 
   target="_blank">
   <i class="bi bi-receipt me-1"></i> View Invoice
</a>

                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
