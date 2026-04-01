<?php
// dealer/add_car.php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header('Location: ../dealerlogin.php');
    exit;
}

$dealerId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $make = trim($_POST['make'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $mileage = (int)($_POST['mileage'] ?? 0);
    $transmission = $_POST['transmission'] ?? 'Automatic';
    $fuel_type = $_POST['fuel_type'] ?? 'Petrol';
    $vin = trim($_POST['vin'] ?? '');
    $engine_no = trim($_POST['engine_no'] ?? '');
    $engine_cc = (int)($_POST['engine_cc'] ?? 0);
    $fuel_tank_capacity = (float)($_POST['fuel_tank_capacity'] ?? 0); // NEW
    $drive_type = $_POST['drive_type'] ?? null;
    $doors = (int)($_POST['doors'] ?? 0);
    $seats = (int)($_POST['seats'] ?? 0);
    $airbags = (int)($_POST['airbags'] ?? 0);
    $color = trim($_POST['color'] ?? '');
    $service_history = trim($_POST['service_history'] ?? '');
    $accident_history = trim($_POST['accident_history'] ?? '');
    $warranty = trim($_POST['warranty'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = 'active';

    // Handle MAIN image
    $path = null;
    if (!empty($_FILES['main_image']['name'])) {
        $dir = '../uploads/dealer_listings/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) die('Invalid image type for main image');

        $name = time() . '_' . $dealerId . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['main_image']['name']);
        $full = $dir . $name;
        if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $full)) die('Failed to upload main image');

        $path = 'uploads/dealer_listings/' . $name;
    }

    // Insert main car data including engine_cc and fuel_tank_capacity
    $stmt = $pdo->prepare("INSERT INTO dealer_listings
        (dealer_id, title, make, model, year, price, description, mileage, transmission, fuel_type, vin, engine_no, engine_cc, fuel_tank_capacity, drive_type, doors, seats, airbags, color, service_history, accident_history, warranty, location, main_image, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $dealerId, $title, $make, $model, $year, $price, $description, $mileage, $transmission, $fuel_type,
        $vin, $engine_no, $engine_cc, $fuel_tank_capacity, $drive_type, $doors, $seats, $airbags, $color, $service_history, $accident_history, $warranty, $location,
        $path, $status
    ]);

    $listingId = $pdo->lastInsertId();

    // Handle ADDITIONAL images
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = '../uploads/dealer_listings/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) continue;

                $fileName = time() . '_' . $dealerId . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['images']['name'][$key]);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmtImg = $pdo->prepare("INSERT INTO dealer_listing_images (listing_id, image_path) VALUES (?, ?)");
                    $stmtImg->execute([$listingId, 'uploads/dealer_listings/' . $fileName]);
                }
            }
        }
    }

    header('Location: dealer.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Add Car</title>
<style>
body{font-family:Arial;padding:20px;background:#f5f7fb}
.form{max-width:800px;margin:0 auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.form input,.form textarea,.form select{width:100%;padding:10px;margin:8px 0;border:1px solid #ddd;border-radius:6px}
.btn{background:#2b8aed;color:#fff;padding:10px 14px;border:none;border-radius:6px;cursor:pointer}
.flex-gap{display:flex; gap:10px}
.flex-1{flex:1}
</style>
</head>
<body>
<div class="form">
  <h2>Add New Listing</h2>
  <form method="post" enctype="multipart/form-data">
    <label>Title</label>
    <input name="title" required>

    <div class="flex-gap">
      <div class="flex-1"><label>Make</label><input name="make"></div>
      <div class="flex-1"><label>Model</label><input name="model"></div>
      <div style="width:140px"><label>Year</label><input name="year" type="number" min="1900" max="2100"></div>
    </div>

    <div class="flex-gap">
      <div class="flex-1"><label>VIN</label><input name="vin"></div>
      <div class="flex-1"><label>Engine No</label><input name="engine_no"></div>
      <div class="flex-1"><label>Engine CC</label><input name="engine_cc" type="number" min="50" max="10000"></div>
      <div class="flex-1"><label>Fuel Tank Capacity (L)</label><input name="fuel_tank_capacity" type="number" step="0.1" min="1" max="500"></div> <!-- NEW -->
      <div class="flex-1"><label>Drive Type</label>
        <select name="drive_type">
          <option value="">Select</option>
          <option value="FWD">FWD</option>
          <option value="RWD">RWD</option>
          <option value="AWD">AWD</option>
          <option value="4WD">4WD</option>
        </select>
      </div>
    </div>

    <div class="flex-gap">
      <div style="width:80px"><label>Doors</label><input name="doors" type="number" min="1" max="6"></div>
      <div style="width:80px"><label>Seats</label><input name="seats" type="number" min="1" max="10"></div>
      <div style="width:80px"><label>Airbags</label><input name="airbags" type="number" min="0" max="10"></div>
      <div class="flex-1"><label>Color</label><input name="color"></div>
    </div>

    <label>Service History</label>
    <textarea name="service_history" rows="3"></textarea>

    <label>Accident History</label>
    <textarea name="accident_history" rows="3"></textarea>

    <label>Warranty</label>
    <input name="warranty">

    <label>Location</label>
    <input name="location">

    <div class="flex-gap">
      <div style="width:150px"><label>Mileage (km)</label><input name="mileage" type="number" min="0"></div>
      <div class="flex-1"><label>Transmission</label>
        <select name="transmission">
          <option value="Automatic">Automatic</option>
          <option value="Manual">Manual</option>
        </select>
      </div>
      <div class="flex-1"><label>Fuel Type</label>
        <select name="fuel_type">
          <option value="Petrol">Petrol</option>
          <option value="Diesel">Diesel</option>
          <option value="Hybrid">Hybrid</option>
          <option value="Electric">Electric</option>
        </select>
      </div>
    </div>

    <label>Price (KES)</label>
    <input name="price" type="number" step="0.01" min="0" required>
    <label>Description</label>
    <textarea name="description" rows="5"></textarea>
    <label>Main Image</label>
    <input type="file" name="main_image" accept="image/*">
    <label>Additional Images</label>
    <input type="file" name="images[]" multiple accept="image/*">
    <button class="btn" type="submit">Save</button>
    <a href="dealer.php" style="margin-left:10px">Cancel</a>
  </form>
</div>
</body>
</html>
