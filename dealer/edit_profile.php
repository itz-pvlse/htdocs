<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dealer') {
    header('Location: ../dealerlogin.php');
    exit;
}

$dealerId = $_SESSION['user_id'];

// Fetch dealer profile
$stmt = $pdo->prepare("SELECT * FROM dealers WHERE user_id=?");
$stmt->execute([$dealerId]);
$dealer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $logoPath = $dealer['logo'] ?? null;

    // Handle logo upload
    if (!empty($_FILES['logo']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/dealers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp  = $_FILES['logo']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['logo']['name']);
        $target   = $uploadDir . $fileName;

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($fileTmp, $target)) {
                // Store relative path in DB
                $logoPath = 'uploads/dealers/' . $fileName;
            }
        }
    }

    // Update dealer profile
    $u = $pdo->prepare("UPDATE dealers SET name=?, phone=?, location=?, logo=? WHERE user_id=?");
    $u->execute([$name, $phone, $location, $logoPath, $dealerId]);

    // Keep users.name in sync (optional)
    $u2 = $pdo->prepare("UPDATE users SET name=? WHERE id=?");
    $u2->execute([$name, $dealerId]);

    header('Location: dealer.php');
    exit;
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Edit Dealer Profile</title>
<style>
body { font-family: Arial; padding: 20px; background: #f5f7fb }
.form { max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.05) }
.form input[type=text], .form input[type=file] { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 6px }
.btn { background: #2b8aed; color: #fff; padding: 10px 14px; border: none; border-radius: 6px; cursor: pointer }
.logo-preview { margin: 10px 0; }
.logo-preview img { max-width: 150px; border-radius: 6px; border: 1px solid #ddd; }
</style>
</head>
<body>
<div class="form">
  <h2>Edit Profile</h2>
  <form method="post" enctype="multipart/form-data">
    <label>Name</label>
    <input type="text" name="name" value="<?= htmlspecialchars($dealer['name'] ?? '') ?>" required>

    <label>Phone</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($dealer['phone'] ?? '') ?>">

    <label>Location</label>
    <input type="text" name="location" value="<?= htmlspecialchars($dealer['location'] ?? '') ?>">

    <label>Logo</label>
    <?php if (!empty($dealer['logo'])): ?>
      <div class="logo-preview">
        <img src="../<?= htmlspecialchars($dealer['logo']) ?>" alt="Current Logo">
      </div>
    <?php endif; ?>
    <input type="file" name="logo" accept="image/*">

    <button class="btn" type="submit">Save</button>
    <a href="dealer.php" style="margin-left:10px">Cancel</a>
  </form>
</div>
</body>
</html>