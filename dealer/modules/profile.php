<?php
session_start();
require_once '../config/db.php';

// Ensure dealer is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dealer') {
    echo '<p>Unauthorized access.</p>';
    exit;
}

$dealerUserId = $_SESSION['user_id'];

// Fetch dealer info
$stmt = $pdo->prepare("SELECT * FROM dealers WHERE user_id = ?");
$stmt->execute([$dealerUserId]);
$dealer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dealer) {
    echo '<p>Dealer profile not found.</p>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dealer Profile | AutoLog</title>
<style>
body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f5f6fa; padding:20px; }
h2 { text-align:center; margin-bottom:20px; color:#007bff; }

.profile-container { max-width:600px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.profile-container label { display:block; margin-top:15px; font-weight:bold; }
.profile-container input, .profile-container textarea { width:100%; padding:8px 10px; margin-top:5px; border:1px solid #ccc; border-radius:6px; }
.profile-container button { margin-top:20px; padding:10px 15px; border:none; border-radius:6px; background:#007bff; color:#fff; cursor:pointer; }
.profile-container button:hover { background:#0056b3; }
.profile-container .cancel { background:#6c757d; margin-left:10px; }
.profile-container .cancel:hover { background:#5a6268; }
#logo-preview { max-width:150px; max-height:150px; margin-top:10px; display:block; border-radius:6px; border:1px solid #ccc; }
#message { margin-top:15px; font-weight:bold; }

/* Coordinate display */
#coords-display { margin-top:10px; font-size:0.9em; color:#333; background:#e9ecef; padding:8px; border-radius:6px; }
</style>
</head>
<body>

<h2>Dealer Profile</h2>

<div class="profile-container">
    <form id="profileForm" enctype="multipart/form-data">
        <label for="name">Dealer Name</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($dealer['name']) ?>" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($dealer['email']) ?>" readonly>

        <label for="phone">Phone</label>
        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($dealer['phone']) ?>">

        <label for="whatsapp">WhatsApp <small style="font-weight:normal;color:#555;">(Start with country code, e.g., +254)</small></label>
        <input type="text" id="whatsapp" name="whatsapp" value="<?= htmlspecialchars($dealer['whatsapp'] ?? '') ?>" placeholder="+254712345678">

        <label for="location">Location <small style="font-weight:normal;color:#555;">(County showroom is located)</small></label>
        <input type="text" id="location" name="location" value="<?= htmlspecialchars($dealer['location']) ?>">

        <!-- Geotags -->
        <label for="location">Geotag <small style="font-weight:normal;color:#555;">(Pin point location on map based on current location)</small></label>
        <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($dealer['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($dealer['longitude'] ?? '') ?>">
        <button type="button" class="btn btn-info mt-2" onclick="setGeotag()">Set Location (Geotag)</button>

        <!-- Display current coordinates -->
        <div id="coords-display">
            Latitude: <span id="current-lat"><?= htmlspecialchars($dealer['latitude'] ?? 'Not set') ?></span><br>
            Longitude: <span id="current-lng"><?= htmlspecialchars($dealer['longitude'] ?? 'Not set') ?></span><br>
            <span id="location-status"><?= !empty($dealer['latitude']) ? 'Location already set' : 'Location not yet set' ?></span>
        </div>

        <label for="logo">Logo</label>
        <input type="file" id="logo" name="logo" accept="image/*">
        <?php if($dealer['logo']): ?>
            <img id="logo-preview" src="../<?= htmlspecialchars($dealer['logo']) ?>" alt="Logo">
        <?php else: ?>
            <img id="logo-preview" style="display:none;">
        <?php endif; ?>

        <!-- Socials -->
        <label for="facebook">Facebook</label>
        <input type="url" id="facebook" name="facebook" value="<?= htmlspecialchars($dealer['facebook'] ?? '') ?>">

        <label for="twitter">Twitter</label>
        <input type="url" id="twitter" name="twitter" value="<?= htmlspecialchars($dealer['twitter'] ?? '') ?>">

        <label for="instagram">Instagram</label>
        <input type="url" id="instagram" name="instagram" value="<?= htmlspecialchars($dealer['instagram'] ?? '') ?>">

        <label for="tiktok">TikTok</label>
        <input type="url" id="tiktok" name="tiktok" value="<?= htmlspecialchars($dealer['tiktok'] ?? '') ?>">

        <label for="website">Website</label>
        <input type="url" id="website" name="website" value="<?= htmlspecialchars($dealer['website'] ?? '') ?>" placeholder="https://example.com">

        <!-- Description -->
        <label for="description">Description <small style="font-weight:normal;color:#555;">(Brief description about your dealership)</small></label>
        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($dealer['description'] ?? '') ?></textarea>

        <button type="submit">Update Profile</button>
        <button type="button" class="cancel" onclick="location.reload()">Cancel</button>
    </form>
    <div id="message"></div>
</div>

<script>
// Preview logo
document.getElementById('logo').addEventListener('change', function() {
    const preview = document.getElementById('logo-preview');
    const file = this.files[0];
    if(file) {
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    }
});

// Set geotag coordinates
function setGeotag() {
    if(navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            document.getElementById('latitude').value = pos.coords.latitude;
            document.getElementById('longitude').value = pos.coords.longitude;
            // Update display
            document.getElementById('current-lat').textContent = pos.coords.latitude;
            document.getElementById('current-lng').textContent = pos.coords.longitude;
            document.getElementById('location-status').textContent = "Location updated successfully!";
        }, function() {
            alert("Unable to retrieve your location.");
        });
    } else {
        alert("Geolocation not supported by your browser.");
    }
}

// AJAX form submission
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('update_profile_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        const msg = document.getElementById('message');
        if(data.status === 'success') {
            msg.style.color = 'green';
            msg.textContent = 'Profile updated successfully!';
        } else {
            msg.style.color = 'red';
            msg.textContent = data.message;
        }
    })
    .catch(() => {
        const msg = document.getElementById('message');
        msg.style.color = 'red';
        msg.textContent = 'Error updating profile.';
    });
});
</script>
</body>
</html>
