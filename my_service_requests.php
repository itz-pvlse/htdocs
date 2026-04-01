<?php
require_once 'auth/auth_check.php';
require_once 'config/db.php';
include 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT sr.id, sr.requested_service, sr.request_date, sr.status, 
           g.name, v.plate_no
    FROM service_requests sr
    JOIN garages g ON sr.garage_id = g.id
    JOIN vehicles v ON sr.vehicle_id = v.id
    WHERE v.user_id = ?
    ORDER BY sr.request_date DESC
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['cancel']) && $_GET['cancel'] === 'success') {
    echo "<p style='color: green;'>Service request cancelled successfully.</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Service Requests</title>
    <style>
        table {
            width: 90%;
            border-collapse: collapse;
            margin: 20px auto;
			border:1;
			cellpadding:10;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }
        th {
            background: #eee;
        }
        .status-Pending { color: orange; }
        .status-Approved { color: green; }
        .status-Rejected { color: red; }
		.cancelled-row {
    background-color: #f8d7da; /* Light red */
    color: #721c24; /* Dark red text */
    font-style: italic;
}
    </style>
</head>
<body><br>
<a href="dashboard.php#my-cars" class="btn btn-secondary">Back</a><br>
<h2 style="text-align:center;">My Service Request History</h2>

<table >
<thead>
    <tr>
        <th>#</th>
        <th>Garage</th>
        <th>Vehicle</th>
        <th>Service</th>
        <th>Requested On</th>
        <th>Status</th>
		<th>Action</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($requests as $index => $req): ?>
       <tr class="<?= $req['status'] === 'Cancelled' ? 'cancelled-row' : '' ?>">
            <td><?= $index + 1 ?></td>
            <td><?= htmlspecialchars($req['name']) ?></td>
            <td><?= htmlspecialchars($req['plate_no']) ?></td>
            <td><?= htmlspecialchars($req['requested_service']) ?></td>
            <td><?= htmlspecialchars($req['request_date']) ?></td>
            <td class="status-<?= htmlspecialchars($req['status']) ?>">
                <?= htmlspecialchars($req['status']) ?>
            </td>
			<td>
			  <?php if (strtolower(trim($req['status'])) === 'pending'): ?>
    <form method="post" action="cancel_request.php" onsubmit="return confirm('Are you sure you want to cancel this request?');">
        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
        <button type="submit">Cancel</button>
    </form>
<?php else: ?>
    -
<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>

</table>

</body>
</html>