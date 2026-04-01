<?php
session_start();
require_once '../config/db.php';

// Ensure dealer is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$dealerUserId = $_SESSION['user_id'];

// Fetch dealer to get current logo
$stmt = $pdo->prepare("SELECT logo FROM dealers WHERE user_id = ?");
$stmt->execute([$dealerUserId]);
$dealer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dealer) {
    echo json_encode(['status' => 'error', 'message' => 'Dealer profile not found.']);
    exit;
}

// Get form inputs
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');
$location = trim($_POST['location'] ?? '');
$latitude = trim($_POST['latitude'] ?? '');
$longitude = trim($_POST['longitude'] ?? '');
$facebook = trim($_POST['facebook'] ?? '');
$twitter = trim($_POST['twitter'] ?? '');
$instagram = trim($_POST['instagram'] ?? '');
$tiktok = trim($_POST['tiktok'] ?? '');
$website = trim($_POST['website'] ?? '');
$description = trim($_POST['description'] ?? '');

// Validate required fields
if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Dealer name is required.']);
    exit;
}

// Validate WhatsApp (if filled, must start with + and digits)
if (!empty($whatsapp) && !preg_match('/^\+\d{8,15}$/', $whatsapp)) {
    echo json_encode(['status' => 'error', 'message' => 'WhatsApp must start with country code, e.g., +254712345678']);
    exit;
}

// Validate social URLs
$socials = ['facebook' => $facebook, 'twitter' => $twitter, 'instagram' => $instagram, 'tiktok' => $tiktok];
foreach ($socials as $platform => $url) {
    if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => ucfirst($platform).' URL is invalid.']);
        exit;
    }
}

// Validate website
if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
    echo json_encode(['status' => 'error', 'message' => 'Website URL is invalid.']);
    exit;
}

// Handle logo upload
$newLogo = $dealer['logo']; // default: keep existing
$logoFile = $_FILES['logo'] ?? null;

if ($logoFile && $logoFile['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg','jpeg','png','gif'];
    $ext = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid logo file type.']);
        exit;
    }

    $newFileName = 'logo_' . $dealerUserId . '_' . time() . '.' . $ext;
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $dest = $uploadDir . $newFileName;

    if (move_uploaded_file($logoFile['tmp_name'], $dest)) {
        $newLogo = $newFileName;

        // Delete old logo
        if ($dealer['logo'] && file_exists($uploadDir . $dealer['logo'])) {
            unlink($uploadDir . $dealer['logo']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload logo.']);
        exit;
    }
}

// Update dealer profile
try {
    $stmt = $pdo->prepare("
        UPDATE dealers 
        SET name = ?, phone = ?, whatsapp = ?, location = ?, logo = ?, facebook = ?, twitter = ?, instagram = ?, tiktok = ?, latitude = ?, longitude = ?, website = ?, description = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$name, $phone, $whatsapp, $location, $newLogo, $facebook, $twitter, $instagram, $tiktok, $latitude, $longitude, $website, $description, $dealerUserId]);

    echo json_encode([
        'status' => 'success',
        'logo' => $newLogo,
        'website' => $website,
        'whatsapp' => $whatsapp,
        'description' => $description
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: '.$e->getMessage()]);
}
?>
