<?php
require_once '../auth/auth_check.php';
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


include '../includes/header.php';


$vehicleId = $_GET['id'];
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND user_id = ?");
$stmt->execute([$vehicleId, $userId]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    echo "Vehicle not found.";
    exit();
}
$missingDetails = empty($vehicle['chassis_number']) || empty($vehicle['engine_number']) || empty($vehicle['rating_cc']);





?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Vehicle</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
	<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f2f2f2;
        margin: 0;
        padding: 0;
    }

    .edit-vehicle-container {
        max-width: 700px;
        margin: 40px auto;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        padding: 30px 40px;
    }

    h2 {
        text-align: center;
        margin-bottom: 25px;
        color: #333;
    }

    form label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #444;
    }

    form input[type="text"],
    form input[type="number"],
    form input[type="date"],
    form select {
        width: 100%;
        padding: 10px 12px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
        border-radius: 6px;
        background-color: #f9f9f9;
        transition: 0.3s;
    }

    form input:focus,
    form select:focus {
        border-color: #007bff;
        outline: none;
        background-color: #fff;
    }

    .form-actions {
        text-align: center;
    }

    .btn-primary,
    .btn-secondary {
        padding: 10px 25px;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
        margin: 5px;
    }

    .btn-primary {
        background-color: #007bff;
        color: #fff;
    }

    .btn-primary:hover {
        background-color: #0056b3;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: #fff;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
    }

    .form-group {
        margin-bottom: 15px;
    }

    @media (max-width: 600px) {
        .edit-vehicle-container {
            padding: 20px;
        }
    }
	
	.success-message {
    color: green;
    background-color: #e1f9e6;
    padding: 10px;
    border-left: 4px solid green;
    margin-bottom: 20px;
    border-radius: 5px;
	}

	.error-message {
    color: red;
    background-color: #fdecea;
    padding: 10px;
    border-left: 4px solid red;
    margin-bottom: 20px;
    border-radius: 5px;
	}

</style>

</head>
<body class="bg-light">
<div class="edit-vehicle-container">
    <h2>Edit Vehicle Details</h2>
    <form action="update_vehicle.php" method="POST" class="mt-4">
        <input type="hidden" name="id" value="<?= htmlspecialchars($vehicle['id']) ?>">


        <div class="form-group">
            <label>Make</label>
            <input type="text" name="make" class="form-control" value="<?= htmlspecialchars($vehicle['make']) ?>" required>
        </div>

        <div class="form-group">
            <label>Model</label>
            <input type="text" name="model" class="form-control" value="<?= htmlspecialchars($vehicle['model']) ?>" required>
        </div>

        <div class="form-group">
            <label>Plate Number</label>
            <input type="text" name="plate_no" class="form-control" value="<?= htmlspecialchars($vehicle['plate_no']) ?>" required>
        </div>

       <label for="transmission_type">Transmission Type</label>
		<select name="transmission_type" id="transmission_type" class="form-control" required>
			<option value="">-- Select Transmission --</option>
			<option value="Automatic" <?= ($vehicle['transmission_type'] == 'Automatic') ? 'selected' : '' ?>>Automatic</option>
			<option value="Manual" <?= ($vehicle['transmission_type'] == 'Manual') ? 'selected' : '' ?>>Manual</option>
			<option value="CVT" <?= ($vehicle['transmission_type'] == 'CVT') ? 'selected' : '' ?>>CVT</option>
			<option value="Semi-Automatic" <?= ($vehicle['transmission_type'] == 'Semi-Automatic') ? 'selected' : '' ?>>Semi-Automatic</option>
		</select>

        <div class="form-group">
            <label>Reg Cert No</label>
            <input type="text" name="reg_cert_no" class="form-control" value="<?= htmlspecialchars($vehicle['reg_cert_no']) ?>" required>
        </div>

        <div class="form-group">
            <label>Chassis No</label>
            <input type="text" name="chassis_number" class="form-control" value="<?= htmlspecialchars($vehicle['chassis_number']) ?>" required>
        </div>

        <div class="form-group">
            <label>Engine No</label>
            <input type="text" name="engine_number" class="form-control" value="<?= htmlspecialchars($vehicle['engine_number']) ?>" required>
        </div>

		<div class="form-group">
            <label>Customs Entry No.:</label>
            <input type="text" name="customs_entry_number" class="form-control" value="<?= htmlspecialchars($vehicle['customs_entry_number']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>Body Type:</label>
            <input type="text" name="body_type" class="form-control" value="<?= htmlspecialchars($vehicle['body_type']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>manufacture year</label>
            <input type="text" name="manufacture_year" class="form-control" value="<?= htmlspecialchars($vehicle['manufacture_year']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>color</label>
            <input type="text" name="color" class="form-control" value="<?= htmlspecialchars($vehicle['color']) ?>" required>
        </div>
		<div class="form-group">
            <label>fuel type</label>
            <input type="text" name="fuel_type" class="form-control" value="<?= htmlspecialchars($vehicle['fuel_type']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>rating cc</label>
            <input type="text" name="rating_cc" class="form-control" value="<?= htmlspecialchars($vehicle['rating_cc']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>load capacity</label>
            <input type="text" name="load_capacity" class="form-control" value="<?= htmlspecialchars($vehicle['load_capacity']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>passengers</label>
            <input type="text" name="passengers" class="form-control" value="<?= htmlspecialchars($vehicle['passengers']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>condition</label>
            <input type="text" name="condition" class="form-control" value="<?= htmlspecialchars($vehicle['condition']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>driver side</label>
            <input type="text" name="driver_side" class="form-control" value="<?= htmlspecialchars($vehicle['driver_side']) ?>" required>
        </div>
				
				<div class="form-group">
            <label>reg cert no</label>
            <input type="text" name="reg_cert_no" class="form-control" value="<?= htmlspecialchars($vehicle['reg_cert_no']) ?>" required>
        </div>
		
		<div class="form-group">
            <label>reg cert serial</label>
            <input type="text" name="reg_cert_serial" class="form-control" value="<?= htmlspecialchars($vehicle['reg_cert_serial']) ?>" required>
        </div>
		<br>
		<div class="form-group">
		<label>Under Caveat:</label>
        <select name="under_caveat">
            <option value="NO">NO</option>
            <option value="YES">YES</option>
        </select>
		</div>
		<br>
		<div class="mb-3">
    <label for="insurance_expiry_date" class="form-label">Insurance Expiry Date</label>
    <input type="date" class="form-group" name="insurance_expiry_date" id="insurance_expiry_date" 
           value="<?= isset($vehicle['insurance_expiry_date']) ? htmlspecialchars($vehicle['insurance_expiry_date']) : '' ?>" required>
</div>

<div class="mb-3">
    <label for="last_service_date" class="form-label">Last Service Date</label>
    <input type="date" class="form-group" name="last_service_date" id="last_service_date" 
           value="<?= isset($vehicle['last_service_date']) ? htmlspecialchars($vehicle['last_service_date']) : '' ?>">
</div>

<div class="mb-3">
    <label for="service_interval_km" class="form-label">Service Interval (KM)</label>
    <input type="number" class="form-group" name="service_interval_km" id="service_interval_km" 
           placeholder="e.g. 5000" value="<?= isset($vehicle['service_interval_km']) ? htmlspecialchars($vehicle['service_interval_km']) : '' ?>">
</div>

        <button type="submit" class="btn btn-primary">Update Vehicle</button>
        <a href="javascript:history.back()" class="btn btn-secondary ml-2">Cancel</a>

    </form>
</div>
</body>
</html>
