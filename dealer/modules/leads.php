<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo '<p>Unauthorized access.</p>';
    exit;
}
$dealerId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Leads</title>
<style>
body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f5f6fa; padding:20px; }
h2 { text-align:center; margin-bottom:20px; color:#007bff; }

.filter-form { display:flex; flex-wrap:wrap; justify-content:center; gap:10px; margin-bottom:20px; }
.filter-form input, .filter-form select {
    padding:8px 12px; border-radius:6px; border:1px solid #ccc; font-size:14px;
}

.leads-table-container { overflow-x:auto; position:relative; }
.leads-table { width:100%; border-collapse:collapse; margin-top:10px; }
.leads-table th, .leads-table td { padding:10px; border:1px solid #ddd; text-align:left; }
.leads-table th { background:#007bff; color:#fff; }
.leads-table tr:hover { background:#f1f1f1; }
.actions button { padding:6px 10px; border:none; border-radius:4px; cursor:pointer; margin-right:5px; }
.actions .contacted { background:#28a745; color:#fff; }
.actions .contacted:hover { background:#218838; }
.actions .delete { background:#dc3545; color:#fff; }
.actions .delete:hover { background:#c82333; }
.contacted-row { background:#e6ffe6; }

/* Overlay spinner */
#loadingOverlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}
.spinner {
    border: 6px solid #f3f3f3;
    border-top: 6px solid #007bff;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
</head>
<body>

<h2>My Leads</h2>

<form id="filterForm" class="filter-form">
    <input type="text" name="search" id="search" placeholder="Search by name, email or phone">
    <select name="contacted" id="contacted">
        <option value="">All</option>
        <option value="1">Contacted</option>
        <option value="0">Not Contacted</option>
    </select>
    <select name="status" id="status">
        <option value="">All Users</option>
        <option value="registered">Registered</option>
        <option value="guest">Guest</option>
    </select>
</form>

<div class="leads-table-container">
    <div id="loadingOverlay" style="display:none;">
        <div class="spinner"></div>
    </div>
    <table class="leads-table">
        <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Phone</th><th>Message</th><th>Date</th><th>Registered?</th><th>Actions</th>
            </tr>
        </thead>
        <tbody id="leadsBody">
            <!-- Table rows loaded via AJAX -->
        </tbody>
    </table>
</div>

<script>
let currentPage = 1;

function fetchLeads(page = 1) {
    currentPage = page;
    const search = document.getElementById('search').value;
    const contacted = document.getElementById('contacted').value;
    const status = document.getElementById('status').value;

    const params = new URLSearchParams({search, contacted, status, page});

    document.getElementById('loadingOverlay').style.display = 'flex';

    fetch('leads_ajax.php?' + params)
        .then(res => res.text())
        .then(data => {
            document.getElementById('leadsBody').innerHTML = data;
            attachActionListeners();
            attachPaginationListeners();
            document.getElementById('loadingOverlay').style.display = 'none';
        })
        .catch(() => {
            document.getElementById('leadsBody').innerHTML = '<tr><td colspan="7" style="text-align:center;color:red;">Error loading leads.</td></tr>';
            document.getElementById('loadingOverlay').style.display = 'none';
        });
}

// Buttons
function attachActionListeners() {
    document.querySelectorAll('.contacted').forEach(button => {
        button.onclick = function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const leadId = form.querySelector('input[name="lead_id"]').value;
            fetch('mark_contacted_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'lead_id=' + leadId
            }).then(res => res.json())
              .then(data => { if(data.status === 'success') fetchLeads(currentPage); });
        };
    });

    document.querySelectorAll('.delete').forEach(button => {
        button.onclick = function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this lead?')) return;
            const form = this.closest('form');
            const leadId = form.querySelector('input[name="lead_id"]').value;
            fetch('delete_lead_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'lead_id=' + leadId
            }).then(res => res.json())
              .then(data => { if(data.status === 'success') fetchLeads(currentPage); });
        };
    });
}

// Pagination
function attachPaginationListeners() {
    document.querySelectorAll('.page-btn').forEach(btn => {
        btn.onclick = function() {
            const page = parseInt(this.dataset.page);
            fetchLeads(page);
        };
    });
}

// Filters reset page
document.getElementById('search').addEventListener('input', () => fetchLeads(1));
document.getElementById('contacted').addEventListener('change', () => fetchLeads(1));
document.getElementById('status').addEventListener('change', () => fetchLeads(1));

fetchLeads();
</script>

</body>
</html>