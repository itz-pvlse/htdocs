<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Africa/Nairobi');
require_once 'config/db.php'; // PDO connection

if (!isset($_GET['ref']) || empty($_GET['ref'])) {
    die("No reference number provided.");
}

$ref_no = trim($_GET['ref']);

// Fetch the report
$stmt = $pdo->prepare("
    SELECT vr.ref_no, vr.generated_at, v.plate_no, v.make, v.model, u.name AS owner_name
    FROM vehicle_reports vr
    JOIN vehicles v ON vr.vehicle_id = v.id
    JOIN users u ON v.user_id = u.id
    WHERE vr.ref_no = ?
    LIMIT 1
");
$stmt->execute([$ref_no]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    die("Report not found or invalid reference number.");
}
?>


<?php
// ... [Keep your existing PHP logic for fetching the report] ...
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Report Verification | AutoLog Digital Registry</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<style>
    :root {
        --bg-silk: #f0f4f8;
        --registry-blue: #0f172a;
        --accent-blue: #2563eb;
        --luminous-green: #00ff88;
        --radius-lg: 35px;
    }

    body { 
        background-color: var(--bg-silk); 
        font-family: 'Inter', sans-serif; 
        color: var(--registry-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 20px;
    }

    .verification-card { 
        background: white; 
        max-width: 550px; 
        width: 100%;
        padding: 50px; 
        border-radius: var(--radius-lg);
        box-shadow: 0 30px 60px rgba(15, 23, 42, 0.1);
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.8);
    }

    /* Top highlight bar */
    .verification-card::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 8px;
        background: linear-gradient(90deg, var(--luminous-green), var(--accent-blue));
    }

    .brand-logo-container {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }

    .brand-logo {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid #fff;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }

    .success-badge {
        width: 32px;
        height: 32px;
        background: var(--luminous-green);
        color: var(--registry-blue);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: -45px auto 35px 60px; /* Positions badge relative to logo */
        position: relative;
        z-index: 10;
        border: 3px solid white;
    }

    .ref-tag {
        font-family: 'JetBrains Mono', monospace;
        background: #f1f5f9;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.8rem;
        color: #475569;
        display: inline-block;
        margin-bottom: 30px;
    }

    .data-row {
        display: flex;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .data-row:last-child { border-bottom: none; }

    .label-text { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .value-text { font-size: 0.95rem; font-weight: 700; color: var(--registry-blue); text-align: right; }

    .btn-return {
        display: block;
        text-align: center;
        background: var(--registry-blue);
        color: white;
        padding: 18px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 40px;
        transition: all 0.3s ease;
    }

    .btn-return:hover {
        background: var(--accent-blue);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
    }
</style>
</head>
<body>

<div class="verification-card">
    
    <div class="brand-logo-container">
        <img src="assets/toplogo.png" alt="AutoLog Logo" class="brand-logo">
    </div>
    
    <div class="success-badge">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
            <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/>
        </svg>
    </div>

    <div class="text-center">
        <h1 class="text-2xl font-black text-slate-900 mb-1">Authenticity Confirmed</h1>
        <p class="text-slate-400 text-sm mb-4">Official AutoLog Verification System</p>
        <span class="ref-tag">REF: <?= $report['ref_no']; ?></span>
    </div>

    <div class="space-y-1">
        <div class="data-row">
            <span class="label-text">Issue Date</span>
            <span class="value-text"><?= date('d M Y, H:i', strtotime($report['generated_at'])); ?></span>
        </div>
        <div class="data-row">
            <span class="label-text">Vehicle Plate</span>
            <span class="value-text font-mono tracking-tighter"><?= $report['plate_no']; ?></span>
        </div>
        <div class="data-row">
            <span class="label-text">Model</span>
            <span class="value-text"><?= $report['make'] . " " . $report['model']; ?></span>
        </div>
        <div class="data-row">
            <span class="label-text">Verified Owner</span>
            <span class="value-text"><?= $report['owner_name']; ?></span>
        </div>
        <div class="data-row">
            <span class="label-text">Status</span>
            <span class="text-[0.7rem] font-black bg-green-100 text-green-700 px-3 py-1 rounded-full uppercase">Valid Entry</span>
        </div>
    </div>

    <p class="mt-8 text-[11px] text-slate-400 text-center leading-relaxed italic">
        This verification confirms that this report snapshot was generated via AutoLog. Any modifications to the physical document will invalidate this verification.
    </p>

    <a href="index.php" class="btn-return">Close Portal</a>
</div>

</body>
</html>
