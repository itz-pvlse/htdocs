<?php
require_once 'auth/auth_check.php';
require_once 'config/db.php';
include 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$garage_id = $_SESSION['user_id'];
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

$sql = "
    SELECT sr.*, v.plate_no, v.make, v.model, v.chassis_number, v.engine_number,
           u.name AS customer_name, u.email, u.phone
    FROM service_requests sr
    JOIN vehicles v ON sr.vehicle_id = v.id
    JOIN users u ON sr.owner_id = u.id
    WHERE sr.garage_id = ?
";
$params = [$garage_id];

if ($statusFilter) {
    $sql .= " AND sr.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY sr.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Service Requests</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f8;
            padding: 20px;
            margin: 0;
            color: #333;
        }

        h2 {
            text-align: center;
            color: #007bff;
            margin-bottom: 20px;
        }

        button.back-btn {
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: #343a40;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        form {
            margin-bottom: 20px;
            text-align: center;
        }

        select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        .table-wrapper {
            overflow-x: auto; /* Enable horizontal scroll on small screens */
        }

        table {
            width: 100%;
            min-width: 800px; /* Ensure table doesn’t shrink too much */
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        th {
            background-color: #f8f9fa;
            color: #333;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
        }

        .pending { background-color: #fff3cd; color: #856404; }
        .approved { background-color: #d4edda; color: #155724; }
        .cancelled { background-color: #f8d7da; color: #721c24; }
        .rejected { background-color: #ffe0cc; color: #993300; }

        button[name="action"] {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            margin: 2px;
            font-size: 13px;
            cursor: pointer;
        }
        button[name="action"][value="approved"] { background-color: #28a745; color: white; }
        button[name="action"][value="rejected"] { background-color: #dc3545; color: white; }

        /* Optional: Slightly reduce padding on very small screens */
        @media (max-width: 500px) {
            th, td { padding: 10px; font-size: 13px; }
            button[name="action"] { font-size: 12px; padding: 6px; }
        }
    </style>
</head>
<body>

<button class="back-btn" onclick="history.back()">← Back</button>
<h2>Service Requests</h2>

<form method="GET">
    <label for="status">Filter by Status:</label>
    <select name="status" id="status" onchange="this.form.submit()">
        <option value="">-- All --</option>
        <option value="Pending" <?= (isset($_GET['status']) && $_GET['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
        <option value="Approved" <?= (isset($_GET['status']) && $_GET['status'] == 'Approved') ? 'selected' : '' ?>>Approved</option>
        <option value="Rejected" <?= (isset($_GET['status']) && $_GET['status'] == 'Rejected') ? 'selected' : '' ?>>Rejected</option>
        <option value="Cancelled" <?= (isset($_GET['status']) && $_GET['status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
    </select>
</form>

<div class="table-wrapper">
<table>
    <thead>
    <tr>
        <th>Vehicle</th>
        <th>Owner</th>
        <th>Service Details</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    </thead>

    <tbody>
    <?php foreach ($requests as $req): ?>
        <tr>
            <td>
                <strong>Plate:</strong> <?= htmlspecialchars($req['plate_no']) ?><br>
                <strong>Make/Model:</strong> <?= htmlspecialchars($req['make']) ?> <?= htmlspecialchars($req['model']) ?><br>
                <strong>Chassis:</strong> <?= htmlspecialchars($req['chassis_number']) ?><br>
                <strong>Engine:</strong> <?= htmlspecialchars($req['engine_number']) ?>
            </td>
            <td>
                <strong>Name:</strong> <?= htmlspecialchars($req['customer_name']) ?><br>
                <strong>Email:</strong> <?= htmlspecialchars($req['email']) ?><br>
                <strong>Phone:</strong> <?= htmlspecialchars($req['phone']) ?>
            </td>
            <td>
                <strong>Service:</strong> <?= htmlspecialchars($req['requested_service']) ?><br>
                <strong>Requested On:</strong> <?= htmlspecialchars($req['request_date']) ?><br>
                <?php if (!empty($req['note'])): ?>
                    <strong>Note:</strong> <?= nl2br(htmlspecialchars($req['note'])) ?>
                <?php endif; ?>
            </td>
            <td>
                <span class="status-badge <?= strtolower($req['status']) ?>">
                    <?= htmlspecialchars($req['status']) ?>
                </span>
            </td>
            <td>
                <?php if ($req['status'] === 'Pending'): ?>
                    <form method="post" action="garage/handle_request.php" style="display:flex; flex-wrap:wrap;">
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        <button type="submit" name="action" value="approved">Approve</button>
                        <button type="submit" name="action" value="rejected">Reject</button>
                    </form>
                <?php else: ?>
                    <span style="color: #888;">No action</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

</body>
</html>
