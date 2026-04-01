<?php
require_once 'auth/auth_check.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';
include 'includes/header.php';


$vehicleId = $_GET['id'];
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND user_id = ?");
$stmt->execute([$vehicleId, $userId]);
$vehicle = $stmt->fetch();

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT mileage, updated_at FROM mileage_logs WHERE vehicle_id = :id ORDER BY updated_at DESC");
$stmt->execute([':id' => $id]);
$mileageLogs = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT current_mileage FROM vehicles WHERE id = :id ");
$stmt->execute([':id' => $id]);
$vehicles = $stmt->fetchAll();


if (!$vehicle) {
    echo "Vehicle not found.";
    exit();
}
$missingDetails = empty($vehicle['chassis_number']) || empty($vehicle['engine_number']) || empty($vehicle['rating_cc']);





?>


<?php if ($missingDetails): ?>

<style>
    form {
        background-color: #f8f9fa;
        padding: 30px;
        border-radius: 8px;
        max-width: 600px;
        margin: 40px auto;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        font-family: Arial, sans-serif;
		
    }

    form label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
        margin-top: 15px;
    }

    form input[type="text"],
    form input[type="number"],
    form select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 14px;
        background-color: #fff;
    }

    form input[type="text"]:focus,
    form input[type="number"]:focus,
    form select:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 4px #007bff44;
    }

    form button[type="submit"] {
        margin-top: 20px;
        width: 100%;
        background-color: #007bff;
        color: white;
        border: none;
        padding: 12px;
        font-size: 16px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    form button[type="submit"]:hover {
        background-color: #0056b3;
    }
	
	
</style>


    <h3>Complete Vehicle Profile</h3>
	<?php
	
if (isset($_SESSION['error'])) {
    echo "<div style='color:red;'>".$_SESSION['error']."</div>";
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    echo "<div style='color:green;'>".$_SESSION['success']."</div>";
    unset($_SESSION['success']);
}
?>
    <form action="php/save_vehicle_details.php" method="POST">
        <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">

        <label>Chassis Number:</label>
        <input type="text" name="chassis_number" required><br>

        <label>Customs Entry No.:</label>
        <input type="text" name="customs_entry_number" required><br>

        <label>Body Type:</label>
        <input type="text" name="body_type"><br>

        <label>Manufacture Year:</label>
        <input type="number" name="manufacture_year"><br>

        <label>Colour:</label>
        <input type="text" name="color"><br>

        <label>Fuel Type:</label>
        <input type="text" name="fuel_type"><br>
		
		<label for="transmission_type">Transmission Type</label>
		<select name="transmission_type" id="transmission_type" class="form-control" required>
			<option value="">-- Select Transmission --</option>
			<option value="Automatic" <?= ($vehicle['transmission_type'] == 'Automatic') ? 'selected' : '' ?>>Automatic</option>
			<option value="Manual" <?= ($vehicle['transmission_type'] == 'Manual') ? 'selected' : '' ?>>Manual</option>
			<option value="CVT" <?= ($vehicle['transmission_type'] == 'CVT') ? 'selected' : '' ?>>CVT</option>
			<option value="Semi-Automatic" <?= ($vehicle['transmission_type'] == 'Semi-Automatic') ? 'selected' : '' ?>>Semi-Automatic</option>
		</select>

        <label>Engine No.:</label>
        <input type="text" name="engine_number"><br>

        <label>Rating (cc):</label>
        <input type="number" name="rating_cc"><br>

        <label>Load Capacity (kgs):</label>
        <input type="number" name="load_capacity"><br>

        <label>Passengers:</label>
        <input type="number" name="passengers"><br>

        <label>Condition:</label>
        <input type="text" name="condition"><br>


        <label>Driver Side:</label>
        <input type="text" name="driver_side"><br>

        <label>Under Caveat:</label>
        <select name="under_caveat">
            <option value="NO">NO</option>
            <option value="YES">YES</option>
        </select><br>

        <label>Reg. Cert No:</label>
        <input type="text" name="reg_cert_no"><br>

        <label>Reg. Cert Serial No:</label>
        <input type="text" name="reg_cert_serial"><br>

        <button type="submit">Save Details</button>
    </form>
<?php else: ?>
    <!-- Show existing details -->
<!DOCTYPE html>
<html>
<head>
    <title>Vehicle Profile</title>
    <link rel="stylesheet" href="your-style.css"> <!-- Optional: your custom style -->
	<style>
.vehicle-profile {
    border: 1px solid #ccc;
    background-color: #fff;
    padding: 25px;
    max-width: 600px;
    margin: 20px auto;
    border-radius: 8px;
    font-family: 'Segoe UI', sans-serif;
    font-size: 15px;
    color: #222;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.vehicle-profile h2 {
    text-align: center;
    font-weight: 600;
    margin-bottom: 20px;
}

.vehicle-profile img {
    width: 100%;
    height: 300px;
    object-fit: cover;
    border-radius: 5px;
    margin-bottom: 20px;
}

.vehicle-details {
    width: 100%;
    border-collapse: collapse;
}

.vehicle-details td {
    padding: 8px 10px;
    vertical-align: top;
}

.vehicle-details td.label {
    font-weight: bold;
    width: 40%;
    color: #333;
}

.vehicle-details td.value {
    color: #000;
}

.vehicle-actions {
    text-align: center;
    margin-top: 20px;
}

.vehicle-actions a {
    margin: 5px;
}


.alert {
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
}
.alert-danger { background: #f8d7da; color: #721c24; }
.alert-warning { background: #fff3cd; color: #856404; }
.alert-success { background: #d4edda; color: #155724; }

</style>
</head>


<div class="vehicle-profile">
    <h2>Vehicle Profile</h2>
	<div class="vehicle-actions">
        <a href="php/edit_vehicle.php?id=<?= $vehicle['id'] ?>" class="btn btn-warning">Edit Vehicle</a>
        <a href="dashboard.php#my-cars" class="btn btn-secondary">Back</a>
    </div>
	<?php
$today = date('Y-m-d');
$expiry_date = $vehicle['insurance_expiry_date'];

if ($expiry_date < $today) {
    echo "<div class='alert alert-danger'>🚨 Insurance has expired on <strong>" . htmlspecialchars($expiry_date) . "</strong>!</div>";
} elseif ($expiry_date <= date('Y-m-d', strtotime('+7 days'))) {
    echo "<div class='alert alert-warning'>⚠️ Insurance is expiring soon on <strong>" . htmlspecialchars($expiry_date) . "</strong>.</div>";
} else {
    echo "<div class='alert alert-success'>✅ Insurance is valid until <strong>" . htmlspecialchars($expiry_date) . "</strong>.</div>";
}
?>
    <img src="<?= $vehicle['image_path'] ?>" alt="Car Image">

    <table class="vehicle-details">
        <tr><td class="label">Make:</td><td class="value"><?= htmlspecialchars($vehicle['make']) ?></td></tr>
        <tr><td class="label">Model:</td><td class="value"><?= htmlspecialchars($vehicle['model']) ?></td></tr>
        <tr><td class="label">Plate Number:</td><td class="value"><?= htmlspecialchars($vehicle['plate_no']) ?></td></tr>
        <tr><td class="label">Year:</td><td class="value"><?= htmlspecialchars($vehicle['year']) ?></td></tr>
        <tr><td class="label">Color:</td><td class="value"><?= htmlspecialchars($vehicle['color']) ?></td></tr>
        <tr><td class="label">Condition:</td><td class="value"><?= htmlspecialchars($vehicle['condition']) ?></td></tr>
        <tr><td class="label">Driver Side:</td><td class="value"><?= htmlspecialchars($vehicle['driver_side']) ?></td></tr>
        <tr><td class="label">Under Caveat:</td><td class="value"><?= htmlspecialchars($vehicle['under_caveat']) ?></td></tr>
        <tr><td class="label">Registration Cert No:</td><td class="value"><?= htmlspecialchars($vehicle['reg_cert_no']) ?></td></tr>
        <tr><td class="label">Chassis Number:</td><td class="value"><?= htmlspecialchars($vehicle['chassis_number']) ?></td></tr>
        <tr><td class="label">Engine Number:</td><td class="value"><?= htmlspecialchars($vehicle['engine_number']) ?></td></tr>
        <tr><td class="label">Fuel Type:</td><td class="value"><?= htmlspecialchars($vehicle['fuel_type']) ?></td></tr>
        <tr><td class="label">Transmission:</td><td class="value"><?= htmlspecialchars($vehicle['transmission_type']) ?></td></tr>
        <tr><td class="label">Body Type:</td><td class="value"><?= htmlspecialchars($vehicle['body_type']) ?></td></tr>
		<tr><td class="label">Insurance expiry date:</td><td class="value"><?= htmlspecialchars($vehicle['insurance_expiry_date']) ?></td></tr>
		<tr><td class="label">Last Service date:</td><td class="value"><?= htmlspecialchars($vehicle['last_service_date']) ?></td></tr>
		<tr><td class="label">Service Interval (km):</td><td class="value"><?= htmlspecialchars($vehicle['service_interval_km']) ?></td></tr>
		
        <tr><td class="label">Uploaded On:</td><td class="value"><?= date('F j, Y', strtotime($vehicle['created_at'])) ?></td></tr>
    </table>
	
	 <!-- mileage -->
<div class="mileage">
<div class="card" id="mileage">
  <h2>Set current mileage.</h2>
<form method="post" action="php/update_mileage.php">
    <input type="hidden" name="vehicle_id" value="<?= $vehicle['id']; ?>">
    
    <div class="form-group mb-2">
        <label for="current_mileage">Update Current Mileage (km)</label>
        <input type="number" class="form-control" name="mileage" id="current_mileage"
               value="<?= htmlspecialchars($vehicle['current_mileage']) ?>" min="0" required>
    </div>

    <button type="submit" class="btn btn-primary">Update Mileage</button>
	<?php if (isset($_GET['mileage_updated'])): ?>
    <div class="alert alert-success">✅ Mileage updated successfully!</div>
<?php endif; ?>


<h4>Mileage History</h4>

<?php if ($mileageLogs): ?>
    <ul class="list-group">
        <?php foreach ($mileageLogs as $log): ?>
            <li class="list-group-item">
                Mileage: <?= htmlspecialchars($log['mileage']) ?> km
                <br>
                Updated At: <?= htmlspecialchars($log['updated_at']) ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No mileage logs found.</p>
<?php endif; ?>

</form>
</div>
</div>



    <div class="vehicle-actions">
        <a href="php/edit_vehicle.php?id=<?= $vehicle['id'] ?>" class="btn btn-warning">Edit Vehicle</a>
        <a href="dashboard.php#my-cars" class="btn btn-secondary">Back</a>
    </div>
</div>


</body>
</html>
<?php endif; ?>