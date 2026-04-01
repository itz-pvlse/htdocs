<?php
require_once '../auth/auth_check.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';
include '../includes/header.php';

$garage_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';



$stmt = $pdo->prepare("
    SELECT 
        users.email,
        users.role,
        garages.name,
        garages.logo,
        garages.phone,
        garages.location,
        garages.description,
        garages.created_at
    FROM users
    JOIN garages ON users.id = garages.id
    WHERE users.id = ?
");
$stmt->execute([$garage_id]);
$garage = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$garage) {
    echo "<p style='color:red;'>Garage profile not found.</p>";
    exit();
}



$sql = "SELECT sl.*, v.plate_no, v.make, v.model 
        FROM service_logs sl
        JOIN vehicles v ON sl.vehicle_id = v.id
        WHERE sl.garage_id = :garage_id";
$params = ['garage_id' => $garage_id];

if (!empty($_GET['filter_plate'])) {
    $sql .= " AND v.plate_no = :plate_no";
    $params['plate_no'] = $_GET['filter_plate'];
}

if (!empty($_GET['search'])) {
    $sql .= " AND (
        sl.service_type LIKE :search1 OR
        sl.service_description LIKE :search2 OR
        v.make LIKE :search3 OR
        v.model LIKE :search4 OR
        v.plate_no LIKE :search5
    )";
    $params['search1'] = '%' . $_GET['search'] . '%';
    $params['search2'] = '%' . $_GET['search'] . '%';
    $params['search3'] = '%' . $_GET['search'] . '%';
    $params['search4'] = '%' . $_GET['search'] . '%';
    $params['search5'] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['start_date'])) {
    $sql .= " AND sl.service_date >= :start_date";
    $params['start_date'] = $_GET['start_date'];
}

if (!empty($_GET['end_date'])) {
    $sql .= " AND sl.service_date <= :end_date";
    $params['end_date'] = $_GET['end_date'];
}

$sql .= " ORDER BY sl.service_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Service Logs - AutoLog</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
	
	   .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
        align-items: center;
    }

    .filter-form input,
    .filter-form select,
    .filter-form button {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    .filter-form button {
        background-color: #007bff;
        color: white;
        cursor: pointer;
    }

    .filter-form a {
        text-decoration: none;
        background-color: #6c757d;
        color: white;
        padding: 8px;
        border-radius: 5px;
    }

    .export-button {
        margin-bottom: 15px;
        padding: 8px 15px;
        background-color: #28a745;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        display: inline-block;
    }
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .containerbox {
            margin: 30px auto;
            max-width: 1100px;
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
			vertical-align:top;
        }
		td.description{
			max-width:300px;
			word-wrap:break-word;
			white-spaces:pre-wrap;
		}
        thead {
            background-color: #1e293b;
            color: white;
        }
        tbody tr:nth-child(even) {
            background-color: #f1f5f9;
        }
        tbody tr:hover {
            background-color: #e2e8f0;
        }
        td {
            color: #333;
        }
        .cost {
            font-weight: bold;
            color: #047857;
        }
		
  @media print {
    .no-print {
      display: none;
    }
  }

    </style>
</head>
<body>

<div class="containerbox">

<button onclick="history.back()" style="margin-bottom: 20px; padding: 10px 20px; background-color: #343a40; color: white; border: none; border-radius: 5px; cursor: pointer;">
  ← Back
</button>

    <h2>Logged Services</h2>
    <?php if (count($services) > 0): ?>
	<div class="no-print">
  <button onclick="printDiv('printable')">Print as PDF</button>
</div>
	<?php
// Fetch all plate numbers for dropdown
$plateStmt = $pdo->prepare("SELECT DISTINCT v.plate_no 
                            FROM service_logs sl
                            JOIN vehicles v ON sl.vehicle_id = v.id
                            WHERE sl.garage_id = ?");
$plateStmt->execute([$garage_id]);
$plates = $plateStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<form method="GET" class="filter-form">
    <!-- Plate No Filter -->
    <select name="filter_plate">
        <option value="">-- Filter by Plate No --</option>
        <?php
        foreach ($plates as $plate) {
            $selected = ($_GET['filter_plate'] ?? '') === $plate ? 'selected' : '';
            echo "<option value=\"$plate\" $selected>$plate</option>";
        }
        ?>
    </select>

    <!-- Date Range -->
    <input type="date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
    <input type="date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">

    <!-- Search -->
    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">

    <button type="submit">Apply</button>
    <a href="view_services.php">Reset</a>
</form>
<div id="printable">
  <div style="text-align: center; margin-bottom: 20px;">
  <img src="../uploads/logo/<?= htmlspecialchars($garage['logo']) ?>" class="garage-logo" alt="Garage Logo" style="height: 80px;"><br>
    
    <strong style="font-size: 20px;"><?php echo $garage['name']; ?></strong><br>
    <span><?php echo $garage['email']; ?> | <?php echo $garage['phone']; ?> | <?php echo $garage['location']; ?></span>
    <hr style="margin-top: 10px;">
    <h2 style="margin: 10px 0;">Garage Service Report</h2>
  </div>
        <table>
		
		
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Service Type</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['plate_no']) ?> (<?= htmlspecialchars($service['make']) ?> <?= htmlspecialchars($service['model']) ?>)</td>
                        <td><?= htmlspecialchars($service['service_type']) ?></td>
                        <td class="description"><?= htmlspecialchars($service['service_description']) ?></td>
                        <td><?= htmlspecialchars(date("d M Y", strtotime($service['service_date']))) ?></td>
                        <td class="cost">KES <?= number_format($service['cost'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
</div>
    <?php else: ?>
        <p>No service logs found for your garage.</p>
    <?php endif; ?>
</div>

<script>
  function printDiv(divId) {
    var content = document.getElementById(divId).innerHTML;
    var win = window.open('', '', 'height=700,width=900');
    win.document.write('<html><head><title>Service Report</title>');
    win.document.write('<style>table { border-collapse: collapse; width: 100%; } table, th, td { border: 1px solid black; padding: 8px; } body { font-family: Arial; }</style>');
    win.document.write('</head><body>');
    win.document.write(content);
    win.document.write('</body></html>');
    win.document.close();
    win.print();
  }
</script>
</body>
</html>