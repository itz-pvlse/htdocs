<?php
require_once '../config/db.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die('User is not logged in.');
}
$userId = $_SESSION['user_id'];

// Default values
$emailNotifications = 1;
$smsNotifications = 1;
$inAppNotifications = 1;
$carCategories = '';
$defaultPricing = '';
$twoFactorAuth = 0;

// Fetch current settings
$stmt = $pdo->prepare("SELECT * FROM dealer_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if ($settings) {
    $emailNotifications = $settings['email_notifications'] ?? 1;
    $smsNotifications = $settings['sms_notifications'] ?? 1;
    $inAppNotifications = $settings['in_app_notifications'] ?? 1;
    $carCategories = $settings['car_categories'] ?? '';
    $defaultPricing = $settings['default_pricing'] ?? '';
    $twoFactorAuth = $settings['two_factor_auth'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">

<title>Dealer Settings</title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f5f7fa;
    padding: 0px;
    margin: 0;
    color: #333;
}

h2 {
    text-align: center;
    color: #007bff;
    margin-bottom: 20px;
}

.settings-container {
    max-width: 9000px;
    width: 95%;
    margin: 0 auto;
}

.card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}

.card-header {
    padding: 15px;
    font-size: 1.2rem;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header:hover {
    background: #f0f0f0;
}

.card-body {
    padding: 15px;
    display: block;
}

.card-body.collapsed {
    display: none;
}

.input-group {
    margin-bottom: 15px;
}

.input-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}

.input-group input,
.input-group select,
.input-group textarea,
.input-group button {
    width: 100%;
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1rem;
    box-sizing: border-box;
}

.input-group button {
    background: #007bff;
    color: #fff;
    border: none;
    cursor: pointer;
    margin-top: 5px;
}

.input-group button:hover {
    background: #0056b3;
}

select[multiple] {
    min-height: 150px;
    max-height: 250px;
    overflow-y: auto;
}

#message {
    margin-top: 10px;
    font-weight: bold;
}

/* Mobile adjustments */
@media(min-width: 769px) {
    .card-body {
        display: block !important; /* Always expanded on desktop */
    }
}
</style>
</head>
<body>

<div class="settings-container">
    <h2>Dealer Settings</h2>

    <!-- Notification Settings -->
    <div class="card">
        <div class="card-header">Notification Settings <span>&#9660;</span></div>
        <div class="card-body">
            <div class="input-group">
                <label>Email Notifications</label>
                <select id="emailNotifications">
                    <option value="1" <?= $emailNotifications == 1 ? 'selected' : '' ?>>Enabled</option>
                    <option value="0" <?= $emailNotifications == 0 ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>
            <div class="input-group">
                <label>SMS Alerts</label>
                <select id="smsNotifications">
                    <option value="1" <?= $smsNotifications == 1 ? 'selected' : '' ?>>Enabled</option>
                    <option value="0" <?= $smsNotifications == 0 ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>
            <div class="input-group">
                <label>In-App Notifications</label>
                <select id="inAppNotifications">
                    <option value="1" <?= $inAppNotifications == 1 ? 'selected' : '' ?>>Enabled</option>
                    <option value="0" <?= $inAppNotifications == 0 ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>
            <div class="input-group">
                <button id="saveNotifications">Save Notifications</button>
            </div>
        </div>
    </div>

    <!-- Car Listing Preferences -->
    <div class="card">
        <div class="card-header">Car Listing Preferences <span>&#9660;</span></div>
        <div class="card-body">
            <div class="input-group">
                <label>Car Categories</label>
                <select id="carCategories" multiple>
                    <?php
                    $categories = ['suv','sedan','hatchback','coupe','convertible','wagon','truck','van','electric','hybrid','luxury','sports','mpv','minibus','crossover','pickup','saloon'];
                    foreach($categories as $cat){
                        $selected = strpos($carCategories, $cat) !== false ? 'selected' : '';
                        echo "<option value='$cat' $selected>".ucfirst($cat)."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="input-group">
                <label>Default Pricing Options (KES)</label>
                <select id="defaultPricing">
                    <?php
                    for($i=1; $i<=10; $i++){
                        $val = $i*1000000;
                        $selected = $defaultPricing == $val ? 'selected' : '';
                        echo "<option value='$val' $selected>KES ".number_format($val)."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="input-group">
                <button id="saveCarPrefs">Save Car Preferences</button>
            </div>
        </div>
    </div>

    <!-- Security Settings -->
    <div class="card">
        <div class="card-header">Security Settings <span>&#9660;</span></div>
        <div class="card-body">
            <div class="input-group">
                <label>Two-Factor Authentication</label>
                <select id="twoFactorAuth">
                    <option value="1" <?= $twoFactorAuth == 1 ? 'selected' : '' ?>>Enabled</option>
                    <option value="0" <?= $twoFactorAuth == 0 ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>
            <div class="input-group">
                <button id="saveSecurity">Save Security Settings</button>
            </div>
        </div>
    </div>

    <!-- Password Management -->
    <div class="card">
        <div class="card-header">Password Management <span>&#9660;</span></div>
        <div class="card-body">
            <div class="input-group">
                <label>Current Password</label>
                <input type="password" id="currentPassword" placeholder="Enter current password">
            </div>
            <div class="input-group">
                <label>New Password</label>
                <input type="password" id="newPassword" placeholder="Enter new password">
            </div>
            <div class="input-group">
                <label>Confirm New Password</label>
                <input type="password" id="confirmNewPassword" placeholder="Confirm new password">
            </div>
            <div class="input-group">
                <button id="savePassword">Update Password</button>
            </div>
        </div>
    </div>

    <div id="message"></div>
</div>

<script>
// Collapse/expand cards on mobile
document.querySelectorAll('.card-header').forEach(header => {
    header.addEventListener('click', () => {
        const body = header.nextElementSibling;
        body.classList.toggle('collapsed');
        const arrow = header.querySelector('span');
        arrow.innerHTML = body.classList.contains('collapsed') ? '&#9654;' : '&#9660;';
    });
});

// Generic save function
function saveSettings(url, data) {
    fetch(url, { method: 'POST', body: data })
    .then(res => res.json())
    .then(response => {
        const msg = document.getElementById('message');
        if(response.status === 'success'){
            msg.style.color = 'green';
            msg.textContent = response.message;
        } else {
            msg.style.color = 'red';
            msg.textContent = response.message;
        }
    })
    .catch(() => {
        const msg = document.getElementById('message');
        msg.style.color = 'red';
        msg.textContent = 'Error updating settings.';
    });
}

// Event listeners
document.getElementById('saveNotifications').addEventListener('click', () => {
    const data = new FormData();
    data.append('action','notifications');
    data.append('emailNotifications', document.getElementById('emailNotifications').value);
    data.append('smsNotifications', document.getElementById('smsNotifications').value);
    data.append('inAppNotifications', document.getElementById('inAppNotifications').value);
    saveSettings('save_settings.php', data);
});

document.getElementById('saveCarPrefs').addEventListener('click', () => {
    const data = new FormData();
    data.append('action','carPrefs');
    const carCats = Array.from(document.getElementById('carCategories').selectedOptions).map(o => o.value);
    data.append('carCategories', carCats.join(','));
    data.append('defaultPricing', document.getElementById('defaultPricing').value);
    saveSettings('save_settings.php', data);
});

document.getElementById('saveSecurity').addEventListener('click', () => {
    const data = new FormData();
    data.append('action','security');
    data.append('twoFactorAuth', document.getElementById('twoFactorAuth').value);
    saveSettings('save_settings.php', data);
});

document.getElementById('savePassword').addEventListener('click', () => {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmNewPassword = document.getElementById('confirmNewPassword').value;

    if(!currentPassword || !newPassword || !confirmNewPassword){
        alert("All password fields are required!");
        return;
    }

    if(newPassword !== confirmNewPassword){
        alert("New password and confirm password do not match!");
        return;
    }

    const data = new FormData();
    data.append('action','password');
    data.append('currentPassword', currentPassword);
    data.append('newPassword', newPassword);
    saveSettings('save_settings.php', data);
});
</script>

</body>
</html>
