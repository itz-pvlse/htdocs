<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    
}

require_once '../auth/auth_check.php';
require_once '../config/db.php';

// Ensure garage role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'garage') {
    header("Location: ../login.php");
    exit();
}

$garage_id = $_SESSION['user_id'] ?? 0;

// Fetch approved service requests
$stmt = $pdo->prepare("
    SELECT sr.id AS request_id, v.plate_no, v.model, sr.status, sr.created_at
    FROM service_requests sr
    JOIN vehicles v ON sr.vehicle_id = v.id
    WHERE sr.garage_id = ? AND sr.status = 'approved'
    ORDER BY sr.created_at DESC
");
$stmt->execute([$garage_id]);
$approvedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch already listed jobs
$stmt = $pdo->prepare("SELECT request_id FROM jobs WHERE garage_id = ?");
$stmt->execute([$garage_id]);
$listedJobs = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// Fetch jobs for this garage
$stmt = $pdo->prepare("
    SELECT j.id, j.request_id, v.plate_no, v.model,
           j.job_status, j.assigned_at, j.completed_at, j.created_at, j.completion_comment AS job_details,
           j.invoice_generated,  -- <<< add this
           m.name AS mechanic_name
    FROM jobs j
    JOIN service_requests sr ON sr.id = j.request_id
    JOIN vehicles v ON sr.vehicle_id = v.id
    LEFT JOIN users m ON m.id = j.mechanic_id
    WHERE j.garage_id = ?
    ORDER BY j.created_at DESC
");
$stmt->execute([$garage_id]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// OPTIONAL: Fetch mechanics list for assign modal (if you have mechanics stored)
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'mechanic' AND garage_id = ?");
$stmt->execute([$garage_id]);
$mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Garage Dashboard — AutoLog</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg1: #0b3a8b;
      --bg2: #071129;
      --glass: rgba(255,255,255,0.06);
      --accent: #39a2ff;
      --accent-2: #6c5ce7;
      --muted: rgba(226,232,240,0.75);
    }html, body {
    height: 100%;
    margin: 0;
    font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
    color: #e6eef8;

    /* full height gradient */
    background: linear-gradient(180deg, var(--bg1) 0%, #051428 40%, var(--bg2) 100%);
    background-repeat: no-repeat;     /* prevent repeating */
    background-attachment: fixed;     /* keeps it fixed when scrolling */
    background-size: cover;           /* fill entire page */
}    /* shimmer */
    .shimmer-wrap{position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none}
    .shimmer{position:absolute;top:-20%;left:-10%;width:120%;height:140%;background: radial-gradient(40% 20% at 10% 10%, rgba(255,255,255,0.03), transparent 10%), radial-gradient(30% 20% at 90% 90%, rgba(255,255,255,0.02), transparent 12%);transform: rotate(-10deg);animation: shimmerSlow 14s linear infinite;filter: blur(30px);mix-blend-mode: overlay;opacity:0.9;}
    @keyframes shimmerSlow{0%{transform:translateX(-5%) translateY(0) rotate(-10deg);}50%{transform:translateX(8%) translateY(2%) rotate(-9deg);}100%{transform:translateX(-5%) translateY(0) rotate(-10deg);}}

    /* navbar */
    .glass-navbar{position:relative;z-index:10;background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,0.04);backdrop-filter: blur(8px) saturate(120%);}
    .brand-circle{width:44px;height:44px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(255,255,255,0.85));box-shadow:0 4px 18px rgba(0,0,0,0.35);}

    /* app */
    .app-wrap{position:relative;z-index:5;padding:20px;max-width:1200px;margin:18px auto;}
    .tabs {display:flex;gap:10px;align-items:center;margin-bottom:12px;}
    .tab-btn{background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,0.04);color:var(--muted);padding:8px 14px;border-radius:12px;font-weight:600;cursor:pointer;transition:all .22s ease;backdrop-filter: blur(6px);box-shadow:0 4px 14px rgba(2,6,23,0.3);}
    .tab-btn.active{color:#f8fbff;background:linear-gradient(90deg, rgba(57,162,255,0.12), rgba(108,92,231,0.10));border:1px solid rgba(57,162,255,0.28);transform:translateY(-2px);}

    /* card */
    .glass-card{background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.02));border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:14px;margin-bottom:12px;box-shadow:0 8px 30px rgba(2,6,23,0.5);transition:transform .18s ease}
    .glass-card:hover{transform:translateY(-3px);}

    .chip{padding:6px 10px;border-radius:999px;font-weight:700;font-size:0.86rem;color:white;background:linear-gradient(90deg,var(--accent), var(--accent-2));box-shadow:0 6px 18px rgba(57,162,255,0.14);}
    .card-subtle{color:var(--muted);font-size:0.92rem;}

    /* tables */
    .fancy-table{width:100%;border-collapse:collapse;color:#eaf4ff}
    .fancy-table thead th{text-align:left;padding:10px 12px;font-size:0.8rem;color:#bcd7ff;text-transform:uppercase;background:rgba(255,255,255,0.02);border-bottom:1px solid rgba(255,255,255,0.04)}
    .fancy-table tbody td{padding:12px;vertical-align:middle;border-bottom:1px solid rgba(255,255,255,0.03)}
    .fancy-table tbody tr:hover td{background:linear-gradient(90deg, rgba(57,162,255,0.02), rgba(108,92,231,0.01));}

    .muted-xs{color:var(--muted);font-size:0.88rem}

    /* buttons */
    .btn-primary-glow{background:linear-gradient(90deg,#39a2ff,#6c5ce7);color:#fff;border:none;padding:7px 12px;border-radius:8px;box-shadow:0 8px 30px rgba(57,162,255,0.14);transition:transform .16s ease}
    .btn-primary-glow:hover{transform:translateY(-3px);box-shadow:0 18px 40px rgba(57,162,255,0.22)}
    .btn-success-soft{background:linear-gradient(90deg, rgba(16,185,129,0.12), rgba(16,185,129,0.08));color:#bff2d9;border:1px solid rgba(16,185,129,0.18);padding:6px 10px;border-radius:8px}

    /* badges */
    .status-badge{padding:6px 10px;border-radius:999px;font-weight:700;font-size:0.8rem;color:#071129}
    .status-completed{background:linear-gradient(90deg,#34d399,#10b981);color:#021009}
    .status-pending{background:linear-gradient(90deg,#fbbf24,#fb923c);color:#2b1700}
    .status-approved{background:linear-gradient(90deg,#60a5fa,#3b82f6);color:#071129}
    .status-in_progress {background: linear-gradient(90deg, #a78bfa, #8b5cf6);color: #12023b;
}
    

  </style>
</head>
<body>

  <!-- shimmer backdrop -->
  <div class="shimmer-wrap" aria-hidden="true">
    <div class="shimmer"></div>
  </div>

  <!-- NAV -->
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

  <!-- MAIN -->
  <main class="app-wrap" role="main">

    <!-- header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h2 style="margin:0 0 6px 0;font-weight:800;">Jobs & Requests</h2>
        <div class="card-subtle">Manage approved requests and active jobs.</div>
      </div>

      <div class="d-flex align-items-center gap-2">

        
      </div>
    </div>

    <!-- tabs (desktop) / dropdown (mobile) -->
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="tabs" role="tablist" aria-label="Jobs tabs">
        <button class="tab-btn active" data-tab="tab-requests" role="tab" aria-selected="true">Approved Requests</button>
        <button class="tab-btn" data-tab="tab-jobs" role="tab" aria-selected="false">Listed Jobs</button>
      </div>

    </div>

    <!-- Tab panels -->
    <section id="tab-requests" class="tab-panel">
      <div class="glass-card">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center gap-3">
           <div>
            <input id="searchRequests" class="form-control form-control-sm" placeholder="Search plate or model..." style="min-width:200px;">
          </div>
            <div class="chip"><i class="bi bi-check2-circle me-2"></i> Approved</div>
            <div class="card-subtle"></div>
          </div>
         
        </div>

        <div class="table-responsive">
          <table class="fancy-table" id="requestsTable">
            <thead>
              <tr>
                <th>Request ID</th>
                <th>Plate</th>
                <th>Model</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($approvedRequests): ?>
                <?php foreach ($approvedRequests as $req):
                    if (in_array((int)$req['request_id'], $listedJobs)) continue;
                ?>
                <tr data-request-row="<?= htmlspecialchars($req['request_id']) ?>"
                    data-plate="<?= htmlspecialchars($req['plate_no']) ?>"
                    data-model="<?= htmlspecialchars($req['model']) ?>"
                    data-created="<?= htmlspecialchars($req['created_at']) ?>"
                    data-details="<?= htmlspecialchars($req['details'] ?? '') ?>">
                  <td>#<?= htmlspecialchars($req['request_id']) ?></td>
                  <td><?= htmlspecialchars($req['plate_no']) ?></td>
                  <td><?= htmlspecialchars($req['model']) ?></td>
                  <td><span class="status-badge status-approved">Approved</span></td>
                  <td class="muted-xs"><?= htmlspecialchars(date("d M Y", strtotime($req['created_at']))) ?></td>
                  <td class="d-flex gap-2">
                    
                    <button class="btn btn-primary-glow listBtn" data-id="<?= htmlspecialchars($req['request_id']) ?>"><i class="bi bi-plus-circle"></i> List</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center muted-xs">No approved service requests found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section id="tab-jobs" class="tab-panel" style="display:none;">
      <div class="glass-card">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center gap-3">
            <div class="chip" style="background: linear-gradient(90deg,#2563eb,#1d4ed8);"><i class="bi bi-wrench me-2"></i> Jobs</div>
            <div class="card-subtle"></div>
          </div>
          <div>
            <input id="searchJobs" class="form-control form-control-sm" placeholder="Search job id, plate or mechanic..." style="min-width:200px;">
          </div>
        </div>

        <div class="table-responsive">
          <table class="fancy-table" id="jobsTable">
            <thead>
              <tr>
                <th>Job ID</th>
                <th>Request</th>
                <th>Plate</th>
                <th>Model</th>
                <th>Mechanic</th>
                <th>Status</th>
                <th>Assigned</th>
                <th>Completed</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($jobs): ?>
                <?php foreach ($jobs as $job): ?>
                <tr data-job-row="<?= htmlspecialchars($job['id']) ?>"
                    data-job-details="<?= htmlspecialchars($job['job_details'] ?? '') ?>"
                    data-mechanic="<?= htmlspecialchars($job['mechanic_name'] ?? '') ?>">
                  <td>#<?= htmlspecialchars($job['id']) ?></td>
                  <td><?= htmlspecialchars($job['request_id']) ?></td>
                  <td><?= htmlspecialchars($job['plate_no']) ?></td>
                  <td><?= htmlspecialchars($job['model']) ?></td>
                  <td><?= htmlspecialchars($job['mechanic_name'] ?? '—') ?></td>
                  <td>
                    <td>
  <?php if ($job['job_status'] === 'completed'): ?>
    <span class="status-badge status-completed">Completed</span>
  <?php elseif ($job['job_status'] === 'in_progress'): ?>
    <span class="status-badge status-in_progress">In&nbsp;Progress</span>
  <?php elseif ($job['job_status'] === 'approved'): ?>
    <span class="status-badge status-approved">Approved</span>
  <?php else: ?>
    <span class="status-badge status-pending"><?= ucfirst(htmlspecialchars($job['job_status'])) ?></span>
  <?php endif; ?>
</td>
                  <td class="muted-xs"><?= htmlspecialchars($job['assigned_at'] ?? '—') ?></td>
                  <td class="muted-xs"><?= htmlspecialchars($job['completed_at'] ?? '—') ?></td>
                  <td class="muted-xs"><?= htmlspecialchars(date("d M Y", strtotime($job['created_at']))) ?></td>
                  <td class="d-flex gap-2">
    <?php if ($job['job_status'] === 'completed' && !$job['invoice_generated']): ?>
    <a href="invoice.php?job_id=<?= htmlspecialchars($job['id']) ?>" class="btn btn-primary-glow btn-sm">Invoice</a>
<?php elseif ($job['invoice_generated']): ?>
    <a href="view_invoice.php?job_id=<?= htmlspecialchars($job['id']) ?>" class="btn btn-success-soft btn-sm" target="_blank">View Invoice</a>
<?php endif; ?>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="10" class="text-center muted-xs">No jobs listed yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>



  <!-- ===== MODALS ===== -->

  <!-- Confirm List Modal -->
  <div class="modal fade" id="confirmListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.02)); border:1px solid rgba(255,255,255,0.04); color: #eaf4ff;">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Confirm List Job</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p id="confirmListText" class="muted-xs">Are you sure you want to list this request as a job?</p>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
          <button id="confirmListBtn" type="button" class="btn btn-primary-glow">Yes, List Job</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Request Details Modal -->
  <div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content" style="background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.02)); border:1px solid rgba(255,255,255,0.04); color: #eaf4ff;">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-file-earmark-text"></i> Request Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <dl class="row">
            <dt class="col-sm-3">Request ID</dt><dd class="col-sm-9" id="reqDetailId">#</dd>
            <dt class="col-sm-3">Plate</dt><dd class="col-sm-9" id="reqDetailPlate">-</dd>
            <dt class="col-sm-3">Model</dt><dd class="col-sm-9" id="reqDetailModel">-</dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9 muted-xs" id="reqDetailCreated">-</dd>
            <dt class="col-sm-3">Notes</dt><dd class="col-sm-9" id="reqDetailNotes">-</dd>
          </dl>
        </div>
        <div class="modal-footer border-0">
          <button class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
          <button id="modalListBtn" class="btn btn-primary-glow">List this request</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Job Details Modal -->
  <div class="modal fade" id="jobDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content" style="background: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.02)); border:1px solid rgba(255,255,255,0.04); color: #eaf4ff;">
        <div class="modal-header border-0">
          <h5 class="modal-title"><i class="bi bi-journal-text"></i> Job Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <dl class="row">
            <dt class="col-sm-3">Job ID</dt><dd class="col-sm-9" id="jobDetailId">#</dd>
            <dt class="col-sm-3">Request</dt><dd class="col-sm-9" id="jobDetailRequest">-</dd>
            <dt class="col-sm-3">Plate</dt><dd class="col-sm-9" id="jobDetailPlate">-</dd>
            <dt class="col-sm-3">Mechanic</dt><dd class="col-sm-9" id="jobDetailMechanic">-</dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9" id="jobDetailStatus">-</dd>
            <dt class="col-sm-3">Notes</dt><dd class="col-sm-9" id="jobDetailNotes">-</dd>
          </dl>
        </div>
        <div class="modal-footer border-0">
          <button class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
          <button id="openAssignModalBtn" class="btn btn-success-soft">Assign Mechanic</button>
        </div>
      </div>
    </div>
  </div>



  <!-- FOOTER INCLUDE -->
  <?php include '../includes/footer.php'; ?>

  <!-- SCRIPTS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    /* Utility toast */
    function showToast(message) {
      const t = document.getElementById('toast');
      t.textContent = message;
      t.classList.add('show');
      setTimeout(() => t.classList.remove('show'), 1800);
    }

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.classList.contains('active')) return;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const target = btn.dataset.tab;
        document.querySelectorAll('.tab-panel').forEach(p => {
          if (p.id === target) {
            p.style.display = 'block';
            p.style.opacity = 0;
            p.style.transform = 'translateY(6px)';
            setTimeout(() => { p.style.transition = 'all .22s ease'; p.style.opacity = 1; p.style.transform = 'translateY(0)'; }, 10);
          } else {
            p.style.transition = '';
            p.style.opacity = 0;
            p.style.transform = 'translateY(6px)';
            setTimeout(() => p.style.display = 'none', 220);
          }
        });
      });
    });

    // Mobile tab select
    const mobileSelect = document.getElementById('mobileTabSelect');
    if (mobileSelect) {
      mobileSelect.addEventListener('change', (e) => {
        const value = e.target.value;
        document.querySelectorAll('.tab-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.tab === value);
        });
        // trigger click
        const btn = document.querySelector('.tab-btn[data-tab="'+value+'"]');
        if (btn) btn.click();
      });
    }

    // Search requests and jobs (client-side simple filter)
    document.getElementById('searchRequests')?.addEventListener('input', function(){
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('#requestsTable tbody tr').forEach(row => {
        const plate = row.dataset.plate?.toLowerCase() || '';
        const model = row.dataset.model?.toLowerCase() || '';
        const id = row.dataset.requestRow || '';
        const show = (!q) || plate.includes(q) || model.includes(q) || String(id).includes(q);
        row.style.display = show ? '' : 'none';
      });
    });
    document.getElementById('searchJobs')?.addEventListener('input', function(){
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('#jobsTable tbody tr').forEach(row => {
        const plate = row.children[2]?.textContent.trim().toLowerCase() || '';
        const model = row.children[3]?.textContent.trim().toLowerCase() || '';
        const mech = row.dataset.mechanic?.toLowerCase() || '';
        const id = row.children[0]?.textContent.trim().toLowerCase() || '';
        const show = (!q) || plate.includes(q) || model.includes(q) || mech.includes(q) || id.includes(q);
        row.style.display = show ? '' : 'none';
      });
    });

    // Confirm List modal flow
    let listTargetRequestId = null;
    const confirmListModal = new bootstrap.Modal(document.getElementById('confirmListModal'));
    const requestDetailsModal = new bootstrap.Modal(document.getElementById('requestDetailsModal'));
    const jobDetailsModal = new bootstrap.Modal(document.getElementById('jobDetailsModal'));
    const assignMechanicModal = new bootstrap.Modal(document.getElementById('assignMechanicModal'));

    // clicking List in table -> open confirm modal
    document.querySelectorAll('.listBtn').forEach(btn => {
      btn.addEventListener('click', () => {
        listTargetRequestId = btn.dataset.id;
        // show short summary in modal (optional)
        const row = document.querySelector('tr[data-request-row="'+listTargetRequestId+'"]');
        const plate = row?.dataset.plate || '';
        const model = row?.dataset.model || '';
        document.getElementById('confirmListText').textContent = `List Request #${listTargetRequestId} — ${plate} (${model}) as job?`;
        confirmListModal.show();
      });
    });

    // Confirm list action
    document.getElementById('confirmListBtn').addEventListener('click', () => {
      if (!listTargetRequestId) { confirmListModal.hide(); return; }
      const btn = document.getElementById('confirmListBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Listing';

      fetch('push_to_jobs.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'request_id=' + encodeURIComponent(listTargetRequestId)
      })
      .then(r => r.text())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = 'Yes, List Job';
        confirmListModal.hide();
        if (data.includes('success') || data.includes('already_listed')) {
          showToast('Job listed successfully');
          const row = document.querySelector('tr[data-request-row="'+listTargetRequestId+'"]');
          if (row) {
            row.style.transition = 'all .35s ease';
            row.style.opacity = 0;
            row.style.height = 0;
            row.style.padding = 0;
            setTimeout(() => row.remove(), 360);
          }
        } else {
          alert('Failed to list job: ' + data);
        }
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = 'Yes, List Job';
        confirmListModal.hide();
        alert('Network error: ' + err);
      });
    });

    // View Request details modal (attempt AJAX get_request.php?id=)
    document.querySelectorAll('.viewReqBtn').forEach(btn => {
      btn.addEventListener('click', () => {
        const requestId = btn.dataset.id;
        // first try to fetch detailed JSON from server if endpoint exists
        fetch(`get_request.php?id=${encodeURIComponent(requestId)}`, { method: 'GET' })
        .then(r => {
          if (!r.ok) throw new Error('no-json');
          return r.json();
        })
        .then(json => {
          // expected JSON: { request_id, plate_no, model, created_at, details }
          document.getElementById('reqDetailId').textContent = '#' + (json.request_id || requestId);
          document.getElementById('reqDetailPlate').textContent = json.plate_no || '-';
          document.getElementById('reqDetailModel').textContent = json.model || '-';
          document.getElementById('reqDetailCreated').textContent = json.created_at || '-';
          document.getElementById('reqDetailNotes').textContent = json.details || '-';
          document.getElementById('modalListBtn').dataset.id = requestId;
          requestDetailsModal.show();
        })
        .catch(() => {
          // fallback to data in DOM
          const row = document.querySelector('tr[data-request-row="'+requestId+'"]');
          const plate = row?.dataset.plate || '';
          const model = row?.dataset.model || '';
          const created = row?.dataset.created || '';
          const details = row?.dataset.details || '';
          document.getElementById('reqDetailId').textContent = '#' + requestId;
          document.getElementById('reqDetailPlate').textContent = plate || '-';
          document.getElementById('reqDetailModel').textContent = model || '-';
          document.getElementById('reqDetailCreated').textContent = created ? new Date(created).toLocaleString() : '-';
          document.getElementById('reqDetailNotes').textContent = details || '-';
          document.getElementById('modalListBtn').dataset.id = requestId;
          requestDetailsModal.show();
        });
      });
    });

    // List from modal
    document.getElementById('modalListBtn').addEventListener('click', function(){
      const requestId = this.dataset.id;
      if (!requestId) return;
      // reuse same flow as confirm modal
      listTargetRequestId = requestId;
      document.getElementById('confirmListText').textContent = `List Request #${requestId} as job?`;
      requestDetailsModal.hide();
      confirmListModal.show();
    });

    // View Job details modal (fetch get_job.php?id=)
    document.querySelectorAll('.viewJobBtn').forEach(btn => {
      btn.addEventListener('click', () => {
        const jobId = btn.dataset.id;
        fetch(`get_job.php?id=${encodeURIComponent(jobId)}`, { method: 'GET' })
          .then(r => {
            if (!r.ok) throw new Error('no-json');
            return r.json();
          })
          .then(json => {
            // expected JSON: { id, request_id, plate_no, model, mechanic_name, job_status, details }
            document.getElementById('jobDetailId').textContent = '#' + (json.id || jobId);
            document.getElementById('jobDetailRequest').textContent = json.request_id || '-';
            document.getElementById('jobDetailPlate').textContent = json.plate_no || '-';
            document.getElementById('jobDetailMechanic').textContent = json.mechanic_name || '-';
            document.getElementById('jobDetailStatus').textContent = json.job_status || '-';
            document.getElementById('jobDetailNotes').textContent = json.details || '-';
            // set assign target
            document.getElementById('assign_job_id').value = jobId;
            jobDetailsModal.show();
          })
          .catch(() => {
            // fallback to DOM
            const row = document.querySelector('tr[data-job-row="'+jobId+'"]');
            const request = row?.children[1]?.textContent || '-';
            const plate = row?.children[2]?.textContent || '-';
            const mechanic = row?.dataset.mechanic || '-';
            const status = row?.children[5]?.textContent || '-';
            const details = row?.dataset.jobDetails || '-';
            document.getElementById('jobDetailId').textContent = '#' + jobId;
            document.getElementById('jobDetailRequest').textContent = request;
            document.getElementById('jobDetailPlate').textContent = plate;
            document.getElementById('jobDetailMechanic').textContent = mechanic || '-';
            document.getElementById('jobDetailStatus').textContent = status || '-';
            document.getElementById('jobDetailNotes').textContent = details || '-';
            document.getElementById('assign_job_id').value = jobId;
            jobDetailsModal.show();
          });
      });
    });

    // Open assign modal from job row button
    document.querySelectorAll('.assignBtn').forEach(btn => {
      btn.addEventListener('click', () => {
        const jobId = btn.dataset.id;
        document.getElementById('assign_job_id').value = jobId;
        assignMechanicModal.show();
      });
    });

    // Open assign from details modal
    document.getElementById('openAssignModalBtn')?.addEventListener('click', () => {
      const jid = document.getElementById('jobDetailId').textContent.replace('#','').trim();
      document.getElementById('assign_job_id').value = jid;
      jobDetailsModal.hide();
      assignMechanicModal.show();
    });

    // Assign mechanic form submit (AJAX)
    document.getElementById('assignMechanicForm')?.addEventListener('submit', function(e){
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      const assignBtn = form.querySelector('button[type="submit"]');
      assignBtn.disabled = true;
      assignBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Assigning';

      // expected endpoint assign_mechanic.php (POST) -> returns text containing "success" on success
      fetch('assign_mechanic.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.text())
      .then(data => {
        assignBtn.disabled = false;
        assignBtn.textContent = 'Assign';
        if (data.includes('success')) {
          showToast('Mechanic assigned');
          assignMechanicModal.hide();
          // Optionally update row's mechanic cell if present
          const jid = formData.get('job_id');
          const row = document.querySelector('tr[data-job-row="'+jid+'"]');
          if (row) {
            const selectedText = form.querySelector('#assign_mechanic_select option:checked')?.textContent || '';
            row.children[4].textContent = selectedText;
          }
        } else {
          alert('Failed to assign: ' + data);
        }
      })
      .catch(err => {
        assignBtn.disabled = false;
        assignBtn.textContent = 'Assign';
        alert('Network error: ' + err);
      });
    });

    // Invoice button redirect
    document.querySelectorAll('.invoiceBtn').forEach(btn => {
      btn.addEventListener('click', () => {
        const jobId = btn.dataset.job;
        window.location.href = `generate_invoice.php?job_id=${encodeURIComponent(jobId)}`;
      });
    });

    // New Job FAB (opens a create page or modal)
    document.getElementById('newJobFab')?.addEventListener('click', () => {
      // If you prefer a modal, we can create an in-page modal here.
      // For now, redirect to create_job.php (change as needed)
      window.location.href = 'create_job.php';
    });

    // refresh (client)
    document.getElementById('refreshBtn')?.addEventListener('click', () => location.reload());

    // accessibility: keyboard switching
    (function keyboardTabs(){
      let tabButtons = Array.from(document.querySelectorAll('.tab-btn'));
      document.addEventListener('keydown', (e) => {
        const activeIndex = tabButtons.findIndex(b => b.classList.contains('active'));
        if (e.key === 'ArrowRight') {
          const next = (activeIndex + 1) % tabButtons.length;
          tabButtons[next].click();
        } else if (e.key === 'ArrowLeft') {
          const prev = (activeIndex - 1 + tabButtons.length) % tabButtons.length;
          tabButtons[prev].click();
        }
      });
    })();

  </script>
</body>
</html>