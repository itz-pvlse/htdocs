<?php
// dealer/edit_car.php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header('Location: ../dealerlogin.php');
    exit;
}

$dealerId = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

// Get main car data
$stmt = $pdo->prepare("SELECT * FROM dealer_listings WHERE id = ? AND dealer_id = ?");
$stmt->execute([$id, $dealerId]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) die('Listing not found');

// Get additional images
$stmtImgs = $pdo->prepare("SELECT * FROM dealer_listing_images WHERE listing_id = ?");
$stmtImgs->execute([$id]);
$additionalImages = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $make = trim($_POST['make'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? $car['status'];
    $mileage = (int)($_POST['mileage'] ?? 0);
    $transmission = $_POST['transmission'] ?? 'Automatic';
    $fuel_type = $_POST['fuel_type'] ?? 'Petrol';
    $vin = trim($_POST['vin'] ?? '');
    $engine_no = trim($_POST['engine_no'] ?? '');
    $engine_cc = (int)($_POST['engine_cc'] ?? 0);
    $fuel_tank_capacity = (float)($_POST['fuel_tank_capacity'] ?? 0); // NEW FIELD
    $drive_type = $_POST['drive_type'] ?? '';
    $doors = (int)($_POST['doors'] ?? 0);
    $seats = (int)($_POST['seats'] ?? 0);
    $airbags = (int)($_POST['airbags'] ?? 0);
    $color = trim($_POST['color'] ?? '');
    $service_history = trim($_POST['service_history'] ?? '');
    $accident_history = trim($_POST['accident_history'] ?? '');
    $warranty = trim($_POST['warranty'] ?? '');
    $location = trim($_POST['location'] ?? '');

    $path = $car['main_image'];

    // Replace MAIN image if provided
    if (!empty($_FILES['main_image']['name'])) {
        $dir = '../uploads/dealer_listings/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) die('Invalid image type');
        $name = time().'_'.$dealerId.'_'.preg_replace('/[^a-zA-Z0-9_\.-]/','_', $_FILES['main_image']['name']);
        $full = $dir.$name;
        if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $full)) die('Upload failed');
        if (!empty($path) && file_exists('../'.ltrim($path,'/'))) @unlink('../'.ltrim($path,'/'));
        $path = 'uploads/dealer_listings/'.$name;
    }

    // Update main car record with fuel_tank_capacity
    $stmtU = $pdo->prepare("UPDATE dealer_listings SET
        title=?, make=?, model=?, year=?, price=?, description=?, main_image=?, status=?,
        mileage=?, transmission=?, fuel_type=?, vin=?, engine_no=?, engine_cc=?, fuel_tank_capacity=?, drive_type=?, doors=?,
        seats=?, airbags=?, color=?, service_history=?, accident_history=?, warranty=?, location=?
        WHERE id=? AND dealer_id=?");
    $stmtU->execute([
        $title, $make, $model, $year, $price, $description, $path, $status,
        $mileage, $transmission, $fuel_type, $vin, $engine_no, $engine_cc, $fuel_tank_capacity, $drive_type, $doors,
        $seats, $airbags, $color, $service_history, $accident_history, $warranty, $location,
        $id, $dealerId
    ]);

    // Upload NEW additional images
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = '../uploads/dealer_listings/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $allowed = ['jpg','jpeg','png','webp'];
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) continue;
                $fileName = time().'_'.$dealerId.'_'.preg_replace('/[^a-zA-Z0-9_\.-]/','_', $_FILES['images']['name'][$key]);
                $targetPath = $uploadDir.$fileName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmtImg = $pdo->prepare("INSERT INTO dealer_listing_images (listing_id, image_path) VALUES (?, ?)");
                    $stmtImg->execute([$id, 'uploads/dealer_listings/'.$fileName]);
                }
            }
        }
    }

    header('Location: dealer.php');
    exit;
}

// Handle delete request for additional images
if (isset($_GET['delete_img'])) {
    $imgId = (int)$_GET['delete_img'];
    $stmtImg = $pdo->prepare("SELECT * FROM dealer_listing_images WHERE id = ? AND listing_id = ?");
    $stmtImg->execute([$imgId, $id]);
    $imgData = $stmtImg->fetch(PDO::FETCH_ASSOC);
    if ($imgData) {
        if (file_exists('../'.ltrim($imgData['image_path'],'/'))) @unlink('../'.ltrim($imgData['image_path'],'/'));
        $pdo->prepare("DELETE FROM dealer_listing_images WHERE id = ?")->execute([$imgId]);
    }
    header("Location: edit_car.php?id={$id}");
    exit;
}

$img = $car['main_image'] ? '../'.ltrim($car['main_image'],'/') : '';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Edit Listing</title>
<style>
body{font-family:Arial;padding:20px;background:#f5f7fb}
.form{max-width:800px;margin:0 auto;background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.form input,.form textarea,.form select{width:100%;padding:10px;margin:8px 0;border:1px solid #ddd;border-radius:6px}
.btn{background:#2b8aed;color:#fff;padding:10px 14px;border:none;border-radius:6px;cursor:pointer}
.thumb{width:100px;height:70px;object-fit:cover;border-radius:4px;margin:5px;border:1px solid #ccc}
.flex-gap{display:flex; gap:10px}
.flex-1{flex:1}
</style>
</head>
<body>
<div class="form">
  <h2>Edit Listing</h2>
  <form method="post" enctype="multipart/form-data">
    
    <label>Title</label>
    <input name="title" value="<?= htmlspecialchars($car['title']) ?>" required>

    <div class="flex-gap">
      <div class="flex-1"><label>Make</label><input name="make" value="<?= htmlspecialchars($car['make']) ?>"></div>
      <div class="flex-1"><label>Model</label><input name="model" value="<?= htmlspecialchars($car['model']) ?>"></div>
      <div style="width:140px"><label>Year</label><input name="year" type="number" min="1900" max="2100" value="<?= (int)$car['year'] ?>"></div>
    </div>

    <label>Price (KES)</label>
    <input name="price" type="number" step="0.01" min="0" value="<?= htmlspecialchars($car['price']) ?>" required>

    <div class="flex-gap">
      <div class="flex-1"><label>VIN</label><input name="vin" value="<?= htmlspecialchars($car['vin']) ?>"></div>
      <div class="flex-1"><label>Engine No</label><input name="engine_no" value="<?= htmlspecialchars($car['engine_no']) ?>"></div>
      <div class="flex-1">
        <label>Engine CC</label>
        <input name="engine_cc" type="number" min="50" max="10000" value="<?= htmlspecialchars($car['engine_cc']) ?>">
      </div>
      <div class="flex-1">
        <label>Fuel Tank Capacity (L)</label>
        <input name="fuel_tank_capacity" type="number" step="0.1" min="1" max="500" value="<?= htmlspecialchars($car['fuel_tank_capacity']) ?>">
      </div>
      <div class="flex-1"><label>Drive Type</label>
        <select name="drive_type">
          <option value="">Select</option>
          <option value="FWD" <?= $car['drive_type']==='FWD'?'selected':''; ?>>FWD</option>
          <option value="RWD" <?= $car['drive_type']==='RWD'?'selected':''; ?>>RWD</option>
          <option value="AWD" <?= $car['drive_type']==='AWD'?'selected':''; ?>>AWD</option>
          <option value="4WD" <?= $car['drive_type']==='4WD'?'selected':''; ?>>4WD</option>
        </select>
      </div>
    </div>


    <div class="flex-gap">
      <div style="width:80px"><label>Doors</label><input name="doors" type="number" min="1" max="6" value="<?= (int)$car['doors'] ?>"></div>
      <div style="width:80px"><label>Seats</label><input name="seats" type="number" min="1" max="10" value="<?= (int)$car['seats'] ?>"></div>
      <div style="width:80px"><label>Airbags</label><input name="airbags" type="number" min="0" max="10" value="<?= (int)$car['airbags'] ?>"></div>
      <div class="flex-1"><label>Color</label><input name="color" value="<?= htmlspecialchars($car['color']) ?>"></div>
    </div>

    <label>Service History</label>
    <textarea name="service_history" rows="3"><?= htmlspecialchars($car['service_history']) ?></textarea>

    <label>Accident History</label>
    <textarea name="accident_history" rows="3"><?= htmlspecialchars($car['accident_history']) ?></textarea>

    <label>Warranty</label>
    <input name="warranty" value="<?= htmlspecialchars($car['warranty']) ?>">

    <label>Location</label>
    <input name="location" value="<?= htmlspecialchars($car['location']) ?>">

    <div class="flex-gap">
      <div style="flex:1">
        <label>Mileage (km)</label>
        <input name="mileage" type="number" min="0" value="<?= htmlspecialchars($car['mileage']) ?>">
      </div>
      <div style="flex:1">
        <label>Transmission</label>
        <select name="transmission">
          <option value="Automatic" <?= $car['transmission']==='Automatic'?'selected':''; ?>>Automatic</option>
          <option value="Manual" <?= $car['transmission']==='Manual'?'selected':''; ?>>Manual</option>
        </select>
      </div>
      <div style="flex:1">
        <label>Fuel Type</label>
        <select name="fuel_type">
          <option value="Petrol" <?= $car['fuel_type']==='Petrol'?'selected':''; ?>>Petrol</option>
          <option value="Diesel" <?= $car['fuel_type']==='Diesel'?'selected':''; ?>>Diesel</option>
          <option value="Hybrid" <?= $car['fuel_type']==='Hybrid'?'selected':''; ?>>Hybrid</option>
          <option value="Electric" <?= $car['fuel_type']==='Electric'?'selected':''; ?>>Electric</option>
        </select>
      </div>
    </div>

    <label>Description</label>
    <textarea name="description" rows="5"><?= htmlspecialchars($car['description']) ?></textarea>

    <?php if ($img): ?>
      <div><img src="<?= htmlspecialchars($img) ?>" style="width:200px;height:140px;object-fit:cover;border-radius:6px"></div>
    <?php endif; ?>
    <label>Replace Main Image</label>
    <input type="file" name="main_image" accept="image/*">

    <label>Current Additional Images</label>
    <div>
      <?php foreach ($additionalImages as $addImg): ?>
        <div style="display:inline-block;position:relative;">
          <img src="../<?= htmlspecialchars($addImg['image_path']) ?>" class="thumb">
          <a href="?id=<?= $id ?>&delete_img=<?= $addImg['id'] ?>" style="position:absolute;top:0;right:0;background:#ff0000;color:#fff;padding:2px 5px;font-size:12px;text-decoration:none;">X</a>
        </div>
      <?php endforeach; ?>
    </div>

    <label>Add More Additional Images</label>
    <input type="file" name="images[]" multiple accept="image/*">

    <label>Status</label>
    <select name="status">
      <option value="active" <?= $car['status']==='active'?'selected':''; ?>>Active</option>
      <option value="sold" <?= $car['status']==='sold'?'selected':''; ?>>Sold</option>
    </select>

    <button class="btn" type="submit">Save Changes</button>
    <a href="dealer.php" style="margin-left:10px">Cancel</a>
  </form>
</div>
</body>
</html>
